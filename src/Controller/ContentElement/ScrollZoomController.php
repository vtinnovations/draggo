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
 * Scroll-Zoom (scrollytelling): a framed image scales up from small to full as it
 * scrolls into view (Apple-style reveal), corners rounding out. draggo-scroll.js
 * (driver "zoom") drives the scale via data-from.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_zoom')]
class ScrollZoomController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $src = $this->fileUrl($this->framework, $model->draggo_zm_src ?? null);
        if ($src === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $from = ($model->draggo_zm_from ?? '') !== '' && is_numeric($model->draggo_zm_from)
            ? max(0.2, min(0.95, (float) $model->draggo_zm_from)) : 0.6;
        $alt = $e(trim((string) ($model->draggo_zm_alt ?? '')));

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-zm ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="zoom" data-from="' . $from . '" style="--zm-from:' . $from . '">'
            // Eager (not lazy): this is the section's only content. A lazy image
            // with no intrinsic size collapses the scaled frame to 0 height, so
            // the section reserves no scroll range and the image never enters
            // view to trigger loading (chicken-and-egg) — the zoom never runs.
            . '<div class="draggo-zm-frame"><img src="' . $e($src) . '" alt="' . $alt . '" decoding="async"></div>'
            . '</div>',
        );
    }
}
