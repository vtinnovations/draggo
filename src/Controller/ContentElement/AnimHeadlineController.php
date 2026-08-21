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

/**
 * Animated headline (Tier 5): a heading with a rotating / typing word. The
 * before/after text stays fixed; the highlighted word cycles. draggo-frontend.js
 * drives the animation.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_anim')]
class AnimHeadlineController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $words = array_values(array_filter(
            array_map('trim', StringUtil::deserialize($model->draggo_an_words, true)),
            static fn (string $s): bool => $s !== '',
        ));
        if ($words === []) {
            return new Response('');
        }
        $tag = \in_array($model->draggo_an_tag ?? '', ['h1', 'h2', 'h3', 'div'], true) ? $model->draggo_an_tag : 'h2';
        $effect = ($model->draggo_an_effect ?? '') === 'type' ? 'type' : 'rotate';
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $before = trim((string) ($model->draggo_an_before ?? ''));
        $after = trim((string) ($model->draggo_an_after ?? ''));
        $dataWords = $e((string) json_encode($words, JSON_UNESCAPED_UNICODE));

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<' . $tag . ' class="' . trim('draggo-anim ' . $cls) . '"' . $idAttr . ' data-effect="' . $effect . '" data-words="' . $dataWords . '">'
            . ($before !== '' ? '<span class="draggo-anim-before">' . $e($before) . ' </span>' : '')
            . '<span class="draggo-anim-words">' . $e($words[0]) . '</span>'
            . ($after !== '' ? '<span class="draggo-anim-after"> ' . $e($after) . '</span>' : '')
            . '</' . $tag . '>',
        );
    }
}
