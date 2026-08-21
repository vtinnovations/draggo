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
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scroll-Progress (scrollytelling): a slim reading-progress bar fixed at the top
 * (or bottom) of the page that fills with the whole-page scroll progress.
 * draggo-scroll.js (driver "progress") sets the fill width. Colour/height from
 * the Style tab (--pr-color / --pr-h / --pr-track).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_progress')]
class ScrollProgressController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $pos = ($model->draggo_pr_pos ?? '') === 'bottom' ? 'bottom' : 'top';
        $h = (is_numeric($model->draggo_pr_h ?? null) && (int) $model->draggo_pr_h >= 1 && (int) $model->draggo_pr_h <= 20) ? (int) $model->draggo_pr_h : 4;

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-pr draggo-pr--' . $pos . ' ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="progress" style="--pr-h:' . $h . 'px">'
            . '<div class="draggo-pr-fill"></div></div>',
        );
    }
}
