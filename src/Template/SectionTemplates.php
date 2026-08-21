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

namespace Vtinnovations\Draggo\Template;

/**
 * Pre-configured section templates ("Vorlagen") — ready-made grid + content
 * trees the user drops in with one click (Elementor "Blocks" equivalent).
 *
 * Each template is a TRUSTED, server-defined list of portable tl_content field
 * sets, inserted verbatim via ContentSynchronizer::insertTree(). The editor
 * only ever sends a template KEY; the actual element data never comes from the
 * client, so there is no injection surface. Images stay empty placeholders the
 * user fills afterward (we can't ship binary file UUIDs).
 */
final class SectionTemplates
{
    /**
     * Catalogue for the palette UI: key + title + category + icon + hint. NO
     * element data here (that lives in items()).
     *
     * @return list<array{key:string,title:string,category:string,icon:string,desc:string}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::DEFS as $key => $d) {
            $out[] = [
                'key'      => $key,
                'title'    => $d['title'],
                'category' => $d['category'],
                'icon'     => $d['icon'],
                'desc'     => $d['desc'],
            ];
        }

        return $out;
    }

    public static function exists(string $key): bool
    {
        return isset(self::DEFS[$key]);
    }

    /** Display title → used as the new container's (tl_article) name. */
    public static function title(string $key): string
    {
        return self::DEFS[$key]['title'] ?? 'Container';
    }

    /**
     * Container-level layout (tl_article.draggo_layout) for a template — the
     * background/overlay/padding that belongs to the whole container. [] = none.
     *
     * @return array<string,mixed>
     */
    public static function articleLayout(string $key): array
    {
        return match ($key) {
            'hero' => [
                'width'      => 'full',
                'bg'         => '#1f2937',
                'color'      => '#ffffff',
                'align'      => 'center',
                'minHeight'  => 480,
                'paddingBox' => ['top' => 96, 'right' => 24, 'bottom' => 96, 'left' => 24, 'unit' => 'px'],
                'ovType'     => 'color',
                'ovColor'    => '#000000',
                'ovOpacity'  => 0.4,
            ],
            'cta' => [
                'width'      => 'full',
                'bg'         => '#0a66ff',
                'color'      => '#ffffff',
                'align'      => 'center',
                'paddingBox' => ['top' => 56, 'right' => 24, 'bottom' => 56, 'left' => 24, 'unit' => 'px'],
            ],
            'newsletter' => [
                'width'      => 'full',
                'bg'         => '#f1f5f9',
                'align'      => 'center',
                'paddingBox' => ['top' => 64, 'right' => 24, 'bottom' => 64, 'left' => 24, 'unit' => 'px'],
            ],
            'text_image', 'image_text', 'features3', 'team', 'faq', 'testimonials', 'pricing3', 'contact', 'logos', 'stats', 'gallery_grid', 'feature_img', 'steps' => [
                'paddingBox' => ['top' => 48, 'right' => 0, 'bottom' => 48, 'left' => 0, 'unit' => 'px'],
            ],
            default => [],
        };
    }

    /**
     * The portable field-set tree for a template key. Built fresh each call.
     * $c = optional AI/user-supplied content overlay (see contentSchema()); when
     * a slot is empty the template's own placeholder is kept, so an empty $c
     * reproduces the original ready-made template verbatim.
     *
     * @param array<string,mixed> $c
     * @return list<array<string,mixed>>
     */
    public static function items(string $key, array $c = []): array
    {
        return match ($key) {
            'hero'        => self::hero($c),
            'text_image'  => self::textImage(true, $c),
            'image_text'  => self::textImage(false, $c),
            'features3'   => self::features3($c),
            'cta'         => self::cta($c),
            'team'        => self::team($c),
            'faq'         => self::faq($c),
            'testimonials' => self::testimonials($c),
            'pricing3'    => self::pricing3($c),
            'contact'     => self::contact($c),
            'logos'       => self::logos(),
            'stats'       => self::stats($c),
            'newsletter'  => self::newsletter($c),
            'gallery_grid' => self::galleryGrid(),
            'feature_img' => self::featureImg($c),
            'steps'       => self::steps($c),
            default       => [],
        };
    }

