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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Grid\GridStack;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Column separator: closes the current column and opens the next one with the
 * width class defined by the enclosing row. Wrapper "separator" element.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_col')]
class ColumnController extends AbstractDraggoElementController
{
    public function __construct(
        private readonly GridStack $gridStack,
        private readonly LayoutStyleCompiler $layoutCompiler,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $raw = $model->draggo_layout ?? null;
        $layout = \is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        $layout = \is_array($layout) ? $layout : [];

        // Per-viewport overrides (see RowStartController): unique hook class +
        // !important responsive rules so they beat the inline base.
        $colHook = 'draggo-rc' . (int) $model->id;
        $resp = $this->layoutCompiler->responsiveBlock('.' . $colHook, $layout, fn (array $m): string => $this->layoutCompiler->flex($m))
            . $this->layoutCompiler->customCssScoped('.' . $colHook, $layout['customCss'] ?? '')
            . $this->layoutCompiler->visibilityCss('.' . $colHook, $layout);
        $style = $resp !== '' ? '<style>' . $resp . '</style>' : '';

        // Raw markup, no template (see RowStartController).
        return new Response($style . $this->gridStack->nextColumn(
            $this->layoutCompiler->compile($layout) . $this->layoutCompiler->flex($layout) . $this->layoutCompiler->bgHostStyle($layout),
            $this->layoutCompiler->attrs($layout),
            $this->layoutCompiler->bgLayersHtml($layout),
            $colHook,
        ));
    }
}
