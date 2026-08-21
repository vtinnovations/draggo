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

/*
 * Adds the "Edit with Draggo" row operation to the page tree. The button
 * itself is rendered by PageOperationListener (#[AsCallback]); here we only
 * register the operation so it appears in the operations toolbar.
 */

$GLOBALS['TL_DCA']['tl_page']['list']['operations']['draggo'] = [
    'icon'  => 'bundles/draggo/icon.svg',
    'label' => &$GLOBALS['TL_LANG']['tl_page']['draggo'],
];
