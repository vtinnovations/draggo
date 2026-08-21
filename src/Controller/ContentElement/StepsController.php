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
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Steps / process tracker (Tier 5): a numbered list of title + text steps with a
 * connector line (key = title, value = text).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_steps')]
class StepsController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $rows = StringUtil::deserialize($model->draggo_stp_items, true);
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $li = '';
        $n = 0;
        foreach ($rows as $row) {
            $title = trim((string) ($row['key'] ?? ''));
            $text = trim((string) ($row['value'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            ++$n;
            $li .= '<li class="draggo-step">'
                . '<span class="draggo-step-num">' . $n . '</span>'
                . '<div class="draggo-step-body">'
                . ($title !== '' ? '<h4 class="draggo-step-title">' . $e($title) . '</h4>' : '')
                . ($text !== '' ? '<div class="draggo-step-text">' . nl2br($e($text)) . '</div>' : '')
                . '</div></li>';
        }
        if ($n === 0) {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<ol class="' . trim('draggo-steps ' . $cls) . '"' . $idAttr . '>' . $li . '</ol>');
    }
}
