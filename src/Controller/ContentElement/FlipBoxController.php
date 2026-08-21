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
 * Flip box (Tier 2): a card that flips on hover to reveal back content. Front =
 * icon + title + text; back = title + text + optional button. Pure-CSS flip.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_flipbox')]
class FlipBoxController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $fTitle = trim((string) ($model->draggo_flip_ftitle ?? ''));
        $fText = trim((string) ($model->draggo_flip_ftext ?? ''));
        $bTitle = trim((string) ($model->draggo_flip_btitle ?? ''));
        $bText = trim((string) ($model->draggo_flip_btext ?? ''));
        if ($fTitle === '' && $fText === '' && $bTitle === '' && $bText === '') {
            return new Response('');
        }
        $icon = IconLibrary::render((string) ($model->draggo_flip_icon ?? ''));
        $h = (int) ($model->draggo_flip_height ?? 300);
        $h = ($h >= 120 && $h <= 900) ? $h : 300;

        $btn = trim((string) ($model->draggo_flip_btn ?? ''));
        $button = '';
        if ($btn !== '') {
            $url = trim((string) ($model->draggo_flip_url ?? ''));
            $url = $url !== '' ? $this->insertTags->replaceInline($url) : '';
            // Outline button (inherits the back face's text colour) — not the
            // global blue .draggo-btn, so it matches the card. No URL → render a
            // non-link span instead of a dead href="#".
            $button = $url !== ''
                ? '<a class="draggo-flip-btn" href="' . $e($url) . '">' . $e($btn) . '</a>'
                : '<span class="draggo-flip-btn">' . $e($btn) . '</span>';
        }

        $front = '<div class="draggo-flip-face draggo-flip-front">'
            . ($icon !== '' ? '<span class="draggo-flip-ico">' . $icon . '</span>' : '')
            . ($fTitle !== '' ? '<h3 class="draggo-flip-title">' . $e($fTitle) . '</h3>' : '')
            . ($fText !== '' ? '<div class="draggo-flip-text">' . $this->richText($fText) . '</div>' : '')
            . '</div>';
        $back = '<div class="draggo-flip-face draggo-flip-back">'
            . ($bTitle !== '' ? '<h3 class="draggo-flip-title">' . $e($bTitle) . '</h3>' : '')
            . ($bText !== '' ? '<div class="draggo-flip-text">' . $this->richText($bText) . '</div>' : '')
            . $button
            . '</div>';

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-flip ' . $cls) . '"' . $idAttr . ' style="--flip-h:' . $h . 'px">'
            . '<div class="draggo-flip-inner">' . $front . $back . '</div>'
            . '</div>',
        );
    }
}
