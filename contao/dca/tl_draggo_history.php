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
 * Restore-point snapshots per container (article/unit). Written/read only by
 * HistoryStore via the editor — no backend list module. Defined here so
 * contao:migrate creates the table.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_history'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'notEditable'   => true,
        'closed'        => true,
        'sql'           => ['keys' => ['id' => 'primary', 'pid,ptable' => 'index']],
    ],
    'fields' => [
        'id'       => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'   => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'pid'      => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'ptable'   => ['sql' => "varchar(64) NOT NULL default ''"],
        'label'    => ['sql' => "varchar(128) NOT NULL default ''"],
        'snapshot' => ['sql' => 'longblob NULL'],
    ],
];
