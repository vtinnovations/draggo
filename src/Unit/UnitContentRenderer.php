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

namespace Vtinnovations\Draggo\Unit;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Css\CssGenerator;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Renders the content elements of a global unit (tl_content rows with
 * ptable='tl_draggo_unit'). Reuses Contao's own content-element rendering, so
 * grid wrappers and every core/3rd-party element behave as on a normal article.
 * Output is wrapped so units can be made sticky.
 */
final class UnitContentRenderer
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly LayoutStyleCompiler $compiler,
        private readonly CssGenerator $cssGenerator,
    ) {
    }

    public function render(int $unitId): string
    {
        if ($unitId <= 0) {
            return '';
        }

        $unit = $this->connection->fetchAssociative(
            'SELECT type, sticky, draggo_layout FROM tl_draggo_unit WHERE id = :id',
            ['id' => $unitId],
        );
        if ($unit === false) {
            return '';
        }

        $this->framework->initialize();

        /** @var ContentModel $contentAdapter */
        $contentAdapter = $this->framework->getAdapter(ContentModel::class);
        $elements = $contentAdapter->findPublishedByPidAndTable($unitId, 'tl_draggo_unit');

        $inner = '';
        if ($elements !== null) {
            /** @var Controller $controller */
            $controller = $this->framework->getAdapter(Controller::class);
            foreach ($elements as $element) {
                $inner .= $controller->getContentElement($element);
            }
        }

        // Per-element styles for the unit's content (the page CSS cache only
        // covers page articles, not units) → emit them here so element/nav
        // styling works inside header/footer.
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, draggo_layout FROM tl_content
                 WHERE ptable = 'tl_draggo_unit' AND pid = :pid AND draggo_layout IS NOT NULL AND draggo_layout != ''",
                ['pid' => $unitId],
            );
            $built = $this->cssGenerator->buildFromRows($rows);
            if ($built['css'] !== '') {
                $inner = '<style>' . $built['css'] . '</style>' . $inner;
            }
        } catch (\Throwable) {
            // table column missing etc. → skip styling, still render content
        }

        $type = preg_replace('/[^a-z]/', '', (string) $unit['type']);

        // Container layout for the unit (bg / display / spacing …).
        $layout = [];
        if (\is_string($unit['draggo_layout'] ?? null) && $unit['draggo_layout'] !== '') {
            $decoded = json_decode((string) $unit['draggo_layout'], true);
            $layout = \is_array($decoded) ? $decoded : [];
        }
        $style = $this->compiler->containerStyle($layout);
        if (($unit['sticky'] ?? '') === '1') {
            $style .= 'position:sticky;top:0;z-index:99999;';
        }

        // Boxed: centre the content (max 1140px) while the unit stays full-width.
        $boxed = ($layout['width'] ?? 'full') === 'boxed' && empty($layout['display']);
        if ($boxed) {
            $inner = '<div style="width:100%;max-width:1140px;margin:0 auto;">' . $inner . '</div>';
        }

        return '<div class="draggo-unit draggo-unit--' . $type . '" style="' . htmlspecialchars($style, ENT_QUOTES) . '">' . $inner . '</div>';
    }
}
