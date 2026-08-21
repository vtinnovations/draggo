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
 * Stack-Reveal (scrollytelling): full-bleed cards that pin and overlap as you
 * scroll — each card scales/dims as the next slides over it. Cards are a
 * title+text repeater (stored in draggo_props); behaviour options (height,
 * scale, gap, …) are content fields; colours/radius come from the Style tab
 * (draggo_layout → compiler emits --ss-* vars). draggo-scroll.js drives it.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_stack')]
class StackRevealController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $cards = $this->decodePairs($model->draggo_ss_cards ?? null);
        if ($cards === []) {
            return new Response('');
        }

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clampInt = static fn (mixed $v, int $min, int $max, int $def): int => (is_numeric($v) && (int) $v >= $min && (int) $v <= $max) ? (int) $v : $def;

        $height = $clampInt($model->draggo_ss_height ?? null, 30, 100, 78);
        $top = $clampInt($model->draggo_ss_top ?? null, 0, 40, 8);
        $gap = $clampInt($model->draggo_ss_gap ?? null, 0, 200, 40);
        $scale = ($model->draggo_ss_scale ?? '') !== '' && is_numeric($model->draggo_ss_scale)
            ? max(0.7, min(1.0, (float) $model->draggo_ss_scale)) : 0.92;
        $showNum = ($model->draggo_ss_num ?? '') === '1';

        [$idAttr, $cls] = $this->draggoScope($model);

        $style = '--ss-h:' . $height . 'vh;--ss-top:' . $top . 'vh;--ss-gap:' . $gap . 'px';

        $out = '';
        foreach ($cards as $i => $card) {
            $title = trim((string) ($card['key'] ?? ''));
            $text = trim((string) ($card['value'] ?? ''));
            $out .= '<div class="draggo-ss-card"><div class="draggo-ss-inner">'
                . ($showNum ? '<div class="draggo-ss-num">' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '</div>' : '')
                . ($title !== '' ? '<h3 class="draggo-ss-title">' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<div class="draggo-ss-text">' . $this->richText($text) . '</div>' : '')
                . '</div></div>';
        }

        return new Response(
            '<div class="' . trim('draggo-ss ' . $cls) . '"' . $idAttr
            . ' data-draggo-scroll="stack" data-scale="' . $scale . '" style="' . $style . '">'
            . $out . '</div>',
        );
    }

    /**
     * Decode a serialized {key,value} repeater into a list of cards.
     *
     * @return list<array{key:string,value:string}>
     */
    private function decodePairs(mixed $raw): array
    {
        $out = [];
        foreach (StringUtil::deserialize($raw, true) as $row) {
            if (\is_array($row) && (trim((string) ($row['key'] ?? '')) !== '' || trim((string) ($row['value'] ?? '')) !== '')) {
                $out[] = ['key' => (string) ($row['key'] ?? ''), 'value' => (string) ($row['value'] ?? '')];
            }
        }

        return $out;
    }
}
