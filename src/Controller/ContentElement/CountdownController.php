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
 * Countdown (Tier 5): counts down to a target date/time. Ticking + expiry text
 * handled by draggo-frontend.js.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_countdown')]
class CountdownController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $ts = (int) ($model->draggo_cd_date ?? 0);
        if ($ts <= 0) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $expired = $e(trim((string) ($model->draggo_cd_expired ?? '')) ?: 'Abgelaufen');

        $units = [['d', 'Tage'], ['h', 'Std'], ['m', 'Min'], ['s', 'Sek']];
        $cells = '';
        foreach ($units as [$u, $lbl]) {
            $cells .= '<div class="draggo-cd-unit"><span class="draggo-cd-num" data-u="' . $u . '">–</span><span class="draggo-cd-lbl">' . $lbl . '</span></div>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-countdown ' . $cls) . '"' . $idAttr . ' data-target="' . $ts . '" data-expired="' . $expired . '">' . $cells . '</div>',
        );
    }
}
