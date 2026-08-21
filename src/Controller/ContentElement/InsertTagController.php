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
 * Renders an arbitrary Contao insert tag at this position (Roadmap Tier 1 —
 * the Contao-native replacement for WordPress shortcodes). The user enters a
 * tag like `date` / `link_url::5` / `news_url::3`; we wrap + resolve it.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_inserttag')]
class InsertTagController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $tag = trim((string) ($model->draggo_it ?? ''));
        if ($tag === '') {
            return new Response('');
        }
        if (!str_contains($tag, '{{')) {
            $tag = '{{' . trim($tag, '{}') . '}}';
        }

        // Wrap in a scoped span so the Style tab actually applies — without the
        // .draggo-el-{id} class on a real element, every style option is dead.
        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<span class="' . trim('draggo-it ' . $cls) . '"' . $idAttr . '>' . $this->insertTags->replaceInline($tag) . '</span>');
    }
}
