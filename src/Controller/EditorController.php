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

namespace Vtinnovations\Draggo\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Agent\UsageSignal;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Security\RequestGuard;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\EventListener\GoogleFontsListener;
use Vtinnovations\Draggo\Font\GoogleFonts;
use Vtinnovations\Draggo\Token\DefaultsStore;
use Vtinnovations\Draggo\Token\TokenStore;

/**
 * Renders the editor shell — a backend-authenticated HTML page that boots the
 * JS editor for one article. The page itself carries no element data; the
 * editor fetches everything via the JSON API (single source of truth).
 *
 * The route lives under /contao so the backend firewall has already
 * authenticated the user before we get here; we still re-assert table access.
 */
final class EditorController
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TokenStore $tokens,
        private readonly DefaultsStore $defaults,
        private readonly GoogleFontsListener $googleFonts,
        private readonly string $csrfTokenName,
        private readonly EditionResolver $edition,
        private readonly UsageSignal $signal,
        private readonly string $projectDir = '',
    ) {
    }

    /**
     * Entitlement gate for the editor itself. Returns a notice response when
     * the bundle is unlicensed, or null when the editor may open. Kept as a
     * plain HTML page (not an exception) so the operator gets a usable next
     * step instead of a bare 403.
     *
     * Also the module-entry point for the once-per-session usage signal: the
     * editor is the protected module, and this is where it is actually opened.
     */
    private function licenseNotice(): ?Response
    {
        $profile = $this->edition->profile();

        if ($profile->allows(EditionProfile::CAP_EDITOR)) {
            $this->signal->moduleEntry();

            return null;
        }

        // An expired licence reads very differently from never-licensed: the
        // customer's site is fine, only editing stopped. Say so, or support
        // gets a panic ticket about a "broken" website.
        [$heading, $text] = EditionProfile::REASON_EXPIRED === $profile->reason
            ? [
                'Draggo-Lizenz abgelaufen',
                'Deine Inhalte bleiben unverändert — der Editor ist gesperrt. '
                . 'Verlängere die Lizenz und wähle unter <strong>Einstellungen → Draggo Licence management</strong> „Lizenz aktualisieren".',
            ]
            : [
                'Draggo ist nicht lizenziert',
                'Der visuelle Editor ist gesperrt. Trage einen gültigen Lizenzschlüssel unter '
                . '<strong>Einstellungen → Draggo Licence management</strong> ein.',
            ];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Draggo — Lizenz erforderlich</title>
<style>body{font:16px/1.6 system-ui,sans-serif;background:#0f1115;color:#e6e9ef;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.b{max-width:32rem;padding:2rem;background:#171a21;border:1px solid #2a2f3a;border-radius:12px}
h1{margin:0 0 .5rem;font-size:1.25rem}a{color:#7c3aed}</style>
</head><body><div class="b">
<h1>{$heading}</h1>
<p>{$text}</p>
<p>Schlüssel und Verlängerung unter <a href="https://v-t.one" target="_blank" rel="noopener">v-t.one</a>.</p>
</div></body></html>
HTML;

        return new Response($html, Response::HTTP_FORBIDDEN);
    }

    public function edit(int $page): Response
    {
        $this->framework->initialize();

        // Entitlement first, so an unlicensed operator gets the explanatory
        // page instead of the bare 403 the guard would raise for the same
        // reason.
        if ($notice = $this->licenseNotice()) {
            return $notice;
        }

        try {
            $this->guard->assertCanEditContent();
        } catch (AccessDeniedException $e) {
            return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        }

        if (!$this->exists('tl_page', $page)) {
            return new Response('Page not found.', Response::HTTP_NOT_FOUND);
        }

        // Per-page authorization (pagemount): a tl_content table grant is not
        // enough — the user must be allowed to edit THIS page's articles.
        try {
            $this->guard->assertCanEditPage($page);
        } catch (AccessDeniedException $e) {
            return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        }

        return new Response($this->renderShell('page', $page));
    }

    public function editUnit(int $unit): Response
    {
        $this->framework->initialize();

        if ($notice = $this->licenseNotice()) {
            return $notice;
        }

        try {
            $this->guard->assertCanEditContent();
        } catch (AccessDeniedException $e) {
            return new Response($e->getMessage(), Response::HTTP_FORBIDDEN);
        }

        if (!$this->exists('tl_draggo_unit', $unit)) {
            return new Response('Unit not found.', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->renderShell('unit', $unit));
    }

    /** True if a published bundle asset (relative to web root) exists on disk. */
    private function publicFileExists(string $relative): bool
    {
        foreach (['/public/', '/web/'] as $dir) {
            if (is_file($this->projectDir . $dir . $relative)) {
                return true;
            }
        }

        return false;
    }

    private function exists(string $table, int $id): bool
    {
        // $table is a fixed internal literal, never user input.
        return (bool) $this->connection->fetchOne(
            "SELECT id FROM {$table} WHERE id = :id",
            ['id' => $id],
        );
    }

    /**
     * Bump on every asset change so browsers/proxies never serve a stale
     * editor bundle. (Standalone shell page → query string is fine here; this
     * is NOT TL_JAVASCRIPT/TL_CSS, so the Contao Combiner is not involved.)
     */
    private const ASSET_VERSION = '20260627rev14';

    /**
     * Absolute frontend URL of the page being edited, for the "live preview"
     * button. Empty for units (no standalone FE URL) or if it can't resolve.
     */
    private function previewUrl(string $mode, int $id): string
    {
        if ($mode !== 'page') {
            return '';
        }

        try {
            $page = PageModel::findWithDetails($id);
            if ($page === null) {
                return '';
            }

            return $page->getAbsoluteUrl();
        } catch (\Throwable) {
            try {
                return (string) ($page?->getFrontendUrl() ?? '');
            } catch (\Throwable) {
                return '';
            }
        }
    }

    private function renderShell(string $mode, int $id): string
    {
        $token = htmlspecialchars(
            $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $mode = $mode === 'unit' ? 'unit' : 'page';
        $v = self::ASSET_VERSION;
        $previewUrl = htmlspecialchars($this->previewUrl($mode, $id), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Editor UI language follows the backend user's profile language.
        // German is fully built-in; everything else falls back to English.
        $lang = str_starts_with((string) ($GLOBALS['TL_LANGUAGE'] ?? 'en'), 'de') ? 'de' : 'en';
        // Tokens globally; default styles scoped to the canvas so they never
        // restyle the editor chrome itself.
        $tokenCss = $this->tokens->cssVars() . $this->defaults->css('.draggo-canvas', '.draggo-canvas ');
        $tokenStyle = $tokenCss !== '' ? '<style>' . $tokenCss . '</style>' : '';

        // Load the Google Fonts used by this page/unit so the canvas previews them.
        $families = $mode === 'unit' ? $this->googleFonts->collectForUnit($id) : $this->googleFonts->collect($id);
        $fontLink = GoogleFonts::link($families) ?? '';
        $tokenStyle = $fontLink . $tokenStyle;

        // Optional Font Awesome (only when the user has dropped it in).
        $faCss = \Vtinnovations\Draggo\Icon\FaIcons::CSS_PATH;
        if ($this->publicFileExists($faCss)) {
            $tokenStyle = '<link rel="stylesheet" href="/' . $faCss . '?v=' . $v . '">' . $tokenStyle;
        }

        // Landing-page stylesheet — ONLY if the separate landing bundle is
        // actually installed (otherwise the link 404s with a text/html body and
        // the browser logs a MIME error).
        $landingCss = 'bundles/vtinnovationsdraggolanding/draggo-landing.css';
        $landingLink = $this->publicFileExists($landingCss)
            ? '<link rel="stylesheet" href="/' . $landingCss . '?v=' . $v . '">'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draggo Editor</title>
    <!-- Apply the saved editor theme before paint (no flash); overrides the OS preference. -->
    <script>try{var t=localStorage.getItem('draggoTheme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="/bundles/draggo/draggo-grid.css?v={$v}">
    <link rel="stylesheet" href="/bundles/draggo/draggo-frontend.css?v={$v}">
    <!-- Landing-page (draggo_lp_*) sections render their real FE markup in the
         canvas; load their stylesheet + fonts so they preview correctly. The
         file is fully scoped under .draggo-lp, so it is inert on non-LP pages.
         Raw <link> (NOT the Combiner) so native CSS nesting survives. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;450;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    {$landingLink}
    <!-- Scrollytelling base styles. The engine JS is NOT loaded in the editor
         (its pin/sticky transforms would hijack the canvas scroll); the canvas
         shows each scroll element in its final, contained state via overrides
         in draggo-editor.css. -->
    <link rel="stylesheet" href="/bundles/draggo/draggo-scroll.css?v={$v}">
    <link rel="stylesheet" href="/bundles/draggo/draggo-editor.css?v={$v}">
    <!-- TinyMCE shipped with Contao (contao-components/tinymce4). Optional: the
         editor falls back to a built-in toolbar if it is not available. -->
    <script src="/assets/tinymce4/js/tinymce.min.js"></script>
    {$tokenStyle}
</head>
<body>
    <div id="draggo-editor"
         data-mode="{$mode}"
         data-id="{$id}"
         data-lang="{$lang}"
         data-csrf-token="{$token}"
         data-preview-url="{$previewUrl}"
         data-api-base="/contao/draggo/api">
        <noscript>Draggo benötigt JavaScript.</noscript>
    </div>
    <script src="/bundles/draggo/draggo-frontend.js?v={$v}" defer></script>
    <script src="/bundles/draggo/draggo-editor.js?v={$v}" defer></script>
</body>
</html>
HTML;
    }
}
