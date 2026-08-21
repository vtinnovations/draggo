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
 * Draggo backend permissions per user group. Registered in TL_PERMISSIONS
 * (config.php) so Contao merges them into non-admin users; RequestGuard checks
 * them via $user->hasAccess($value, 'draggo_perms'). Admins always pass.
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('draggo_legend', 'amg_legend', PaletteManipulator::POSITION_BEFORE, true)
    ->addField('draggo_perms', 'draggo_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_user_group');

$GLOBALS['TL_DCA']['tl_user_group']['fields']['draggo_perms'] = [
    'inputType' => 'checkbox',
    'multiple'  => true,
    'options'   => ['ai_use', 'ai_delete'],
    'reference' => &$GLOBALS['TL_LANG']['tl_user_group']['draggo_perms_ref'],
    'eval'      => ['multiple' => true],
    'sql'       => 'blob NULL',
];
