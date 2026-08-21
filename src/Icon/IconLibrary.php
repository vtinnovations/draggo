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

namespace Vtinnovations\Draggo\Icon;

/**
 * Dependency-free inline-SVG icon library (Tier 1 "Icon"). A curated, currentColor
 * stroke set (24×24, Feather-style) so icons inherit text colour + font-size
 * (svg sized to 1em). No FontAwesome / webfont / external request — GDPR-safe and
 * works offline. Shared by the standalone Icon element, Button icon and Box icon.
 *
 * @phpstan-type Icon array{label:string, paths:list<string>}
 */
final class IconLibrary
{
    /**
     * key => [label, list<path-d>]. Drawn with fill:none + stroke:currentColor.
     *
     * @return array<string, array{label:string, paths:list<string>}>
     */
    public static function icons(): array
    {
        return [
            'check'         => ['label' => 'Häkchen', 'paths' => ['M20 6L9 17l-5-5']],
            'check-circle'  => ['label' => 'Häkchen (Kreis)', 'paths' => ['M22 11.08V12a10 10 0 11-5.93-9.14', 'M22 4L12 14.01l-3-3']],
            'x'             => ['label' => 'Schließen', 'paths' => ['M18 6L6 18', 'M6 6l12 12']],
            'plus'          => ['label' => 'Plus', 'paths' => ['M12 5v14', 'M5 12h14']],
            'minus'         => ['label' => 'Minus', 'paths' => ['M5 12h14']],
            'arrow-right'   => ['label' => 'Pfeil rechts', 'paths' => ['M5 12h14', 'M12 5l7 7-7 7']],
            'arrow-left'    => ['label' => 'Pfeil links', 'paths' => ['M19 12H5', 'M12 19l-7-7 7-7']],
            'arrow-up'      => ['label' => 'Pfeil hoch', 'paths' => ['M12 19V5', 'M5 12l7-7 7 7']],
            'arrow-down'    => ['label' => 'Pfeil runter', 'paths' => ['M12 5v14', 'M19 12l-7 7-7-7']],
            'chevron-right' => ['label' => 'Chevron rechts', 'paths' => ['M9 18l6-6-6-6']],
            'chevron-left'  => ['label' => 'Chevron links', 'paths' => ['M15 18l-6-6 6-6']],
            'chevron-down'  => ['label' => 'Chevron runter', 'paths' => ['M6 9l6 6 6-6']],
            'chevron-up'    => ['label' => 'Chevron hoch', 'paths' => ['M18 15l-6-6-6 6']],
            'star'          => ['label' => 'Stern', 'paths' => ['M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z']],
            'heart'         => ['label' => 'Herz', 'paths' => ['M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z']],
            'home'          => ['label' => 'Haus', 'paths' => ['M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z', 'M9 22V12h6v10']],
            'user'          => ['label' => 'Benutzer', 'paths' => ['M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2', 'M16 7a4 4 0 11-8 0 4 4 0 018 0z']],
            'mail'          => ['label' => 'E-Mail', 'paths' => ['M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z', 'M22 6l-10 7L2 6']],
            'phone'         => ['label' => 'Telefon', 'paths' => ['M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z']],
            'search'        => ['label' => 'Suche', 'paths' => ['M19 11a8 8 0 11-16 0 8 8 0 0116 0z', 'M21 21l-4.35-4.35']],
            'menu'          => ['label' => 'Menü', 'paths' => ['M3 12h18', 'M3 6h18', 'M3 18h18']],
            'clock'         => ['label' => 'Uhr', 'paths' => ['M22 12a10 10 0 11-20 0 10 10 0 0120 0z', 'M12 6v6l4 2']],
            'calendar'      => ['label' => 'Kalender', 'paths' => ['M19 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2z', 'M16 2v4', 'M8 2v4', 'M3 10h18']],
            'map-pin'       => ['label' => 'Standort', 'paths' => ['M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z', 'M15 10a3 3 0 11-6 0 3 3 0 016 0z']],
            'info'          => ['label' => 'Info', 'paths' => ['M22 12a10 10 0 11-20 0 10 10 0 0120 0z', 'M12 16v-4', 'M12 8h.01']],
            'download'      => ['label' => 'Download', 'paths' => ['M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4', 'M7 10l5 5 5-5', 'M12 15V3']],
            'external-link' => ['label' => 'Externer Link', 'paths' => ['M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6', 'M15 3h6v6', 'M10 14L21 3']],
            'settings'      => ['label' => 'Einstellungen', 'paths' => ['M4 21v-7', 'M4 10V3', 'M12 21v-9', 'M12 8V3', 'M20 21v-5', 'M20 12V3', 'M1 14h6', 'M9 8h6', 'M17 16h6']],
            'facebook'      => ['label' => 'Facebook', 'paths' => ['M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z']],
            'instagram'     => ['label' => 'Instagram', 'paths' => ['M17 2H7a5 5 0 00-5 5v10a5 5 0 005 5h10a5 5 0 005-5V7a5 5 0 00-5-5z', 'M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z', 'M17.5 6.5h.01']],
            'linkedin'      => ['label' => 'LinkedIn', 'paths' => ['M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-4 0v7h-4v-7a6 6 0 016-6z', 'M6 9H2v12h4z', 'M6 5a2 2 0 11-4 0 2 2 0 014 0z']],
            'youtube'       => ['label' => 'YouTube', 'paths' => ['M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33A2.78 2.78 0 003.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 001.94-2 29 29 0 00.46-5.25 29 29 0 00-.46-5.33z', 'M9.75 15.02l5.75-3.27-5.75-3.27z']],
        ];
    }

    /**
     * Render one icon as inline SVG, or '' for an unknown key. $extraClass is
     * appended to the svg class.
     */
    public static function render(string $key, string $extraClass = ''): string
    {
        $key = trim($key);
        // Font Awesome class string → render as <i> (optional FA integration).
        if (FaIcons::isFa($key)) {
            return FaIcons::render($key, $extraClass);
        }

        $key = self::sanitizeKey($key);
        $icons = self::icons();
        if ($key === '' || !isset($icons[$key])) {
            return '';
        }

        $paths = '';
        foreach ($icons[$key]['paths'] as $d) {
            $paths .= '<path d="' . htmlspecialchars($d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></path>';
        }
        $cls = trim('draggo-svg ' . $extraClass);

        return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
    }

    /** Keep only icon-key-safe characters. */
    public static function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($key))) ?? '';
    }

    /**
     * Flat list for the editor picker: [{key, label, svg}]. svg carries the full
     * inline markup so the picker can render a live preview with no asset round-trip.
     *
     * @return list<array{key:string,label:string,svg:string}>
     */
    public static function pickerList(): array
    {
        $out = [];
        foreach (self::icons() as $key => $def) {
            $out[] = ['key' => $key, 'label' => $def['label'], 'svg' => self::render($key)];
        }

        return $out;
    }
}
