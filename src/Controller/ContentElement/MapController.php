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

/**
 * Google Maps (Tier 5): embeds a map for an address/place via the key-less
 * Google "output=embed" URL. GDPR: by default the map loads only after the user
 * clicks the consent placeholder (no Google request before consent).
 */
#[AsContentElement(category: 'draggo', type: 'draggo_map')]
class MapController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $query = trim((string) ($model->draggo_map_query ?? ''));
        if ($query === '') {
            return new Response('');
        }
        $zoom = (int) ($model->draggo_map_zoom ?? 14);
        $zoom = ($zoom >= 1 && $zoom <= 21) ? $zoom : 14;
        $h = (int) ($model->draggo_map_height ?? 400);
        $h = ($h >= 120 && $h <= 1200) ? $h : 400;
        // Consent placeholder is OPT-IN: only shown when the editor explicitly
        // enabled "load only after click" ('1'). Default (unsaved/'') = the map
        // embeds directly, so it is fully interactive (zoom/drag) out of the box.
        $consent = ($model->draggo_map_consent ?? '') === '1';

        // Interactive key-less embed: hl localises the UI, t=m forces the map
        // (not the static place card that swallows clicks → Google redirect).
        $page = $GLOBALS['objPage'] ?? null;
        $hl = \is_object($page) ? strtolower(substr((string) ($page->language ?? 'de'), 0, 2)) : 'de';
        $en = $hl !== '' && !str_starts_with($hl, 'de');
        $src = 'https://maps.google.com/maps?q=' . rawurlencode($query) . '&t=m&z=' . $zoom . '&hl=' . rawurlencode($hl) . '&ie=UTF8&iwloc=&output=embed';
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        [$idAttr, $cls] = $this->draggoScope($model);
        $open = '<div class="' . trim('draggo-map ' . $cls) . '"' . $idAttr . ' style="--map-h:' . $h . 'px">';

        if ($consent) {
            $msg = $en
                ? 'To protect your data, the map is only loaded from Google after your consent.'
                : 'Zum Schutz Ihrer Daten wird die Karte erst nach Ihrer Zustimmung von Google geladen.';
            $btn = $en ? 'Load map' : 'Karte laden';

            return new Response(
                $open
                . '<div class="draggo-map-consent" data-src="' . $e($src) . '">'
                . '<p>' . $e($msg) . '</p>'
                . '<button type="button" class="draggo-map-load">' . $e($btn) . '</button>'
                . '</div></div>',
            );
        }

        return new Response(
            $open . '<iframe class="draggo-map-frame" src="' . $e($src) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>',
        );
    }
}
