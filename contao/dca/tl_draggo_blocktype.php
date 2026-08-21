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
 * AI-generated content-element DEFINITIONS (BlockSpec JSON). One row per custom
 * element type the user created via the Draggo KI generator. Placed instances
 * are normal tl_content rows (type=draggo_block, draggo_blocktype=key). This
 * table is the source of truth for rendering + the editor palette.
 *
 * Created/updated through the generator API; the backend list here is for
 * overview, (un)publishing and deletion. The `spec` JSON is not hand-edited.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_blocktype'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql'           => ['keys' => ['id' => 'primary', 'type' => 'unique']],
    ],
    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['category', 'label'],
            'flag'        => 1,
            'panelLayout' => 'search,limit',
        ],
        'label' => [
            'fields' => ['label', 'category'],
            'format' => '%s [%s]',
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'toggle' => [
                'href'  => 'act=toggle&amp;field=published',
                'icon'  => 'visible.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Element-Typ wirklich löschen? Platzierte Instanzen rendern dann nicht mehr.\'))return false;"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},label,category;{publish_legend},published',
    ],
    'fields' => [
        'id'     => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'type' => [
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'label' => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'eval'      => ['mandatory' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'category' => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        // The validated BlockSpec (fields + sandboxed template + scoped CSS).
        'spec' => [
            'sql' => 'mediumblob NULL',
        ],
        'published' => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'toggle'    => true,
            'eval'      => ['tl_class' => 'w50', 'isBoolean' => true],
            'sql'       => "char(1) NOT NULL default '1'",
        ],
    ],
];
