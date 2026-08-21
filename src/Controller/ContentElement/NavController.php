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
use Vtinnovations\Draggo\Nav\NavRenderer;

/**
 * Draggo navigation element: renders the Contao page-tree navigation with a
 * chosen preset (horizontal / vertical / hamburger). Auto-connected to the
 * page structure — ideal for headers/footers built in Draggo.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_nav')]
class NavController extends AbstractDraggoElementController
{
    public function __construct(private readonly NavRenderer $renderer)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        // cssID = serialized [id, class] — carry it onto the <nav> so scoped
        // element styles (.draggo-el-{id} …) apply to the navigation.
        $cssID = StringUtil::deserialize($model->cssID, true);

        // Hamburger/toggle icon comes from the element's style layout (Style tab).
        $iconKey = '';
        if (!empty($model->draggo_layout)) {
            $layout = json_decode((string) $model->draggo_layout, true);
            if (\is_array($layout) && !empty($layout['navHamburgerIcon'])) {
                $iconKey = (string) $layout['navHamburgerIcon'];
            }
        }
        $iconHtml = IconLibrary::render($iconKey !== '' ? $iconKey : 'menu');

        return new Response($this->renderer->render(
            (int) ($model->draggo_nav_root ?? 0),
            (int) ($model->draggo_nav_levels ?? 1),
            (string) ($model->draggo_nav_preset ?? 'horizontal'),
            (string) ($cssID[1] ?? ''),
            (string) ($cssID[0] ?? ''),
            $iconHtml,
        ));
    }
}
