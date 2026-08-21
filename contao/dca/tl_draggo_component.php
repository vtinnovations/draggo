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
 * Reusable component library (Tier 4). Rows hold a portable element field set
 * (JSON) that the editor inserts as a detached clone. Managed via the Draggo
 * editor API, not a backend module — only the schema needs defining here so
 * contao:migrate creates the table.
 */
$GLOBALS['TL_DCA']['tl_draggo_component'] = [
    'config' => [
        'dataContainer'    => 'Table',
        'enableVersioning' => false,
        'sql'              => ['keys' => ['id' => 'primary']],
    ],
    'fields' => [
        'id'       => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'   => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'title'    => ['sql' => "varchar(255) NOT NULL default ''"],
        'category' => ['sql' => "varchar(64) NOT NULL default ''"],
        'eltype'   => ['sql' => "varchar(64) NOT NULL default ''"],
        'data'     => ['sql' => 'mediumtext NULL'],
    ],
];
