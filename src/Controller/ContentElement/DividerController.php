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

/**
 * Atomic divider / horizontal rule (Tier 1).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_divider')]
class DividerController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $style = \in_array($model->draggo_div_style ?? '', ['solid', 'dashed', 'dotted', 'double'], true) ? $model->draggo_div_style : 'solid';
        $thick = (int) ($model->draggo_div_thickness ?? 1);
        $thick = ($thick >= 1 && $thick <= 30) ? $thick : 1;
        $width = (int) ($model->draggo_div_width ?? 100);
        $width = ($width >= 1 && $width <= 100) ? $width : 100;
        $color = (\is_string($model->draggo_div_color ?? null) && preg_match('/^(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-color-[a-z0-9-]+\))$/', (string) $model->draggo_div_color)) ? (string) $model->draggo_div_color : 'currentColor';

        $align = \in_array($model->draggo_div_align ?? '', ['left', 'center', 'right'], true) ? $model->draggo_div_align : 'center';
        $margin = $align === 'left' ? 'margin:0 auto 0 0;' : ($align === 'right' ? 'margin:0 0 0 auto;' : 'margin:0 auto;');

        [$idAttr, $cls] = $this->draggoScope($model);
        $text = trim((string) ($model->draggo_div_text ?? ''));

        // With centre text → flanked-line wrapper; otherwise a plain <hr>.
        if ($text !== '') {
            $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $line = 'border-top:' . $thick . 'px ' . $style . ' ' . $color . ';';
            $wrapCss = 'width:' . $width . '%;' . $margin;
            $clsAttr = trim('draggo-divider-wrap ' . $cls);

            return new Response(
                '<div class="' . $clsAttr . '"' . $idAttr . ' style="' . htmlspecialchars($wrapCss, ENT_QUOTES) . '">'
                . '<span class="draggo-divider-line" style="' . htmlspecialchars($line, ENT_QUOTES) . '"></span>'
                . '<span class="draggo-divider-text">' . $e($text) . '</span>'
                . '<span class="draggo-divider-line" style="' . htmlspecialchars($line, ENT_QUOTES) . '"></span>'
                . '</div>',
            );
        }

        $css = 'border:0;border-top:' . $thick . 'px ' . $style . ' ' . $color . ';width:' . $width . '%;' . $margin;
        $clsAttr = trim('draggo-divider ' . $cls);

        return new Response('<hr class="' . $clsAttr . '"' . $idAttr . ' style="' . htmlspecialchars($css, ENT_QUOTES) . '">');
    }
}
