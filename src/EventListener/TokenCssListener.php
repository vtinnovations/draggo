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
use Vtinnovations\Draggo\Token\DefaultsStore;
use Vtinnovations\Draggo\Token\TokenStore;

/**
 * Emits the design-token CSS custom properties (`:root{--bld-…}`) into every
 * frontend page head, so elements referencing var(--bld-…) resolve (Phase E).
 */
#[AsHook('generatePage')]
final class TokenCssListener
{
    public function __construct(
        private readonly TokenStore $tokens,
        private readonly DefaultsStore $defaults,
    ) {
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        // Intentionally a no-op. The global token CSS + default styles are now
        // injected by ElementStyleListener via the outputFrontendTemplate buffer
        // (template-independent). Emitting them here through $GLOBALS['TL_HEAD']
        // only worked when the page template rendered the head placeholder —
        // custom/theme templates that drop it silently disabled ALL global styles
        // (font-family, heading sizes, colour tokens never applied). Kept as a
        // registered hook for BC; the work moved, the duplicate was removed.
    }
}
