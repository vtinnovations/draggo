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
 * Minimal-invasive extension of tl_content. We ADD columns; we never rewrite
 * core element definitions. DB columns derive from the `sql` keys via
 * `contao:migrate` — no separate migration required.
 */

use Vtinnovations\Draggo\Dca\PropsCallbacks;
use Vtinnovations\Draggo\Grid\GridPresets;

/*
 * Hide the Draggo grid WRAPPER types (row-start / column / row-stop) from the
 * backend "new element" type dropdown. They emit unbalanced markup unless placed
 * as a complete set (start + column(s) + stop), so hand-placing a lone one
 * breaks the page. Grids are built ATOMICALLY in the Draggo visual editor
 * (structure tool) — never one wrapper at a time. Existing rows keep their type;
 * this only removes them from the picker.
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['type']['options_callback'] = static function (): array {
    $wrappers = ['draggo_row_start', 'draggo_col', 'draggo_row_stop', 'draggo_block'];
    $opts = [];
    foreach (($GLOBALS['TL_CTE'] ?? []) as $group => $els) {
        foreach (array_keys((array) $els) as $type) {
            if (\in_array($type, $wrappers, true)) {
                continue;
            }
            $opts[(string) $group][] = (string) $type;
        }
    }

    return $opts;
};

// ── Columns ─────────────────────────────────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_managed'] = [
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_layout'] = [
    'sql' => 'blob NULL',
];

// ── Consolidated element content (de-bloat) ─────────────────────────
// ALL Draggo element content fields are stored as one JSON object here instead
// of 145 individual tl_content columns (that bloated the shared core table,
// risked the InnoDB row-size limit and was not update-safe). New element types
// add JSON keys, never columns. The individual draggo_* content fields below
// keep their DCA definition for the standard backend form (palettes), but are
// virtual (no own SQL column) and read/write this JSON via PropsCallbacks.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_props'] = [
    'sql' => 'mediumblob NULL',
];

// ── AI block-type instance (Phase 3) ────────────────────────────────
// Instances of AI-generated elements: type=draggo_block, the chosen block-type
// key in draggo_blocktype, and the field VALUES as JSON in draggo_data. The
// definition itself lives in tl_draggo_blocktype. Edited via the Draggo editor.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_blocktype'] = [
    'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_data'] = [
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_block'] = '{type_legend},type;{draggo_legend},draggo_blocktype';

// ── Grid fields (on the draggo_row_start element) ──────────────────
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_grid_preset'] = [
    'inputType' => 'select',
    'options'   => GridPresets::choices(),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['draggo_grid_presets'],
    'eval'      => ['tl_class' => 'w50', 'mandatory' => true, 'submitOnChange' => true],
    'sql'       => "varchar(32) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_container'] = [
    'inputType' => 'select',
    'options'   => ['container', 'container-fluid', 'none'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['draggo_containers'],
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default 'container'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_grid_custom'] = [
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'maxlength' => 255, 'placeholder' => 'z. B. 6,6 oder 4,4,4'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

// Responsive: per-breakpoint structure (empty = inherit desktop / stack).
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_grid_tablet'] = [
    'inputType' => 'select',
    'options'   => GridPresets::choices(),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['draggo_grid_presets'],
    'eval'      => ['tl_class' => 'w50', 'includeBlankOption' => true, 'chosen' => true],
    'sql'       => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_grid_mobile'] = [
    'inputType' => 'select',
    'options'   => GridPresets::choices(),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['draggo_grid_presets'],
    'eval'      => ['tl_class' => 'w50', 'includeBlankOption' => true, 'chosen' => true],
    'sql'       => "varchar(32) NOT NULL default ''",
];

// ── Palettes ────────────────────────────────────────────────────────
// "Eigene Spalten" only appears when preset = custom (subpalette below).
$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'draggo_grid_preset';

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['draggo_grid_preset_custom'] = 'draggo_grid_custom';

$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_row_start'] =
    '{type_legend},type;'
    . '{draggo_grid_legend},draggo_grid_preset,draggo_container;'
    . '{draggo_responsive_legend:hide},draggo_grid_tablet,draggo_grid_mobile;'
    . '{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_col'] =
    '{type_legend},type;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_row_stop'] =
    '{type_legend},type';

// ── Draggo navigation element (header/footer menus, page-tree driven) ──
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_nav'] =
    '{type_legend},type;'
    . '{draggo_nav_legend},draggo_nav_root,draggo_nav_levels,draggo_nav_preset;'
    . '{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_nav_root'] = [
    'inputType' => 'pageTree',
    'eval'      => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql'       => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_nav_levels'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default 1",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_nav_preset'] = [
    'inputType' => 'select',
    'options'   => ['horizontal', 'vertical', 'hamburger'],
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default 'horizontal'",
];

// ── Insert-tag element ──────────────────────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_inserttag'] =
    '{type_legend},type;{draggo_it_legend},draggo_it;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_it'] = [
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

// ── Atomic widgets: button / spacer / divider ───────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_button'] =
    '{type_legend},type;{draggo_btn_legend},draggo_btn_text,draggo_btn_url,draggo_btn_blank,draggo_btn_icon,draggo_btn_icon_pos;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_spacer'] =
    '{type_legend},type;{draggo_spacer_legend},draggo_space;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_divider'] =
    '{type_legend},type;{draggo_div_legend},draggo_div_style,draggo_div_thickness,draggo_div_width,draggo_div_color,draggo_div_align,draggo_div_text;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_icon'] =
    '{type_legend},type;{draggo_icon_legend},draggo_icon,draggo_icon_link;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_icon'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_icon_link'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true],
    'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_btn_icon'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_btn_icon_pos'] = [
    'inputType' => 'select', 'options' => ['before', 'after'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'before'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_align'] = [
    'inputType' => 'select', 'options' => ['left', 'center', 'right'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'center'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_text'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_btn_text'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_btn_url'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true],
    'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_btn_blank'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_space'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 24",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_style'] = [
    'inputType' => 'select', 'options' => ['solid', 'dashed', 'dotted', 'double'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'solid'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_thickness'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 1",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_width'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 100",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_div_color'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default ''",
];

// ── Icon-Box / Image-Box (shared fields) ────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_iconbox'] =
    '{type_legend},type;{draggo_box_legend},draggo_ib_icon,draggo_ib_src,draggo_ib_title,draggo_ib_text,draggo_ib_pos;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_imagebox'] =
    '{type_legend},type;{draggo_box_legend},draggo_ib_src,draggo_ib_title,draggo_ib_text,draggo_ib_pos;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ib_icon'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ib_src'] = [
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'extensions' => '%contao.image.valid_extensions%,svg', 'tl_class' => 'clr'],
    'sql' => 'binary(16) NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ib_title'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ib_text'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'],
    'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ib_pos'] = [
    'inputType' => 'select', 'options' => ['top', 'left', 'right'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'top'",
];

// ── Accordion / Tabs (shared items field) ───────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_accordion'] =
    '{type_legend},type;{draggo_items_legend},draggo_acc_multi,draggo_items;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_tabs'] =
    '{type_legend},type;{draggo_items_legend},draggo_items;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_items'] = [
    'inputType' => 'keyValueWizard',
    'eval' => ['tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_acc_multi'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

// ── Image carousel ──────────────────────────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_carousel'] =
    '{type_legend},type;{draggo_car_legend},draggo_car_src,draggo_car_per,draggo_car_fade,draggo_car_arrows,draggo_car_dots,draggo_car_auto,draggo_car_pause,draggo_car_speed;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_fade'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_pause'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_src'] = [
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'draggo_car_order', 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_order'] = ['sql' => 'blob NULL'];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_per'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 1",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_arrows'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_dots'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_auto'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_car_speed'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 4",
];

// ── Loop grid (data-driven: child pages) ────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_loop'] =
    '{type_legend},type;{draggo_loop_legend},draggo_loop_source,draggo_loop_parent,draggo_loop_archive,draggo_loop_calendar,draggo_loop_category,draggo_loop_featured,draggo_loop_order,draggo_loop_cols,draggo_loop_limit,draggo_loop_teaser,draggo_loop_more;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_source'] = [
    'inputType' => 'select', 'options' => ['pages', 'news', 'events'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(16) NOT NULL default 'pages'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_archive'] = [
    'inputType' => 'select', 'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_calendar'] = [
    'inputType' => 'select', 'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_category'] = [
    'inputType' => 'select', 'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_featured'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_order'] = [
    'inputType' => 'select', 'options' => ['default', 'oldest', 'alpha'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(16) NOT NULL default 'default'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_parent'] = [
    'inputType' => 'pageTree', 'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_cols'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 3",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_limit'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_teaser'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_loop_more'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];

// ── Tier 2: Alert / Blockquote / Counter / Progress ─────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_alert'] =
    '{type_legend},type;{draggo_alert_legend},draggo_alert_type,draggo_alert_title,draggo_alert_text,draggo_alert_dismiss;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_quote'] =
    '{type_legend},type;{draggo_quote_legend},draggo_quote_text,draggo_quote_author;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_counter'] =
    '{type_legend},type;{draggo_counter_legend},draggo_counter_start,draggo_counter_end,draggo_counter_prefix,draggo_counter_suffix,draggo_counter_duration;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_progress'] =
    '{type_legend},type;{draggo_progress_legend},draggo_progress_label,draggo_progress_value,draggo_progress_show;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_alert_type'] = [
    'inputType' => 'select', 'options' => ['info', 'success', 'warning', 'error'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(16) NOT NULL default 'info'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_alert_title'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_alert_text'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'], 'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_alert_dismiss'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_quote_text'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'], 'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_quote_author'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_counter_start'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "int(10) NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_counter_end'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "int(10) NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_counter_prefix'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'], 'sql' => "varchar(32) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_counter_suffix'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'], 'sql' => "varchar(32) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_counter_duration'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "int(10) unsigned NOT NULL default 2000",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_progress_label'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_progress_value'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_progress_show'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];

// ── Tier 2 (Batch 2): Icon-List / CTA / Social / Flip-Box ───────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_iconlist'] =
    '{type_legend},type;{draggo_il_legend},draggo_il_icon,draggo_il_items;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_cta'] =
    '{type_legend},type;{draggo_cta_legend},draggo_cta_title,draggo_cta_text,draggo_cta_btn,draggo_cta_url,draggo_cta_blank;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_social'] =
    '{type_legend},type;{draggo_social_legend},draggo_social_items;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_flipbox'] =
    '{type_legend},type;{draggo_flip_legend},draggo_flip_icon,draggo_flip_ftitle,draggo_flip_ftext,draggo_flip_btitle,draggo_flip_btext,draggo_flip_btn,draggo_flip_url,draggo_flip_height;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_il_icon'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'], 'sql' => "varchar(64) NOT NULL default 'check'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_il_items'] = [
    'inputType' => 'listWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cta_title'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cta_text'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'], 'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cta_btn'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cta_url'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cta_blank'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_social_items'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_icon'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'], 'sql' => "varchar(64) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_ftitle'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_ftext'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'], 'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_btitle'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_btext'] = [
    'inputType' => 'textarea', 'eval' => ['tl_class' => 'clr'], 'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_btn'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_url'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_flip_height'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 300",
];

// ── Tier 4 (Theme-Builder Batch 1): Logo / PageTitle / Breadcrumb / Sitemap ──
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_logo'] =
    '{type_legend},type;{draggo_logo_legend},draggo_logo_src,draggo_logo_alt,draggo_logo_link,draggo_logo_url;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_pagetitle'] =
    '{type_legend},type;{draggo_pt_legend},draggo_pt_tag,draggo_pt_source;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_breadcrumb'] =
    '{type_legend},type;{draggo_bc_legend},draggo_bc_sep;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_sitemap'] =
    '{type_legend},type;{draggo_sm_legend},draggo_sm_root,draggo_sm_levels;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_logo_src'] = [
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
    'sql' => 'binary(16) NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_logo_alt'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_logo_link'] = [
    'inputType' => 'select', 'options' => ['home', 'custom', 'none'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'home'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_logo_url'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_pt_tag'] = [
    'inputType' => 'select', 'options' => ['h1', 'h2', 'h3', 'div'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'h1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_pt_source'] = [
    'inputType' => 'select', 'options' => ['title', 'pageTitle', 'description'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(16) NOT NULL default 'title'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_bc_sep'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 8, 'tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default '›'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sm_root'] = [
    'inputType' => 'pageTree', 'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sm_levels'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 3",
];

// ── Tier 4 (Single-Builder): Reader-Field ───────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_readerfield'] =
    '{type_legend},type;{draggo_rf_legend},draggo_rf_source,draggo_rf_field,draggo_rf_tag;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_rf_source'] = [
    'inputType' => 'select', 'options' => ['auto', 'news', 'event'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'auto'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_rf_field'] = [
    'inputType' => 'select', 'options' => ['title', 'teaser', 'image', 'date', 'author'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(16) NOT NULL default 'title'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_rf_tag'] = [
    'inputType' => 'select', 'options' => ['h1', 'h2', 'h3', 'div'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'h1'",
];

// ── Tier 4 (Global block): embed a reusable section unit (live link) ──
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_global'] =
    '{type_legend},type;{draggo_global_legend},draggo_global_unit;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_global_unit'] = [
    'inputType' => 'select', 'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];

// ── Tier 5: Countdown / Map / Share / Table of Contents ─────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_countdown'] =
    '{type_legend},type;{draggo_cd_legend},draggo_cd_date,draggo_cd_expired;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_map'] =
    '{type_legend},type;{draggo_map_legend},draggo_map_query,draggo_map_zoom,draggo_map_height,draggo_map_consent;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_share'] =
    '{type_legend},type;{draggo_share_legend},draggo_sh_facebook,draggo_sh_linkedin,draggo_sh_email,draggo_sh_copy;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_toc'] =
    '{type_legend},type;{draggo_toc_legend},draggo_toc_title,draggo_toc_levels;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cd_date'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_cd_expired'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_map_query'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'clr'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_map_zoom'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 14",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_map_height'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 400",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_map_consent'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sh_facebook'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sh_linkedin'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sh_email'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sh_copy'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_toc_title'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default 'Inhalt'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_toc_levels'] = [
    'inputType' => 'select', 'options' => ['2', '2-3', '2-4'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default '2-3'",
];

// ── Tier 5 (Batch 2): Price-Table / Price-List / Steps / Search ─────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_pricetable'] =
    '{type_legend},type;{draggo_prt_legend},draggo_prt_title,draggo_prt_price,draggo_prt_period,draggo_prt_features,draggo_prt_btn,draggo_prt_url,draggo_prt_featured;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_pricelist'] =
    '{type_legend},type;{draggo_prl_legend},draggo_prl_items;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_steps'] =
    '{type_legend},type;{draggo_stp_legend},draggo_stp_items;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_search'] =
    '{type_legend},type;{draggo_se_legend},draggo_se_page,draggo_se_ph,draggo_se_btn;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_title'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_price'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'], 'sql' => "varchar(32) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_period'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'], 'sql' => "varchar(32) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_features'] = [
    'inputType' => 'listWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_btn'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_url'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'dcaPicker' => true], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prt_featured'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_prl_items'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_stp_items'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_se_page'] = [
    'inputType' => 'pageTree', 'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default 0",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_se_ph'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 128, 'tl_class' => 'w50'], 'sql' => "varchar(128) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_se_btn'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 64, 'tl_class' => 'w50'], 'sql' => "varchar(64) NOT NULL default ''",
];

// ── Tier 5 (Batch 3): Animated headline / Hotspot / Code block ──────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_anim'] =
    '{type_legend},type;{draggo_an_legend},draggo_an_before,draggo_an_words,draggo_an_after,draggo_an_effect,draggo_an_tag;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_hotspot'] =
    '{type_legend},type;{draggo_hs_legend},draggo_hs_img,draggo_hs_points;{expert_legend:hide},cssID';
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_codeblock'] =
    '{type_legend},type;{draggo_code_legend},draggo_code,draggo_code_lang,draggo_code_copy;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_an_before'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_an_words'] = [
    'inputType' => 'listWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_an_after'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 255, 'tl_class' => 'w50'], 'sql' => "varchar(255) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_an_effect'] = [
    'inputType' => 'select', 'options' => ['rotate', 'type'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'rotate'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_an_tag'] = [
    'inputType' => 'select', 'options' => ['h1', 'h2', 'h3', 'div'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'h2'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hs_img'] = [
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
    'sql' => 'binary(16) NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hs_points'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_code'] = [
    'inputType' => 'textarea', 'eval' => ['preserveTags' => true, 'decodeEntities' => false, 'class' => 'monospace', 'rte' => false, 'tl_class' => 'clr'],
    'sql' => 'text NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_code_lang'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 32, 'tl_class' => 'w50'], 'sql' => "varchar(32) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_code_copy'] = [
    'inputType' => 'checkbox', 'eval' => ['tl_class' => 'w50'], 'sql' => "char(1) NOT NULL default '1'",
];

// ── Tier 5 (Batch 4): Video playlist ────────────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_videoplaylist'] =
    '{type_legend},type;{draggo_vp_legend},draggo_vp_items,draggo_vp_ratio;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_vp_items'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'], 'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_vp_ratio'] = [
    'inputType' => 'select', 'options' => ['16:9', '4:3', '1:1'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default '16:9'",
];

// ── Draggo gallery (grid / masonry / tiles) ────────────────────────
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_gallery'] =
    '{type_legend},type;{draggo_ga_legend},draggo_ga_images,draggo_ga_layout,draggo_ga_cols,draggo_ga_gap,draggo_ga_ratio,draggo_ga_hover,draggo_ga_link;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_images'] = [
    'inputType' => 'fileTree',
    'eval' => ['filesOnly' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'draggo_ga_order', 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_order'] = ['sql' => 'blob NULL'];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_layout'] = [
    'inputType' => 'select', 'options' => ['grid', 'masonry', 'tiles'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'grid'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_cols'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 3",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_gap'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'], 'sql' => "smallint(5) unsigned NOT NULL default 8",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_ratio'] = [
    'inputType' => 'select', 'options' => ['1:1', '4:3', '3:4', '16:9', '3:2'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default '1:1'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_hover'] = [
    'inputType' => 'select', 'options' => ['none', 'zoom', 'overlay', 'grayscale'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(12) NOT NULL default 'zoom'",
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ga_link'] = [
    'inputType' => 'select', 'options' => ['none', 'file', 'lightbox'],
    'eval' => ['tl_class' => 'w50'], 'sql' => "varchar(8) NOT NULL default 'lightbox'",
];

/*
 * Backend list visualisation of the grid wrapper elements. Adds a coloured,
 * labelled bar + width data for draggo-backend.js. Non-Draggo rows fall
 * through to Contao's default renderer (captured before we override it).
 */
