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
 * Code block (Tier 5): a styled, escaped code box with an optional language
 * label + copy button. No external highlighter (no egress) — plain monospaced
 * presentation; a syntax-highlight lib can be layered later if wanted.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_codeblock')]
class CodeBlockController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $code = (string) ($model->draggo_code ?? '');
        if (trim($code) === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lang = trim((string) ($model->draggo_code_lang ?? ''));
        $copy = ($model->draggo_code_copy ?? '1') === '1';

        $bar = '';
        if ($lang !== '' || $copy) {
            $bar = '<div class="draggo-code-bar">'
                . ($lang !== '' ? '<span class="draggo-code-lang">' . $e($lang) . '</span>' : '<span></span>')
                . ($copy ? '<button type="button" class="draggo-code-copy">Kopieren</button>' : '')
                . '</div>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-code ' . $cls) . '"' . $idAttr . '>'
            . $bar
            . '<pre class="draggo-code-pre"><code>' . $e($code) . '</code></pre>'
            . '</div>',
        );
    }
}
