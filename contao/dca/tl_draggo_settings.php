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
 * Single-row Draggo settings, edited via the backend module „Einstellungen".
 * SettingsStore reads this at runtime, so the AI key etc. can be set in the BE
 * with no file editing and no cache clear. SettingsSingletonListener guarantees
 * exactly one row and jumps straight into its edit form.
 */

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_draggo_settings'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed'        => true,
        'notDeletable'  => true,
        'notCopyable'   => true,
        'sql'           => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting'    => ['mode' => 1, 'fields' => ['id']],
        'label'      => ['fields' => ['id'], 'format' => 'Draggo-Einstellungen'],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
        ],
    ],
    'palettes' => [
        // No licence fields here: the sole licence surface is the
        // "Draggo Licence management" section in Contao → Settings.
        'default' => '{ai_legend},ai_api_key,ai_model,ai_max_rounds,ai_no_self_review;{image_legend},img_provider,img_unsplash_key,img_pexels_key,img_openai_key,img_openai_model;{telemetry_legend},ai_telemetry,ai_telemetry_url',
    ],
    'fields' => [
        'id'     => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'ai_api_key' => [
            'inputType' => 'text',
            'exclude'   => true,
            // hideInput masks the value in the form without hashing it.
            'eval'      => ['hideInput' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'doNotShow' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'ai_model' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50', 'placeholder' => 'claude-opus-4-8'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'ai_max_rounds' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['rgxp' => 'natural', 'tl_class' => 'w50', 'placeholder' => '8'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],
        'ai_no_self_review' => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'img_provider' => [
            'inputType' => 'select',
            'exclude'   => true,
            'options'   => ['', 'unsplash', 'pexels', 'openai'],
            'reference' => &$GLOBALS['TL_LANG']['tl_draggo_settings']['img_provider_opts'],
            'eval'      => ['includeBlankOption' => false, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'img_unsplash_key' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['hideInput' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'doNotShow' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'img_pexels_key' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['hideInput' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'doNotShow' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'img_openai_key' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['hideInput' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'doNotShow' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'img_openai_model' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50', 'placeholder' => 'gpt-image-1'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'ai_telemetry' => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'ai_telemetry_url' => [
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['rgxp' => 'url', 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];
