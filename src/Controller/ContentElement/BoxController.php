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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Icon-Box and Image-Box (Tier 1): media (icon/image from the file picker) +
 * title + text, with media position (top/left/right). One controller, two
 * registered types; the variant only changes the wrapper class.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_iconbox')]
#[AsContentElement(category: 'draggo', type: 'draggo_imagebox')]
class BoxController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $title = trim((string) ($model->draggo_ib_title ?? ''));
        $text = trim((string) ($model->draggo_ib_text ?? ''));
        $variant = ($model->type === 'draggo_imagebox') ? 'imagebox' : 'iconbox';
        $pos = \in_array($model->draggo_ib_pos ?? '', ['top', 'left', 'right'], true) ? $model->draggo_ib_pos : 'top';

        $this->framework->initialize();
        $src = $this->path($model->draggo_ib_src ?? null);

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Icon (library) takes precedence over an uploaded image when set.
        $iconSvg = \Vtinnovations\Draggo\Icon\IconLibrary::render((string) ($model->draggo_ib_icon ?? ''));
        if ($iconSvg !== '') {
            $media = '<span class="draggo-box-media draggo-box-icon">' . $iconSvg . '</span>';
        } else {
            // Dedicated alt text; falls back to the title only if none given.
            $alt = trim((string) ($model->draggo_ib_alt ?? ''));
            if ($alt === '') {
                $alt = $title;
            }
            $media = $src !== '' ? '<span class="draggo-box-media"><img src="/' . $e($src) . '" alt="' . $e($alt) . '"></span>' : '';
        }
        $body = '<div class="draggo-box-body">'
            . ($title !== '' ? '<h3 class="draggo-box-title">' . $e($title) . '</h3>' : '')
            . ($text !== '' ? '<div class="draggo-box-text">' . $this->richText($text) . '</div>' : '')
            . '</div>';

        if ($media === '' && $title === '' && $text === '') {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);
        $clsAttr = trim('draggo-box draggo-' . $variant . ' draggo-box--' . $pos . ' ' . $cls);

        return new Response('<div class="' . $clsAttr . '"' . $idAttr . '>' . $media . $body . '</div>');
    }

    /** Binary UUID → files/ path. */
    private function path(mixed $uuid): string
    {
        if (empty($uuid)) {
            return '';
        }
        /** @var FilesModel $adapter */
        $adapter = $this->framework->getAdapter(FilesModel::class);
        $file = $adapter->findByUuid($uuid);

        return $file !== null ? (string) $file->path : '';
    }
}
