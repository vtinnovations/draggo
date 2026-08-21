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
 * Declarative STYLE-tab schema for a content element (Roadmap Phase A). Replaces
 * the hand-built JS `buildStyleSection`: the editor now renders these groups
 * generically. Typography is offered only for text-like elements; box/effect/
 * visibility groups are universal. Keys match the `draggo_layout` vocabulary
 * understood by LayoutStyleCompiler + InputSanitizer (unchanged).
 */
final class StyleSchema
{
    private const TEXT_TYPES = ['text', 'headline', 'html', 'code', 'markdown', 'unfiltered_html', 'hyperlink', 'draggo_quote', 'draggo_counter', 'draggo_pagetitle', 'draggo_breadcrumb', 'draggo_sitemap', 'draggo_readerfield', 'draggo_countdown', 'draggo_toc', 'draggo_pricetable', 'draggo_pricelist', 'draggo_steps', 'draggo_anim'];

    private const SYSTEM_FONTS = [
        ['', 'Standard'], ['Arial', 'Arial'], ['Helvetica', 'Helvetica'], ['Georgia', 'Georgia'],
        ['Times New Roman', 'Times'], ['Courier New', 'Courier'], ['Verdana', 'Verdana'],
        ['Tahoma', 'Tahoma'], ['Trebuchet MS', 'Trebuchet'], ['system-ui', 'System'],
    ];

    /**
     * System fonts + all curated Google Fonts (grouped via the label prefix).
     *
     * @return list<array{0:string,1:string}>
     */
    private static function fonts(): array
    {
        $opts = self::SYSTEM_FONTS;
        foreach (\Vtinnovations\Draggo\Font\GoogleFonts::FAMILIES as $f) {
            $opts[] = [$f, 'G · ' . $f];
        }

        return $opts;
    }
    private const WEIGHTS = [
        ['', 'Standard'], ['300', 'Light'], ['400', 'Normal'], ['500', 'Medium'],
        ['600', 'Semibold'], ['700', 'Bold'], ['800', 'Extrabold'], ['900', 'Black'],
    ];
    private const SPACING = [['', '—'], ['none', '0'], ['xs', 'XS'], ['s', 'S'], ['m', 'M'], ['l', 'L'], ['xl', 'XL']];

