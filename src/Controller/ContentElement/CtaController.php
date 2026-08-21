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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Call to Action (Tier 2): heading + text + button. Button reuses the .draggo-btn
 * base look; the box itself is styled via the universal Style tab.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_cta')]
class CtaController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $title = trim((string) ($model->draggo_cta_title ?? ''));
        $text = trim((string) ($model->draggo_cta_text ?? ''));
        $btn = trim((string) ($model->draggo_cta_btn ?? ''));
        if ($title === '' && $text === '' && $btn === '') {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $button = '';
        if ($btn !== '') {
            $url = trim((string) ($model->draggo_cta_url ?? ''));
            $url = $url !== '' ? $this->insertTags->replaceInline($url) : '#';
            $blank = ($model->draggo_cta_blank ?? '') === '1' ? ' target="_blank" rel="noopener noreferrer"' : '';
            $button = '<a class="draggo-btn draggo-cta-btn" href="' . $e($url) . '"' . $blank . '>' . $e($btn) . '</a>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response(
            '<div class="' . trim('draggo-cta ' . $cls) . '"' . $idAttr . '>'
            . ($title !== '' ? '<h3 class="draggo-cta-title">' . $e($title) . '</h3>' : '')
            . ($text !== '' ? '<div class="draggo-cta-text">' . $this->richText($text) . '</div>' : '')
            . $button
            . '</div>',
        );
    }
}
