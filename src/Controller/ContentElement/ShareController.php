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
use Contao\Environment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Icon\IconLibrary;

/**
 * Share buttons (Tier 5): share the current page on Facebook / LinkedIn / e-mail
 * or copy the link. URLs built server-side from the current request; copy is
 * handled by draggo-frontend.js.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_share')]
class ShareController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $this->framework->initialize();
        /** @var Environment $env */
        $env = $this->framework->getAdapter(Environment::class);
        $url = (string) $env->get('uri');
        if ($url === '') {
            $url = $request->getUri();
        }
        $enc = rawurlencode($url);
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $links = '';
        $link = static function (string $href, string $iconKey, string $label) use ($e): string {
            return '<a class="draggo-share-link" href="' . $e($href) . '" target="_blank" rel="noopener noreferrer" aria-label="' . $e($label) . '">' . IconLibrary::render($iconKey) . '</a>';
        };

        // The four network toggles are default-ON checkboxes. Their values live in
        // draggo_props, which intentionally DROPS empty/falsy values to stay lean —
        // so "unchecked" cannot persist a stored '' and a naive `?? '1'` default
        // would make every network un-hideable (bug: the user's selection is
        // ignored). Resolve via intent: once ANY toggle is explicitly enabled the
        // element is "configured", and only the enabled networks render. With none
        // enabled (brand-new, untouched element) all show as a sensible default.
        $keys = ['draggo_sh_facebook', 'draggo_sh_linkedin', 'draggo_sh_email', 'draggo_sh_copy'];
        $on = static fn (string $k): bool => (string) ($model->{$k} ?? '') === '1';
        $configured = false;
        foreach ($keys as $k) {
            if ($on($k)) {
                $configured = true;
                break;
            }
        }
        $show = static fn (string $k): bool => !$configured || $on($k);

        if ($show('draggo_sh_facebook')) {
            $links .= $link('https://www.facebook.com/sharer/sharer.php?u=' . $enc, 'facebook', 'Auf Facebook teilen');
        }
        if ($show('draggo_sh_linkedin')) {
            $links .= $link('https://www.linkedin.com/sharing/share-offsite/?url=' . $enc, 'linkedin', 'Auf LinkedIn teilen');
        }
        if ($show('draggo_sh_email')) {
            $links .= $link('mailto:?body=' . $enc, 'mail', 'Per E-Mail teilen');
        }
        if ($show('draggo_sh_copy')) {
            $links .= '<button type="button" class="draggo-share-link draggo-share-copy" data-url="' . $e($url) . '" aria-label="Link kopieren">' . IconLibrary::render('external-link') . '</button>';
        }
        if ($links === '') {
            return new Response('');
        }

        [$idAttr, $cls] = $this->draggoScope($model);

        return new Response('<div class="' . trim('draggo-share ' . $cls) . '"' . $idAttr . '>' . $links . '</div>');
    }
}
