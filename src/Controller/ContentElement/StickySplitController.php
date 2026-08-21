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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sticky-Split Story (scrollytelling): a pinned visual column whose image swaps
 * as the reader scrolls past each text step in the other column. Steps are a
 * title+text repeater; the visuals are an image list, matched to steps by index.
 * draggo-scroll.js (driver "split") activates step + visual together.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_split')]
class StickySplitController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $steps = $this->decodePairs($model->draggo_sp_steps ?? null);
        if ($steps === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $images = $this->fileUrls($this->framework, $model->draggo_sp_images ?? null);
        $visualsRight = ($model->draggo_sp_side ?? '') === 'right';

        [$idAttr, $cls] = $this->draggoScope($model);

        $visuals = '';
        foreach ($steps as $i => $step) {
            $img = $images[$i] ?? ($images !== [] ? $images[count($images) - 1] : '');
            $visuals .= '<div class="draggo-sp-visual' . ($i === 0 ? ' is-active' : '') . '">'
                . ($img !== '' ? '<img src="' . $e($img) . '" alt="" loading="lazy">' : '')
                . '</div>';
        }
        $stepsHtml = '';
        foreach ($steps as $i => $step) {
            $title = trim((string) ($step['key'] ?? ''));
            $text = trim((string) ($step['value'] ?? ''));
            $stepsHtml .= '<div class="draggo-sp-step' . ($i === 0 ? ' is-active' : '') . '">'
                . ($title !== '' ? '<h3>' . $e($title) . '</h3>' : '')
                . ($text !== '' ? '<div>' . $this->richText($text) . '</div>' : '')
                . '</div>';
        }

        $visCol = '<div class="draggo-sp-visuals">' . $visuals . '</div>';
        $stepCol = '<div class="draggo-sp-steps">' . $stepsHtml . '</div>';
        $grid = $visualsRight ? $stepCol . $visCol : $visCol . $stepCol;

        return new Response(
            '<div class="' . trim('draggo-sp ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="split">'
            . '<div class="draggo-sp-grid">' . $grid . '</div></div>',
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
