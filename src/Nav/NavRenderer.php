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

namespace Vtinnovations\Draggo\Nav;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;

/**
 * Renders the Contao page-tree navigation as a nested list for the Draggo
 * navigation element. Auto-connected to the page structure (no manual menu):
 * children of a start page, published + not hidden-from-nav, with proper
 * frontend URLs. Presets (horizontal / vertical / hamburger) are pure CSS/JS
 * shipped inline once per request.
 */
final class NavRenderer
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    public function render(int $rootId, int $levels, string $preset, string $cssClass = '', string $cssId = '', string $toggleIcon = ''): string
    {
        $this->framework->initialize();
        $preset = \in_array($preset, ['horizontal', 'vertical', 'hamburger'], true) ? $preset : 'horizontal';
        $levels = ($levels >= 1 && $levels <= 5) ? $levels : 1;

        $startPid = $rootId > 0 ? $rootId : $this->currentOrFirstRoot();
        if ($startPid <= 0) {
            return '';
        }

        // Element id/class (from the Draggo editor) go onto the <nav> itself,
        // so scoped element styles (.draggo-el-{id} …) apply.
        $cls = 'draggo-nav draggo-nav--' . $preset;
        $extra = preg_replace('/[^A-Za-z0-9 _-]/', '', $cssClass) ?? '';
        if (trim($extra) !== '') {
            $cls .= ' ' . trim($extra);
        }
        $idAttr = '';
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $cssId) ?? '';
        if ($id !== '') {
            $idAttr = ' id="' . $id . '"';
        }

        $list = $this->buildLevel($startPid, $levels);
        if ($list === '') {
            // Visible hint instead of silent emptiness (helps diagnose setup).
            return '<nav class="' . $cls . '"' . $idAttr . '><em style="opacity:.6">Keine Navigationspunkte gefunden (Start-Seite prüfen).</em></nav>';
        }

        // The toggle is always rendered (hidden by default; shown by the
        // hamburger preset OR the responsive-drawer media query). Icon = chosen
        // library/FA icon, else a default bars glyph.
        $icon = $toggleIcon !== '' ? $toggleIcon : '☰';
        $toggle = '<button type="button" class="draggo-nav-toggle" aria-label="Menü" aria-expanded="false">' . $icon . '</button>';

        // Styles/JS ship as draggo-frontend.css/js (loaded on every page +
        // in the editor canvas) so presets render reliably everywhere.
        return '<nav class="' . $cls . '"' . $idAttr . '>' . $toggle . $list . '</nav>';
    }

    private function buildLevel(int $pid, int $levelsLeft): string
    {
        try {
            $ids = $this->connection->fetchFirstColumn(
                "SELECT id FROM tl_page WHERE pid = :pid AND published = '1' AND (hide = '' OR hide IS NULL)
                 AND type != 'root' AND type != 'error_401' AND type != 'error_403' AND type != 'error_404'
                 ORDER BY sorting",
                ['pid' => $pid],
            );
        } catch (\Throwable) {
            return '';
        }
        if ($ids === []) {
            return '';
        }

        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        // Current page for active/trail marking.
        $objPage = $GLOBALS['objPage'] ?? null;
        $curId = \is_object($objPage) ? (int) $objPage->id : 0;
        $trail = (\is_object($objPage) && \is_array($objPage->trail ?? null)) ? array_map('intval', $objPage->trail) : [];

        $items = '';
        foreach ($ids as $id) {
            $page = $pageAdapter->findById((int) $id);
            if ($page === null) {
                continue;
            }
            try {
                $href = $page->getFrontendUrl();
            } catch (\Throwable) {
                $href = '#';
            }
            $title = htmlspecialchars((string) ($page->title ?: $page->alias), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $sub = $levelsLeft > 1 ? $this->buildLevel((int) $id, $levelsLeft - 1) : '';
            $state = ((int) $id === $curId) ? ' active' : (\in_array((int) $id, $trail, true) ? ' trail' : '');
            $items .= '<li class="draggo-nav-item' . ($sub !== '' ? ' has-sub' : '') . $state . '">'
                . '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $title . '</a>'
                . $sub . '</li>';
        }

        return $items === '' ? '' : '<ul class="draggo-nav-list">' . $items . '</ul>';
    }

    /**
     * Root page whose children form the top-level nav: prefer the CURRENT
     * frontend page's root (so the menu auto-connects to the right site),
     * else the first published root.
     */
    private function currentOrFirstRoot(): int
    {
        $page = $GLOBALS['objPage'] ?? null;
        if (\is_object($page) && !empty($page->rootId)) {
            return (int) $page->rootId;
        }

        try {
            return (int) $this->connection->fetchOne(
                "SELECT id FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting LIMIT 1",
            );
        } catch (\Throwable) {
            return 0;
        }
    }

}
