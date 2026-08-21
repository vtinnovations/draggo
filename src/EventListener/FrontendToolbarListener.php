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

namespace Vtinnovations\Draggo\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Injects a floating Draggo launch button into the FRONTEND of every page, but
 * only when a backend user is logged in. Clicking it opens the page-scoped
 * Draggo editor for the current tl_page.
 *
 * Uses the `outputFrontendTemplate` hook (direct buffer injection before
 * </body>) instead of the `generatePage` + $GLOBALS['TL_BODY'] path: the latter
 * only renders if the page template emits the [[TL_BODY]] placeholder
 * (`<?= $this->mootools ?>`), which custom/theme templates often drop — so the
 * button silently never appeared. Buffer injection is template-independent and
 * mirrors the proven FrontendInjectListener. The button is re-parented to <html>
 * in JS so a theme ancestor with transform/filter cannot trap the fixed
 * position mid-page (see contao-bundle-patterns).
 */
#[AsHook('outputFrontendTemplate')]
final class FrontendToolbarListener
{
    /** Bump when the launch icon / toolbar CSS changes so caches refresh. */
    private const ICON_VERSION = '20260619a';

    public function __construct(
        private readonly TokenChecker $tokenChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EditionResolver $edition,
    ) {
    }

    public function __invoke(string $buffer, string $template): string
    {
        // Unlicensed: no entry point into the editor anywhere in the frontend.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_EDITOR)) {
            return $buffer;
        }

        // Only for authenticated backend users, on full HTML page documents.
        if (!$this->tokenChecker->hasBackendUser() || stripos($buffer, '</body>') === false) {
            return $buffer;
        }

        $page = $GLOBALS['objPage'] ?? null;
        if ($page === null || empty($page->id)) {
            return $buffer;
        }

        try {
            $editUrl = $this->urlGenerator->generate('draggo_editor', ['page' => (int) $page->id]);
        } catch (\Throwable) {
            return $buffer;
        }
        $editUrl = htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $v = self::ICON_VERSION;
        $html = '<link rel="stylesheet" href="/bundles/draggo/draggo-toolbar.css?v=' . $v . '">'
            . '<a id="draggo-launch" href="' . $editUrl . '" title="Mit Draggo bearbeiten" aria-label="Mit Draggo bearbeiten">'
            . '<img class="draggo-launch-img" src="/bundles/draggo/draggo-launch.png?v=' . $v . '" alt="" width="56" height="56"></a>'
            . '<script>(function(){var b=document.getElementById("draggo-launch");if(b&&b.parentNode!==document.documentElement){document.documentElement.appendChild(b);}})();</script>';

        // str_replace (not preg) → the markup's $ / \ are not interpreted.
        return str_replace('</body>', $html . '</body>', $buffer);
    }
}
