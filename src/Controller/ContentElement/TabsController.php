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
 * Tabs (Tier 2): label/content items. Switching ships in draggo-frontend.js.
 * Uses .draggo-tabw (not .draggo-tabs, which is the editor panel class).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_tabs')]
class TabsController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $items = StringUtil::deserialize($model->draggo_items, true);
        if ($items === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $nav = '';
        $panels = '';
        $i = 0;
        foreach ($items as $item) {
            $label = $e((string) ($item['key'] ?? ''));
            // Body is rich text from the editor (trusted BE content) → raw HTML.
            $body = (string) ($item['value'] ?? '');
            if ($label === '' && $body === '') {
                continue;
            }
            $on = $i === 0 ? ' is-active' : '';
            $nav .= '<button type="button" class="draggo-tabw-btn' . $on . '" data-i="' . $i . '">' . ($label !== '' ? $label : ('Tab ' . ($i + 1))) . '</button>';
            $panels .= '<div class="draggo-tabw-panel' . $on . '" data-i="' . $i . '">' . $body . '</div>';
            ++$i;
        }
        if ($i === 0) {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<div class="' . trim('draggo-tabw ' . $cls) . '"' . $idAttr . '><div class="draggo-tabw-nav">' . $nav . '</div><div class="draggo-tabw-panels">' . $panels . '</div></div>');
    }
}
