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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Draggo gallery (Tier 2): a designable image gallery — grid / masonry / tiles
 * layout, columns, gap, hover effect, optional captions and linking (lightbox or
 * full image). The Contao core gallery only stacks images; this gives full
 * design control. Lightbox handled by draggo-frontend.js.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_gallery')]
class GalleryController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    private const RATIOS = ['1:1' => '100%', '4:3' => '75%', '3:4' => '133.33%', '16:9' => '56.25%', '3:2' => '66.66%'];

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        /** @var FilesModel $files */
        $files = $this->framework->getAdapter(FilesModel::class);

        $images = [];
        foreach (StringUtil::deserialize($model->draggo_ga_images, true) as $uuid) {
            if (empty($uuid)) {
                continue;
            }
            $f = $files->findByUuid($uuid);
            if ($f !== null && $f->path) {
                $images[] = (string) $f->path;
            }
        }
        if ($images === []) {
            return new Response('');
        }

        $cols = (int) ($model->draggo_ga_cols ?? 3);
        $cols = ($cols >= 1 && $cols <= 8) ? $cols : 3;
        $gap = (int) ($model->draggo_ga_gap ?? 8);
        $gap = ($gap >= 0 && $gap <= 80) ? $gap : 8;
        $layout = \in_array($model->draggo_ga_layout ?? '', ['grid', 'masonry', 'tiles'], true) ? $model->draggo_ga_layout : 'grid';
        $ratio = self::RATIOS[(string) ($model->draggo_ga_ratio ?? '1:1')] ?? '100%';
        $hover = \in_array($model->draggo_ga_hover ?? '', ['none', 'zoom', 'overlay', 'grayscale'], true) ? $model->draggo_ga_hover : 'zoom';
        $link = \in_array($model->draggo_ga_link ?? '', ['none', 'file', 'lightbox'], true) ? $model->draggo_ga_link : 'lightbox';

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $items = '';
        foreach ($images as $path) {
            $src = '/' . $e($path);
            $img = '<img class="draggo-ga-img" src="' . $src . '" alt="" loading="lazy">';
            $inner = $layout === 'tiles' ? '<span class="draggo-ga-frame">' . $img . '</span>' : $img;
            $overlay = $hover === 'overlay' ? '<span class="draggo-ga-ov"></span>' : '';

            if ($link === 'none') {
                $items .= '<figure class="draggo-ga-item">' . $inner . $overlay . '</figure>';
            } else {
                $rel = $link === 'lightbox' ? ' data-lightbox' : '';
                $target = $link === 'file' ? ' target="_blank" rel="noopener"' : '';
                $items .= '<a class="draggo-ga-item" href="' . $src . '"' . $rel . $target . '>' . $inner . $overlay . '</a>';
            }
        }

        [$idAttr, $cls] = $this->draggoScope($model);
        $lb = $link === 'lightbox' ? ' data-gallery-lightbox' : '';

        return new Response(
            '<div class="' . trim('draggo-gallery draggo-gallery--' . $layout . ' draggo-gallery--hover-' . $hover . ' ' . $cls) . '"' . $idAttr
            . ' style="--ga-cols:' . $cols . ';--ga-gap:' . $gap . 'px;--ga-ratio:' . $ratio . '"' . $lb . '>'
            . $items
            . '</div>',
        );
    }
}