    /**
     * Per-template CONTENT SCHEMA the AI fills with real, on-topic copy. Slot
     * names are stable; `items` (when present) is a repeating list. Image-only
     * templates (logos, gallery_grid) take no copy. Drives the AI tool prompt in
     * AgentService::composePage(). Kept terse — it goes into the prompt verbatim.
     *
     * @return array<string,string>  templateKey => human description of its slots
     */
    public static function contentSchema(): array
    {
        return [
            'hero'        => 'headline, lead (1 sentence), button (label), button_url, image_query (English photo search term / image prompt for the background)',
            'text_image'  => 'headline, text (1-2 sentences), image_query (English photo search term)',
            'image_text'  => 'headline, text (1-2 sentences), image_query (English photo search term)',
            'features3'   => 'items: 3× {title, text} (one feature each)',
            'cta'         => 'title, text, button (label), button_url',
            'team'        => 'items: 3× {name, role}',
            'faq'         => 'heading, items: 3-6× {q, a}',
            'testimonials' => 'items: 3× {text (quote), author}',
            'pricing3'    => 'items: 3× {title, price (e.g. "29 €"), period (e.g. "/Monat"), features (array of strings), button}',
            'contact'     => 'headline, text, items: array of contact lines (phone/email/address)',
            'stats'       => 'items: 4× {end (number), suffix (e.g. "+","%")}',
            'newsletter'  => 'headline, text, button (label), button_url',
            'feature_img' => 'headline, text, items: array of benefit lines, image_query (English photo search term)',
            'steps'       => 'heading, items: 3-5× {title, text}',
        ];
    }

    /**
     * Trim a scalar slot; fall back to the template's placeholder when empty.
     * AI copy is inserted RAW into tl_content columns (insertTree does no
     * per-field sanitising), so strip any tags from plain-text slots — neutralises
     * an injected <script> in a title/label/name before it ever reaches a column.
     */
    private static function slot(array $c, string $key, string $fallback): string
    {
        $v = trim(strip_tags((string) ($c[$key] ?? '')));

        return $v !== '' ? $v : $fallback;
    }

    /** A URL slot: only safe schemes (http(s)/relative/#/mailto/tel), else fallback. */
    private static function urlSlot(array $c, string $key, string $fallback): string
    {
        $v = trim((string) ($c[$key] ?? ''));
        if ($v === '') {
            return $fallback;
        }

        return preg_match('~^(https?://|/|#|mailto:|tel:)~i', $v) === 1 ? $v : $fallback;
    }

    /** AI copy → safe paragraph HTML for `text` elements (escaped, never raw). */
    private static function para(array $c, string $key, string $fallbackHtml): string
    {
        $v = trim((string) ($c[$key] ?? ''));
        if ($v === '') {
            return $fallbackHtml;
        }

        return '<p>' . htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }

    /** A repeating-item list from the overlay (or [] when absent). @return list<array<string,mixed>> */
    private static function rows(array $c): array
    {
        return (isset($c['items']) && \is_array($c['items'])) ? array_values($c['items']) : [];
    }

