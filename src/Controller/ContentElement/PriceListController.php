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
 * Price list (Tier 5): a menu-style list — name … price per row (key = name,
 * value = price).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_pricelist')]
class PriceListController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $rows = StringUtil::deserialize($model->draggo_prl_items, true);
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $li = '';
        foreach ($rows as $row) {
            $name = trim((string) ($row['key'] ?? ''));
            $price = trim((string) ($row['value'] ?? ''));
            if ($name === '' && $price === '') {
                continue;
            }
            $li .= '<li class="draggo-prl-row">'
                . '<span class="draggo-prl-name">' . $e($name) . '</span>'
                . '<span class="draggo-prl-dots" aria-hidden="true"></span>'
                . '<span class="draggo-prl-price">' . $e($price) . '</span>'
                . '</li>';
        }
        if ($li === '') {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<ul class="' . trim('draggo-pricelist ' . $cls) . '"' . $idAttr . '>' . $li . '</ul>');
    }
}
