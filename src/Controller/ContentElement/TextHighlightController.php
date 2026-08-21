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
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Text-Highlight (scrollytelling): a large statement whose words fill in with
 * colour one by one as the block scrolls through the viewport. The sentence is
 * split into per-word spans; draggo-scroll.js (driver "text") lights them.
 * dim/lit colours come from the Style tab (--th-dim / --th-lit).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_text')]
class TextHighlightController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    private const TAGS = ['h2', 'h3', 'p', 'div'];

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $text = trim((string) ($model->draggo_th_text ?? ''));
        if ($text === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tag = \in_array($model->draggo_th_tag ?? '', self::TAGS, true) ? (string) $model->draggo_th_tag : 'div';

        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $spans = '';
        foreach ($words as $w) {
            $spans .= '<span class="draggo-th-w">' . $e($w) . '</span> ';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<' . $tag . ' class="' . trim('draggo-th ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="text">'
            . trim($spans) . '</' . $tag . '>',
        );
    }
}