    /** Catalogue metadata (no element payload). */
    private const DEFS = [
        'hero'       => ['title' => 'Hero-Container', 'category' => 'Held', 'icon' => 'fas fa-star', 'desc' => 'Vollbreiter Container mit dunklem Hintergrund + Overlay, großer Überschrift, Text und Button.'],
        'text_image' => ['title' => 'Text-Bild-Container (Bild links)', 'category' => 'Text & Bild', 'icon' => 'fas fa-image', 'desc' => 'Container mit 2 Spalten: Bild links, Überschrift + Text rechts.'],
        'image_text' => ['title' => 'Text-Bild-Container (Bild rechts)', 'category' => 'Text & Bild', 'icon' => 'fas fa-image', 'desc' => 'Container mit 2 Spalten: Überschrift + Text links, Bild rechts.'],
        'features3'  => ['title' => 'Feature-Container (3 Boxen)', 'category' => 'Features', 'icon' => 'fas fa-th-large', 'desc' => 'Container mit drei gleich breiten Icon-Boxen nebeneinander.'],
        'cta'        => ['title' => 'Call-to-Action-Container', 'category' => 'Call-to-Action', 'icon' => 'fas fa-bullhorn', 'desc' => 'Farbiger Vollbreite-Container mit Titel, Text und Button.'],
        'team'       => ['title' => 'Team-Container (3 Personen)', 'category' => 'Team', 'icon' => 'fas fa-users', 'desc' => 'Drei Spalten mit Foto, Name und Rolle.'],
        'faq'        => ['title' => 'FAQ-Container (Akkordeon)', 'category' => 'FAQ', 'icon' => 'fas fa-question', 'desc' => 'Überschrift + aufklappbares Frage-Antwort-Akkordeon.'],
        'testimonials' => ['title' => 'Bewertungen-Container (3 Zitate)', 'category' => 'Bewertungen', 'icon' => 'fas fa-quote-right', 'desc' => 'Drei Kunden-Zitate nebeneinander.'],
        'pricing3'   => ['title' => 'Preis-Container (3 Tarife)', 'category' => 'Preise', 'icon' => 'fas fa-money-bill-wave', 'desc' => 'Drei Preistabellen, mittlerer Tarif hervorgehoben.'],
        'contact'    => ['title' => 'Kontakt-Container (Split)', 'category' => 'Kontakt', 'icon' => 'fas fa-envelope', 'desc' => 'Zwei Spalten: Text links, Kontaktdaten-Liste rechts.'],
        'logos'      => ['title' => 'Logo-Leiste', 'category' => 'Logos', 'icon' => 'fas fa-tag', 'desc' => 'Galerie-Leiste für Partner-/Kundenlogos (Graustufen, 5 pro Reihe).'],
        'stats'      => ['title' => 'Zahlen-Container (4 Counter)', 'category' => 'Zahlen', 'icon' => 'fas fa-chart-line', 'desc' => 'Vier animierte Zähler nebeneinander (Statistik-Band).'],
        'newsletter' => ['title' => 'Newsletter-Container', 'category' => 'Call-to-Action', 'icon' => 'fas fa-paper-plane', 'desc' => 'Heller zentrierter Container: Titel, Text und Abonnieren-Button.'],
        'gallery_grid' => ['title' => 'Galerie-Grid-Container', 'category' => 'Galerie', 'icon' => 'fas fa-images', 'desc' => 'Bilderraster (3 Spalten) mit Lightbox und Zoom-Hover.'],
        'feature_img' => ['title' => 'Feature-Container (Bild + Liste)', 'category' => 'Text & Bild', 'icon' => 'fas fa-list', 'desc' => 'Zwei Spalten: Bild links, Überschrift + Vorteils-Liste rechts.'],
        'steps'      => ['title' => 'Ablauf-Container (Schritte)', 'category' => 'Ablauf', 'icon' => 'fas fa-shoe-prints', 'desc' => 'Überschrift + nummerierte Schritt-für-Schritt-Liste.'],
    ];

    // ── Templates ────────────────────────────────────────────────────

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function hero(array $c): array
    {
        // Background/overlay/padding live on the CONTAINER (articleLayout).
        return [
            self::rowStart('1', 'container-fluid', ['row' => ['align' => 'center'], 'col' => []]),
            self::headline(self::slot($c, 'headline', 'Deine große Überschrift'), 'h1', ['fontSize' => 48, 'color' => '#ffffff', 'align' => 'center']),
            self::text(self::para($c, 'lead', '<p>Ein kurzer, einladender Satz, der erklärt worum es geht.</p>'), ['color' => '#ffffff', 'align' => 'center', 'fontSize' => 18]),
            self::button(self::slot($c, 'button', 'Jetzt starten'), self::urlSlot($c, 'button_url', '#'), ['align' => 'center']),
            self::rowStop(),
        ];
    }

