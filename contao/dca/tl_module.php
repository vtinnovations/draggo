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
 * Palette + field for the "draggo_unit" frontend module (renders a global
 * Draggo unit). The select options are populated by UnitOptionsListener.
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['draggo_unit'] =
    '{title_legend},name,headline,type;{config_legend},draggo_unit;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['draggo_unit'] = [
    'inputType' => 'select',
    'exclude'   => true,
    'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'       => "int(10) unsigned NOT NULL default 0",
    // options provided by Vtinnovations\Draggo\EventListener\UnitOptionsListener
];
