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
 * Progress / skill bar (Tier 2). Fills to a percentage when scrolled into view
 * (draggo-frontend.js). Optional label + live percentage.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_progress')]
class ProgressController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $val = (int) ($model->draggo_progress_value ?? 0);
        $val = max(0, min(100, $val));
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = $e(trim((string) ($model->draggo_progress_label ?? '')));
        $show = ($model->draggo_progress_show ?? '') === '1';

        [$idAttr, $cls] = $this->draggoScope($model);

        $head = ($label !== '' || $show)
            ? '<div class="draggo-progress-label">' . ($label !== '' ? '<span>' . $label . '</span>' : '<span></span>')
                . ($show ? '<span class="draggo-progress-pct">' . $val . '%</span>' : '') . '</div>'
            : '';

        return new Response(
            '<div class="' . trim('draggo-progress ' . $cls) . '"' . $idAttr . '>'
            . $head
            . '<div class="draggo-progress-track"><div class="draggo-progress-bar" data-value="' . $val . '"></div></div>'
            . '</div>',
        );
    }
}
