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
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Icon\IconLibrary;

/**
 * Standalone Icon element (Tier 1). Renders one inline-SVG icon from the
 * library; size + colour come from the universal Style tab (font-size → svg
 * size, colour → currentColor). Optional link wraps it in an <a>.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_icon')]
class IconElementController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $svg = IconLibrary::render((string) ($model->draggo_icon ?? ''));
        if ($svg === '') {
            return new Response('');
        }

        $link = trim((string) ($model->draggo_icon_link ?? ''));
        if ($link !== '') {
            $url = htmlspecialchars($this->insertTags->replaceInline($link), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $svg = '<a href="' . $url . '">' . $svg . '</a>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<span class="' . trim('draggo-icon ' . $cls) . '"' . $idAttr . '>' . $svg . '</span>');
    }
}
