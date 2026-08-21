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

namespace Vtinnovations\Draggo\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Emits scoped CSS for container (article) layouts on the current page. Each
 * styled article carries the class draggo-art-{id} (set on save); here we
 * inject `.draggo-art-{id}{…}` into the page head.
 */
#[AsHook('generatePage')]
final class ContainerLayoutListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LayoutStyleCompiler $compiler,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, draggo_layout FROM tl_article WHERE pid = :pid AND draggo_layout IS NOT NULL AND draggo_layout != ''",
            ['pid' => (int) $pageModel->id],
        );

        $css = '';
        $mediaMap = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['draggo_layout'], true);
            if (!\is_array($decoded)) {
                continue;
            }
            $id = (int) $row['id'];
            $boxed = (($decoded['width'] ?? 'full') === 'boxed');
            $display = (string) ($decoded['display'] ?? '');

            // Container display/flex/grid layout (shared with the responsive pass).
            $layoutCss = $this->compiler->containerLayoutCss($decoded);

            $css .= '.draggo-art-' . $id . '{' . $layoutCss . $this->compiler->compile($decoded) . '}';

            // Per-viewport overrides incl. display/flex/grid/align/gap → tablet/
            // mobile @media (same breakpoints as the editor canvas → 1:1).
            $css .= $this->compiler->responsiveBlock('.draggo-art-' . $id, $decoded, fn (array $m): string => $this->compiler->containerLayoutCss($m));

            // Background overlay (color/gradient/image, above bg, below content).
            $css .= $this->compiler->overlayBefore('.draggo-art-' . $id, $decoded);

            // Custom CSS + per-device visibility for the container.
            $css .= $this->compiler->customCssScoped('.draggo-art-' . $id, $decoded['customCss'] ?? '');
            $css .= $this->compiler->visibilityCss('.draggo-art-' . $id, $decoded);

            // Video/slider background: the article wrapper has no server-side DOM
            // hook here, so we ship the layer HTML to the FE runtime, which
            // injects it into .draggo-art-{id}.
            if ($this->compiler->hasMediaBg($decoded)) {
                $mediaMap[$id] = $this->compiler->mediaBgLayerHtml($decoded);
                $css .= '.draggo-art-' . $id . '{position:relative;isolation:isolate;}';
            }

            // Boxed: children fill up to 1140px (only meaningful in column mode).
            if ($boxed && $display === '') {
                $css .= '.draggo-art-' . $id . ' > *{width:100%;max-width:1140px;}';
            }
        }

        if ($css !== '') {
            $GLOBALS['TL_HEAD'][] = '<style>' . $css . '</style>';
        }
        if ($mediaMap !== []) {
            $json = json_encode($mediaMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $GLOBALS['TL_HEAD'][] = '<script>window.__draggoBg=Object.assign(window.__draggoBg||{},' . $json . ');</script>';
        }
    }

    /** Gap scale → CSS length (matches the editor's spacing scale). */
    private function gapLen(mixed $gap): string
    {
        return [
            'none' => '0', 'xs' => '.25rem', 's' => '.5rem', 'm' => '1rem', 'l' => '2rem', 'xl' => '3rem',
        ][(string) $gap] ?? '';
    }
}

