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
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Atomic button (Tier 1). The element's cssID hook class goes on the <a> itself
 * so the Style tab (background, padding, radius, colour …) styles the button
 * directly. Base look ships in draggo-frontend.css (.draggo-btn).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_button')]
class ButtonController extends AbstractDraggoElementController
{
    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $text = trim((string) ($model->draggo_btn_text ?? ''));
        if ($text === '') {
            return new Response('');
        }

        // Dynamic tags (Tier 3): resolve insert tags in both label and link.
        $text = $this->insertTags->replaceInline($text);

        $url = trim((string) ($model->draggo_btn_url ?? ''));
        $url = $url !== '' ? $this->insertTags->replaceInline($url) : '#';

        $cssID = StringUtil::deserialize($model->cssID, true);
        $cls = 'draggo-btn';
        $extra = preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($cssID[1] ?? '')) ?? '';
        if (trim($extra) !== '') {
            $cls .= ' ' . trim($extra);
        }
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($cssID[0] ?? '')) ?? '';
        $idAttr = $id !== '' ? ' id="' . $id . '"' : '';
        $blank = ($model->draggo_btn_blank ?? '') === '1' ? ' target="_blank" rel="noopener noreferrer"' : '';

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Optional icon (before/after the label).
        $iconSvg = \Vtinnovations\Draggo\Icon\IconLibrary::render((string) ($model->draggo_btn_icon ?? ''));
        $label = $e($text);
        if ($iconSvg !== '') {
            $pos = ($model->draggo_btn_icon_pos ?? 'before') === 'after' ? 'after' : 'before';
            $cls .= ' draggo-btn--ico-' . $pos;
            $ico = '<span class="draggo-btn-ico">' . $iconSvg . '</span>';
            $label = $pos === 'after' ? ($label . $ico) : ($ico . $label);
        }

        return new Response('<a class="' . $cls . '"' . $idAttr . ' href="' . $e($url) . '"' . $blank . '>' . $label . '</a>');
    }
}