    /**
     * Two-column text/image split. $imageLeft=true → image left, text right.
     *
     * @return list<array<string,mixed>>
     */
    private static function textImage(bool $imageLeft, array $c = []): array
    {
        // Padding lives on the CONTAINER (articleLayout); row just centres cols.
        $rowLayout = ['row' => [], 'col' => ['alignY' => 'center']];
        $textCol = ['alignY' => 'center'];

        $image = self::image($c);
        $textBlock = [
            self::headline(self::slot($c, 'headline', 'Überschrift'), 'h2'),
            self::text(self::para($c, 'text', '<p>Beschreibender Text neben dem Bild. Hier kannst du dein Angebot, deine Geschichte oder dein Produkt vorstellen.</p>')),
        ];

        if ($imageLeft) {
            // col0 = image (on row-start), col1 = text.
            return array_merge(
                [self::rowStart('6-6', 'container', $rowLayout), $image, self::col($textCol)],
                $textBlock,
                [self::rowStop()],
            );
        }

        // col0 = text (on row-start), col1 = image.
        return array_merge(
            [self::rowStart('6-6', 'container', ['row' => $rowLayout['row'], 'col' => $textCol])],
            $textBlock,
            [self::col($textCol), $image, self::rowStop()],
        );
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function features3(array $c): array
    {
        // Padding lives on the CONTAINER (articleLayout).
        $it = self::rows($c);
        $def = [['fas fa-bolt', 'Schnell'], ['fas fa-shield-alt', 'Sicher'], ['fas fa-heart', 'Beliebt']];
        $box = static function (int $i) use ($it, $def): array {
            $r = \is_array($it[$i] ?? null) ? $it[$i] : [];
            $icon = trim(strip_tags((string) ($r['icon'] ?? ''))) ?: $def[$i][0];
            $title = trim(strip_tags((string) ($r['title'] ?? ''))) ?: $def[$i][1];
            $text = trim(strip_tags((string) ($r['text'] ?? ''))) ?: 'Kurzer Vorteilstext.';

            return self::iconBox($icon, $title, $text);
        };

        return [
            self::rowStart('4-4-4', 'container', ['row' => [], 'col' => []]),
            $box(0), self::col([]), $box(1), self::col([]), $box(2),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function cta(array $c): array
    {
        // Background/padding live on the CONTAINER (articleLayout).
        return [
            self::rowStart('1', 'container-fluid', ['row' => ['align' => 'center'], 'col' => []]),
            self::el('draggo_cta', [
                'draggo_cta_title' => self::slot($c, 'title', 'Bereit loszulegen?'),
                'draggo_cta_text'  => self::slot($c, 'text', 'Starte noch heute — es dauert nur eine Minute.'),
                'draggo_cta_btn'   => self::slot($c, 'button', 'Jetzt starten'),
                'draggo_cta_url'   => self::urlSlot($c, 'button_url', '#'),
            ]),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function team(array $c): array
    {
        $member = static fn (string $name, string $role): array => self::el('draggo_imagebox', [
            'draggo_ib_src'   => '',
            'draggo_ib_title' => $name,
            'draggo_ib_text'  => $role,
            'draggo_ib_pos'   => 'top',
        ], ['align' => 'center']);
        $it = self::rows($c);
        $def = [['Vorname Name', 'Geschäftsführung'], ['Vorname Name', 'Entwicklung'], ['Vorname Name', 'Design']];
        $m = static function (int $i) use ($member, $it, $def): array {
            $r = \is_array($it[$i] ?? null) ? $it[$i] : [];

            return $member(trim(strip_tags((string) ($r['name'] ?? ''))) ?: $def[$i][0], trim(strip_tags((string) ($r['role'] ?? ''))) ?: $def[$i][1]);
        };

        return [
            self::rowStart('4-4-4', 'container', ['row' => [], 'col' => []]),
            $m(0), self::col([]), $m(1), self::col([]), $m(2),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function faq(array $c): array
    {
        $rows = [];
        foreach (self::rows($c) as $r) {
            if (!\is_array($r)) {
                continue;
            }
            $q = trim(strip_tags((string) ($r['q'] ?? '')));
            $a = trim((string) ($r['a'] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $rows[] = [$q ?: 'Frage', '<p>' . htmlspecialchars($a, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'];
        }
        if ($rows === []) {
            $rows = [
                ['Wie funktioniert das?', '<p>Antwort auf die erste Frage.</p>'],
                ['Was kostet es?', '<p>Antwort auf die zweite Frage.</p>'],
                ['Wie kann ich kündigen?', '<p>Antwort auf die dritte Frage.</p>'],
            ];
        }

        return [
            self::rowStart('1', 'container', ['row' => [], 'col' => []]),
            self::headline(self::slot($c, 'heading', 'Häufige Fragen'), 'h2', ['align' => 'center']),
            self::el('draggo_accordion', ['draggo_acc_multi' => '', 'draggo_items' => self::pairs($rows)]),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function testimonials(array $c): array
    {
        $quote = static fn (string $text, string $author): array => self::el('draggo_quote', [
            'draggo_quote_text'   => $text,
            'draggo_quote_author' => $author,
        ]);
        $it = self::rows($c);
        $def = [
            ['Ein großartiges Produkt — hat unseren Workflow enorm verbessert.', 'Kunde A, Firma'],
            ['Schnell, zuverlässig und super Support.', 'Kunde B, Firma'],
            ['Würde ich jederzeit weiterempfehlen.', 'Kunde C, Firma'],
        ];
        $q = static function (int $i) use ($quote, $it, $def): array {
            $r = \is_array($it[$i] ?? null) ? $it[$i] : [];

            return $quote(trim(strip_tags((string) ($r['text'] ?? ''))) ?: $def[$i][0], trim(strip_tags((string) ($r['author'] ?? ''))) ?: $def[$i][1]);
        };

        return [
            self::rowStart('4-4-4', 'container', ['row' => [], 'col' => []]),
            $q(0), self::col([]), $q(1), self::col([]), $q(2),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function pricing3(array $c): array
    {
        $plan = static fn (string $title, string $price, string $period, array $features, string $btn, bool $featured): array => self::el('draggo_pricetable', [
            'draggo_prt_title'    => $title,
            'draggo_prt_price'    => $price,
            'draggo_prt_period'   => $period,
            'draggo_prt_features' => self::lines($features),
            'draggo_prt_btn'      => $btn,
            'draggo_prt_url'      => '#',
            'draggo_prt_featured' => $featured ? '1' : '',
        ]);
        $it = self::rows($c);
        $def = [
            ['Basis', '9 €', '/Monat', ['1 Projekt', '5 GB Speicher', 'E-Mail-Support']],
            ['Pro', '29 €', '/Monat', ['10 Projekte', '100 GB Speicher', 'Priorisierter Support']],
            ['Business', '79 €', '/Monat', ['Unbegrenzte Projekte', '1 TB Speicher', 'Telefon-Support']],
        ];
        $p = static function (int $i) use ($plan, $it, $def): array {
            $r = \is_array($it[$i] ?? null) ? $it[$i] : [];
            $feats = (isset($r['features']) && \is_array($r['features']))
                ? array_values(array_filter(array_map(static fn ($x): string => \is_string($x) ? trim(strip_tags($x)) : '', $r['features']), static fn (string $x): bool => $x !== ''))
                : [];
            if ($feats === []) {
                $feats = $def[$i][3];
            }

            return $plan(
                trim(strip_tags((string) ($r['title'] ?? ''))) ?: $def[$i][0],
                trim(strip_tags((string) ($r['price'] ?? ''))) ?: $def[$i][1],
                trim(strip_tags((string) ($r['period'] ?? ''))) ?: $def[$i][2],
                $feats,
                trim(strip_tags((string) ($r['button'] ?? ''))) ?: 'Auswählen',
                $i === 1,
            );
        };

        return [
            self::rowStart('4-4-4', 'container', ['row' => [], 'col' => []]),
            $p(0), self::col([]), $p(1), self::col([]), $p(2),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function contact(array $c): array
    {
        $lines = array_values(array_filter(
            array_map(static fn ($x): string => \is_string($x) ? trim(strip_tags($x)) : '', self::rows($c)),
            static fn (string $x): bool => $x !== '',
        ));
        if ($lines === []) {
            $lines = ['+49 123 456789', 'info@example.com', 'Musterstraße 1, 12345 Stadt'];
        }

        return array_merge(
            [self::rowStart('8-4', 'container', ['row' => [], 'col' => ['alignY' => 'center']])],
            [
                self::headline(self::slot($c, 'headline', 'Kontakt aufnehmen'), 'h2'),
                self::text(self::para($c, 'text', '<p>Schreib uns — wir melden uns schnellstmöglich zurück.</p>')),
            ],
            [self::col(['alignY' => 'center'])],
            [self::el('draggo_iconlist', [
                'draggo_il_icon'  => 'fas fa-circle',
                'draggo_il_items' => self::lines($lines),
            ])],
            [self::rowStop()],
        );
    }

    /** @return list<array<string,mixed>> */
    private static function logos(): array
    {
        return [
            self::rowStart('1', 'container', ['row' => [], 'col' => []]),
            self::el('draggo_gallery', [
                'draggo_ga_images' => '',
                'draggo_ga_layout' => 'grid',
                'draggo_ga_cols'   => '5',
                'draggo_ga_gap'    => '24',
                'draggo_ga_hover'  => 'grayscale',
                'draggo_ga_link'   => 'none',
            ]),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function stats(array $c): array
    {
        $counter = static fn (string $end, string $suffix): array => self::el('draggo_counter', [
            'draggo_counter_start'    => '0',
            'draggo_counter_end'      => $end,
            'draggo_counter_suffix'   => $suffix,
            'draggo_counter_duration' => '2000',
        ], ['align' => 'center']);
        $it = self::rows($c);
        $def = [['1200', '+'], ['98', '%'], ['24', '/7'], ['15', ' Jahre']];
        $s = static function (int $i) use ($counter, $it, $def): array {
            $r = \is_array($it[$i] ?? null) ? $it[$i] : [];
            $end = preg_replace('/[^0-9.]/', '', (string) ($r['end'] ?? '')) ?: $def[$i][0];
            $suf = trim(strip_tags((string) ($r['suffix'] ?? ''))) ?: $def[$i][1];

            return $counter($end, $suf);
        };

        return [
            self::rowStart('3-3-3-3', 'container', ['row' => [], 'col' => []]),
            $s(0), self::col([]), $s(1), self::col([]), $s(2), self::col([]), $s(3),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function newsletter(array $c): array
    {
        // Light background lives on the CONTAINER (articleLayout).
        return [
            self::rowStart('1', 'container', ['row' => ['align' => 'center'], 'col' => []]),
            self::headline(self::slot($c, 'headline', 'Bleib auf dem Laufenden'), 'h2', ['align' => 'center']),
            self::text(self::para($c, 'text', '<p>Melde dich für unseren Newsletter an — kein Spam, jederzeit kündbar.</p>'), ['align' => 'center']),
            self::button(self::slot($c, 'button', 'Abonnieren'), self::urlSlot($c, 'button_url', '#'), ['align' => 'center']),
            self::rowStop(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function galleryGrid(): array
    {
        return [
            self::rowStart('1', 'container', ['row' => [], 'col' => []]),
            self::el('draggo_gallery', [
                'draggo_ga_images' => '',
                'draggo_ga_layout' => 'grid',
                'draggo_ga_cols'   => '3',
                'draggo_ga_gap'    => '16',
                'draggo_ga_hover'  => 'zoom',
                'draggo_ga_link'   => 'lightbox',
            ]),
            self::rowStop(),
        ];
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function featureImg(array $c): array
    {
        $col = ['alignY' => 'center'];
        $benefits = array_values(array_filter(
            array_map(static fn ($x): string => \is_string($x) ? trim(strip_tags($x)) : '', self::rows($c)),
            static fn (string $x): bool => $x !== '',
        ));
        if ($benefits === []) {
            $benefits = ['Erster Vorteil', 'Zweiter Vorteil', 'Dritter Vorteil'];
        }

        return array_merge(
            [self::rowStart('6-6', 'container', ['row' => [], 'col' => $col]), self::image($c), self::col($col)],
            [
                self::headline(self::slot($c, 'headline', 'Darum lohnt es sich'), 'h2'),
                self::text(self::para($c, 'text', '<p>Kurze Einleitung zu den Vorteilen.</p>')),
                self::el('draggo_iconlist', [
                    'draggo_il_icon'  => 'fas fa-check',
                    'draggo_il_items' => self::lines($benefits),
                ]),
            ],
            [self::rowStop()],
        );
    }

    /** @param array<string,mixed> $c @return list<array<string,mixed>> */
    private static function steps(array $c): array
    {
        $rows = [];
        foreach (self::rows($c) as $r) {
            if (!\is_array($r)) {
                continue;
            }
            $t = trim(strip_tags((string) ($r['title'] ?? '')));
            $x = trim(strip_tags((string) ($r['text'] ?? '')));
            if ($t === '' && $x === '') {
                continue;
            }
            $rows[] = [$t ?: 'Schritt', $x];
        }
        if ($rows === []) {
            $rows = [
                ['Registrieren', 'Lege in wenigen Sekunden dein Konto an.'],
                ['Einrichten', 'Konfiguriere alles nach deinen Wünschen.'],
                ['Loslegen', 'Starte sofort durch.'],
            ];
        }

        return [
            self::rowStart('1', 'container', ['row' => [], 'col' => []]),
            self::headline(self::slot($c, 'heading', 'So funktioniert es'), 'h2', ['align' => 'center']),
            self::el('draggo_steps', ['draggo_stp_items' => self::pairs($rows)]),
            self::rowStop(),
        ];
    }

    /** Serialize [[title, html], …] → editor pair/accitem format. */
    private static function pairs(array $rows): string
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['key' => (string) ($r[0] ?? ''), 'value' => (string) ($r[1] ?? '')];
        }

        return serialize($out);
    }

    /** Serialize a list of strings → Contao "lines" field. */
    private static function lines(array $items): string
    {
        return serialize(array_values(array_map('strval', $items)));
    }

    // ── Field-set builders ───────────────────────────────────────────

    /**
     * @param array<string,mixed> $layout namespaced {row:{},col:{}}
     * @return array<string,mixed>
     */
    private static function rowStart(string $preset, string $container, array $layout): array
    {
        return [
            'type'                => 'draggo_row_start',
            'draggo_grid_preset' => $preset,
            'draggo_container'   => $container,
            'draggo_layout'      => json_encode($layout),
        ];
    }

    /**
     * @param array<string,mixed> $layout flat column layout
     * @return array<string,mixed>
     */
    private static function col(array $layout): array
    {
        $row = ['type' => 'draggo_col'];
        if ($layout !== []) {
            $row['draggo_layout'] = json_encode($layout);
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private static function rowStop(): array
    {
        return ['type' => 'draggo_row_stop'];
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private static function headline(string $value, string $unit = 'h2', array $layout = []): array
    {
        $row = [
            'type'     => 'headline',
            'headline' => serialize(['unit' => $unit, 'value' => $value]),
        ];
        if ($layout !== []) {
            $row['draggo_layout'] = json_encode($layout);
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private static function text(string $html, array $layout = []): array
    {
        $row = ['type' => 'text', 'text' => $html];
        if ($layout !== []) {
            $row['draggo_layout'] = json_encode($layout);
        }

        return $row;
    }

    /**
     * Image element. Empty by default (the user picks the file). When the AI
     * image provider resolved a photo for this section, the controller passes the
     * BINARY singleSRC UUID in $c['_imageBin'] (+ optional alt) so it is prefilled.
     *
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private static function image(array $c = []): array
    {
        $row = ['type' => 'image'];
        if (!empty($c['_imageBin'])) {
            $row['singleSRC'] = $c['_imageBin'];
            $alt = trim(strip_tags((string) ($c['_imageAlt'] ?? '')));
            if ($alt !== '') {
                $row['alt'] = $alt;
            }
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private static function button(string $text, string $url, array $layout = []): array
    {
        return self::el('draggo_button', ['draggo_btn_text' => $text, 'draggo_btn_url' => $url], $layout);
    }

    /** @return array<string,mixed> */
    private static function iconBox(string $icon, string $title, string $text): array
    {
        return self::el('draggo_iconbox', [
            'draggo_ib_icon'  => $icon,
            'draggo_ib_title' => $title,
            'draggo_ib_text'  => $text,
            'draggo_ib_pos'   => 'top',
        ], ['align' => 'center']);
    }

    /**
     * Generic element field set.
     *
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private static function el(string $type, array $fields, array $layout = []): array
    {
        $row = array_merge(['type' => $type], $fields);
        if ($layout !== []) {
            $row['draggo_layout'] = json_encode($layout);
        }

        return $row;
    }
}
