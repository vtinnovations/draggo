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
use Vtinnovations\Draggo\Css\CssGenerator;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Token\DefaultsStore;
use Vtinnovations\Draggo\Token\TokenStore;

/**
 * Links the page's cached Draggo stylesheet (Phase B) and wires the FE runtime
 * (scroll animation + lightbox + interactive widgets) on every frontend page.
 *
 * Uses the `outputFrontendTemplate` hook (direct buffer injection: stylesheets
 * before </head>, scripts before </body>) instead of $GLOBALS['TL_HEAD']/
 * ['TL_BODY']: the latter only render if the page template emits the
 * [[TL_HEAD]] / [[TL_BODY]] placeholders (`<?= $this->head ?>` /
 * `<?= $this->mootools ?>`), which custom/theme templates often drop — then
 * draggo-frontend.js / draggo-scroll.js never load and EVERY interactive +
 * scroll element silently stops working in the frontend. Buffer injection is
 * template-independent (mirrors FrontendInjectListener / FrontendToolbarListener).
 */
#[AsHook('outputFrontendTemplate')]
final class ElementStyleListener
{
    /** Bump when the FE runtime/assets change so caches refresh. */
    private const RUNTIME_VERSION = '20260627fe3';

    public function __construct(
        private readonly CssGenerator $cssGenerator,
        private readonly EditionResolver $edition,
        private readonly string $projectDir = '',
        private readonly string $bootstrapMode = 'grid',
        private readonly ?TokenStore $tokens = null,
        private readonly ?DefaultsStore $defaults = null,
    ) {
    }

    public function __invoke(string $buffer, string $template): string
    {
        // Never licensed: inject nothing — the elements render empty anyway, so
        // their CSS/JS would be dead weight. An expired licence still gets the
        // assets, otherwise the (still rendering) live site would lose its
        // styling and look broken.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_EDITOR)) {
            return $buffer;
        }

        // Only full HTML page documents.
        if (stripos($buffer, '</body>') === false) {
            return $buffer;
        }

        $page = $GLOBALS['objPage'] ?? null;
        if ($page === null || empty($page->id)) {
            return $buffer;
        }

        $v = self::RUNTIME_VERSION;

        // ── <head>: stylesheets ──────────────────────────────────────────
        $head = '';
        $faCss = \Vtinnovations\Draggo\Icon\FaIcons::CSS_PATH;
        foreach (['/public/', '/web/'] as $dir) {
            if (is_file($this->projectDir . $dir . $faCss)) {
                $head .= '<link rel="stylesheet" href="/' . $faCss . '?v=' . $v . '">';
                break;
            }
        }
        $head .= '<link rel="stylesheet" href="/bundles/draggo/draggo-frontend.css?v=' . $v . '">'
            . '<link rel="stylesheet" href="/bundles/draggo/draggo-scroll.css?v=' . $v . '">';

        // Self-contained Bootstrap grid (mode "grid"). BootstrapAssetsListener
        // adds this via $GLOBALS['TL_CSS'], but that only renders if the page
        // template emits the stylesheet placeholder — custom/theme templates that
        // drop it leave every .row/.col without flex, so multi-column rows stack
        // (collapse) in the frontend. Buffer injection here is template-independent
        // (mirrors draggo-frontend.css above). Skipped for "full"/"off", where
        // Bootstrap comes from the CDN / theme.
        if ($this->bootstrapMode === 'grid') {
            $head .= '<link rel="stylesheet" href="/bundles/draggo/draggo-grid.css?v=' . $v . '">';
        }

        // Global design tokens (:root{--bld-…}) + global default styles (h1 size,
        // base font-family, …). TokenCssListener used to add these via
        // $GLOBALS['TL_HEAD'], but that only renders when the template emits the
        // head placeholder — custom/theme templates that drop it silently disable
        // ALL global styles (font, headings, colours never apply). Inject inline
        // here so they always take effect. Tokens first so var(--bld-…) resolve,
        // then the global defaults that consume them.
        $themeScript = '';
        $globalsAllowed = $this->edition->profile()->allows(EditionProfile::CAP_GLOBALS);
        if ($this->tokens !== null && $globalsAllowed) {
            $tokenCss = $this->tokens->cssVars();
            $globalCss = $tokenCss . ($this->defaults !== null ? $this->defaults->css() : '');
            if ($globalCss !== '') {
                $head .= '<style>' . $globalCss . '</style>';
            }
            // Apply the visitor's saved manual light/dark choice before paint.
            if (str_contains($tokenCss, 'data-draggo-theme')) {
                $themeScript = "<script>try{var t=localStorage.getItem('draggoTheme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-draggo-theme',t);}catch(e){}</script>";
                $head .= $themeScript;
            }
        }

        $result = $this->cssGenerator->cacheForPage((int) $page->id);
        if ($result['url'] !== null) {
            $head .= '<link rel="stylesheet" href="' . $result['url'] . '">';
        }

        // ── before </body>: scripts ──────────────────────────────────────
        $body = '<script src="/bundles/draggo/draggo-frontend.js?v=' . $v . '" defer></script>'
            . '<script src="/bundles/draggo/draggo-scroll.js?v=' . $v . '" defer></script>';

        $fx = $result['fx'];
        $sticky = $fx['sticky'] ?? [];
        if ($fx['anim'] !== [] || $fx['lightbox'] !== [] || $sticky !== []) {
            $config = json_encode(
                ['anim' => $fx['anim'], 'lightbox' => $fx['lightbox'], 'sticky' => $sticky],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
            $body .= '<script>window.DRAGGO_FX=' . $config . ';</script>'
                . '<script src="/bundles/draggo/draggo-runtime.js?v=' . $v . '" defer></script>';
        }

        // str_replace (not preg) → no $ / \ interpretation of the markup.
        if (stripos($buffer, '</head>') !== false) {
            $buffer = preg_replace('#</head>#i', $this->esc($head) . '</head>', $buffer, 1) ?? $buffer;
        }

        return str_replace('</body>', $body . '</body>', $buffer);
    }

    /** Neutralise $ / \ for preg_replace replacement. */
    private function esc(string $s): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $s);
    }
}
