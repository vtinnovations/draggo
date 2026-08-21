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
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tilt-3D Cards (scrollytelling + interactive): a card grid that reveals on
 * scroll-in and tilts in 3D toward the pointer. draggo-scroll.js (one-shot "tilt")
 * adds .is-in and wires per-card pointer tilt.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_tilt')]
class TiltCardsController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $cards = $this->decodePairs($model->draggo_ti_cards ?? null);
        if ($cards === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cols = (is_numeric($model->draggo_ti_cols ?? null) && (int) $model->draggo_ti_cols >= 1 && (int) $model->draggo_ti_cols <= 6) ? (int) $model->draggo_ti_cols : 3;

        [$idAttr, $cls] = $this->draggoScope($model);

        $out = '';
        foreach ($cards as $card) {
            $title = trim((string) ($card['key'] ?? ''));
            $text = trim((string) ($card['value'] ?? ''));
            $img = trim((string) ($card['img'] ?? ''));

            $cardCls = 'draggo-tilt-card';
            $style = '';
            if ($img !== '') {
                // files/… path (or http URL) → background-image. The save path
                // (safeFilePath) already stripped quotes/parens, so url() is safe.
                $url = preg_match('#^https?://#i', $img) ? $img : '/' . ltrim($img, '/');
                $cardCls .= ' draggo-tilt-card--img';
                $style = ' style="background-image:url(&quot;' . $e($url) . '&quot;)"';
            }
            // With a photo, title+text sit in an inner layer so the ::before
            // overlay can darken the image behind them for readability.
            // Body comes from the editor RTE → already block HTML (<p>…</p>);
            // richText() emits it raw (and still escapes legacy plain text).
            $inner = ($title !== '' ? '<h3>' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<div class="draggo-tilt-card-text">' . $this->richText($text) . '</div>' : '');
            $out .= '<div class="' . $cardCls . '"' . $style . '>'
                . ($img !== '' ? '<div class="draggo-tilt-card-body">' . $inner . '</div>' : $inner)
                . '</div>';
        }

        return new Response(
            '<div class="' . trim('draggo-tilt ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="tilt" style="--tilt-cols:' . $cols . '">'
            . $out . '</div>',
        );
    }

    /** @return list<array{key:string,value:string,img:string}> */
    private function decodePairs(mixed $raw): array
    {
        $out = [];
        foreach (StringUtil::deserialize($raw, true) as $row) {
            if (\is_array($row) && (trim((string) ($row['key'] ?? '')) !== '' || trim((string) ($row['value'] ?? '')) !== '' || trim((string) ($row['img'] ?? '')) !== '')) {
                $out[] = [
                    'key'   => (string) ($row['key'] ?? ''),
                    'value' => (string) ($row['value'] ?? ''),
                    'img'   => (string) ($row['img'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
