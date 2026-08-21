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
 * Curtain-Cover (scrollytelling): full-bleed panels (image background + heading +
 * text) pinned so each next panel slides over the previous like a curtain; the
 * covered panel scales + dims. draggo-scroll.js (driver "curtain") drives it.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_curtain')]
class CurtainController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $panels = $this->decodePairs($model->draggo_ct_panels ?? null);
        if ($panels === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $images = $this->fileUrls($this->framework, $model->draggo_ct_images ?? null);

        [$idAttr, $cls] = $this->draggoScope($model);

        $out = '';
        foreach ($panels as $i => $panel) {
            $title = trim((string) ($panel['key'] ?? ''));
            $text = trim((string) ($panel['value'] ?? ''));
            $img = $images[$i] ?? ($images !== [] ? $images[count($images) - 1] : '');
            $bg = $img !== '' ? ' style="background-image:url(\'' . $e($img) . '\')"' : '';
            $out .= '<div class="draggo-ct-panel"' . $bg . '><div class="draggo-ct-inner">'
                . ($title !== '' ? '<h2>' . $e($title) . '</h2>' : '')
                . ($text !== '' ? '<p>' . $this->richText($text) . '</p>' : '')
                . '</div></div>';
        }

        return new Response(
            '<div class="' . trim('draggo-ct ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="curtain">' . $out . '</div>',
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
