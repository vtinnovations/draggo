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
 * SVG-Path-Draw (scrollytelling): a preset stroke that draws itself as the block
 * scrolls past. draggo-scroll.js (driver "svgdraw") animates stroke-dashoffset.
 * Colour + thickness come from the Style tab (--sd-color / --sd-w).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_svgdraw')]
class SvgDrawController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    /** Preset shapes on a 0 0 1000 120 viewBox. */
    private const SHAPES = [
        'line'      => 'M0,60 L1000,60',
        'wave'      => 'M0,60 Q125,0 250,60 T500,60 T750,60 T1000,60',
        'zigzag'    => 'M0,100 L200,20 L400,100 L600,20 L800,100 L1000,20',
        'underline' => 'M10,80 C300,130 700,130 990,80',
        'arrow'     => 'M0,60 L940,60 M880,20 L960,60 L880,100',
    ];

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $shape = (string) ($model->draggo_sd_shape ?? 'line');
        $d = self::SHAPES[$shape] ?? self::SHAPES['line'];

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-sd ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="svgdraw">'
            . '<svg viewBox="0 0 1000 120" preserveAspectRatio="none" aria-hidden="true">'
            . '<path class="draggo-sd-path" d="' . $d . '"/></svg></div>',
        );
    }
}
