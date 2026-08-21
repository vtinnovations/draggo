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
 * Accordion (Tier 2): a list of title/content items. Toggle behaviour ships in
 * draggo-frontend.js; base look in draggo-frontend.css. ARIA-friendly.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_accordion')]
class AccordionController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $items = StringUtil::deserialize($model->draggo_items, true);
        if ($items === []) {
            return new Response('');
        }
        $multi = ($model->draggo_acc_multi ?? '') === '1' ? '1' : '0';
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        [$idAttr, $cls] = $this->draggoScope($model);
        $html = '<div class="' . trim('draggo-acc ' . $cls) . '"' . $idAttr . ' data-multi="' . $multi . '">';
        foreach ($items as $item) {
            $title = $e((string) ($item['key'] ?? ''));
            // Body is rich text from the editor (trusted BE content) → raw HTML.
            $body = (string) ($item['value'] ?? '');
            if ($title === '' && $body === '') {
                continue;
            }
            $html .= '<div class="draggo-acc-item">'
                . '<button type="button" class="draggo-acc-head" aria-expanded="false">' . $title . '<span class="draggo-acc-ico" aria-hidden="true"></span></button>'
                . '<div class="draggo-acc-body"><div class="draggo-acc-inner">' . $body . '</div></div>'
                . '</div>';
        }
        $html .= '</div>';

        return new Response($html);
    }
}
