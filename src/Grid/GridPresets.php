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
 * The predefined grid structures offered in the editor, plus the parser for
 * the "custom" choice. Widths are Bootstrap column units (1..12) per column;
 * they should sum to 12 for a single row but are not forced to.
 */
final class GridPresets
{
    /** @var array<string, array{label:string, cols:list<int>}> */
    public const PRESETS = [
        '1'       => ['label' => '1 Spalte',                       'cols' => [12]],
        '6-6'     => ['label' => '2 Spalten (50 / 50)',            'cols' => [6, 6]],
        '4-8'     => ['label' => 'Sidebar links (33 / 66)',        'cols' => [4, 8]],
        '8-4'     => ['label' => 'Sidebar rechts (66 / 33)',       'cols' => [8, 4]],
        '4-4-4'   => ['label' => '3 Spalten (Drittel)',            'cols' => [4, 4, 4]],
        '3-6-3'   => ['label' => '3 Spalten (schmal/breit/schmal)','cols' => [3, 6, 3]],
        '3-3-3-3' => ['label' => '4 Spalten (Viertel)',            'cols' => [3, 3, 3, 3]],
        '2-8-2'   => ['label' => 'Zentriert (schmaler Inhalt)',    'cols' => [2, 8, 2]],
    ];

    /**
     * Build a grid definition with responsive breakpoints.
     *
     * Bootstrap mapping: mobile (<768) = base `col-N`, tablet (≥768) = `col-md-N`,
     * desktop (≥992) = `col-lg-N`. Empty tablet inherits desktop; empty mobile
     * stacks (full width).
     */
    public static function definition(
        string $key,
        string $container = 'container',
        ?string $custom = null,
        string $tabletKey = '',
        string $mobileKey = '',
    ): GridDefinition {
        $desktop = self::widthsFor($key, $custom);
        $n = \count($desktop);

        $tablet = $tabletKey !== '' ? self::normalize(self::widthsFor($tabletKey, null), $n, $desktop) : $desktop;
        $mobile = $mobileKey !== '' ? self::normalize(self::widthsFor($mobileKey, null), $n, $desktop) : array_fill(0, $n, 12);

        $columnClasses = [];
        for ($i = 0; $i < $n; $i++) {
            $columnClasses[] = 'col-' . $mobile[$i] . ' col-md-' . $tablet[$i] . ' col-lg-' . $desktop[$i];
        }

        $containerClass = \in_array($container, ['container', 'container-fluid'], true) ? $container : '';

        return new GridDefinition($containerClass, 'row g-3', $columnClasses);
    }

    /**
     * Number of columns a preset (or custom CSV) defines.
     */
    public static function columnCount(string $key, ?string $custom): int
    {
        return \count(self::widthsFor($key, $custom));
    }

    /**
     * Human ratio label, e.g. [6,6] → "50% · 50%".
     */
    public static function ratioLabel(string $key, ?string $custom): string
    {
        $parts = array_map(
            static fn (int $w): string => (string) round($w / 12 * 100) . '%',
            self::widthsFor($key, $custom),
        );

        return implode(' · ', $parts);
    }

    /**
     * @return list<int>
     */
    private static function widthsFor(string $key, ?string $custom): array
    {
        $widths = $key === 'custom' ? self::parseCustom((string) $custom) : (self::PRESETS[$key]['cols'] ?? [12]);

        return $widths === [] ? [12] : array_values($widths);
    }

    /**
     * Make $widths exactly $n long. A SHORTER pattern is CYCLED (repeated), not
     * padded from the desktop fallback — so "mobile 1" ([12]) makes EVERY column
     * full-width (all stack) and "6-6" on 3 columns becomes 6-6-6 (wrap).
     * Padding from desktop was the bug where only column 1 stacked on mobile
     * while columns 2/3 kept their desktop width. Mirrors the editor's
     * gridWidths() so editor and frontend agree.
     *
     * @param list<int> $widths
     * @param list<int> $fallback unused (kept for signature stability)
     * @return list<int>
     */
    private static function normalize(array $widths, int $n, array $fallback): array
    {
        if (\count($widths) === $n) {
            return $widths;
        }
        if ($widths === []) {
            $even = (int) floor(12 / max(1, $n)) ?: 12;

            return array_fill(0, $n, $even);
        }

        $count = \count($widths);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $widths[$i % $count];
        }

        return $out;
    }

    /**
     * Parse "6,6" / "4-4-4" / "col-md-6 col-md-6" loosely into a clamped
     * width list. Anything non-numeric is dropped; values clamp to 1..12.
     *
     * @return list<int>
     */
    public static function parseCustom(string $custom): array
    {
        preg_match_all('/\d+/', $custom, $m);
        $widths = [];

        foreach ($m[0] as $n) {
            $n = (int) $n;
            if ($n >= 1 && $n <= 12) {
                $widths[] = $n;
            }
        }

        return $widths;
    }

    /**
     * Values for a DCA select (preset keys + 'custom').
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return [...array_keys(self::PRESETS), 'custom'];
    }

    /**
     * key => column-width list, for the editor to draw column boxes.
     *
     * @return array<string, list<int>>
     */
    public static function widths(): array
    {
        $widths = [];
        foreach (self::PRESETS as $key => $data) {
            $widths[$key] = $data['cols'];
        }
        $widths['custom'] = [];

        return $widths;
    }

    /**
     * value => label map for DCA 'reference'.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::PRESETS as $key => $data) {
            $labels[$key] = $data['label'];
        }
        $labels['custom'] = 'Benutzerdefiniert';

        return $labels;
    }
}
