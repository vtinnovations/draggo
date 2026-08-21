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
 * Design tokens / variables (Roadmap Phase E). Named colours, spacing, fonts,
 * radii, shadows referenced by elements via CSS custom properties
 * (--bld-{type}-{token}). Managed in the Draggo editor "Globals" tab; this DCA
 * exists mainly so contao:migrate creates the table.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_token'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql'           => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => ['mode' => 1, 'fields' => ['type', 'sorting'], 'panelLayout' => 'filter;search,limit'],
        'label'   => ['fields' => ['label', 'type', 'value'], 'format' => '%s [%s] %s'],
        'operations' => [
            'edit'   => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
        ],
    ],
    'palettes' => [
        'default' => '{token_legend},type,token,label,value,darkValue',
    ],
    'fields' => [
        'id'      => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp'  => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'sorting' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'type' => [
            'inputType' => 'select',
            'options'   => ['color', 'space', 'font', 'radius', 'shadow'],
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default 'color'",
        ],
        'token' => [
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'alnum', 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'label' => [
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'value' => [
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 512, 'tl_class' => 'w50'],
            'sql'       => "varchar(512) NOT NULL default ''",
        ],
        // Optional dark-mode value (colour tokens). Empty = same as `value`.
        'darkValue' => [
            'inputType' => 'text',
            'eval'      => ['maxlength' => 512, 'tl_class' => 'w50'],
            'sql'       => "varchar(512) NOT NULL default ''",
        ],
    ],
];
