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
use Vtinnovations\Draggo\Editor\ContentSynchronizer;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Unit\UnitContentRenderer;

/**
 * Auto-places global Draggo header/footer units on every frontend page —
 * published "header" units right after <body>, "footer" units before </body> —
 * so the user does not have to wire a frontend module into the page layout.
 * (If a unit is also placed manually via a module, disable it here by
 * unpublishing one of them.)
 */
#[AsHook('outputFrontendTemplate')]
final class FrontendInjectListener
{
    public function __construct(
        private readonly ContentSynchronizer $synchronizer,
        private readonly UnitContentRenderer $renderer,
        private readonly EditionResolver $edition,
    ) {
    }

    public function __invoke(string $buffer, string $template): string
    {
        // Global units are a paid feature: not on the free tier, but an expired
        // licence keeps injecting them so the live site keeps its header/footer.
        if (!$this->edition->profile()->allows(EditionProfile::CAP_LIBRARY)) {
            return $buffer;
        }

        // Only touch full HTML page documents.
        if (stripos($buffer, '</body>') === false || stripos($buffer, '<body') === false) {
            return $buffer;
        }

        $header = $this->renderUnits('header');
        if ($header !== '') {
            // Sticky must sit on the <header> wrapper (relative to <body>, which
            // is tall) — not on the short inner unit, where it would never stick.
            $sticky = $this->synchronizer->hasStickyUnit('header')
                ? ' style="position:fixed;top:0;left:0;right:0;z-index:99999;"'
                : '';
            $buffer = preg_replace('/(<body\b[^>]*>)/i', '$1' . $this->escapeReplacement('<header class="draggo-auto-header"' . $sticky . '>' . $header . '</header>'), $buffer, 1) ?? $buffer;
        }

        $footer = $this->renderUnits('footer');
        if ($footer !== '') {
            $buffer = str_replace('</body>', '<footer class="draggo-auto-footer">' . $footer . '</footer></body>', $buffer);
        }

        return $buffer;
    }

    private function renderUnits(string $type): string
    {
        $html = '';
        foreach ($this->synchronizer->publishedUnitIds($type) as $id) {
            $html .= $this->renderer->render($id);
        }

        return $html;
    }

    /** preg_replace treats $ / \ in the replacement specially — neutralise. */
    private function escapeReplacement(string $s): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $s);
    }
}
