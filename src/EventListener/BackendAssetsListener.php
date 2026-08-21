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

/**
 * Loads the backend grid visualiser assets when the tl_content DCA is loaded
 * (i.e. when editing an article's content in the backend). The JS regroups the
 * Draggo grid wrapper rows into side-by-side column boxes; the CSS styles them.
 *
 * String keys on TL_CSS/TL_JAVASCRIPT prevent duplicate injection across the
 * multiple loadDataContainer calls in one request.
 */
#[AsHook('loadDataContainer')]
final class BackendAssetsListener
{
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_content') {
            return;
        }

        $GLOBALS['TL_CSS']['draggo_be'] = 'bundles/draggo/draggo-backend.css|static';
        $GLOBALS['TL_JAVASCRIPT']['draggo_be'] = 'bundles/draggo/draggo-backend.js|static';
    }
}
