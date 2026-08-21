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
 * Draggo's licence surface lives in Contao → Settings and nowhere else.
 *
 * This is the ONE administrator-facing licence control for the bundle. The
 * former panel in the Draggo settings module has been removed rather than
 * hidden, so there is no second screen, no second route and no second store
 * that could disagree with this one.
 *
 * The field renders no widget of its own: EditionCallbacks emits the whole
 * section server-side, including the buttons, which post to their own
 * registered backend routes. Nothing is persisted into localconfig — the
 * authoritative record lives in the private state directory.
 *
 * The field name is package-unique so other V&T products can add their own
 * field to this same screen without colliding, but the legend key is
 * deliberately shared ("vtone_licence_legend"): every V&T product's field
 * lands in the same fieldset, prepended above Contao's own legends, with the
 * product name shown as the field's own heading instead of a separate legend.
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Vtinnovations\Draggo\Dca\EditionCallbacks;

$GLOBALS['TL_DCA']['tl_settings']['fields']['draggo_licence'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['draggo_licence'],
    'input_field_callback' => [EditionCallbacks::class, 'render'],
    'eval' => ['doNotSaveEmpty' => true, 'tl_class' => 'clr'],
];

PaletteManipulator::create()
    ->addLegend('vtone_licence_legend', null, PaletteManipulator::POSITION_PREPEND)
    ->addField('draggo_licence', 'vtone_licence_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings')
;
