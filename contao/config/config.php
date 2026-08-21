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
 * Backend module — manage global Draggo units (header/footer/section). The
 * tl_draggo_unit table exists as of Phase 2, so the module is safe to show.
 */
$GLOBALS['BE_MOD']['draggo'] = [
    'units' => [
        'tables' => ['tl_draggo_unit'],
        'icon'   => 'bundles/draggo/icon.svg',
    ],
    // Overview / (un)publish / delete of AI-generated element types (Phase 3).
    'blocktypes' => [
        'tables' => ['tl_draggo_blocktype'],
        'icon'   => 'bundles/draggo/icon.svg',
    ],
    // Draggo settings (AI key, model, telemetry) — single-row edit form.
    // NB: key must be unique across ALL modules — "settings" collides with
    // Contao's core system-settings module (both would be do=settings).
    'draggo_settings' => [
        'tables' => ['tl_draggo_settings'],
        'icon'   => 'bundles/draggo/icon.svg',
    ],
];

/*
 * Grid wrapper elements (Phase 2). Registered as Contao wrapper elements so
 * the backend indents nested content and the editor treats them as a group.
 * The three controllers are auto-registered via #[AsContentElement].
 */
/*
 * Draggo backend permissions (per user group) — merged into non-admin users
 * so RequestGuard->hasAccess(..., 'draggo_perms') works. Admins always pass.
 */
$GLOBALS['TL_PERMISSIONS'][] = 'draggo_perms';

$GLOBALS['TL_WRAPPERS']['start'][]     = 'draggo_row_start';
$GLOBALS['TL_WRAPPERS']['separator'][] = 'draggo_col';
$GLOBALS['TL_WRAPPERS']['stop'][]      = 'draggo_row_stop';

/*
 * The grid wrapper controllers are auto-registered via #[AsContentElement];
 * they are not mapped here.
 */
