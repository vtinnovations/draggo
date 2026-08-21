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
 * Reveal-Grid (scrollytelling): a card grid whose items fade + rise in, staggered
 * by index, when the grid scrolls into view. draggo-scroll.js (one-shot "revealgrid")
 * adds .is-in; the per-item --i delay drives the stagger.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_revealgrid')]
class RevealGridController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $items = $this->decodePairs($model->draggo_rg_items ?? null);
        if ($items === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cols = (is_numeric($model->draggo_rg_cols ?? null) && (int) $model->draggo_rg_cols >= 1 && (int) $model->draggo_rg_cols <= 6) ? (int) $model->draggo_rg_cols : 3;

        [$idAttr, $cls] = $this->draggoScope($model);

        $out = '';
        foreach ($items as $i => $item) {
            $title = trim((string) ($item['key'] ?? ''));
            $text = trim((string) ($item['value'] ?? ''));
            $out .= '<div class="draggo-rg-item" style="--i:' . $i . '">'
                . ($title !== '' ? '<h3>' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<p>' . $this->richText($text) . '</p>' : '')
                . '</div>';
        }

        return new Response(
            '<div class="' . trim('draggo-rg ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="revealgrid" style="--rg-cols:' . $cols . '">'
            . $out . '</div>',
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
