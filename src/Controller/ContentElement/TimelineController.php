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
 * Pinned-Timeline (scrollytelling): a vertical line that fills as you scroll,
 * activating each step in turn. Steps are a title+text repeater (draggo_props);
 * the accent/track colours come from the Style tab (--tl-accent / --tl-track).
 * draggo-scroll.js (driver "timeline") drives the fill + activation.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_timeline')]
class TimelineController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $steps = $this->decodePairs($model->draggo_tl_steps ?? null);
        if ($steps === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        [$idAttr, $cls] = $this->draggoScope($model);

        $out = '';
        foreach ($steps as $step) {
            $title = trim((string) ($step['key'] ?? ''));
            $text = trim((string) ($step['value'] ?? ''));
            $out .= '<div class="draggo-tl-step">'
                . ($title !== '' ? '<h3>' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<p>' . $this->richText($text) . '</p>' : '')
                . '</div>';
        }

        return new Response(
            '<div class="' . trim('draggo-tl ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="timeline">'
            . '<div class="draggo-tl-line"><div class="draggo-tl-fill"></div></div>'
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
