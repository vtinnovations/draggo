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
 * Text-Mask (scrollytelling): a large headline with an image visible THROUGH the
 * letters (background-clip:text); the image parallax-shifts as you scroll.
 * draggo-scroll.js (driver "textmask") moves the background position.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_scroll_textmask')]
class TextMaskController extends AbstractDraggoElementController
{
    use ScopedElementTrait;
    use FilePathTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $text = trim((string) ($model->draggo_tm_text ?? ''));
        $img = $this->fileUrl($this->framework, $model->draggo_tm_img ?? null);
        if ($text === '' || $img === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-tm ' . $cls) . '"' . $idAttr . ' data-draggo-scroll="textmask">'
            . '<h2 class="draggo-tm-text" style="--tm-img:url(\'' . $e($img) . '\');background-image:url(\'' . $e($img) . '\')">' . $e($text) . '</h2>'
            . '</div>',
        );
    }
}
