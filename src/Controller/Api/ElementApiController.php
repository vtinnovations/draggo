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

namespace Vtinnovations\Draggo\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Editor\ContentSynchronizer;
use Vtinnovations\Draggo\Exception\AccessDeniedException;
use Vtinnovations\Draggo\Exception\ElementNotFoundException;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Security\InputSanitizer;
use Vtinnovations\Draggo\Security\RequestGuard;
use Vtinnovations\Draggo\Unit\UnitContentRenderer;

/**
 * JSON API for the editor's element CRUD + reorder + grid structures + the
 * container (article) and unit operations.
 *
 * Container abstraction: page-scoped elements live in tl_article (ptable
 * 'tl_article'); global-unit elements live in tl_draggo_unit (ptable
 * 'tl_draggo_unit'). The "doX" helpers take the ptable so both share one code
 * path. Every action runs auth + CSRF + ownership + whitelist validation.
 */
final class ElementApiController
{
    private const PT_ARTICLE = 'tl_article';
    private const PT_UNIT = 'tl_draggo_unit';

    public function __construct(
        private readonly ContentSynchronizer $synchronizer,
        private readonly RequestGuard $guard,
        private readonly InputSanitizer $sanitizer,
        private readonly ContaoFramework $framework,
        private readonly UnitContentRenderer $unitRenderer,
        private readonly \Vtinnovations\Draggo\Component\ComponentStore $components,
    ) {
    }

    /**
     * Rendered header/footer units for the page editor preview (read-only),
     * so the editor shows realistic surrounding chrome.
     */
    public function frame(int $id): JsonResponse
    {
        return $this->safe(function (): JsonResponse {
            $this->boot();
            $render = function (string $type): string {
                $html = '';
                foreach ($this->synchronizer->publishedUnitIds($type) as $uid) {
                    $html .= $this->unitRenderer->render($uid);
                }

                return $html;
            };

            return new JsonResponse([
                'header'       => $render('header'),
                'footer'       => $render('footer'),
                'headerSticky' => $this->synchronizer->hasStickyUnit('header'),
            ]);
        });
    }

