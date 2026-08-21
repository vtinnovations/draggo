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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parallax-Layers (scrollytelling): a background (and optional mid) layer that
 * move at different speeds behind centred content, creating depth as you scroll.
 * draggo-scroll.js (driver "parallax") translates each [data-speed] layer.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_parallax')]
class ParallaxController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $bg = $this->fileUrl($this->framework, $model->draggo_px_bg ?? null);
        $mid = $this->fileUrl($this->framework, $model->draggo_px_mid ?? null);
        $title = trim((string) ($model->draggo_px_title ?? ''));
        $text = trim((string) ($model->draggo_px_text ?? ''));
        if ($bg === '' && $title === '' && $text === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clampInt = static fn (mixed $v, int $min, int $max, int $def): int => (is_numeric($v) && (int) $v >= $min && (int) $v <= $max) ? (int) $v : $def;
        $height = $clampInt($model->draggo_px_height ?? null, 30, 100, 70);
        $speed = ($model->draggo_px_speed ?? '') !== '' && is_numeric($model->draggo_px_speed)
            ? max(0.0, min(1.0, (float) $model->draggo_px_speed)) : 0.4;

        [$idAttr, $cls] = $this->draggoScope($model);

        $layers = '';
        if ($bg !== '') {
            $layers .= '<div class="draggo-px-layer" data-speed="' . $speed . '" style="background-image:url(\'' . $e($bg) . '\')"></div>';
        }
        if ($mid !== '') {
            $layers .= '<div class="draggo-px-layer" data-speed="' . ($speed + 0.3) . '" style="background-image:url(\'' . $e($mid) . '\')"></div>';
        }
        $content = '<div class="draggo-px-content">'
            . ($title !== '' ? '<h2>' . $e($title) . '</h2>' : '')
            . ($text !== '' ? '<div>' . $this->richText($text) . '</div>' : '')
            . '</div>';

        return new Response(
            '<div class="' . trim('draggo-px ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="parallax" style="--px-h:' . $height . 'vh">'
            . $layers . $content . '</div>',
        );
    }
}
