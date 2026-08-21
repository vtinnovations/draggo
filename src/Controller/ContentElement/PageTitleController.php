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
 * Page Title (Tier 4, Theme-Builder): outputs the CURRENT page's title /
 * navigation title / description, in a chosen heading tag. Dynamic — reads the
 * global $objPage. Shows a placeholder in the editor where no page is in scope.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_pagetitle')]
class PageTitleController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $tag = \in_array($model->draggo_pt_tag ?? '', ['h1', 'h2', 'h3', 'div'], true) ? $model->draggo_pt_tag : 'h1';
        $source = \in_array($model->draggo_pt_source ?? '', ['title', 'pageTitle', 'description'], true) ? $model->draggo_pt_source : 'title';

        global $objPage;
        $value = '';
        if ($objPage !== null) {
            $value = (string) ($objPage->{$source} ?? '');
            if ($value === '') {
                $value = (string) ($objPage->title ?? '');
            }
        }
        if ($value === '') {
            $value = '[Seitentitel]';
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<' . $tag . ' class="' . trim('draggo-pagetitle ' . $cls) . '"' . $idAttr . '>' . $e($value) . '</' . $tag . '>');
    }
}
