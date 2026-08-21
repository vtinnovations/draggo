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
 * Animated number counter (Tier 2). Counts from start→end when scrolled into
 * view (draggo-frontend.js). prefix/suffix for units like "+" or "%".
 */
#[AsContentElement(category: 'draggo', type: 'draggo_counter')]
class CounterController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $end = (int) ($model->draggo_counter_end ?? 0);
        $start = (int) ($model->draggo_counter_start ?? 0);
        $dur = (int) ($model->draggo_counter_duration ?? 2000);
        $dur = ($dur >= 100 && $dur <= 10000) ? $dur : 2000;
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $prefix = $e(trim((string) ($model->draggo_counter_prefix ?? '')));
        $suffix = $e(trim((string) ($model->draggo_counter_suffix ?? '')));

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-counter ' . $cls) . '"' . $idAttr . ' data-start="' . $start . '" data-end="' . $end . '" data-dur="' . $dur . '">'
            . ($prefix !== '' ? '<span class="draggo-counter-prefix">' . $prefix . '</span>' : '')
            . '<span class="draggo-counter-num">' . $start . '</span>'
            . ($suffix !== '' ? '<span class="draggo-counter-suffix">' . $suffix . '</span>' : '')
            . '</div>',
        );
    }
}