    /**
     * Full per-part typography control set for one text part (title / text /
     * rotating word …). Key suffixes match LayoutStyleCompiler::partType() and
     * the InputSanitizer part loop. Used so multi-text elements can style each
     * text independently.
     *
     * @return list<array<string,mixed>>
     */
    private static function typoControls(string $p, bool $withColor = true, bool $withBg = true): array
    {
        $controls = [];
        if ($withColor) {
            $controls[] = Control::color($p . 'Color', 'Textfarbe');
        }
        if ($withBg) {
            $controls[] = Control::color($p . 'Bg', 'Hintergrund');
        }

        return array_merge($controls, [
            Control::select($p . 'FontFamily', 'Schriftart', self::fonts()),
            Control::number($p . 'Size', 'Schriftgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 400]),
            Control::select($p . 'Weight', 'Gewicht', self::WEIGHTS),
            Control::select($p . 'Style', 'Stil', [['', '–'], ['normal', 'Normal'], ['italic', 'Kursiv']]),
            Control::select($p . 'Transform', 'Transform', [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']]),
            Control::select($p . 'Decoration', 'Dekoration', [['', '–'], ['none', 'Keine'], ['underline', 'Unterstrichen'], ['line-through', 'Durchgestrichen']]),
            Control::number($p . 'LineHeight', 'Zeilenhöhe', ['placeholder' => '1.5', 'min' => 0, 'max' => 5, 'step' => 0.1]),
            Control::number($p . 'Spacing', 'Buchstabenabstand (px)', ['placeholder' => 'px', 'min' => -100, 'max' => 100]),
            Control::select($p . 'Align', 'Ausrichtung', [['', '–'], ['left', 'Links'], ['center', 'Mitte'], ['right', 'Rechts'], ['justify', 'Blocksatz']]),
            Control::number($p . 'Mb', 'Abstand unten (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 300]),
        ]);
    }

    /** A collapsible Style group of per-part typography controls. */
    private static function partGroup(string $title, string $prefix, bool $open = false, bool $withColor = true, bool $withBg = true): array
    {
        return Control::group($title, self::typoControls($prefix, $withColor, $withBg), $open);
    }

    /**
     * Elements that render a rich-text body (RTE / multi-line text the editor
     * may fill with `<a>` links). They get the shared "Links" style group even
     * though they aren't TEXT_TYPES — the compiler emits `$sel a{…}` link CSS for
     * any element, so the keys already work; only the UI group was missing.
     */
    private const RICH_BODY_TYPES = [
        'draggo_cta', 'draggo_iconbox', 'draggo_imagebox', 'draggo_alert',
        'draggo_accordion', 'draggo_tabs', 'draggo_loop',
    ];

    /**
     * Shared "Links" style group (link colour, hover, underline behaviour,
     * weight, symbol before/after). Keys map to LayoutStyleCompiler::scoped()
     * link block + CssGenerator linkColor + InputSanitizer link whitelist.
     */
    private static function linksGroup(bool $open = false): array
    {
        return Control::group('Links', [
            Control::color('linkColor', 'Linkfarbe'),
            Control::color('linkHoverColor', 'Linkfarbe (Hover)'),
            Control::select('linkDeco', 'Unterstreichung', [['', '–'], ['none', 'Keine'], ['underline', 'Immer'], ['hover', 'Nur bei Hover']]),
            Control::select('linkWeight', 'Schriftgewicht', self::WEIGHTS),
            Control::text('linkBefore', 'Symbol davor', ['placeholder' => 'z. B. → oder 🔗']),
            Control::text('linkAfter', 'Symbol danach', ['placeholder' => 'z. B. ↗']),
        ], $open);
    }

    /**
     * @return list<array{title:string,open:bool,controls:list<array<string,mixed>>}>
     */
    public static function for(string $type): array
    {
        $isText = \in_array($type, self::TEXT_TYPES, true);
        $isImage = \in_array($type, ['image', 'gallery'], true);

        $groups = [];

        // Navigation element: dedicated styling for links/hover/active/indicator.
        if ($type === 'draggo_nav') {
            $groups[] = Control::group('Navigation', [
                Control::color('navBg', 'Leisten-Hintergrund'),
                Control::color('navColor', 'Linkfarbe'),
                Control::color('navHover', 'Hover-Farbe'),
                Control::color('navHoverBg', 'Hover-Hintergrund'),
                Control::color('navActive', 'Aktiv-Farbe'),
                Control::select('navAlign', 'Ausrichtung', [['', '–'], ['flex-start', 'Links'], ['center', 'Mitte'], ['flex-end', 'Rechts'], ['space-between', 'Verteilt']]),
                Control::select('navFontFamily', 'Schriftart', self::fonts()),
                Control::number('navSize', 'Schriftgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 80]),
                Control::select('navWeight', 'Gewicht', self::WEIGHTS),
                Control::select('navTransform', 'Transform', [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']]),
                Control::number('navLetterSpacing', 'Buchstabenabstand (px)', ['placeholder' => 'px']),
                Control::number('navGap', 'Abstand zw. Punkten (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 100]),
                Control::number('navPadV', 'Innenabstand vertikal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('navPadH', 'Innenabstand horizontal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('navRadius', 'Link-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
                Control::select('navSeparator', 'Trenner zw. Punkten', [['', 'Keiner'], ['line', 'Linie'], ['dot', 'Punkt']]),
                Control::color('navSeparatorColor', 'Trenner-Farbe', ['condition' => ['field' => 'navSeparator', 'op' => 'neq', 'value' => '']]),
                Control::select('navIndicator', 'Indikator', [['', 'Keiner'], ['underline', 'Unterstrich'], ['box', 'Hintergrund'], ['dot', 'Punkt']]),
                Control::color('navIndicatorColor', 'Indikator-Farbe'),
                Control::number('navIndicatorSize', 'Indikator-Dicke (px)', ['placeholder' => 'px', 'min' => 1, 'max' => 12, 'condition' => ['field' => 'navIndicator', 'op' => 'neq', 'value' => '']]),
            ], true);

            // Level-2 (dropdown / submenu) styled independently.
            $groups[] = Control::group('Untermenü (Ebene 2)', [
                Control::color('subBg', 'Hintergrund (Panel)'),
                Control::color('subColor', 'Linkfarbe'),
                Control::color('subHover', 'Hover-Farbe'),
                Control::color('subHoverBg', 'Hover-Hintergrund'),
                Control::select('subFontFamily', 'Schriftart', self::fonts()),
                Control::number('subSize', 'Schriftgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 60]),
                Control::select('subWeight', 'Gewicht', self::WEIGHTS),
                Control::select('subTransform', 'Transform', [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']]),
                Control::select('subAlign', 'Text-Ausrichtung', [['', '–'], ['left', 'Links'], ['center', 'Mitte'], ['right', 'Rechts']]),
                Control::number('subPadV', 'Innenabstand vertikal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
                Control::number('subPadH', 'Innenabstand horizontal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
                Control::color('subBorderColor', 'Rahmenfarbe'),
                Control::number('subRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::select('subShadow', 'Schatten', [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß']]),
                Control::number('subMinWidth', 'Mindestbreite (px)', ['placeholder' => 'px', 'min' => 40, 'max' => 800]),
                Control::color('subDivider', 'Trennlinie zw. Einträgen'),
            ]);

            // Responsive: auto-collapse to a sliding hamburger drawer.
            $hbCond = ['field' => 'navMobileMode', 'op' => 'eq', 'value' => 'drawer'];
            $groups[] = Control::group('Responsive (Hamburger)', [
                Control::select('navMobileMode', 'Mobil-Modus', [['', 'Aus'], ['drawer', 'Hamburger + Slide-in']]),
                Control::number('navBreakpoint', 'Breakpoint (px, darunter Hamburger)', ['placeholder' => '992', 'min' => 320, 'max' => 1600, 'condition' => $hbCond]),
                Control::select('navDrawerSide', 'Einschub-Seite', [['right', 'Rechts'], ['left', 'Links']], ['condition' => $hbCond]),
                Control::number('navDrawerWidth', 'Menü-Breite (px)', ['placeholder' => '280', 'min' => 180, 'max' => 600, 'condition' => $hbCond]),
                Control::color('navDrawerBg', 'Menü-Hintergrund', ['condition' => $hbCond]),
                Control::select('navDrawerAlign', 'Text-Ausrichtung', [['', '–'], ['left', 'Links'], ['center', 'Mitte'], ['right', 'Rechts']], ['condition' => $hbCond]),
                Control::number('navDrawerGap', 'Abstand zw. Punkten (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60, 'condition' => $hbCond]),
                Control::color('navDrawerDivider', 'Trennlinie zw. Punkten', ['condition' => $hbCond]),
                Control::color('navOverlayColor', 'Hintergrund-Abdunkelung', ['condition' => $hbCond]),
                Control::icon('navHamburgerIcon', 'Hamburger-Icon', ['condition' => $hbCond]),
                Control::color('navHamburgerColor', 'Icon-Farbe', ['condition' => $hbCond]),
                Control::number('navHamburgerSize', 'Icon-Größe (px)', ['placeholder' => '28', 'min' => 14, 'max' => 64, 'condition' => $hbCond]),
            ]);
        }

        // ── Per-widget Style groups (Phase 3.5, Elementor-style) ────────
        if ($type === 'draggo_icon') {
            $groups[] = Control::group('Icon', [
                Control::number('fontSize', 'Größe (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_logo') {
            $groups[] = Control::group('Logo', [
                Control::number('logoWidth', 'Breite (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 1200]),
                Control::number('logoMaxWidth', 'Max-Breite (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 1200]),
                Control::number('logoHeight', 'Höhe (px, 0 = auto)', ['placeholder' => 'auto', 'min' => 0, 'max' => 800]),
                Control::select('logoFit', 'Skalierung', [['', '–'], ['contain', 'Einpassen (contain)'], ['cover', 'Füllen (cover)']]),
                Control::number('logoRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'image') {
            $groups[] = Control::group('Bild', [
                Control::number('imgWidth', 'Breite (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 2000]),
                Control::number('imgMaxWidth', 'Max-Breite (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 2000]),
                Control::number('imgHeight', 'Höhe (px, 0 = auto)', ['placeholder' => 'auto', 'min' => 0, 'max' => 1600]),
                Control::select('imgFit', 'Skalierung', [['', '–'], ['contain', 'Einpassen (contain)'], ['cover', 'Füllen (cover)']]),
                Control::number('imgRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_button') {
            $groups[] = Control::group('Button', [
                Control::select('fontFamily', 'Schriftart', self::fonts()),
                Control::number('fontSize', 'Schriftgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 80]),
                Control::select('fontWeight', 'Gewicht', self::WEIGHTS),
                Control::number('letterSpacing', 'Buchstabenabstand (px)', ['placeholder' => 'px']),
                Control::switcher('btnFull', 'Volle Breite'),
                Control::number('btnRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
                Control::number('btnPadV', 'Innenabstand vertikal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 100]),
                Control::number('btnPadH', 'Innenabstand horizontal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 200]),
                Control::number('btnIconGap', 'Icon-Abstand (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
                Control::color('btnHoverBg', 'Hover-Hintergrund'),
                Control::color('btnHoverColor', 'Hover-Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_iconbox' || $type === 'draggo_imagebox') {
            $isIcon = $type === 'draggo_iconbox';
            $boxControls = [
                Control::number('boxMediaW', $isIcon ? 'Icon-Breite (px)' : 'Bild-Breite (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 600]),
                Control::number('boxGap', 'Abstand Medien ↔ Text (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 200]),
            ];
            // Icon-only controls (circle frame / icon colour) make no sense for
            // the image box — they'd render dead UI there.
            if ($isIcon) {
                $boxControls[] = Control::color('boxIconColor', 'Icon-Farbe');
                $boxControls[] = Control::color('boxIconBg', 'Icon-Hintergrund (Kreis)');
                $boxControls[] = Control::number('boxIconPad', 'Icon-Innenabstand (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 100, 'condition' => ['field' => 'boxIconBg', 'op' => 'neq', 'value' => '']]);
            } else {
                // Image box: let the picture crop to a fixed height (uniform rows).
                $boxControls[] = Control::number('boxImgHeight', 'Bild-Höhe (px, 0 = auto)', ['placeholder' => 'auto', 'min' => 0, 'max' => 1600]);
                $boxControls[] = Control::select('boxImgFit', 'Skalierung', [['', '–'], ['contain', 'Einpassen (contain)'], ['cover', 'Füllen (cover)']]);
            }
            $boxControls[] = Control::number('boxMediaRadius', 'Medien-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]);
            $boxControls[] = Control::switcher('boxHover', 'Hover-Effekt (anheben)');
            $groups[] = Control::group($isIcon ? 'Icon-Box' : 'Bild-Box', $boxControls, true);
            $groups[] = self::partGroup('Titel-Stil', 'boxTitle');
            $groups[] = self::partGroup('Text-Stil', 'boxText');
        }

        if ($type === 'draggo_accordion') {
            $groups[] = Control::group('Akkordeon', [
                Control::color('accHeadBg', 'Kopf-Hintergrund'),
                Control::color('accHeadColor', 'Kopf-Textfarbe'),
                Control::color('accActiveBg', 'Aktiv-Hintergrund'),
                Control::color('accActiveColor', 'Aktiv-Textfarbe'),
                Control::color('accContentBg', 'Inhalt-Hintergrund'),
                Control::color('accBorderColor', 'Rahmenfarbe'),
                Control::number('accRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('accGap', 'Abstand zw. Einträgen (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
            ], true);
            // Typography per part (colours/bg live in the structural group above).
            $groups[] = self::partGroup('Kopf-Typografie', 'accHd', false, false, false);
            $groups[] = self::partGroup('Inhalt-Typografie', 'accBd', false, true, false);
        }

        if ($type === 'draggo_tabs') {
            $groups[] = Control::group('Tabs', [
                Control::select('tabLayout', 'Anordnung', [['', 'Horizontal (oben)'], ['vertical', 'Vertikal (links)']]),
                Control::color('tabColor', 'Reiter-Farbe'),
                Control::color('tabActiveColor', 'Aktiver Reiter'),
                Control::color('tabNavBorder', 'Trennlinie'),
                Control::color('tabContentBg', 'Inhalt-Hintergrund'),
                Control::select('tabAlign', 'Reiter-Ausrichtung', [['', '–'], ['flex-start', 'Links'], ['center', 'Mitte'], ['flex-end', 'Rechts'], ['space-between', 'Verteilt']]),
            ], true);
            // Typography per part (reiter colours live in the structural group).
            $groups[] = self::partGroup('Reiter-Typografie', 'tabLbl', false, false, false);
            $groups[] = self::partGroup('Inhalt-Typografie', 'tabPanel', false, true, false);
        }

        if ($type === 'draggo_carousel') {
            $groups[] = Control::group('Karussell', [
                Control::color('carArrowColor', 'Pfeil-Farbe'),
                Control::color('carArrowBg', 'Pfeil-Hintergrund'),
                Control::color('carDotColor', 'Punkt-Farbe'),
                Control::color('carDotActive', 'Aktiver Punkt'),
                Control::number('carRadius', 'Bild-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('carGap', 'Abstand zw. Folien (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
                Control::number('carImgHeight', 'Feste Bildhöhe (px, 0 = auto)', ['placeholder' => 'auto', 'min' => 0, 'max' => 1200]),
            ], true);
        }

        if ($type === 'draggo_gallery') {
            $groups[] = Control::group('Galerie', [
                Control::number('gaImgRadius', 'Bild-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 200]),
                Control::number('gaImgHeight', 'Feste Bildhöhe (px, 0 = auto)', ['placeholder' => 'auto', 'min' => 0, 'max' => 1200]),
                Control::color('gaOverlayColor', 'Hover-Overlay-Farbe'),
            ], true);
        }

        if ($type === 'draggo_social') {
            $groups[] = Control::group('Social-Icons', [
                Control::color('socialColor', 'Icon-Farbe'),
                Control::color('socialBg', 'Hintergrund'),
                Control::color('socialHoverBg', 'Hover-Hintergrund'),
                Control::number('socialSize', 'Icon-Größe (px)', ['placeholder' => 'px', 'min' => 16, 'max' => 96]),
                Control::number('socialRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_flipbox') {
            $groups[] = Control::group('Flip-Box', [
                Control::color('flipFrontBg', 'Vorderseite Hintergrund'),
                Control::color('flipFrontColor', 'Vorderseite Text (Standard)'),
                Control::color('flipBackBg', 'Rückseite Hintergrund'),
                Control::color('flipBackColor', 'Rückseite Text (Standard)'),
            ], true);
            $groups[] = self::partGroup('Vorderseite – Titel', 'flipFTitle');
            $groups[] = self::partGroup('Vorderseite – Text', 'flipFText');
            $groups[] = self::partGroup('Rückseite – Titel', 'flipBTitle');
            $groups[] = self::partGroup('Rückseite – Text', 'flipBText');
        }

        if ($type === 'draggo_cta') {
            $groups[] = Control::group('Call-to-Action', [
                Control::select('ctaLayout', 'Layout', [['', 'Gestapelt'], ['horizontal', 'Horizontal (Button rechts)']]),
                Control::color('ctaBtnBg', 'Button-Hintergrund'),
                Control::color('ctaBtnColor', 'Button-Textfarbe'),
                Control::color('ctaBtnHoverBg', 'Button-Hover-Hintergrund'),
                Control::color('ctaBtnHoverColor', 'Button-Hover-Textfarbe'),
                Control::number('ctaBtnRadius', 'Button-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
            $groups[] = self::partGroup('Titel-Stil', 'ctaTitle');
            $groups[] = self::partGroup('Text-Stil', 'ctaText');
        }

        if ($type === 'draggo_map') {
            $groups[] = Control::group('Karte', [
                Control::switcher('mapGray', 'Graustufen (Farbe bei Hover)'),
            ], true);
        }

        if ($type === 'draggo_alert') {
            $groups[] = Control::group('Hinweis', [
                Control::switcher('alertIcon', 'Typ-Icon anzeigen'),
                Control::number('alertRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 200]),
                // Override the semantic border colour (otherwise it stays the
                // variant default even when you set a custom background).
                Control::color('alertBorder', 'Rahmenfarbe'),
            ], true);
            $groups[] = self::partGroup('Titel-Stil', 'alertTitle');
            $groups[] = self::partGroup('Text-Stil', 'alertText');
        }

        if ($type === 'draggo_spacer') {
            $groups[] = Control::group('Abstandhalter (responsive)', [
                Control::number('spacerTablet', 'Höhe Tablet (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 1000]),
                Control::number('spacerMobile', 'Höhe Mobil (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 1000]),
            ], true);
        }

        if ($type === 'draggo_anim') {
            $groups[] = self::partGroup('Text davor', 'anBefore', true);
            $groups[] = self::partGroup('Rotierende Wörter', 'anWord');
            $groups[] = self::partGroup('Text danach', 'anAfter');
        }

        if ($type === 'draggo_hotspot') {
            $groups[] = Control::group('Hotspots', [
                Control::color('hsDotColor', 'Punkt-Farbe'),
                Control::color('hsDotText', 'Punkt-Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_stack') {
            $groups[] = Control::group('Stack-Reveal', [
                Control::color('ssBg', 'Karten-Hintergrund'),
                Control::color('ssFg', 'Karten-Textfarbe'),
                Control::number('ssRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
            ], true);
            $groups[] = self::partGroup('Titel-Typografie', 'ssTitle', false, true, false);
            $groups[] = self::partGroup('Text-Typografie', 'ssText', false, true, false);
        }

        if ($type === 'draggo_scroll_textmask') {
            // The text is clipped to a background image (background-clip:text), so
            // colour is the mask — expose only the typography that shapes it.
            $groups[] = self::partGroup('Text-Typografie', 'tmText', true, false, false);
        }

        if ($type === 'draggo_scroll_hpin') {
            $groups[] = Control::group('Horizontal-Pin', [
                Control::color('hpBg', 'Panel-Hintergrund'),
                Control::color('hpFg', 'Panel-Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_text') {
            $groups[] = Control::group('Text-Highlight', [
                Control::color('thDim', 'Farbe ungelesen'),
                Control::color('thLit', 'Farbe hervorgehoben'),
            ], true);
        }

        if ($type === 'draggo_scroll_timeline') {
            $groups[] = Control::group('Timeline', [
                Control::color('tlAccent', 'Akzentfarbe (Linie/Punkt)'),
                Control::color('tlTrack', 'Spurfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_parallax') {
            $groups[] = Control::group('Parallax', [
                Control::color('pxFg', 'Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_zoom') {
            // The universal radius hits the .draggo-zm root, not the scaled image
            // frame — give the frame its own corner-radius control.
            $groups[] = Control::group('Zoom', [
                Control::number('zmRadius', 'Bild-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
            ], true);
        }

        if ($type === 'draggo_scroll_revealgrid') {
            $groups[] = Control::group('Reveal-Grid', [
                Control::color('rgBg', 'Karten-Hintergrund'),
                Control::color('rgFg', 'Karten-Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_svgdraw') {
            $groups[] = Control::group('Linie', [
                Control::color('sdColor', 'Linienfarbe'),
                Control::number('sdWidth', 'Liniendicke', ['placeholder' => '4', 'min' => 1, 'max' => 40]),
            ], true);
        }

        if ($type === 'draggo_scroll_progress') {
            $groups[] = Control::group('Fortschritt', [
                Control::color('prColor', 'Balkenfarbe'),
                Control::color('prTrack', 'Spurfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_curtain') {
            $groups[] = Control::group('Curtain', [
                Control::color('ctBg', 'Panel-Hintergrund (ohne Bild)'),
                Control::color('ctFg', 'Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_scroll_tilt') {
            $groups[] = Control::group('Tilt-Cards', [
                Control::color('tiBg', 'Karten-Hintergrund'),
                Control::color('tiFg', 'Karten-Textfarbe'),
                // Options for cards that have a background photo.
                Control::select('tiOverlay', 'Bild-Abdunklung', [['', 'Standard'], ['0', 'Keine'], ['0.25', 'Leicht'], ['0.45', 'Mittel'], ['0.65', 'Stark'], ['0.85', 'Sehr stark']]),
                Control::select('tiPos', 'Bild-Ausschnitt', [['', 'Mitte'], ['top', 'Oben'], ['bottom', 'Unten'], ['left', 'Links'], ['right', 'Rechts']]),
            ], true);
        }

        if ($type === 'draggo_form') {
            $groups[] = Control::group('Formular', [
                Control::color('formLabelColor', 'Beschriftungs-Farbe'),
                Control::color('formInputBg', 'Feld-Hintergrund'),
                Control::color('formInputColor', 'Feld-Textfarbe'),
                Control::color('formInputBorder', 'Feld-Rahmenfarbe'),
                Control::color('formAccent', 'Fokus-/Akzentfarbe'),
                Control::number('formInputRadius', 'Feld-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('formGap', 'Abstand zwischen Feldern (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::color('formBtnBg', 'Button-Hintergrund'),
                Control::color('formBtnColor', 'Button-Textfarbe'),
                Control::color('formBtnHoverBg', 'Button-Hover-Hintergrund'),
                Control::number('formBtnRadius', 'Button-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::switcher('formBtnFull', 'Button volle Breite'),
            ], true);
        }

        if ($type === 'draggo_codeblock') {
            $groups[] = Control::group('Code', [
                Control::color('codeBg', 'Hintergrund'),
                Control::color('codeColor', 'Textfarbe'),
            ], true);
        }

        if ($type === 'draggo_share') {
            $groups[] = Control::group('Buttons', [
                Control::color('shareColor', 'Icon-Farbe'),
                Control::color('shareBg', 'Hintergrund'),
                Control::color('shareHoverBg', 'Hover-Hintergrund'),
                Control::number('shareSize', 'Größe (px)', ['placeholder' => 'px', 'min' => 16, 'max' => 96]),
                Control::number('shareRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_countdown') {
            $groups[] = Control::group('Countdown', [
                Control::color('cdNumColor', 'Zahlenfarbe'),
                Control::number('cdNumSize', 'Zahlengröße (px)', ['placeholder' => 'px', 'min' => 12, 'max' => 200]),
                Control::color('cdLblColor', 'Label-Farbe'),
                Control::number('cdGap', 'Abstand (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 100]),
            ], true);
        }

        if ($type === 'draggo_opennow') {
            $groups[] = Control::group('Öffnungsstatus', [
                Control::color('onOpenColor', 'Farbe „geöffnet“'),
                Control::color('onClosedColor', 'Farbe „geschlossen“'),
                Control::color('onBg', 'Hintergrund (Badge)'),
                Control::number('onSize', 'Schriftgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 60]),
                Control::number('onDotSize', 'Punkt-Größe (px)', ['placeholder' => 'px', 'min' => 4, 'max' => 32]),
                Control::number('onPadV', 'Innenabstand vertikal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
                Control::number('onPadH', 'Innenabstand horizontal (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('onRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_iconlist') {
            $groups[] = Control::group('Icon-Liste', [
                Control::color('ilIconColor', 'Icon-Farbe'),
                Control::number('ilIconSize', 'Icon-Größe (px)', ['placeholder' => 'px', 'min' => 10, 'max' => 80]),
                Control::number('ilGap', 'Zeilenabstand (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 60]),
            ], true);
            $groups[] = self::partGroup('Text-Typografie', 'ilTxt', false, true, false);
        }

        if ($type === 'draggo_steps') {
            $groups[] = Control::group('Schritte', [
                Control::color('stepNumBg', 'Nummer-Hintergrund'),
                Control::color('stepNumColor', 'Nummer-Farbe'),
                Control::color('stepLineColor', 'Verbindungslinie'),
            ], true);
            $groups[] = self::partGroup('Titel-Typografie', 'stepTtl', false, true, false);
            $groups[] = self::partGroup('Text-Typografie', 'stepTxt', false, true, false);
        }

        if ($type === 'draggo_pricetable') {
            $groups[] = Control::group('Preistabelle', [
                Control::color('prtTitleColor', 'Titelfarbe'),
                Control::color('prtPriceColor', 'Preisfarbe'),
                Control::color('prtFeatureBorderColor', 'Trennlinie'),
                Control::color('prtBtnBg', 'Button-Hintergrund'),
                Control::color('prtBtnColor', 'Button-Textfarbe'),
                Control::color('prtBtnHoverBg', 'Button-Hover-Hintergrund'),
                Control::number('prtBtnRadius', 'Button-Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 400]),
            ], true);
        }

        if ($type === 'draggo_pricelist') {
            $groups[] = Control::group('Preisliste', [
                Control::color('prlNameColor', 'Namensfarbe'),
                Control::color('prlPriceColor', 'Preisfarbe'),
                Control::color('prlDotColor', 'Punktlinie'),
            ], true);
        }

        if ($type === 'draggo_quote') {
            $groups[] = Control::group('Zitat', [
                Control::color('quoteBarColor', 'Balkenfarbe'),
                Control::number('quoteBarWidth', 'Balkenbreite (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 20]),
                // Italic toggle removed — redundant with the "Quote text" part
                // group's Font-style control (Rudra bug #34). Legacy quoteItalic
                // values are still honoured by LayoutStyleCompiler.
            ], true);
            $groups[] = self::partGroup('Zitat-Text', 'quoteText');
            $groups[] = self::partGroup('Autor', 'quoteAuthor');
        }

        if ($type === 'draggo_breadcrumb') {
            $groups[] = Control::group('Breadcrumb', [
                Control::color('bcActiveColor', 'Aktive Seite'),
                Control::color('bcSepColor', 'Trennzeichen'),
            ], true);
        }

        if ($type === 'draggo_toc') {
            $groups[] = Control::group('Inhaltsverzeichnis', [
                Control::color('tocBarColor', 'Akzentbalken'),
                Control::color('tocTitleColor', 'Titelfarbe'),
            ], true);
        }

        if ($type === 'draggo_progress') {
            $groups[] = Control::group('Fortschritt', [
                Control::color('progressBarColor', 'Balkenfarbe'),
                Control::color('progressTrackColor', 'Spurfarbe'),
                Control::number('progressHeight', 'Höhe (px)', ['placeholder' => 'px', 'min' => 2, 'max' => 60]),
            ], true);
            $groups[] = self::partGroup('Label-Typografie', 'prgLbl', false, true, false);
        }

        if ($type === 'draggo_loop') {
            $groups[] = Control::group('Karten', [
                Control::color('cardBg', 'Karten-Hintergrund'),
                Control::color('cardBorderColor', 'Karten-Rahmen'),
                Control::number('cardRadius', 'Eckenradius (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 80]),
                Control::number('cardPad', 'Innenabstand (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 120]),
                Control::select('cardShadow', 'Schatten', [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß'], ['xl', 'Sehr groß']]),
                Control::number('loopGap', 'Abstand zw. Karten (px)', ['placeholder' => 'px', 'min' => 0, 'max' => 120]),
                Control::color('loopTitleColor', 'Titelfarbe'),
                Control::number('loopTitleSize', 'Titelgröße (px)', ['placeholder' => 'px', 'min' => 8, 'max' => 120]),
                Control::color('loopTeaserColor', 'Teaser-Farbe'),
                Control::number('loopImgHeight', 'Bildhöhe (px)', ['placeholder' => 'px', 'min' => 40, 'max' => 1200]),
                Control::color('moreColor', 'Mehr-Link-Farbe'),
                Control::switcher('loopHover', 'Hover-Effekt (anheben)'),
            ], true);
        }

        // Draggo Landing sections share the same markup classes (.eyebrow,
        // h1/h2/h3, .grad, .lead) across every section → one branch gives
        // per-part colour + typography (responsive via the bp switcher) to all
        // draggo_lp_* elements. Element-specific parts (buttons/pills/cards) are
        // layered on per type elsewhere.
        if (str_starts_with($type, 'draggo_lp_')) {
            $groups[] = self::partGroup('Eyebrow / Label', 'lpEye');
            $groups[] = self::partGroup('Überschrift', 'lpHead');
            $groups[] = self::partGroup('Überschrift – Verlauf-Teil', 'lpGrad', false, true, false);
            $groups[] = self::partGroup('Lead-Text', 'lpLead');
        }

        if ($isText) {
            $groups[] = Control::group('Typografie', [
                Control::select('fontFamily', 'Schriftart', self::fonts()),
                Control::number('fontSize', 'Größe (px)', ['placeholder' => 'px', 'min' => 1, 'max' => 400]),
                Control::select('fontWeight', 'Gewicht', self::WEIGHTS),
                Control::select('textTransform', 'Transform', [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']]),
                Control::select('fontStyle', 'Stil', [['', '–'], ['normal', 'Normal'], ['italic', 'Kursiv']]),
                Control::select('textDecoration', 'Dekoration', [['', '–'], ['none', 'Keine'], ['underline', 'Unterstrichen'], ['line-through', 'Durchgestrichen']]),
                Control::number('lineHeight', 'Zeilenhöhe', ['placeholder' => '1.5', 'step' => 0.1]),
                Control::number('letterSpacing', 'Buchstabenabstand (px)', ['placeholder' => 'px']),
                Control::number('wordSpacing', 'Wortabstand (px)', ['placeholder' => 'px']),
                Control::color('color', 'Textfarbe'),
                Control::select('textShadow', 'Textschatten', [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß']]),
                Control::switcher('textGradient', 'Verlaufstext'),
                Control::color('gradFrom', 'Verlauf von', ['condition' => ['field' => 'textGradient', 'op' => 'truthy']]),
                Control::color('gradTo', 'Verlauf bis', ['condition' => ['field' => 'textGradient', 'op' => 'truthy']]),
            ], true);

            // The native Contao "text" element renders a headline (h1–h6) AND a
            // rich-text body. The "Typografie" group above is the shared base
            // (cascades to both); these two part groups let the title and the
            // body text be styled INDEPENDENTLY on top of it.
            if ($type === 'text') {
                $groups[] = self::partGroup('Titel-Typografie', 'txTtl');
                $groups[] = self::partGroup('Text-Typografie', 'txTxt');
            }

            // Dedicated styling for links inside the text (color, hover,
            // underline behaviour, weight, and a symbol before/after each link).
            $groups[] = self::linksGroup(true);
        }

        // Rich-body non-text elements (cta/iconbox/alert/accordion/tabs/loop …)
        // also render `<a>` links inside their bodies → give them the same
        // "Links" group. (The compiler already styles `$sel a` for any element.)
        if (!$isText && \in_array($type, self::RICH_BODY_TYPES, true)) {
            $groups[] = self::linksGroup();
        }

        // Universal background + base colour for EVERY element (Elementor parity:
        // previously only text types could set a colour and nothing had a bg).
        $bgControls = [Control::color('bg', 'Hintergrundfarbe')];
        if (!$isText) {
            $bgControls[] = Control::color('color', 'Textfarbe');
        }
        $groups[] = Control::group('Hintergrund', $bgControls);

        $groups[] = Control::group('Layout & Abstände', [
            Control::dimensions('paddingBox', 'Innenabstand (oben/rechts/unten/links)'),
            Control::dimensions('marginBox', 'Außenabstand (oben/rechts/unten/links)'),
            Control::choose('align', 'Ausrichtung', [['left', 'tleft'], ['center', 'tcenter'], ['right', 'tright']], ['toggle' => true]),
        ], !$isText, true);

        $groups[] = Control::group('Größe & Rahmen', [
            Control::length('width', 'Breite'),
            Control::length('maxWidth', 'Max-Breite'),
            Control::length('height', 'Höhe'),
            Control::length('minHeight', 'Min-Höhe'),
            Control::length('maxHeight', 'Max-Höhe'),
            Control::number('opacity', 'Deckkraft (0–1)', ['step' => 0.1, 'min' => 0, 'max' => 1, 'placeholder' => '1']),
            Control::select('borderStyle', 'Rahmen-Stil', [['', 'Keiner'], ['solid', 'Solid'], ['dashed', 'Gestrichelt'], ['dotted', 'Gepunktet'], ['double', 'Doppelt']]),
            Control::number('borderWidth', 'Rahmen-Breite (px)', ['placeholder' => 'px']),
            Control::color('borderColor', 'Rahmen-Farbe'),
            Control::number('borderRadius', 'Eckenradius (px)', ['placeholder' => 'px']),
            Control::select('boxShadow', 'Schatten', [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß'], ['xl', 'Sehr groß']]),
        ], false, true);

        $effects = [
            Control::select('animation', 'Scroll-Animation', [['', 'Keine'], ['fade', 'Einblenden'], ['fade-up', 'Hoch'], ['fade-down', 'Runter'], ['zoom', 'Zoom'], ['slide-left', 'Von links'], ['slide-right', 'Von rechts']]),
            Control::number('animDuration', 'Dauer (ms)', ['placeholder' => '600', 'condition' => ['field' => 'animation', 'op' => 'neq', 'value' => '']]),
            Control::number('animDelay', 'Verzögerung (ms)', ['placeholder' => '0', 'condition' => ['field' => 'animation', 'op' => 'neq', 'value' => '']]),
            Control::switcher('sticky', 'Sticky'),
            Control::number('stickyOffset', 'Sticky-Offset (px)', ['placeholder' => '0', 'condition' => ['field' => 'sticky', 'op' => 'truthy']]),
        ];
        if ($isImage) {
            $effects[] = Control::switcher('lightbox', 'Lightbox');
            $effects[] = Control::switcher('hoverZoom', 'Hover-Zoom');
        }
        $groups[] = Control::group('Effekte', $effects, false, true);

        $groups[] = Control::group('Sichtbarkeit', [
            Control::switcher('hideDesktop', 'Auf Desktop verbergen'),
            Control::switcher('hideTablet', 'Auf Tablet verbergen'),
            Control::switcher('hideMobile', 'Auf Mobil verbergen'),
        ], false, true);

        // Universal Advanced tab (reference 2.3). Applies to every element.
        $posSet = ['field' => 'position', 'op' => 'neq', 'value' => ''];
        $groups[] = Control::group('Erweitert', [
            Control::text('cssId', 'Element-ID'),
            Control::text('cssClass', 'CSS-Klassen'),
            Control::number('zIndex', 'z-index'),
            Control::select('position', 'Position', [['', 'Standard'], ['relative', 'Relativ'], ['absolute', 'Absolut'], ['fixed', 'Fixiert'], ['sticky', 'Sticky']]),
            Control::length('posTop', 'Oben', ['condition' => $posSet]),
            Control::length('posRight', 'Rechts', ['condition' => $posSet]),
            Control::length('posBottom', 'Unten', ['condition' => $posSet]),
            Control::length('posLeft', 'Links', ['condition' => $posSet]),
            Control::number('transformRotate', 'Drehen (deg)', ['min' => -360, 'max' => 360]),
            Control::number('transformScale', 'Skalieren', ['step' => 0.1, 'min' => 0, 'max' => 5, 'placeholder' => '1']),
            Control::length('transformTranslateX', 'Verschieben X'),
            Control::length('transformTranslateY', 'Verschieben Y'),
            Control::textarea('customCss', 'Eigenes CSS', ['placeholder' => 'selector { color: red } — oder nur Deklarationen']),
        ], false, true);

        return $groups;
    }
}
