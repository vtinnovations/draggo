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
 * Global Draggo unit: a reusable header / footer / section. Its content
 * elements live in tl_content with ptable='tl_draggo_unit', pid=unit.id, so
 * the same grid + element editor works inside a unit. A unit is placed onto
 * pages globally via the "draggo_unit" frontend module in the page layout.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_unit'] = [
    'config' => [
        'dataContainer'    => DC_Table::class,
        'sql'              => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['type', 'title'],
            'flag'        => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title', 'type'],
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
            'draggo' => [
                'icon' => 'bundles/draggo/icon.svg',
                // Button rendered by UnitOperationListener (#[AsCallback]).
            ],
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Wirklich löschen?\'))return false;"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,type;{options_legend},sticky;{publish_legend},published',
    ],
    'fields' => [
        'id'     => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        // Container layout for the unit itself (set in the Draggo editor).
        'draggo_layout' => ['sql' => 'text NULL'],
        'title' => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'type' => [
            'inputType' => 'select',
            'exclude'   => true,
            'filter'    => true,
            'options'   => ['header', 'footer', 'section'],
            'reference' => &$GLOBALS['TL_LANG']['tl_draggo_unit']['types'],
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default 'section'",
        ],
        'sticky' => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'published' => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
    ],
];