$prevChildRecord = $GLOBALS['TL_DCA']['tl_content']['list']['sorting']['child_record_callback'] ?? null;

$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['child_record_callback'] = static function (array $row) use ($prevChildRecord): string {
    $labels = [
        'draggo_row_start' => '▦ Grid · Reihe Anfang',
        'draggo_col'       => '▏ Grid · Spalte',
        'draggo_row_stop'  => '▦ Grid · Reihe Ende',
    ];

    if (isset($labels[$row['type']])) {
        $shortType = ['draggo_row_start' => 'row_start', 'draggo_col' => 'col', 'draggo_row_stop' => 'row_stop'][$row['type']];
        $text = $labels[$row['type']];

        $cols = '';
        if ($row['type'] === 'draggo_row_start') {
            $preset = $row['draggo_grid_preset'] ?: '1';
            $custom = $row['draggo_grid_custom'] ? (string) $row['draggo_grid_custom'] : null;
            $text .= ' · ' . GridPresets::ratioLabel($preset, $custom);
            $widths = GridPresets::widths()[$preset] ?? [];
            if ($preset === 'custom' || $widths === []) {
                preg_match_all('/\d+/', (string) $custom, $mm);
                $widths = array_map('intval', $mm[0]);
            }
            $cols = implode(',', $widths);
        }

        $color = $row['type'] === 'draggo_col' ? '#7aa2f7' : '#2f6fed';

        return '<div class="draggo-be-wrapper" data-draggo-type="' . $shortType . '"'
            . ' data-draggo-cols="' . htmlspecialchars($cols, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' style="padding:6px 10px;background:#eef3ff;border-left:4px solid ' . $color . ';font-weight:600;color:#1b1f24">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
    }

    if (\is_array($prevChildRecord) && isset($prevChildRecord[0], $prevChildRecord[1])) {
        try {
            return (string) \Contao\System::importStatic($prevChildRecord[0])->{$prevChildRecord[1]}($row);
        } catch (\Throwable) {
            // fall through
        }
    } elseif (\is_callable($prevChildRecord)) {
        return (string) $prevChildRecord($row);
    }

    $label = $GLOBALS['TL_LANG']['CTE'][$row['type']][0] ?? ($GLOBALS['TL_LANG']['CTE'][$row['type']] ?? $row['type']);

    return '<div style="padding:6px 10px">' . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
};


/*
 * ── Scrollytelling elements ─────────────────────────────────────────────
 * Content fields are virtual (no 'sql' — stored in draggo_props by the loop
 * below; editable in the Draggo editor via DcaFieldReader and in the standard
 * backend form via PropsCallbacks). Visual styling (colours/radius) lives in
 * the Style tab (StyleSchema → draggo_layout → compiler --ss-* vars).
 */

// Stack-Reveal: overlapping pinned cards.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_cards'] = [
    'inputType' => 'keyValueWizard',
    'eval'      => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_num'] = [
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50 m12'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_height'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '78', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_top'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '8', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_gap'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '40', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ss_scale'] = [
    'inputType' => 'text',
    'eval'      => ['placeholder' => '0.92', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_stack'] =
    '{type_legend},type;{draggo_ss_legend},draggo_ss_cards,draggo_ss_num,draggo_ss_height,draggo_ss_top,draggo_ss_gap,draggo_ss_scale;{expert_legend:hide},cssID';

// Horizontal-Pin: pin the section, scroll the inner track sideways.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hp_panels'] = [
    'inputType' => 'keyValueWizard',
    'eval'      => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hp_track'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => 'auto', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hp_w'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '70', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_hp_gap'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '3', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_hpin'] =
    '{type_legend},type;{draggo_hp_legend},draggo_hp_panels,draggo_hp_track,draggo_hp_w,draggo_hp_gap;{expert_legend:hide},cssID';

// Sticky-Split Story: pinned visual + scrolling text steps.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sp_steps'] = [
    'inputType' => 'keyValueWizard',
    'eval'      => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sp_images'] = [
    'inputType' => 'fileTree',
    'eval'      => ['multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'isGallery' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sp_side'] = [
    'inputType' => 'select',
    'options'   => ['left', 'right'],
    'reference' => ['left' => 'Bild links', 'right' => 'Bild rechts'],
    'eval'      => ['tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_split'] =
    '{type_legend},type;{draggo_sp_legend},draggo_sp_steps,draggo_sp_images,draggo_sp_side;{expert_legend:hide},cssID';

// Text-Highlight: word-by-word colour fill.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_th_text'] = [
    'inputType' => 'textarea',
    'eval'      => ['rows' => 4, 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_th_tag'] = [
    'inputType' => 'select',
    'options'   => ['h2', 'h3', 'p', 'div'],
    'eval'      => ['tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_text'] =
    '{type_legend},type;{draggo_th_legend},draggo_th_text,draggo_th_tag;{expert_legend:hide},cssID';

// Scroll-Zoom: framed image scales to full.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_zm_src'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_zm_alt'] = [
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_zm_from'] = [
    'inputType' => 'text',
    'eval'      => ['placeholder' => '0.6', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_zoom'] =
    '{type_legend},type;{draggo_zm_legend},draggo_zm_src,draggo_zm_alt,draggo_zm_from;{expert_legend:hide},cssID';

// Parallax-Layers.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_bg'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_mid'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_title'] = [
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_text'] = [
    'inputType' => 'textarea',
    'eval'      => ['rows' => 3, 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_height'] = [
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'placeholder' => '70', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_px_speed'] = [
    'inputType' => 'text',
    'eval'      => ['placeholder' => '0.4', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_parallax'] =
    '{type_legend},type;{draggo_px_legend},draggo_px_bg,draggo_px_mid,draggo_px_title,draggo_px_text,draggo_px_height,draggo_px_speed;{expert_legend:hide},cssID';

// Pinned-Timeline.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_tl_steps'] = [
    'inputType' => 'keyValueWizard',
    'eval'      => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_timeline'] =
    '{type_legend},type;{draggo_tl_legend},draggo_tl_steps;{expert_legend:hide},cssID';

// Before/After compare slider (interactive).
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ba_before'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ba_after'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ba_lblbefore'] = [
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ba_lblafter'] = [
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_beforeafter'] =
    '{type_legend},type;{draggo_ba_legend},draggo_ba_before,draggo_ba_after,draggo_ba_lblbefore,draggo_ba_lblafter;{expert_legend:hide},cssID';

// ── Scrollytelling Wave 2 ───────────────────────────────────────────────
// Reveal-Grid.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_rg_items'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_rg_cols'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'placeholder' => '3', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_revealgrid'] =
    '{type_legend},type;{draggo_rg_legend},draggo_rg_items,draggo_rg_cols;{expert_legend:hide},cssID';

// SVG-Path-Draw.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_sd_shape'] = [
    'inputType' => 'select',
    'options'   => ['line', 'wave', 'zigzag', 'underline', 'arrow'],
    'eval'      => ['tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_svgdraw'] =
    '{type_legend},type;{draggo_sd_legend},draggo_sd_shape;{expert_legend:hide},cssID';

// Scroll-Progress.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_pr_pos'] = [
    'inputType' => 'select', 'options' => ['top', 'bottom'], 'eval' => ['tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_pr_h'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'placeholder' => '4', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_progress'] =
    '{type_legend},type;{draggo_pr_legend},draggo_pr_pos,draggo_pr_h;{expert_legend:hide},cssID';

// Curtain-Cover.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ct_panels'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ct_images'] = [
    'inputType' => 'fileTree',
    'eval'      => ['multiple' => true, 'fieldType' => 'checkbox', 'filesOnly' => true, 'isGallery' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_curtain'] =
    '{type_legend},type;{draggo_ct_legend},draggo_ct_panels,draggo_ct_images;{expert_legend:hide},cssID';

// Text-Mask.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_tm_text'] = [
    'inputType' => 'text', 'eval' => ['maxlength' => 60, 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_tm_img'] = [
    'inputType' => 'fileTree',
    'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => '%contao.image.valid_extensions%', 'tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_textmask'] =
    '{type_legend},type;{draggo_tm_legend},draggo_tm_text,draggo_tm_img;{expert_legend:hide},cssID';

// Tilt-3D Cards.
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ti_cards'] = [
    'inputType' => 'keyValueWizard', 'eval' => ['tl_class' => 'clr'],
];
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_ti_cols'] = [
    'inputType' => 'text', 'eval' => ['rgxp' => 'natural', 'placeholder' => '3', 'tl_class' => 'w50'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_scroll_tilt'] =
    '{type_legend},type;{draggo_ti_legend},draggo_ti_cards,draggo_ti_cols;{expert_legend:hide},cssID';

// ── Form element (visual builder on top of native Contao forms) ─────────
$GLOBALS['TL_DCA']['tl_content']['fields']['draggo_form_id'] = [
    'inputType'  => 'select',
    'foreignKey' => 'tl_form.title',
    'eval'       => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
];
$GLOBALS['TL_DCA']['tl_content']['palettes']['draggo_form'] =
    '{type_legend},type;{draggo_form_legend},draggo_form_id;{expert_legend:hide},cssID';

/*
 * De-bloat (consolidation): every Draggo element CONTENT field is virtual — its
 * value lives in the single tl_content.draggo_props JSON column, NOT in 145
 * individual columns (which bloated the shared core table, risked the InnoDB
 * row-size limit and broke update-safety). This loop, running on every DCA load:
 *   1. strips the field's `sql` so contao:migrate never (re)creates the column,
 *   2. wires load/save callbacks so the STANDARD backend form still reads and
 *      writes the value transparently via draggo_props.
 * Structural draggo_* columns (grid/layout/data/blocktype/container/managed/
 * props) are kept as real columns. New element fields cost zero schema changes.
 */
$draggoPropKeep = [
    'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container',
    'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile', 'draggo_props',
];
foreach (array_keys($GLOBALS['TL_DCA']['tl_content']['fields'] ?? []) as $draggoField) {
    $draggoField = (string) $draggoField;
    if (!str_starts_with($draggoField, 'draggo_') || \in_array($draggoField, $draggoPropKeep, true)) {
        continue;
    }
    unset($GLOBALS['TL_DCA']['tl_content']['fields'][$draggoField]['sql']);
    $GLOBALS['TL_DCA']['tl_content']['fields'][$draggoField]['load_callback'][] = [PropsCallbacks::class, 'load'];
    $GLOBALS['TL_DCA']['tl_content']['fields'][$draggoField]['save_callback'][] = [PropsCallbacks::class, 'save'];
    // CRITICAL: without a real column, DC_Table::save() would still leak the
    // field into the UPDATE (as ''), producing `SET draggo_xxx=?` for a dropped
    // column → SQL 1054, aborting the whole record save. doNotSaveEmpty makes
    // the save guard skip the (post-callback null) value so no column write is
    // emitted; PropsCallbacks::save already persisted the real value to
    // draggo_props. See DC_Table::save() guard (~line 3021).
    $GLOBALS['TL_DCA']['tl_content']['fields'][$draggoField]['eval']['doNotSaveEmpty'] = true;
}
unset($draggoPropKeep, $draggoField);

// Defense-in-depth: strip ANY orphan draggo_* content key (one with no real
// column) from the values right before the core UPDATE — covers every save path
// (edit / editAll / overrideAll / copy / import), not just the standard form.
// The values are already persisted to draggo_props by PropsCallbacks::save, so
// dropping them from the column write is lossless. Runs before DC_Table builds
// the UPDATE (onbeforesubmit_callback, must return the values).
$GLOBALS['TL_DCA']['tl_content']['config']['onbeforesubmit_callback'][] = [PropsCallbacks::class, 'beforeSubmit'];
