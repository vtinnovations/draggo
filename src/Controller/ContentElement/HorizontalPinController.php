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
 * Horizontal-Pin (scrollytelling): the section pins to the viewport and its inner
 * track scrolls sideways as the user scrolls down — panels reveal one by one —
 * then vertical scrolling resumes. Panels are a title+text repeater (draggo_props);
 * track length / panel width / gap are content options; colours come from the
 * Style tab (--hp-* vars). draggo-scroll.js drives it; a reduced-motion fallback
 * turns the track into a normal horizontal swipe strip.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_hpin')]
class HorizontalPinController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $panels = $this->decodePairs($model->draggo_hp_panels ?? null);
        if ($panels === []) {
            return new Response('');
        }

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clampInt = static fn (mixed $v, int $min, int $max, int $def): int => (is_numeric($v) && (int) $v >= $min && (int) $v <= $max) ? (int) $v : $def;

        // Track length scales with panel count by default so each panel gets ~one
        // screen of scroll; the user can override.
        $track = $clampInt($model->draggo_hp_track ?? null, 120, 800, min(600, 100 + \count($panels) * 80));
        $width = $clampInt($model->draggo_hp_w ?? null, 30, 100, 70);
        $gap = $clampInt($model->draggo_hp_gap ?? null, 0, 12, 3);

        [$idAttr, $cls] = $this->draggoScope($model);
        $style = '--hp-track:' . $track . 'vh;--hp-w:' . $width . 'vw;--hp-gap:' . $gap . 'vw';

        $out = '';
        foreach ($panels as $panel) {
            $title = trim((string) ($panel['key'] ?? ''));
            $text = trim((string) ($panel['value'] ?? ''));
            $out .= '<div class="draggo-hp-panel">'
                . ($title !== '' ? '<h3>' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<p>' . $this->richText($text) . '</p>' : '')
                . '</div>';
        }

        return new Response(
            '<div class="' . trim('draggo-hp ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="hpin" style="' . $style . '">'
            . '<div class="draggo-hp-stage"><div class="draggo-hp-track">' . $out . '</div></div>'
            . '</div>',
        );
    }

    /** @return list<array{key:string,value:string}> */
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
