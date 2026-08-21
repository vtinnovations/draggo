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
 * Video playlist (Tier 5): a main player + a clickable list. Supports YouTube
 * (privacy nocookie), Vimeo and self-hosted MP4/WebM. Nothing loads from an
 * external provider until the visitor clicks (GDPR-friendly, no consent gate
 * needed because no request happens up front). No external library.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_videoplaylist')]
class VideoPlaylistController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    private const RATIOS = ['16:9' => '56.25%', '4:3' => '75%', '1:1' => '100%'];

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $items = [];
        foreach (StringUtil::deserialize($model->draggo_vp_items, true) as $row) {
            $title = trim((string) ($row['key'] ?? ''));
            $url = trim((string) ($row['value'] ?? ''));
            if ($url === '') {
                continue;
            }
            $video = $this->parse($url);
            if ($video === null) {
                continue;
            }
            $items[] = ['title' => $title !== '' ? $title : 'Video ' . (\count($items) + 1)] + $video;
        }
        if ($items === []) {
            return new Response('');
        }

        $ratio = self::RATIOS[(string) ($model->draggo_vp_ratio ?? '16:9')] ?? '56.25%';

        $list = '';
        foreach ($items as $i => $it) {
            $attr = $it['kind'] === 'native'
                ? ' data-native="' . $e($it['src']) . '"'
                : ' data-embed="' . $e($it['src']) . '"';
            $list .= '<li><button type="button" class="draggo-vp-item' . ($i === 0 ? ' is-active' : '') . '" data-i="' . $i . '"' . $attr . '>'
                . '<span class="draggo-vp-ico" aria-hidden="true">▶</span><span class="draggo-vp-label">' . $e($it['title']) . '</span></button></li>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-vplaylist ' . $cls) . '"' . $idAttr . ' style="--vp-ratio:' . $ratio . '">'
            . '<div class="draggo-vp-stage"><button type="button" class="draggo-vp-play" data-i="0" aria-label="Video abspielen">▶</button></div>'
            . '<ul class="draggo-vp-list">' . $list . '</ul>'
            . '</div>',
        );
    }

    /**
     * @return array{kind:string,src:string}|null
     */
    private function parse(string $url): ?array
    {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})#i', $url, $m)) {
            return ['kind' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0'];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
            return ['kind' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1'];
        }
        if (preg_match('#^https?://[^\s\'"<>]+\.(mp4|webm|ogg|ogv)(\?[^\s\'"<>]*)?$#i', $url)
            || preg_match('#^files/[^\s\'"<>]+\.(mp4|webm|ogg|ogv)$#i', $url)) {
            $src = str_starts_with($url, 'files/') ? '/' . $url : $url;
            return ['kind' => 'native', 'src' => $src];
        }

        return null;
    }
}
