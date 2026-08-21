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
 * Price table (Tier 5): a pricing card — title, price + period, feature list,
 * call-to-action button, optional "featured" highlight.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_pricetable')]
class PriceTableController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly InsertTagParser $insertTags)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = trim((string) ($model->draggo_prt_title ?? ''));
        $price = trim((string) ($model->draggo_prt_price ?? ''));
        if ($title === '' && $price === '') {
            return new Response('');
        }
        $period = trim((string) ($model->draggo_prt_period ?? ''));
        $featured = ($model->draggo_prt_featured ?? '') === '1';
        $features = array_values(array_filter(
            array_map('trim', StringUtil::deserialize($model->draggo_prt_features, true)),
            static fn (string $s): bool => $s !== '',
        ));

        $li = '';
        foreach ($features as $f) {
            $li .= '<li class="draggo-prt-feature">' . $e($f) . '</li>';
        }

        $btnText = trim((string) ($model->draggo_prt_btn ?? ''));
        $button = '';
        if ($btnText !== '') {
            $url = trim((string) ($model->draggo_prt_url ?? ''));
            $url = $url !== '' ? $this->insertTags->replaceInline($url) : '#';
            $button = '<a class="draggo-btn draggo-prt-btn" href="' . $e($url) . '">' . $e($btnText) . '</a>';
        }

        [$idAttr, $cls] = $this->draggoScope($model);
        $clsAttr = trim('draggo-pricetable ' . ($featured ? 'draggo-pricetable--featured ' : '') . $cls);

        return new Response(
            '<div class="' . $clsAttr . '"' . $idAttr . '>'
            . '<div class="draggo-prt-head">'
            . ($title !== '' ? '<h3 class="draggo-prt-title">' . $e($title) . '</h3>' : '')
            . ($price !== '' ? '<div class="draggo-prt-price"><span class="draggo-prt-amount">' . $e($price) . '</span>'
                . ($period !== '' ? '<span class="draggo-prt-period">' . $e($period) . '</span>' : '') . '</div>' : '')
            . '</div>'
            . ($li !== '' ? '<ul class="draggo-prt-features">' . $li . '</ul>' : '')
            . $button
            . '</div>',
        );
    }
}
