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

namespace Vtinnovations\Draggo\Docs;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Vtinnovations\Draggo\Control\StyleSchema;
use Vtinnovations\Draggo\Editor\DcaFieldReader;
use Vtinnovations\Draggo\Editor\ElementRegistry;
use Vtinnovations\Draggo\Editor\FieldRegistry;

/**
 * Builds the Draggo documentation set: an element reference auto-generated from
 * the live code (ElementRegistry + field specs + StyleSchema + language labels —
 * so it never drifts from what the editor actually offers) plus hand-written
 * how-to guides. The same sections drive the in-editor docs panel AND ground the
 * help chatbot (DocChatService), so the bot can only answer from real docs.
 */
final class DocGenerator
{
    /** @var list<array{id:string,title:string,cat:string,keywords:string,body:string}>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ElementRegistry $elements,
        private readonly DcaFieldReader $dcaFields,
    ) {
    }

    /**
     * All documentation sections (guides first, then the element reference).
     *
     * @return list<array{id:string,title:string,cat:string,keywords:string,body:string}>
     */
    public function sections(string $lang = 'de'): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $this->framework->initialize();
        try {
            System::loadLanguageFile('default', $lang);
            System::loadLanguageFile('tl_content', $lang);
        } catch (\Throwable) {
            // No booted framework (tests) — labels fall back to keys.
        }

