<?php

declare(strict_types=1);

/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

namespace Vtinnovations\Draggo\Security;

use Contao\BackendUser;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Central authorization gate for every Draggo API request.
 *
 * The Contao backend firewall already guarantees an authenticated backend
 * user reaches these routes. RequestGuard layers the application-specific
 * checks on top:
 *   1. CSRF — REQUEST_TOKEN must match for state-changing requests.
 *   2. AuthZ — the user must have access to the tl_content table.
 *   3. Ownership — an addressed element must live under the addressed article
 *      (defends against IDOR: editing element X via article Y).
 *
 * Every method throws AccessDeniedException on failure; controllers map that
 * to HTTP 403.
 */
final class RequestGuard
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly Security $security,
        private readonly Connection $connection,
        private readonly EditionResolver $edition,
    ) {
    }

    /**
     * Entitlement gate. Enforced here rather than per controller so EVERY
     * editor API route is covered by construction — the JSON API must not stay
     * open when the editor UI itself is locked.
     *
     * The resolver is a REQUIRED dependency: there is no "no gate configured"
     * branch that could leave the API open if a service definition went
     * missing.
     */
    private function assertLicensed(string $capability): void
    {
        if (!$this->edition->profile()->allows($capability)) {
            throw new AccessDeniedException(
                'Draggo ist nicht lizenziert. Lizenzschlüssel unter Einstellungen → Draggo Licence management eintragen (v-t.one).',
            );
        }
    }

    /**
     * Validate CSRF for a mutating request. Token may arrive as a header
     * (X-Contao-Csrf-Token) or a POST/JSON field (REQUEST_TOKEN).
     */
    public function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-Contao-Csrf-Token')
            ?? $request->request->get('REQUEST_TOKEN')
            ?? (\is_array($payload = $this->decodeJson($request)) ? ($payload['REQUEST_TOKEN'] ?? null) : null);

        if (!\is_string($token) || $token === ''
            || !$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))
        ) {
            throw new AccessDeniedException('Invalid or missing CSRF token.');
        }
    }

    /**
     * Require an entitled capability (globals, library, AI …). Public so the
     * API controllers can gate their own surface at their own boundary rather
     * than trusting a single upstream check.
     */
    public function assertFeature(string $capability): void
    {
        $this->assertLicensed($capability);
    }

    /** Read-only variant for places that want to degrade instead of throw. */
    public function allowsFeature(string $capability): bool
    {
        return $this->edition->profile()->allows($capability);
    }

    /** Whether an element type may be used by this installation. */
    public function allowsElement(string $type): bool
    {
        return $this->edition->profile()->allowsElement($type);
    }

    /** Whether a grid preset (column layout) may be used. */
    public function allowsStructure(string $preset, bool $custom = false): bool
    {
        return $this->edition->profile()->allowsStructure($preset, $custom);
    }

    /** Reject column layouts an unlicensed installation may not place. */
    public function assertStructureAllowed(string $preset, bool $custom = false): void
    {
        if (!$this->allowsStructure($preset, $custom)) {
            throw new AccessDeniedException('Draggo ist nicht lizenziert.');
        }
    }

    /**
     * Reject element types this installation may not use. Enforced at CREATION
     * time so the operator gets an honest error instead of an element that
     * silently renders nothing on the frontend.
     */
    public function assertElementAllowed(string $type): void
    {
        if (!$this->allowsElement($type)) {
            throw new AccessDeniedException(
                sprintf('Das Element „%s" erfordert eine gültige Draggo-Lizenz.', $type),
            );
        }
    }

    /**
     * Require an authenticated backend user with access to tl_content.
     */
    public function assertCanEditContent(): BackendUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            throw new AccessDeniedException('No backend user in context.');
        }

        if (!$user->isAdmin && !$user->hasAccess('tl_content', 'tables')) {
            throw new AccessDeniedException('User lacks access to tl_content.');
        }

        $this->assertLicensed(EditionProfile::CAP_EDITOR);

        return $user;
    }

    /**
     * Require access to the tl_form table. Forms are a separate managed table
     * (recipients, fields, redirects) — a tl_content-only grant must NOT let an
     * editor create or rewrite global forms (e.g. point submissions at their own
     * address). Admins always pass.
     */
    public function assertCanEditForms(): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            throw new AccessDeniedException('No backend user in context.');
        }

        if (!$user->isAdmin && !$user->hasAccess('tl_form', 'tables')) {
            throw new AccessDeniedException('User lacks access to tl_form.');
        }
    }

    /**
     * Per-page authorization (pagemount). The user must be allowed to edit the
     * articles of $pageId — Contao's USER_CAN_EDIT_ARTICLES check, which folds in
     * the pagemount restriction and the article-edit right. Admins always pass.
     *
     * assertCanEditContent() only grants TABLE-level access to tl_content; without
     * this a restricted editor could reach pages outside their pagemounts (IDOR).
     */
    public function assertCanEditPage(int $pageId): void
    {
        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_EDIT_ARTICLES, $pageId)) {
            throw new AccessDeniedException(sprintf('No permission to edit content on page %d.', $pageId));
        }
    }

    /**
     * Resolve the page an article belongs to and assert edit access to it.
     * Returns the page id. Throws if the article does not exist.
     */
    public function assertCanEditArticle(int $articleId): int
    {
        $pageId = $this->connection->fetchOne(
            'SELECT pid FROM tl_article WHERE id = :id',
            ['id' => $articleId],
        );

        if ($pageId === false) {
            throw new AccessDeniedException(sprintf('Article %d not found.', $articleId));
        }

        $this->assertCanEditPage((int) $pageId);

        return (int) $pageId;
    }

    /**
     * AI element generator access — admins always; otherwise the backend group
     * must grant the "ai_use" Draggo permission. Block-type deletion needs the
     * separate "ai_delete" permission.
     */
    public function canUseAi(): bool
    {
        // The entitlement check comes before the permission check so an
        // unlicensed install cannot burn inference budget.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_AI)) {
            return false;
        }

        $user = $this->security->getUser();

        return $user instanceof BackendUser && ($user->isAdmin || $user->hasAccess('ai_use', 'draggo_perms'));
    }

    public function canDeleteBlockType(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof BackendUser && ($user->isAdmin || $user->hasAccess('ai_delete', 'draggo_perms'));
    }

    public function assertCanUseAi(): void
    {
        $this->assertLicensed(EditionProfile::CAP_AI);

        if (!$this->canUseAi()) {
            throw new AccessDeniedException('No permission to use the Draggo AI generator.');
        }
    }

    public function assertCanDeleteBlockType(): void
    {
        if (!$this->canDeleteBlockType()) {
            throw new AccessDeniedException('No permission to delete Draggo element types.');
        }
    }

    /**
     * Ensure the element belongs to the addressed article (no IDOR).
     */
    public function assertElementInArticle(int $elementId, int $articleId): void
    {
        $pid = $this->connection->fetchOne(
            "SELECT pid FROM tl_content WHERE id = :id AND ptable = 'tl_article'",
            ['id' => $elementId],
        );

        if ($pid === false || (int) $pid !== $articleId) {
            throw new AccessDeniedException(sprintf(
                'Element %d does not belong to article %d.', $elementId, $articleId,
            ));
        }
    }

    /**
     * Resolve the article id an element belongs to, throwing if it is not a
     * tl_article-owned content element.
     */
    public function articleOf(int $elementId): int
    {
        $pid = $this->connection->fetchOne(
            "SELECT pid FROM tl_content WHERE id = :id AND ptable = 'tl_article'",
            ['id' => $elementId],
        );

        if ($pid === false) {
            throw new AccessDeniedException(sprintf('Element %d is not an article content element.', $elementId));
        }

        return (int) $pid;
    }

    /**
     * Ensure an element belongs to the addressed container (article or unit).
     * Generalised ownership check used by the unit editor.
     */
    public function assertElementInContainer(int $elementId, int $pid, string $ptable): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable FROM tl_content WHERE id = :id',
            ['id' => $elementId],
        );

        if ($row === false || (int) $row['pid'] !== $pid || (string) $row['ptable'] !== $ptable) {
            throw new AccessDeniedException(sprintf(
                'Element %d does not belong to %s %d.', $elementId, $ptable, $pid,
            ));
        }
    }

    /**
     * Resolve the container (pid + ptable) of any tl_content element. Used by
     * id-addressed routes (update/delete) that accept both article and unit
     * elements. Throws if the element does not exist.
     *
     * @return array{pid:int, ptable:string}
     */
    public function containerOf(int $elementId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable FROM tl_content WHERE id = :id',
            ['id' => $elementId],
        );

        if ($row === false) {
            throw new AccessDeniedException(sprintf('Element %d not found.', $elementId));
        }

        return ['pid' => (int) $row['pid'], 'ptable' => (string) $row['ptable']];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
