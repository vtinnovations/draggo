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
use Vtinnovations\Draggo\Grid\GridPresets;
use Vtinnovations\Draggo\Grid\GridStack;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Opens a Bootstrap grid row (container + row + first column). Wrapper "start"
 * element — see $GLOBALS['TL_WRAPPERS'] in contao/config/config.php.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_row_start')]
class RowStartController extends AbstractDraggoElementController
{
    public function __construct(
        private readonly GridStack $gridStack,
        private readonly LayoutStyleCompiler $layoutCompiler,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $definition = GridPresets::definition(
            (string) ($model->draggo_grid_preset ?: '1'),
            (string) ($model->draggo_container ?: 'container'),
            $model->draggo_grid_custom ? (string) $model->draggo_grid_custom : null,
            (string) ($model->draggo_grid_tablet ?? ''),
            (string) ($model->draggo_grid_mobile ?? ''),
        );

        $layout = $this->decodeLayout($model->draggo_layout ?? null);
        $ns = isset($layout['row']) || isset($layout['col']);
        $rowL = $ns ? (array) ($layout['row'] ?? []) : [];
        $colL = $ns ? (array) ($layout['col'] ?? []) : $layout; // legacy flat = col0

        // Container host gets bg/padding only (must stay full-width); the row
        // alignment (justify/align-items) goes on the .row itself — putting flex
        // on the container would shrink the .row to content width.
        $rowStyle = $this->layoutCompiler->compile($rowL) . $this->layoutCompiler->bgHostStyle($rowL);
        $rowAlignStyle = $this->layoutCompiler->rowAlign($rowL);
        $colStyle = $this->layoutCompiler->compile($colL) . $this->layoutCompiler->flex($colL) . $this->layoutCompiler->bgHostStyle($colL);
        $gapClass = $this->layoutCompiler->gapClass($rowL);
        $rowAttr = $this->layoutCompiler->attrs($rowL);
        $colAttr = $this->layoutCompiler->attrs($colL);
        $rowLayers = $this->layoutCompiler->bgLayersHtml($rowL);
        $colLayers = $this->layoutCompiler->bgLayersHtml($colL);

        // Per-viewport overrides. rowHook = container host (bg/padding/size);
        // rowInnerHook = the .row (alignment). Same breakpoints as the editor.
        $rowHook = 'draggo-rr' . (int) $model->id;
        $rowInnerHook = 'draggo-ri' . (int) $model->id;
        $colHook = 'draggo-rc' . (int) $model->id;
        $resp = $this->layoutCompiler->responsiveBlock('.' . $rowHook, $rowL)
            . $this->layoutCompiler->rowAlignResponsive('.' . $rowInnerHook, $rowL)
            . $this->layoutCompiler->responsiveBlock('.' . $colHook, $colL, fn (array $m): string => $this->layoutCompiler->flex($m))
            . $this->layoutCompiler->customCssScoped('.' . $rowHook, $rowL['customCss'] ?? '')
            . $this->layoutCompiler->customCssScoped('.' . $colHook, $colL['customCss'] ?? '')
            . $this->layoutCompiler->visibilityCss('.' . $rowHook, $rowL)
            . $this->layoutCompiler->visibilityCss('.' . $colHook, $colL);
        $style = $resp !== '' ? '<style>' . $resp . '</style>' : '';

        // Raw markup, no template (markup-only wrapper, emits unbalanced tags).
        return new Response($style . $this->gridStack->openRow($definition, $colStyle, $rowStyle, $gapClass, $rowAttr, $colAttr, $rowLayers, $colLayers, $rowHook, $colHook, $rowAlignStyle, $rowInnerHook));
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeLayout(mixed $raw): array
    {
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
