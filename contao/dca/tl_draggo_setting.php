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
 * Key/value store for site-wide Draggo settings (currently: global default
 * styles — body typography, h1–h6, links). Managed in the editor "Globals"
 * tab; this DCA exists so contao:migrate creates the table.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_setting'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql'           => ['keys' => ['id' => 'primary', 'skey' => 'unique']],
    ],
    'fields' => [
        'id'     => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'skey'   => ['sql' => "varchar(64) NOT NULL default ''"],
        'svalue' => ['sql' => 'text NULL'],
    ],
];
