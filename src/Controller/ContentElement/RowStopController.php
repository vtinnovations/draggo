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
use Vtinnovations\Draggo\Grid\GridStack;

/**
 * Closes a Bootstrap grid row (last column + row + container). Wrapper "stop"
 * element.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_row_stop')]
class RowStopController extends AbstractDraggoElementController
{
    public function __construct(private readonly GridStack $gridStack)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        // Raw markup, no template (see RowStartController).
        return new Response($this->gridStack->closeRow());
    }
}
