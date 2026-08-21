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
 * Blockquote (Tier 2): quote text + optional author / source.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_quote')]
class QuoteController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $text = trim((string) ($model->draggo_quote_text ?? ''));
        if ($text === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $author = trim((string) ($model->draggo_quote_author ?? ''));

        [$idAttr, $cls] = $this->draggoScope($model);

        $caption = $author !== '' ? '<figcaption class="draggo-quote-author">' . $e($author) . '</figcaption>' : '';

        return new Response(
            '<figure class="' . trim('draggo-quote ' . $cls) . '"' . $idAttr . '>'
            . '<blockquote class="draggo-quote-text">' . $this->richText($text) . '</blockquote>'
            . $caption
            . '</figure>',
        );
    }
}
