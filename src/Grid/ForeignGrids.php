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

namespace Vtinnovations\Draggo\Grid;

/**
 * Known THIRD-PARTY grid systems that, like Draggo, are built from wrapper
 * content elements (start / separator / stop). Draggo's editor recognises
 * these so existing theme content (SubColumns, contao-bootstrap grid, RockSolid)
 * shows up as editable rows/columns — WITHOUT touching the frontend (those
 * elements keep rendering through their own controllers / theme CSS).
 *
 * Recognition needs only the type triples; column widths are approximated as
 * equal in the editor (the real widths come from the theme on the frontend).
 * Opt-in conversion to Draggo wrappers is a separate, explicit step.
 *
 * @var array<string,array{start:string,separator:string,stop:string,label:string}>
 */
final class ForeignGrids
{
    public const SYSTEMS = [
        // HeimrichHannot contao-subcolumns (ex-RockSolid lineage) — most common.
        'subcolumns' => ['start' => 'colsetStart', 'separator' => 'colsetPart', 'stop' => 'colsetEnd', 'label' => 'SubColumns'],
        // contao-bootstrap / grid.
        'bootstrap'  => ['start' => 'bs_gridStart', 'separator' => 'bs_gridSeparator', 'stop' => 'bs_gridStop', 'label' => 'Bootstrap-Grid'],
        // RockSolid columns (MadeYourWeb) — separate type keys in some versions.
        'rocksolid'  => ['start' => 'rsColumnStart', 'separator' => 'rsColumnPart', 'stop' => 'rsColumnEnd', 'label' => 'RockSolid Columns'],
    ];

    /**
     * Start-type name PREFIXES that are wrappers but NOT column grids — they
     * bracket content too, but treating them as rows/columns is wrong. Excluded
     * from auto-derivation (curated grids above are always kept).
     */
    private const NON_GRID_PREFIXES = ['accordion', 'slider', 'fieldset', 'draggo_'];

    /**
     * Curated systems PLUS any further grid triples auto-derived from
     * $GLOBALS['TL_WRAPPERS'] — so a theme/3rd-party module (incl. PCT) that
     * registers conventionally-named column wrappers (xStart / xPart|xSeparator /
     * xStop|xEnd) is recognised without hardcoding. Curated entries win.
     *
     * @return array<string,array{start:string,separator:string,stop:string,label:string}>
     */
    public static function systems(): array
    {
        $systems = self::SYSTEMS;
        $curatedStarts = array_map(static fn (array $s): string => $s['start'], self::SYSTEMS);

        $wrappers = $GLOBALS['TL_WRAPPERS'] ?? [];
        $starts = (array) ($wrappers['start'] ?? []);
        $stops = array_map('strval', (array) ($wrappers['stop'] ?? []));
        $separators = array_map('strval', (array) ($wrappers['separator'] ?? []));

        foreach ($starts as $start) {
            $start = (string) $start;
            if ($start === '' || \in_array($start, $curatedStarts, true) || !str_ends_with($start, 'Start')) {
                continue;
            }
            $lower = strtolower($start);
            foreach (self::NON_GRID_PREFIXES as $skip) {
                if (str_starts_with($lower, strtolower($skip))) {
                    continue 2;
                }
            }
            $base = substr($start, 0, -\strlen('Start'));
            if ($base === '') {
                continue;
            }
            // Pair stop / separator by shared base prefix.
            $stop = self::firstWithPrefix($stops, $base, $start);
            if ($stop === null) {
                continue; // a grid needs a matching stop
            }
            // Only bridge SEPARATOR-model grids (columns split by a separator,
            // like Draggo's own). Systems WITHOUT a separator use per-column
            // start/stop wrappers (e.g. PCT autogrid: Grid⊃Row⊃Col, each column
            // its own pair) — that doesn't fit Draggo's column model and editing
            // it here would drop the per-column stop ids on save (data loss). Such
            // systems stay with their own backend UI; Draggo doesn't claim them.
            $sep = self::firstWithPrefix($separators, $base, $start);
            if ($sep === null) {
                continue;
            }
            $systems[$base] = ['start' => $start, 'separator' => $sep, 'stop' => $stop, 'label' => ucfirst($base) . ' (Grid)'];
        }

        return $systems;
    }

    /** First entry that starts with $base (case-insensitive) and isn't $exclude. */
    private static function firstWithPrefix(array $candidates, string $base, string $exclude): ?string
    {
        $b = strtolower($base);
        foreach ($candidates as $c) {
            $c = (string) $c;
            if ($c !== $exclude && str_starts_with(strtolower($c), $b)) {
                return $c;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function startTypes(): array
    {
        return array_values(array_map(static fn (array $s): string => $s['start'], self::systems()));
    }

    /** @return list<string> */
    public static function separatorTypes(): array
    {
        return array_values(array_filter(array_map(static fn (array $s): string => $s['separator'], self::systems())));
    }

    /** @return list<string> */
    public static function stopTypes(): array
    {
        return array_values(array_map(static fn (array $s): string => $s['stop'], self::systems()));
    }

    /** The triple a given start type belongs to, or null. @return array{start:string,separator:string,stop:string,label:string}|null */
    public static function tripleForStart(string $start): ?array
    {
        foreach (self::systems() as $s) {
            if ($s['start'] === $start) {
                return $s;
            }
        }

        return null;
    }

    /** All foreign wrapper types (start+separator+stop). @return list<string> */
    public static function allTypes(): array
    {
        return array_merge(self::startTypes(), self::separatorTypes(), self::stopTypes());
    }

    /**
     * Wrapper-triple descriptor for the editor JS (incl. Draggo's own), so
     * buildTree can group any of them into rows/columns.
     *
     * @return array{start:list<string>,separator:list<string>,stop:list<string>}
     */
    public static function editorTriples(): array
    {
        return [
            'start'     => array_merge(['draggo_row_start'], self::startTypes()),
            'separator' => array_merge(['draggo_col'], self::separatorTypes()),
            'stop'      => array_merge(['draggo_row_stop'], self::stopTypes()),
        ];
    }
}
