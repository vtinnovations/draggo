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

namespace Vtinnovations\Draggo\Font;

/**
 * Curated set of popular Google Fonts offered in the editor + helper to build a
 * Google Fonts stylesheet link for the families actually used on a page.
 * (Loaded on demand so pages never pull fonts they don't use.)
 *
 * Note: served from Google's CDN. For strict GDPR setups, host the fonts
 * locally and drop the listener — the font-family declarations stay valid.
 */
final class GoogleFonts
{
    /** @var list<string> Popular families (extend as needed). */
    public const FAMILIES = [
        'Roboto', 'Open Sans', 'Montserrat', 'Lato', 'Poppins', 'Inter', 'Oswald', 'Raleway',
        'Nunito', 'Nunito Sans', 'Playfair Display', 'Merriweather', 'Roboto Condensed', 'Ubuntu',
        'Rubik', 'Work Sans', 'Mukta', 'Noto Sans', 'Noto Serif', 'PT Sans', 'PT Serif', 'Quicksand',
        'Mulish', 'Manrope', 'Barlow', 'Karla', 'Heebo', 'DM Sans', 'DM Serif Display', 'Source Sans 3',
        'Source Serif 4', 'Fira Sans', 'Cabin', 'Josefin Sans', 'Titillium Web', 'Dosis', 'Bebas Neue',
        'Anton', 'Archivo', 'Archivo Black', 'Libre Franklin', 'Libre Baskerville', 'Bitter', 'Arvo',
        'Lora', 'Crimson Text', 'EB Garamond', 'Cormorant Garamond', 'Space Grotesk', 'Space Mono',
        'IBM Plex Sans', 'IBM Plex Serif', 'IBM Plex Mono', 'JetBrains Mono', 'Fira Code', 'Outfit',
        'Plus Jakarta Sans', 'Sora', 'Figtree', 'Albert Sans', 'Schibsted Grotesk', 'Hanken Grotesk',
        'Caveat', 'Pacifico', 'Dancing Script', 'Lobster', 'Shadows Into Light', 'Permanent Marker',
        'Abril Fatface', 'Comfortaa', 'Teko', 'Kanit', 'Prompt', 'Saira', 'Exo 2', 'Righteous',
        'Russo One', 'Fredoka', 'Baloo 2', 'Zilla Slab', 'Roboto Slab', 'Slabo 27px', 'Domine',
        'Cardo', 'Spectral', 'Vollkorn', 'Frank Ruhl Libre', 'Assistant', 'Tajawal', 'Cairo',
        'Roboto Mono', 'Inconsolata', 'Overpass', 'Signika', 'Maven Pro', 'Catamaran', 'Hind',
    ];

    /** @return array<string,bool> family => true for O(1) lookup */
    private static function set(): array
    {
        static $set = null;
        if ($set === null) {
            $set = [];
            foreach (self::FAMILIES as $f) {
                $set[strtolower($f)] = true;
            }
        }

        return $set;
    }

    /**
     * Pull Google family names out of arbitrary font-family / value strings.
     *
     * @param iterable<string|null> $values raw fontFamily / token values
     * @return list<string>
     */
    public static function extract(iterable $values): array
    {
        $set = self::set();
        $found = [];
        foreach ($values as $v) {
            if (!\is_string($v) || $v === '') {
                continue;
            }
            // First family in a stack, stripped of quotes.
            $first = trim(explode(',', $v)[0], " \t\n\r\0\x0B'\"");
            if ($first !== '' && isset($set[strtolower($first)]) && !\in_array($first, $found, true)) {
                $found[] = $first;
            }
        }

        return $found;
    }

    /**
     * Extract Google families from a blob of draggo_layout JSON (matches all
     * "fontFamily":"…" occurrences, incl. responsive overrides).
     *
     * @return list<string>
     */
    public static function fromBlob(string $blob): array
    {
        preg_match_all('/"(?:fontFamily|navFontFamily|subFontFamily)":"([^"]*)"/', $blob, $m);

        return self::extract($m[1] ?? []);
    }

    /**
     * Build the Google Fonts stylesheet link for the given families (common
     * weights), or null when none.
     *
     * @param list<string> $families
     */
    public static function link(array $families): ?string
    {
        $families = array_values(array_unique(array_filter($families)));
        if ($families === []) {
            return null;
        }
        $parts = [];
        foreach ($families as $f) {
            $parts[] = 'family=' . str_replace(' ', '+', $f) . ':wght@300;400;500;600;700;800;900';
        }

        $href = 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';

        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '">';
    }
}
