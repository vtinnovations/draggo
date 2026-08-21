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
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;

/**
 * Loads the grid/Bootstrap assets into the frontend per config:
 *   - mode "full": link external Bootstrap 5 CSS+JS (configured URLs). Added
 *     as raw <link>/<script> via TL_HEAD/TL_BODY so the Contao Combiner never
 *     touches them (avoids the Combiner-rejects-query-string trap).
 *   - mode "grid": load the bundle's self-contained grid CSS via TL_CSS (local,
 *     no query string → safely combinable).
 *   - mode "off": nothing (theme already provides Bootstrap).
 */
#[AsHook('generatePage')]
final class BootstrapAssetsListener
{
    public function __construct(
        private readonly string $mode,
        private readonly string $cssUrl,
        private readonly string $jsUrl,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        switch ($this->mode) {
            case 'full':
                if ($this->cssUrl !== '') {
                    $GLOBALS['TL_HEAD'][] = sprintf(
                        '<link rel="stylesheet" href="%s">',
                        htmlspecialchars($this->cssUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    );
                }
                if ($this->jsUrl !== '') {
                    $GLOBALS['TL_BODY'][] = sprintf(
                        '<script src="%s"></script>',
                        htmlspecialchars($this->jsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    );
                }
                break;

            case 'grid':
                // The local grid CSS is injected by ElementStyleListener via the
                // outputFrontendTemplate buffer (template-independent) instead of
                // $GLOBALS['TL_CSS'] here — TL_CSS only renders when the page
                // template emits the stylesheet placeholder, which custom/theme
                // templates often drop, leaving rows un-gridded. Nothing to do.
                break;

            case 'off':
            default:
                // Theme provides Bootstrap; emit nothing.
                break;
        }
    }
}