        return $this->cache = array_merge($this->guides($lang), $this->elementReference($lang));
    }

    /** Hand-written how-to guides (the conceptual layer the code can't derive). */
    private function guides(string $lang): array
    {
        // Bodies use NOWDOC so any quotes/markup are safe and newlines are literal.
        $g = [];
        $g[] = ['id' => 'start', 'cat' => 'Guide', 'title' => 'Erste Schritte',
            'keywords' => 'start einstieg editor öffnen seite bearbeiten container element hinzufügen drag drop', 'body' => <<<'TXT'
Draggo ist der visuelle Page-Builder für Contao 5. Im Frontend erscheint für eingeloggte Backend-Nutzer oben rechts der Draggo-Button — er öffnet den Editor für die aktuelle Seite.

Aufbau einer Seite:
1. "+ Container am Ende" legt einen Abschnitt (= Contao-Artikel) an.
2. Über "Strukturen" eine Reihe/Spalten-Aufteilung in den Container ziehen.
3. Eine Spalte anklicken (wird markiert), dann links ein Element aus der Palette anklicken oder hineinziehen.
4. Element bearbeiten: Stift-Icon → Tab "Inhalt" und Tab "Stil" setzen, Speichern.

Die Vorschau im Editor entspricht 1:1 dem Frontend (auch responsive).
TXT];
        $g[] = ['id' => 'grid', 'cat' => 'Guide', 'title' => 'Strukturen & Grid (Reihen/Spalten)',
            'keywords' => 'grid struktur reihe spalte spalten layout container breite boxed vollbreite preset 4-4-4 6-6 responsive tablet mobil', 'body' => <<<'TXT'
Layout entsteht über "Strukturen": eine Reihe enthält Spalten nach einem Preset (z. B. 6-6, 4-4-4, 3-3-3-3) oder eine eigene Aufteilung.

Responsive: pro Reihe lässt sich für Tablet und Mobil eine eigene Spalten-Aufteilung wählen. Leer = erbt vom Desktop; Mobil ohne Wert = Spalten stapeln (1-spaltig).

Container-Breite: "Vollbreite" oder "Boxed (zentriert)". Reihe und Spalte haben jeweils eigene Stil-Optionen (Hintergrund, Abstände, Ausrichtung) — auch getrennt für Tablet/Mobil.
TXT];
        $g[] = ['id' => 'responsive', 'cat' => 'Guide', 'title' => 'Responsive bearbeiten',
            'keywords' => 'responsive tablet mobil desktop breakpoint viewport gerät vorschau verbergen sichtbarkeit', 'body' => <<<'TXT'
Oben rechts (und über die Geräte-Buttons) zwischen Desktop / Tablet / Mobil umschalten. Der Canvas zeigt die jeweilige Breite; die Auswahl bleibt nach einem Refresh erhalten.

Im Stil-Tab gelten leere Tablet/Mobil-Felder als "erbt vom Desktop". Über "Sichtbarkeit" lässt sich ein Element pro Gerät aus-/einblenden.

Breakpoints: Tablet ≤ 991px, Mobil ≤ 767px.
TXT];
        $g[] = ['id' => 'style', 'cat' => 'Guide', 'title' => 'Stil-Tab (Farben, Typografie, Abstände)',
            'keywords' => 'stil style farbe primärfarbe token typografie schrift abstand padding margin rahmen schatten hintergrund verlauf position', 'body' => <<<'TXT'
Jedes Element hat einen Stil-Tab mit universellen Gruppen: Hintergrund (Farbe/Verlauf/Bild), Typografie (Schriftart, Größe, Gewicht, Farbe), Layout & Abstände (Innen-/Außenabstand, Ausrichtung), Größe & Rahmen, Effekte (Scroll-Animation, Sticky) und Sichtbarkeit.

Farben: eigene Hex-Werte oder globale Design-Werte (Tokens, z. B. Primärfarbe) aus dem Dropdown — änderst du einen Token unter "Globals", ändert er sich überall.

Bei Elementen mit mehreren Texten (z. B. Titel + Text) gibt es separate Typografie-Gruppen pro Textteil.
TXT];
        $g[] = ['id' => 'scrolly', 'cat' => 'Guide', 'title' => 'Scrollytelling-Effekte',
            'keywords' => 'scrollytelling scroll effekt animation pin sticky stack horizontal parallax zoom timeline reveal tilt before after curtain text-highlight svg progress', 'body' => <<<'TXT'
Draggo bietet WOW-Scroll-Elemente: Stack-Reveal (überlappende Karten), Horizontal-Pin (seitwärts), Sticky-Split, Text-Highlight, Scroll-Zoom, Parallax, Timeline, Before/After, Reveal-Grid, SVG-Path-Draw, Scroll-Progress, Curtain, Text-Mask, Tilt-3D.

Wichtig: Die Effekte animieren im echten Frontend beim Scrollen. Im Editor-Canvas werden sie bewusst statisch (Endzustand) gezeigt, weil Pin/Scroll den echten Seiten-Scroll braucht.

Pin-Effekte (Horizontal-Pin, Curtain, Stack, Sticky-Split) bleiben am Element kleben, während die Animation mit dem Scrollen läuft. Sie wirken am stärksten als volle Container-Breite. Bei prefers-reduced-motion zeigt der Browser automatisch den ruhigen Endzustand.
TXT];
        $g[] = ['id' => 'ai', 'cat' => 'Guide', 'title' => 'KI: Elemente & Seiten generieren',
            'keywords' => 'ki ai chatbot generieren element seite brief api key modell anthropic openai', 'body' => <<<'TXT'
Im "KI"-Tab beschreibst du ein gewünschtes Element (z. B. "Logo-Carousel") und die KI schlägt einen kompletten, editierbaren Vorschlag vor. Im Seiten-Modus erzeugt "Ganze Seite aus Brief" eine komplette Landingpage.

Voraussetzung: ein KI-API-Schlüssel unter Backend → Draggo → Einstellungen (BYOK — dein eigener Anbieter-Key). Ohne Key bleibt die KI aus.

Generierte Element-Typen erscheinen als eigene Elemente in der Palette und werden sicher gerendert (kein ausführbarer PHP-Code).
TXT];
        $g[] = ['id' => 'images', 'cat' => 'Guide', 'title' => 'Bilder hochladen',
            'keywords' => 'bild bilder upload hochladen datei mehrere multi galerie ordner public sichtbar 404', 'body' => <<<'TXT'
In jedem Bild-/Datei-Feld öffnet "Datei wählen" den Picker. Dort lassen sich mehrere Dateien auf einmal hochladen (Fortschritt pro Datei) oder per Drag & Drop in die Liste ziehen.

Hochgeladene Dateien werden automatisch im public-Ordner verfügbar gemacht, damit sie im Frontend erscheinen. Falls Vorschauen fehlen: auf dem Server einmal `vendor/bin/contao-console contao:symlinks` ausführen.
TXT];
        $g[] = ['id' => 'publish', 'cat' => 'Guide', 'title' => 'Speichern & Veröffentlichen',
            'keywords' => 'speichern veröffentlichen publish live online cache leeren refresh migrate symlinks', 'body' => <<<'TXT'
Änderungen werden pro Element/Container gespeichert. Im Frontend ggf. mit Strg+F5 (Hard-Reload) neu laden, um aktuelle Styles/Skripte zu sehen.

Auf Produktion nach einem Update: `vendor/bin/contao-console contao:migrate` (Schema/Konsolidierung) und `contao:symlinks` (Datei-/Asset-Links) ausführen.
TXT];

        return $g;
    }

    /** Auto-generated per-element reference (kept in sync with the real code). */
    private function elementReference(string $lang): array
    {
        $cte = $GLOBALS['TL_LANG']['CTE'] ?? [];
        $out = [];
        foreach ($this->elements->palette() as $group => $items) {
            foreach ($items as $item) {
                $type = $item['type'];
                if (!str_starts_with($type, 'draggo_')) {
                    continue; // document Draggo's own elements only
                }
                $label = $item['label'];
                $desc = \is_array($cte[$type] ?? null) ? (string) ($cte[$type][1] ?? '') : '';

                $fields = $this->fieldLabels($type);
                $styleGroups = $this->styleGroupTitles($type);

                $body = ($desc !== '' ? $desc . "\n\n" : '');
                if ($fields !== []) {
                    $body .= 'Inhalts-Felder: ' . implode(', ', $fields) . ".\n";
                }
                if ($styleGroups !== []) {
                    $body .= 'Stil-Optionen: ' . implode(', ', $styleGroups) . '.';
                }
                if (trim($body) === '') {
                    $body = $label . ' — Draggo-Element.';
                }

                $out[] = [
                    'id'       => 'el-' . $type,
                    'cat'      => 'Element · ' . $group,
                    'title'    => $label,
                    'keywords' => $type . ' ' . mb_strtolower($label . ' ' . $group . ' ' . implode(' ', $fields)),
                    'body'     => trim($body),
                ];
            }
        }

        return $out;
    }

    /** Content-field labels for a type (curated FieldRegistry, else live DCA). */
    private function fieldLabels(string $type): array
    {
        $labels = [];
        $names = FieldRegistry::fieldsFor($type);
        if ($names !== []) {
            foreach ($names as $name) {
                $l = FieldRegistry::LABEL[$name] ?? $name;
                $labels[] = $this->cleanLabel($l);
            }

            return array_values(array_unique(array_filter($labels)));
        }
        foreach ($this->dcaFields->specs($type) as $spec) {
            $labels[] = $this->cleanLabel($spec['label'] ?? $spec['name']);
        }

        return array_values(array_unique(array_filter($labels)));
    }

    /** Style-tab group titles offered for a type. */
    private function styleGroupTitles(string $type): array
    {
        $titles = [];
        foreach (StyleSchema::for($type) as $group) {
            $t = (string) ($group['title'] ?? '');
            if ($t !== '') {
                $titles[] = $t;
            }
        }

        return array_values(array_unique($titles));
    }

    private function cleanLabel(mixed $l): string
    {
        if (\is_array($l)) {
            $l = $l[0] ?? '';
        }

        return trim((string) $l);
    }
}
