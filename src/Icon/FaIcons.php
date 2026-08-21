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
 * Font Awesome (Free) integration as an OPTIONAL icon source next to the
 * built-in inline-SVG IconLibrary. Icons are stored as a FA class string
 * (e.g. "fa-solid fa-star", "fa-brands fa-instagram") and rendered as <i>.
 *
 * The FA assets are NOT bundled (font binaries). Drop the Font Awesome Free
 * package into Resources/public/vendor/fontawesome/ (css/all.min.css + webfonts);
 * Draggo links it on the frontend + editor only when that file is present.
 */
final class FaIcons
{
    /** Relative public path (after assets:install) of the FA stylesheet. */
    public const CSS_PATH = 'bundles/draggo/vendor/fontawesome/css/all.min.css';

    /**
     * Curated picker catalogue (FA6 Free). Grouped; each = "class" + label.
     * Not exhaustive (FA Free has ~2000) but covers the common cases; users may
     * type any valid FA class manually.
     *
     * @return list<array{label:string, icons:list<array{class:string,label:string}>}>
     */
    public static function catalog(): array
    {
        // Font Awesome 5 Free syntax: solid = "fas fa-…", brands = "fab fa-…".
        $solid = static fn (string $n): array => ['class' => 'fas fa-' . $n, 'label' => $n];
        $brand = static fn (string $n): array => ['class' => 'fab fa-' . $n, 'label' => $n];

        return [
            [
                'label' => 'Allgemein',
                'icons' => array_map($solid, [
                    'home', 'user', 'users', 'cog', 'search', 'bars', 'times', 'check',
                    'plus', 'minus', 'info-circle', 'question-circle', 'check-circle', 'times-circle',
                    'star', 'heart', 'bell', 'bookmark', 'flag', 'fire', 'bolt', 'lightbulb', 'gift',
                    'thumbs-up', 'thumbs-down', 'eye', 'eye-slash', 'lock', 'lock-open', 'trophy', 'crown',
                ]),
            ],
            [
                'label' => 'Pfeile',
                'icons' => array_map($solid, [
                    'arrow-right', 'arrow-left', 'arrow-up', 'arrow-down', 'chevron-right', 'chevron-left',
                    'chevron-up', 'chevron-down', 'angle-double-right', 'angle-double-left', 'redo',
                    'external-link-alt', 'download', 'upload', 'share', 'reply',
                ]),
            ],
            [
                'label' => 'Kontakt & Ort',
                'icons' => array_map($solid, [
                    'envelope', 'phone', 'mobile-alt', 'map-marker-alt', 'map', 'map-pin', 'compass',
                    'paper-plane', 'at', 'address-book', 'comment', 'comments', 'headset',
                ]),
            ],
            [
                'label' => 'Medien',
                'icons' => array_map($solid, [
                    'play', 'pause', 'stop', 'forward', 'backward', 'volume-up', 'volume-mute',
                    'image', 'images', 'video', 'camera', 'music', 'microphone', 'film', 'photo-video',
                ]),
            ],
            [
                'label' => 'Commerce & Zeit',
                'icons' => array_map($solid, [
                    'shopping-cart', 'shopping-bag', 'credit-card', 'tag', 'tags', 'percent',
                    'truck', 'box', 'wallet', 'coins', 'euro-sign', 'calendar', 'calendar-alt', 'clock',
                    'hourglass-half', 'business-time',
                ]),
            ],
            [
                'label' => 'Dateien & Web',
                'icons' => array_map($solid, [
                    'file', 'file-alt', 'file-pdf', 'folder', 'folder-open', 'paperclip', 'print',
                    'cloud', 'cloud-upload-alt', 'cloud-download-alt', 'database', 'globe', 'link', 'wifi',
                    'code', 'terminal', 'rss',
                ]),
            ],
            [
                'label' => 'Business & Symbole',
                'icons' => array_map($solid, [
                    'briefcase', 'chart-line', 'chart-pie', 'chart-bar', 'building', 'industry',
                    'handshake', 'shield-alt', 'award', 'certificate', 'graduation-cap', 'book',
                    'tachometer-alt', 'rocket', 'wrench', 'tools', 'leaf', 'recycle', 'sun', 'moon',
                ]),
            ],
            [
                'label' => 'Marken (Social)',
                'icons' => array_map($brand, [
                    'facebook-f', 'facebook', 'instagram', 'twitter', 'linkedin-in', 'linkedin',
                    'youtube', 'whatsapp', 'tiktok', 'pinterest', 'xing', 'github', 'vimeo-v',
                    'telegram-plane', 'spotify', 'apple', 'google', 'amazon', 'paypal',
                ]),
            ],
        ];
    }

    /** Whether a stored value is a Font Awesome class (vs. a built-in SVG key). */
    public static function isFa(string $value): bool
    {
        return (bool) preg_match('/(?:^|\s)fa[bsrl]?-/', trim($value)) || str_contains($value, 'fa-');
    }

    /**
     * Keep only safe FA class characters (letters, digits, space, hyphen). The
     * spaces matter — "fa-solid fa-star" is two classes.
     */
    public static function sanitize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9 -]/', '', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /** Render an FA class string as an <i> element. */
    public static function render(string $classes, string $extraClass = ''): string
    {
        $classes = self::sanitize($classes);
        if ($classes === '') {
            return '';
        }
        $cls = trim('draggo-fa ' . $extraClass . ' ' . $classes);

        return '<i class="' . htmlspecialchars($cls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-hidden="true"></i>';
    }

    /**
     * Flat picker list for the editor: [{class, label, group}].
     *
     * @return list<array{class:string,label:string,group:string}>
     */
    public static function pickerList(): array
    {
        // Full generated list (all FA5 Free icons) if present, else curated set.
        $file = __DIR__ . '/../../Resources/public/vendor/fontawesome/draggo-fa-icons.json';
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $raw = ltrim($raw, "\xEF\xBB\xBF"); // strip UTF-8 BOM if any
                $list = json_decode($raw, true);
                if (\is_array($list) && $list !== []) {
                    return $list;
                }
            }
        }

        $out = [];
        foreach (self::catalog() as $group) {
            foreach ($group['icons'] as $it) {
                $out[] = ['class' => $it['class'], 'label' => $it['label'], 'group' => $group['label']];
            }
        }

        return $out;
    }
}
