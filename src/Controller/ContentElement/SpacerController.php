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
 * Atomic vertical spacer (Tier 1).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_spacer')]
class SpacerController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $h = (int) ($model->draggo_space ?? 0);
        $h = ($h >= 0 && $h <= 2000) ? $h : 24;

        [$idAttr, $cls] = $this->draggoScope($model);
        $clsAttr = trim('draggo-spacer ' . $cls);

        return new Response('<div class="' . $clsAttr . '"' . $idAttr . ' style="height:' . $h . 'px" aria-hidden="true"></div>');
    }
}
