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
use Vtinnovations\Draggo\Icon\IconLibrary;

/**
 * Icon list (Tier 2): a bullet list where the marker is a chosen library icon
 * (one icon for the whole list — the common Elementor case). One item per line.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_iconlist')]
class IconListController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $items = array_values(array_filter(
            array_map('trim', StringUtil::deserialize($model->draggo_il_items, true)),
            static fn (string $s): bool => $s !== '',
        ));
        if ($items === []) {
            return new Response('');
        }
        $icon = IconLibrary::render((string) ($model->draggo_il_icon ?? 'check'));
        if ($icon === '') {
            $icon = IconLibrary::render('check');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $li = '';
        foreach ($items as $item) {
            $li .= '<li class="draggo-ilist-item"><span class="draggo-ilist-ico">' . $icon . '</span><span class="draggo-ilist-txt">' . $e($item) . '</span></li>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<ul class="' . trim('draggo-ilist ' . $cls) . '"' . $idAttr . '>' . $li . '</ul>');
    }
}