    // ── Page (article) routes ───────────────────────────────────────
    public function listPage(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();
            $this->guard->assertCanEditPage($id);

            return new JsonResponse(['articles' => $this->synchronizer->listForPage($id)]);
        });
    }

    public function list(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();
            $this->guard->assertCanEditArticle($id);

            return new JsonResponse(['elements' => $this->synchronizer->listForContainer($id, self::PT_ARTICLE)]);
        });
    }

    /**
     * All pages in the same site root as {id}, hierarchical — powers the
     * editor's page switcher. Read-only listing (BE-firewalled).
     */
    public function pageSiblings(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();
            $this->guard->assertCanEditPage($id);

            return new JsonResponse([
                'pages'   => $this->synchronizer->pagesInRootOf($id),
                'current' => $id,
            ]);
        });
    }

    public function create(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doCreate($id, $request, self::PT_ARTICLE));
    }

    public function reorder(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doReorder($id, $request, self::PT_ARTICLE));
    }

    public function structure(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doStructure($id, $request, self::PT_ARTICLE));
    }

    /** Create a new container (= tl_article) in a page. */
    public function createArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditPage($id);

            $payload = $this->json($request);
            $title = trim((string) ($payload['title'] ?? 'Container'));
            $after = \array_key_exists('after', $payload) && $payload['after'] !== null ? (int) $payload['after'] : null;
            $newId = $this->synchronizer->createArticle($id, $title, time(), $after);

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    /** Rename a container (= tl_article). */
    public function renameArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditArticle($id);
            $this->synchronizer->renameArticle($id, (string) ($this->json($request)['title'] ?? ''), time());

            return new JsonResponse(['ok' => true]);
        });
    }

    /**
     * Paste a copied container (= tl_article) WITH all its content into a page
     * (cross-page). {id} = target page. Body: {source, after?}.
     */
    public function pasteArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditPage($id);

            $payload = $this->json($request);
            $source = (int) ($payload['source'] ?? 0);
            if ($source <= 0) {
                throw new InvalidInputException('Kein Quell-Container angegeben.');
            }
            // IDOR guard: the source article must be editable by this user.
            $this->guard->assertCanEditArticle($source);

            $after = \array_key_exists('after', $payload) && $payload['after'] !== null ? (int) $payload['after'] : null;
            if ($after !== null && !$this->synchronizer->articleInPage($after, $id)) {
                throw new AccessDeniedException(sprintf('Article %d not in page %d.', $after, $id));
            }

            $newId = $this->synchronizer->copyArticle($source, $id, $after, time());

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    /** Duplicate a container (= tl_article) with ALL its content, in place. */
    public function duplicateArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditArticle($id);
            $newId = $this->synchronizer->duplicateArticle($id, time());

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    /** Delete a container (= tl_article) and its content. */
    public function deleteArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditArticle($id);
            $this->synchronizer->deleteArticle($id);

            return new JsonResponse(['ok' => true]);
        });
    }

    /** Reorder containers (articles) within a page. */
    public function reorderArticles(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditPage($id);

            $order = $this->sanitizer->sanitizeOrder($this->json($request)['order'] ?? null);
            foreach ($order as $articleId) {
                if (!$this->synchronizer->articleInPage($articleId, $id)) {
                    throw new AccessDeniedException(sprintf('Article %d not in page %d.', $articleId, $id));
                }
            }
            $this->synchronizer->reorderArticles($id, $order, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    // ── Unit routes ─────────────────────────────────────────────────
    public function listUnit(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();

            return new JsonResponse([
                'elements' => $this->synchronizer->listForContainer($id, self::PT_UNIT),
                'layout'   => $this->synchronizer->unitLayout($id),
            ]);
        });
    }

    public function setUnitLayout(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);

            $layout = $this->sanitizer->sanitizeLayout($this->json($request)['layout'] ?? null);
            $this->synchronizer->updateUnitLayout($id, $layout, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    public function createInUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doCreate($id, $request, self::PT_UNIT));
    }

    public function reorderUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doReorder($id, $request, self::PT_UNIT));
    }

    public function structureUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doStructure($id, $request, self::PT_UNIT));
    }

    // ── Element-id routes (ptable-agnostic) ─────────────────────────
    public function update(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id); // ensures it exists (article or unit)

            $values = $this->sanitizer->sanitizeUpdate($this->json($request));
            $this->synchronizer->updateElement($id, $values, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    public function delete(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);

            $this->synchronizer->deleteElement($id);

            return new JsonResponse(['ok' => true]);
        });
    }

    /** Change a grid row's column structure. Body: {preset, custom?}. */
    /** Convert a recognised theme grid row into Draggo wrappers (opt-in). */
    public function convertGrid(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);
            $this->synchronizer->convertForeignRow($id, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    public function restructure(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);

            $payload = $this->json($request);
            $preset = $this->sanitizer->assertValidPreset((string) ($payload['preset'] ?? ''));
            $custom = isset($payload['custom']) ? (string) $payload['custom'] : null;
            $this->guard->assertStructureAllowed($preset, $custom !== null && $custom !== '');
            // Responsive presets: empty string allowed (= inherit/auto).
            $bp = function (string $key) use ($payload): ?string {
                if (!isset($payload[$key])) {
                    return null;
                }
                $v = (string) $payload[$key];

                return $v === '' ? '' : $this->sanitizer->assertValidPreset($v);
            };
            $this->synchronizer->restructureRow($id, $preset, $custom, time(), $bp('tablet'), $bp('mobile'));

            return new JsonResponse(['ok' => true]);
        });
    }

    /** Duplicate an element in place (same container, right after itself). */
    public function duplicate(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);
            // Close the tier hole: duplicating is another way to obtain an
            // element the licence does not cover.
            $this->guard->assertElementAllowed($this->elementType($id));

            $newId = $this->synchronizer->duplicateElement($id, time());

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    /** Paste a copied element into an article. Body: {source, after?}. */
    public function pasteInArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doPaste($id, $request, self::PT_ARTICLE));
    }

    /** Paste a copied element into a unit. Body: {source, after?}. */
    public function pasteInUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doPaste($id, $request, self::PT_UNIT));
    }

    // ── Component library (Tier 4) ──────────────────────────────────
    public function listComponents(): JsonResponse
    {
        return $this->safe(function (): JsonResponse {
            $this->boot();

            // Free tier has no component library — return an empty list rather
            // than 403 so the editor panel simply stays empty.
            if (!$this->guard->allowsFeature(EditionProfile::CAP_LIBRARY)) {
                return new JsonResponse(['components' => []]);
            }

            return new JsonResponse(['components' => $this->components->all()]);
        });
    }

    /** Save an element as a reusable component. Body: {title, category?}. */
    public function saveComponent(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertFeature(EditionProfile::CAP_LIBRARY);
            $this->assertElementEditable($id);

            $payload = $this->json($request);
            $title = (string) ($payload['title'] ?? '');
            $category = (string) ($payload['category'] ?? '');
            $newId = $this->components->saveFromElement($id, $title, $category, time());

            return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
        });
    }

    public function deleteComponent(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            // Was CSRF-only: any authenticated backend user could delete a
            // component without tl_content access. Assert authz + licence.
            $this->guard->assertCanEditContent();
            $this->guard->assertFeature(EditionProfile::CAP_LIBRARY);
            $this->components->delete($id);

            return new JsonResponse(['ok' => true]);
        });
    }

    /** Insert a component into an article. Body: {component, after?}. */
    public function insertComponentArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doInsertComponent($id, $request, self::PT_ARTICLE));
    }

    /** Insert a component into a unit. Body: {component, after?}. */
    public function insertComponentUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doInsertComponent($id, $request, self::PT_UNIT));
    }

    /** Insert a prebuilt section template into an article. Body: {template, after?}. */
    public function insertTemplateArticle(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doInsertTemplate($id, $request, self::PT_ARTICLE));
    }

    /** Insert a prebuilt section template into a unit. Body: {template, after?}. */
    public function insertTemplateUnit(int $id, Request $request): JsonResponse
    {
        return $this->safe(fn (): JsonResponse => $this->doInsertTemplate($id, $request, self::PT_UNIT));
    }

    /**
     * Create a whole prebuilt CONTAINER (tl_article) on a page from a template:
     * a new article + its row/column/element tree + container-level layout.
     * Body: {template, after?} where after = an existing article id.
     */
    public function createContainerTemplate(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertFeature(EditionProfile::CAP_LIBRARY);
            $this->guard->assertCanEditPage($id);

            $payload = $this->json($request);
            $key = (string) ($payload['template'] ?? '');
            if (!\Vtinnovations\Draggo\Template\SectionTemplates::exists($key)) {
                throw new InvalidInputException('Unbekannte Vorlage.');
            }
            $after = \array_key_exists('after', $payload) && $payload['after'] !== null ? (int) $payload['after'] : null;

            $now = time();
            $articleId = $this->synchronizer->createArticle($id, \Vtinnovations\Draggo\Template\SectionTemplates::title($key), $now, $after);
            $this->synchronizer->insertTree(\Vtinnovations\Draggo\Template\SectionTemplates::items($key), $articleId, self::PT_ARTICLE, null, $now);

            $layout = \Vtinnovations\Draggo\Template\SectionTemplates::articleLayout($key);
            if ($layout !== []) {
                $this->synchronizer->updateArticleLayout($articleId, $this->sanitizer->sanitizeLayout($layout), $now);
            }

            return new JsonResponse(['id' => $articleId], Response::HTTP_CREATED);
        });
    }

    public function setLayout(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);

            $payload = $this->json($request);
            $layout = $this->sanitizer->sanitizeLayout($payload['layout'] ?? null);
            $scope = \in_array($payload['scope'] ?? 'flat', ['flat', 'row', 'col'], true) ? $payload['scope'] : 'flat';
            $this->synchronizer->updateLayout($id, $layout, (string) $scope, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    /** Container (article) layout. */
    public function setContainerLayout(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->guard->assertCanEditArticle($id);

            $layout = $this->sanitizer->sanitizeLayout($this->json($request)['layout'] ?? null);
            $this->synchronizer->updateArticleLayout($id, $layout, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    public function fields(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();
            $this->assertElementEditable($id);

            return new JsonResponse($this->synchronizer->getElementFields($id));
        });
    }

    public function saveFields(int $id, Request $request): JsonResponse
    {
        return $this->safe(function () use ($id, $request): JsonResponse {
            $this->boot();
            $this->guard->assertCsrf($request);
            $this->assertElementEditable($id);

            $values = $this->json($request)['fields'] ?? [];
            if (!\is_array($values)) {
                $values = [];
            }
            $this->synchronizer->saveElementFields($id, $values, time());

            return new JsonResponse(['ok' => true]);
        });
    }

    public function preview(int $id): JsonResponse
    {
        return $this->safe(function () use ($id): JsonResponse {
            $this->boot();
            $row = $this->synchronizer->findRow($id);
            if ($row === null) {
                throw new ElementNotFoundException($id);
            }

            return new JsonResponse(['id' => $id, 'type' => (string) $row['type'], 'html' => null]);
        });
    }

    // ── Shared helpers ──────────────────────────────────────────────
    private function doCreate(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        $this->assertContainerEditable($pid, $ptable);

        $payload = $this->json($request);
        $type = $this->sanitizer->assertValidType((string) ($payload['type'] ?? ''));
        $this->guard->assertElementAllowed($type);
        $after = isset($payload['after']) ? (int) $payload['after'] : null;
        // AI block instance carries its block-type key (validated shape only;
        // rendering ignores unknown/unpublished types).
        $blocktype = null;
        if ($type === 'draggo_block') {
            $bt = (string) ($payload['blocktype'] ?? '');
            $blocktype = preg_match('/^[a-z][a-z0-9_]{1,40}$/', $bt) ? $bt : null;
        }

        if ($after !== null) {
            $this->guard->assertElementInContainer($after, $pid, $ptable);
        }

        $newId = $this->synchronizer->createElement($pid, $type, $after, time(), $ptable, $blocktype);

        return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
    }

    /** Content type of an existing element ('' when it cannot be resolved). */
    private function elementType(int $id): string
    {
        $row = $this->synchronizer->findRow($id);

        return \is_array($row) ? (string) ($row['type'] ?? '') : '';
    }

    private function doPaste(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        $this->assertContainerEditable($pid, $ptable);

        $payload = $this->json($request);
        $source = (int) ($payload['source'] ?? 0);
        if ($source <= 0) {
            throw new InvalidInputException('Kein Quell-Element angegeben.');
        }
        // Pasting (incl. cross-page clipboard) must respect the licence tier.
        $this->guard->assertElementAllowed($this->elementType($source));
        // Source must live in an editable container (IDOR guard).
        $this->guard->containerOf($source);

        $after = isset($payload['after']) ? (int) $payload['after'] : null;
        if ($after !== null) {
            $this->guard->assertElementInContainer($after, $pid, $ptable);
        }

        $newId = $this->synchronizer->cloneElement($source, $pid, $ptable, $after, time());

        return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
    }

    private function doInsertComponent(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        $this->guard->assertFeature(EditionProfile::CAP_LIBRARY);
        $this->assertContainerEditable($pid, $ptable);

        $payload = $this->json($request);
        $component = (int) ($payload['component'] ?? 0);
        if ($component <= 0) {
            throw new InvalidInputException('Keine Komponente angegeben.');
        }
        $after = isset($payload['after']) ? (int) $payload['after'] : null;
        if ($after !== null) {
            $this->guard->assertElementInContainer($after, $pid, $ptable);
        }

        $newId = $this->components->insert($component, $pid, $ptable, $after, time());

        return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
    }

    private function doInsertTemplate(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        // Prebuilt sections are curated design work — a paid feature, like the
        // component library they effectively extend.
        $this->guard->assertFeature(EditionProfile::CAP_LIBRARY);
        $this->assertContainerEditable($pid, $ptable);

        $payload = $this->json($request);
        $key = (string) ($payload['template'] ?? '');
        if (!\Vtinnovations\Draggo\Template\SectionTemplates::exists($key)) {
            throw new InvalidInputException('Unbekannte Vorlage.');
        }
        $after = isset($payload['after']) ? (int) $payload['after'] : null;
        if ($after !== null) {
            $this->guard->assertElementInContainer($after, $pid, $ptable);
        }

        // Trusted, server-defined element tree — never client data.
        $items = \Vtinnovations\Draggo\Template\SectionTemplates::items($key);
        $newId = $this->synchronizer->insertTree($items, $pid, $ptable, $after, time());

        return new JsonResponse(['id' => $newId], Response::HTTP_CREATED);
    }

    private function doReorder(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        $this->assertContainerEditable($pid, $ptable);

        $order = $this->sanitizer->sanitizeOrder($this->json($request)['order'] ?? null);
        foreach ($order as $elementId) {
            $this->guard->assertElementInContainer($elementId, $pid, $ptable);
        }

        $this->synchronizer->reorder($pid, $order, time(), $ptable);

        return new JsonResponse(['ok' => true]);
    }

    private function doStructure(int $pid, Request $request, string $ptable): JsonResponse
    {
        $this->boot();
        $this->guard->assertCsrf($request);
        $this->assertContainerEditable($pid, $ptable);

        $payload = $this->json($request);
        $preset = $this->sanitizer->assertValidPreset((string) ($payload['preset'] ?? ''));
        $custom = isset($payload['custom']) ? (string) $payload['custom'] : null;
        $this->guard->assertStructureAllowed($preset, $custom !== null && $custom !== '');
        $after = isset($payload['after']) ? (int) $payload['after'] : null;

        if ($after !== null) {
            $this->guard->assertElementInContainer($after, $pid, $ptable);
        }

        $ids = $this->synchronizer->insertStructure($pid, $preset, $custom, $after, time(), $ptable);

        return new JsonResponse(['ids' => $ids], Response::HTTP_CREATED);
    }

    private function boot(): void
    {
        $this->framework->initialize();
        $this->guard->assertCanEditContent();
    }

    /**
     * Per-page authorization for a container operation. Article containers are
     * pagemount-checked; units are global content (no per-page restriction).
     */
    private function assertContainerEditable(int $pid, string $ptable): void
    {
        if ($ptable === self::PT_ARTICLE) {
            $this->guard->assertCanEditArticle($pid);
        }
    }

    /**
     * Resolve an element's container (existence check) AND assert the user may
     * edit it (pagemount). Replaces a bare containerOf() on element-addressed
     * routes so a restricted editor can't mutate elements on foreign pages.
     */
    private function assertElementEditable(int $id): void
    {
        $c = $this->guard->containerOf($id);
        $this->assertContainerEditable($c['pid'], $c['ptable']);
    }

    private function safe(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (AccessDeniedException $e) {
            return $this->error('forbidden', $e->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (InvalidInputException $e) {
            return $this->error('invalid_input', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ElementNotFoundException $e) {
            return $this->error('not_found', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            // Surface the real cause as JSON instead of a bare 500/503.
            return $this->error(
                'server_error',
                $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * @return array<string,mixed>
     */
    private function json(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return $request->request->all();
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded)) {
            throw new InvalidInputException('Request body is not valid JSON.');
        }

        return $decoded;
    }
}
