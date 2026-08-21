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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Search box (Tier 5): a GET form that submits "keywords" to a chosen results
 * page (which carries a Contao search module). No own search engine — binds to
 * Contao's native search.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_search')]
class SearchController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $action = '';
        $pageId = (int) ($model->draggo_se_page ?? 0);
        if ($pageId > 0) {
            /** @var PageModel $pageAdapter */
            $pageAdapter = $this->framework->getAdapter(PageModel::class);
            $page = $pageAdapter->findByPk($pageId);
            if ($page !== null) {
                try {
                    $action = $page->getFrontendUrl();
                } catch (\Throwable) {
                    $action = '';
                }
            }
        }
        $placeholder = $e(trim((string) ($model->draggo_se_ph ?? '')) ?: 'Suchen …');
        $btn = $e(trim((string) ($model->draggo_se_btn ?? '')) ?: 'Suchen');

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<form class="' . trim('draggo-search ' . $cls) . '"' . $idAttr . ' action="' . $e($action) . '" method="get" role="search">'
            . '<input type="search" name="keywords" class="draggo-search-input" placeholder="' . $placeholder . '" aria-label="' . $placeholder . '">'
            . '<button type="submit" class="draggo-search-btn">' . $btn . '</button>'
            . '</form>',
        );
    }
}
