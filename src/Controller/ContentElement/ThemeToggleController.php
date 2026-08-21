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
 * Dark/Light theme toggle. A visitor-facing button that flips a manual theme
 * override (data-draggo-theme on <html>), persisted in localStorage by
 * draggo-frontend.js. The colour TOKENS' dark values (TokenStore::cssVars)
 * provide the actual dark palette; this element is just the switch. Pure CSS
 * shows the right icon per active theme — no flash, no JS needed for the glyph.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_themetoggle')]
class ThemeToggleController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        [$idAttr, $cls] = $this->draggoScope($model);

        $style = (string) ($model->draggo_tt_style ?? 'icon');
        if (!\in_array($style, ['icon', 'text', 'switch'], true)) {
            $style = 'icon';
        }
        $size = (string) ($model->draggo_tt_size ?? 'md');
        if (!\in_array($size, ['sm', 'md', 'lg'], true)) {
            $size = 'md';
        }

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $moon = '<i class="fas fa-moon draggo-tt-moon" aria-hidden="true"></i>';
        $sun = '<i class="fas fa-sun draggo-tt-sun" aria-hidden="true"></i>';

        if ($style === 'switch') {
            // State switch: sun (light) left, moon (dark) right, a knob marks the
            // currently active side. CSS slides the knob per active theme.
            $inner = $sun . $moon . '<span class="draggo-tt-knob" aria-hidden="true"></span>';
        } elseif ($style === 'text') {
            $label = trim((string) ($model->draggo_tt_label ?? ''));
            if ($label === '') {
                $label = 'Theme';
            }
            $inner = $moon . $sun . '<span class="draggo-tt-label">' . $e($label) . '</span>';
        } else {
            $inner = $moon . $sun;
        }

        $classes = trim('draggo-themetoggle draggo-tt--' . $style . ' draggo-tt--' . $size . ' ' . $cls);

        return new Response(
            '<button type="button" class="' . $classes . '"' . $idAttr
            . ' data-draggo-theme-toggle aria-label="Hell-/Dunkelmodus umschalten" title="Hell / Dunkel">'
            . $inner
            . '</button>',
        );
    }
}
