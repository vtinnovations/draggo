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
 * Table of contents (Tier 5): builds a jump-link list from the page's headings.
 * draggo-frontend.js scans the headings, assigns missing ids and fills the list.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_toc')]
class TocController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $levels = \in_array($model->draggo_toc_levels ?? '', ['2', '2-3', '2-4'], true) ? $model->draggo_toc_levels : '2-3';
        $map = ['2' => '2', '2-3' => '2,3', '2-4' => '2,3,4'];
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $e(trim((string) ($model->draggo_toc_title ?? '')) ?: 'Inhalt');

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<nav class="' . trim('draggo-toc ' . $cls) . '"' . $idAttr . ' data-levels="' . $map[$levels] . '" aria-label="Inhaltsverzeichnis">'
            . '<p class="draggo-toc-title">' . $title . '</p>'
            . '<ul class="draggo-toc-list"></ul>'
            . '</nav>',
        );
    }
}
