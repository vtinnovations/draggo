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

namespace Vtinnovations\Draggo\Control;

/**
 * The control-type vocabulary (Roadmap Phase A, reference Teil 1). A control
 * declares ONE of these; the editor JS has a generic renderer per type. New
 * widgets/styles are just declarations of these primitives — no hand-built
 * fields. Not every primitive is wired in the first slice; the set is the
 * foundation everything else hangs off.
 */
final class ControlType
{
    // Data controls
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const TEXTAREA = 'textarea';
    public const WYSIWYG = 'rte';
    public const CODE = 'code';
    public const SWITCHER = 'switcher';
    public const SELECT = 'select';
    public const CHOOSE = 'choose';
    public const COLOR = 'color';
    public const FONT = 'font';
    public const MEDIA = 'file';
    public const GALLERY = 'files';
    public const IMAGE_DIMENSIONS = 'imgsize';
    public const ICON = 'icon';
    public const URL = 'url';
    public const REPEATER = 'repeater';

    // Unit / multi-value
    public const SLIDER = 'slider';
    public const LENGTH = 'length';
    public const DIMENSIONS = 'dimensions';
    public const BOX_SHADOW = 'boxshadow';
    public const TEXT_SHADOW = 'textshadow';

    // Domain widgets (Contao-specific data shapes already in use)
    public const HEADLINE = 'headline';
    public const TABLE = 'table';
    public const PAIRS = 'pairs';
    public const LINES = 'lines';

    // UI / structure (no value)
    public const HEADING = 'ui_heading';
    public const DIVIDER = 'ui_divider';
    public const RAW_HTML = 'ui_html';
}
