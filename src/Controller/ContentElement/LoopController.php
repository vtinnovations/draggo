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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Loop\LoopQuery;

/**
 * Loop grid (Tier 3 — data-driven): renders child pages of a chosen start page
 * as cards via a Contao query. The first, always-available source; further
 * sources (news/events/own models) plug into LoopQuery.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_loop')]
class LoopController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(
        private readonly LoopQuery $query,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $parent = (int) ($model->draggo_loop_parent ?? 0);
        $limit = (int) ($model->draggo_loop_limit ?? 0);
        $cols = (int) ($model->draggo_loop_cols ?? 3);
        $cols = ($cols >= 1 && $cols <= 4) ? $cols : 3;
        $showTeaser = ($model->draggo_loop_teaser ?? '') === '1';
        $more = ($model->draggo_loop_more ?? '') === '1';

        $source = (string) ($model->draggo_loop_source ?? 'pages');
        $order = (string) ($model->draggo_loop_order ?? 'default');
        $featured = ($model->draggo_loop_featured ?? '') === '1';

        $items = match ($source) {
            'news'   => $this->query->news((int) ($model->draggo_loop_archive ?? 0), $limit, $featured, $order, (int) ($model->draggo_loop_category ?? 0)),
            'events' => $this->query->events((int) ($model->draggo_loop_calendar ?? 0), $limit, $order),
            default  => $this->query->childPages($parent, $limit, $order),
        };
        if ($items === []) {
            return new Response('');
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $cards = '';
        foreach ($items as $it) {
            $cards .= '<a class="draggo-loop-card" href="' . $e($it['href']) . '">'
                . ($it['image'] !== '' ? '<span class="draggo-loop-img"><img src="/' . $e($it['image']) . '" alt=""></span>' : '')
                . '<h3 class="draggo-loop-title">' . $e($it['title']) . '</h3>'
                . ($showTeaser && $it['teaser'] !== '' ? '<p class="draggo-loop-teaser">' . $e($it['teaser']) . '</p>' : '')
                . ($more ? '<span class="draggo-loop-more">Mehr →</span>' : '')
                . '</a>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<div class="' . trim('draggo-loop ' . $cls) . '"' . $idAttr . ' style="--cols:' . $cols . '">' . $cards . '</div>');
    }
}
