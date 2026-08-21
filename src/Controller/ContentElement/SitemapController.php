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

namespace Vtinnovations\Draggo\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sitemap (Tier 4): nested list of published pages under a chosen start page
 * (or the current root). Honours the page's "hide in sitemap" / publish state.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_sitemap')]
class SitemapController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();

        $root = (int) ($model->draggo_sm_root ?? 0);
        if ($root <= 0) {
            global $objPage;
            $root = $objPage !== null ? (int) $objPage->rootId : 0;
        }
        $levels = (int) ($model->draggo_sm_levels ?? 3);
        $levels = ($levels >= 1 && $levels <= 6) ? $levels : 3;

        /** @var Adapter<PageModel> $pageAdapter */
        $pageAdapter = $this->framework->getAdapter(PageModel::class);
        $list = $this->renderLevel($root, 1, $levels, $pageAdapter);
        if ($list === '') {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<nav class="' . trim('draggo-sitemap ' . $cls) . '"' . $idAttr . '>' . $list . '</nav>');
    }

    private function renderLevel(int $parentId, int $level, int $maxLevels, Adapter $pageAdapter): string
    {
        if ($parentId <= 0 || $level > $maxLevels) {
            return '';
        }
        try {
            $ids = array_map('intval', $this->connection->fetchFirstColumn(
                "SELECT id FROM tl_page WHERE pid = :pid AND published = '1' AND (hide = '' OR hide IS NULL)
                 AND (sitemap != 'map_never' OR sitemap IS NULL)
                 AND type IN ('regular','forward','redirect') ORDER BY sorting",
                ['pid' => $parentId],
            ));
        } catch (\Throwable) {
            return '';
        }
        if ($ids === []) {
            return '';
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $items = '';
        foreach ($ids as $id) {
            $pg = $pageAdapter->findByPk($id);
            if ($pg === null) {
                continue;
            }
            try {
                $href = $e($pg->getFrontendUrl());
            } catch (\Throwable) {
                $href = '#';
            }
            $children = $this->renderLevel($id, $level + 1, $maxLevels, $pageAdapter);
            $items .= '<li class="draggo-sitemap-item"><a href="' . $href . '">' . $e((string) ($pg->title ?: $pg->alias)) . '</a>' . $children . '</li>';
        }

        return $items !== '' ? '<ul class="draggo-sitemap-list draggo-sitemap-l' . $level . '">' . $items . '</ul>' : '';
    }
}
