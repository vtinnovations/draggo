/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

/*
 * Draggo editor — Phase 2.3.
 *
 * page mode: edits a tl_page (one section per article/container). unit mode:
 * edits one global unit. Grids are reconstructed recursively (grid-in-grid),
 * elements render as chips inside columns. The whole content order — including
 * nested wrappers — is serialised straight from the DOM, so adds/moves at any
 * depth persist via the reorder endpoint.
 *
 * Mutations carry the Contao REQUEST_TOKEN (X-Contao-Csrf-Token). Contao stays
 * source of truth; we reload after each change.
 */
(function () {
  'use strict';

  var DRAGGO_VERSION = '20260618do';
  var PADDINGS = [['', '—'], ['none', '0'], ['xs', 'XS'], ['s', 'S'], ['m', 'M'], ['l', 'L'], ['xl', 'XL']];
  // Wrapper (grid) types the editor groups into rows/columns. Draggo's own by
  // default; extended at runtime with recognised third-party systems
  // (SubColumns, contao-bootstrap grid, RockSolid) from the palette API.
  var WSTART = ['draggo_row_start'];
  var WSEP = ['draggo_col'];
  var WSTOP = ['draggo_row_stop'];
  function isRowStart(t) { return WSTART.indexOf(t) !== -1; }
  function isRowSep(t) { return WSEP.indexOf(t) !== -1; }
  function isRowStop(t) { return WSTOP.indexOf(t) !== -1; }
  function isWrapper(t) { return isRowStart(t) || isRowSep(t) || isRowStop(t); }
  var WRAPPERS = ['draggo_row_start', 'draggo_col', 'draggo_row_stop']; // legacy alias

  // Element icons as Font Awesome 5 classes (FA Free ships bundled under
  // Resources/public/vendor/fontawesome). Replaces the old per-OS emoji glyphs
  // so every device shows the same clean monochrome icon. iconHtml() renders the
  // <i>; iconFor() returns the class (with prefix fallback).
  var ICONS = {
    text: 'fas fa-paragraph', headline: 'fas fa-heading', html: 'fas fa-code', list: 'fas fa-list-ul', table: 'fas fa-table', code: 'fas fa-code',
    accordionStart: 'fas fa-stream', accordionStop: 'fas fa-stream', sliderStart: 'fas fa-images', sliderStop: 'fas fa-images',
    image: 'fas fa-image', gallery: 'fas fa-images', player: 'fas fa-play', youtube: 'fas fa-play', vimeo: 'fas fa-play', download: 'fas fa-download', downloads: 'fas fa-download',
    hyperlink: 'fas fa-link', toplink: 'fas fa-arrow-up', teaser: 'fas fa-newspaper', module: 'fas fa-puzzle-piece', form: 'fas fa-envelope', markdown: 'fas fa-file-alt', rsce_: 'fas fa-star',
    draggo_accordion: 'fas fa-stream', draggo_alert: 'fas fa-exclamation-triangle', draggo_anim: 'fas fa-magic', draggo_breadcrumb: 'fas fa-shoe-prints',
    draggo_button: 'fas fa-hand-pointer', draggo_carousel: 'fas fa-images', draggo_codeblock: 'fas fa-code', draggo_countdown: 'fas fa-hourglass-half',
    draggo_counter: 'fas fa-stopwatch', draggo_cta: 'fas fa-bullhorn', draggo_divider: 'fas fa-minus', draggo_flipbox: 'fas fa-sync',
    draggo_opennow: 'fas fa-clock', draggo_themetoggle: 'fas fa-adjust',
    draggo_form: 'fas fa-envelope', draggo_gallery: 'fas fa-images', draggo_global: 'fas fa-globe', draggo_hotspot: 'fas fa-map-marker-alt',
    draggo_icon: 'fas fa-star', draggo_iconbox: 'fas fa-icons', draggo_iconlist: 'fas fa-tasks', draggo_imagebox: 'fas fa-image',
    draggo_inserttag: 'fas fa-tag', draggo_logo: 'fas fa-flag', draggo_loop: 'fas fa-redo-alt', draggo_map: 'fas fa-map-marker-alt',
    draggo_nav: 'fas fa-bars', draggo_pagetitle: 'fas fa-heading', draggo_pricelist: 'fas fa-list-ol', draggo_pricetable: 'fas fa-money-bill-wave',
    draggo_progress: 'fas fa-chart-bar', draggo_quote: 'fas fa-quote-right', draggo_readerfield: 'fas fa-file-alt', draggo_search: 'fas fa-search',
    draggo_share: 'fas fa-share-alt', draggo_sitemap: 'fas fa-sitemap', draggo_social: 'fas fa-users', draggo_spacer: 'fas fa-arrows-alt-v',
    draggo_steps: 'fas fa-shoe-prints', draggo_tabs: 'fas fa-folder', draggo_toc: 'fas fa-list', draggo_videoplaylist: 'fas fa-film',
    draggo_block: 'fas fa-puzzle-piece',
    // Scrollytelling
    draggo_scroll_beforeafter: 'fas fa-adjust', draggo_scroll_curtain: 'fas fa-columns', draggo_scroll_hpin: 'fas fa-expand-arrows-alt',
    draggo_scroll_parallax: 'fas fa-layer-group', draggo_scroll_progress: 'fas fa-chart-bar', draggo_scroll_revealgrid: 'fas fa-th',
    draggo_scroll_split: 'fas fa-columns', draggo_scroll_stack: 'fas fa-clone', draggo_scroll_svgdraw: 'fas fa-draw-polygon',
    draggo_scroll_text: 'fas fa-highlighter', draggo_scroll_textmask: 'fas fa-paint-brush', draggo_scroll_tilt: 'fas fa-clone',
    draggo_scroll_timeline: 'fas fa-stream', draggo_scroll_zoom: 'fas fa-search',
    // Landing-page sections
    draggo_lp_ai: 'fas fa-robot', draggo_lp_build: 'fas fa-hammer', draggo_lp_chrome: 'fas fa-desktop', draggo_lp_cta: 'fas fa-bullseye',
    draggo_lp_faq: 'fas fa-question', draggo_lp_features: 'fas fa-th-large', draggo_lp_footer: 'fas fa-sign-out-alt', draggo_lp_hero: 'fas fa-user-ninja',
    draggo_lp_marquee: 'fas fa-stream', draggo_lp_pricing: 'fas fa-tag', draggo_lp_responsive: 'fas fa-mobile-alt',
    draggo_lp_stats: 'fas fa-chart-line', draggo_lp_tech: 'fas fa-cog',
    // Prefix fallbacks (specific → general; exact keys above win first).
    draggo_scroll_: 'fas fa-film', draggo_lp_: 'fas fa-layer-group', draggo_: 'fas fa-cube',
  };
  function iconFor(type) {
    if (ICONS[type]) return ICONS[type];
    var k = Object.keys(ICONS).filter(function (p) { return p.slice(-1) === '_' && type.indexOf(p) === 0; })[0];
    return k ? ICONS[k] : 'fas fa-cube';
  }
  // Render any FA class as an editor icon <i>; faHtml for ad-hoc icons.
  function faHtml(cls) { return '<i class="draggo-ic ' + cls + '" aria-hidden="true"></i>'; }
  function iconHtml(type) { return faHtml(iconFor(type)); }
  // AI block types store an icon string. Render FA classes as-is; anything else
  // (legacy emoji) falls back to a clean cube so the palette stays uniform.
  function blockIcon(ic) { return faHtml(/(^|\s)fa[bsrl]?-|fa-/.test(ic || '') ? ic : 'fas fa-cube'); }

  // ── i18n: German is built-in (source strings); other locales translate via
  // an exact-match dictionary. English is the fallback. The chrome (sidebar +
  // toolbar) is translated by a DOM sweep so we don't touch every call site;
  // the canvas + KI chat/preview (user/AI content) are never translated. ──
  var DRAGGO_LANG = 'de';
  var I18N = {
    en: {
      // Tabs
      'Inhalt': 'Content', 'Layout': 'Layout', 'Globals': 'Globals', 'Element': 'Element', 'KI': 'AI',
      // Sub-tabs / palette
      'Elemente': 'Elements', 'Strukturen': 'Structures', 'Komponenten': 'Components',
      'Element suchen …': 'Search element …', 'Spalten-Layouts': 'Column layouts',
      'KI-Elemente': 'AI elements', 'Draggo': 'Draggo', 'Text': 'Text', 'Medien': 'Media',
      'Dateien': 'Files', 'Links': 'Links', 'Einbindungen': 'Includes',
      'Klick = in aktive Spalte/Container · oder auf eine Spalte ziehen.': 'Click = into active column/container · or drag onto a column.',
      'Klick = fertiger Container · oder in eine Spalte ziehen (nur Inhalt).': 'Click = ready-made container · or drag into a column (content only).',
      // Toolbar / containers
      'Container am Ende': 'Container at end', 'Container darunter einfügen': 'Insert container below',
      'Container löschen': 'Delete container', 'Zum Verschieben ziehen': 'Drag to move',
      'Desktop': 'Desktop', 'Tablet': 'Tablet', 'Mobil': 'Mobile',
      // Inspector group titles
      'Layout & Struktur': 'Layout & structure', 'Hintergrund': 'Background',
      'Abstände & Größe': 'Spacing & size', 'Rahmen & Schatten': 'Border & shadow', 'Erweitert (CSS)': 'Advanced (CSS)',
      'Container-Layout': 'Container layout', 'Reihen-Layout': 'Row layout', 'Spalten-Layout': 'Column layout',
      'Hintergrundfarbe': 'Background colour', 'Hintergrundbild': 'Background image', 'Textfarbe': 'Text colour',
      'Verlauf (Hintergrund)': 'Gradient (background)', 'Hintergrund-Medien': 'Background media',
      'Hintergrund-Overlay': 'Background overlay', 'Wählen': 'Choose', 'Mindesthöhe (px)': 'Min height (px)',
      'Text-Ausrichtung': 'Text alignment', 'Rahmen-Stil': 'Border style', 'Rahmen-Breite (px)': 'Border width (px)',
      'Rahmen-Farbe': 'Border colour', 'Eckenradius (px)': 'Corner radius (px)', 'Schatten': 'Shadow',
      'CSS-Klasse': 'CSS class', 'Element-ID': 'Element ID', 'Inhaltsbreite': 'Content width',
      'Struktur · Desktop': 'Structure · desktop', 'Spalten-Abstand (gap)': 'Column gap',
      // KI generator
      'KI-Element-Generator': 'AI element generator', 'Neues Element': 'New element',
      'Meine KI-Elemente': 'My AI elements', 'Senden': 'Send', 'Übernehmen': 'Apply',
      'Verwerfen': 'Discard', 'Speichert …': 'Saving …', 'Bearbeiten': 'Edit', 'Löschen': 'Delete',
      // ── Style/field labels (StyleSchema + FieldRegistry) — de→en ──────────
      'Typografie': 'Typography', 'Schriftart': 'Font family', 'Schriftgröße (px)': 'Font size (px)',
      'Größe (px)': 'Size (px)', 'Gewicht': 'Weight', 'Schriftgewicht': 'Font weight', 'Stil': 'Style',
      'Transform': 'Transform', 'Dekoration': 'Decoration', 'Zeilenhöhe': 'Line height',
      'Buchstabenabstand (px)': 'Letter spacing (px)', 'Wortabstand (px)': 'Word spacing (px)',
      'Ausrichtung': 'Alignment', 'Text-Ausrichtung': 'Text alignment', 'Textschatten': 'Text shadow',
      'Verlaufstext': 'Gradient text', 'Verlauf von': 'Gradient from', 'Verlauf bis': 'Gradient to',
      'Abstand unten (px)': 'Margin bottom (px)', 'Kursiv': 'Italic',
      // Background / box
      'Innenabstand (oben/rechts/unten/links)': 'Padding (top/right/bottom/left)',
      'Außenabstand (oben/rechts/unten/links)': 'Margin (top/right/bottom/left)',
      'Innenabstand (px)': 'Padding (px)', 'Innenabstand horizontal (px)': 'Padding horizontal (px)',
      'Innenabstand vertikal (px)': 'Padding vertical (px)', 'Layout & Abstände': 'Layout & spacing',
      'Größe & Rahmen': 'Size & border', 'Breite': 'Width', 'Breite (px)': 'Width (px)',
      'Max-Breite': 'Max width', 'Max-Breite (px)': 'Max width (px)', 'Höhe': 'Height',
      'Höhe (px)': 'Height (px)', 'Höhe (px, 0 = auto)': 'Height (px, 0 = auto)', 'Min-Höhe': 'Min height',
      'Mindesthöhe (px)': 'Min height (px)', 'Max-Höhe': 'Max height', 'Deckkraft (0–1)': 'Opacity (0–1)',
      'Rahmen-Stil': 'Border style', 'Rahmen-Breite (px)': 'Border width (px)', 'Rahmen-Farbe': 'Border colour',
      'Rahmenfarbe': 'Border colour', 'Eckenradius (px)': 'Corner radius (px)', 'Schatten': 'Shadow',
      'Effekte': 'Effects', 'Scroll-Animation': 'Scroll animation', 'Dauer (ms)': 'Duration (ms)',
      'Verzögerung (ms)': 'Delay (ms)', 'Sticky': 'Sticky', 'Sticky-Offset (px)': 'Sticky offset (px)',
      'Lightbox': 'Lightbox', 'Hover-Zoom': 'Hover zoom', 'Sichtbarkeit': 'Visibility',
      'Auf Desktop verbergen': 'Hide on desktop', 'Auf Tablet verbergen': 'Hide on tablet',
      'Auf Mobil verbergen': 'Hide on mobile', 'Erweitert': 'Advanced', 'CSS-Klassen': 'CSS classes',
      'Position': 'Position', 'Oben': 'Top', 'Rechts': 'Right', 'Unten': 'Bottom',
      'Drehen (deg)': 'Rotate (deg)', 'Skalieren': 'Scale', 'Skalierung': 'Scaling',
      'Verschieben X': 'Translate X', 'Verschieben Y': 'Translate Y', 'Eigenes CSS': 'Custom CSS',
      // Links
      'Linkfarbe': 'Link colour', 'Linkfarbe (Hover)': 'Link colour (hover)', 'Unterstreichung': 'Underline',
      'Symbol davor': 'Symbol before', 'Symbol danach': 'Symbol after',
      // Per-part typography groups
      'Eyebrow / Label': 'Eyebrow / label', 'Überschrift': 'Headline',
      'Überschrift – Verlauf-Teil': 'Headline – gradient part', 'Lead-Text': 'Lead text',
      'Titel-Stil': 'Title style', 'Text-Stil': 'Text style', 'Titel-Typografie': 'Title typography',
      'Text-Typografie': 'Text typography', 'Kopf-Typografie': 'Header typography',
      'Inhalt-Typografie': 'Content typography', 'Reiter-Typografie': 'Tab typography',
      'Label-Typografie': 'Label typography', 'Autor': 'Author', 'Rotierende Wörter': 'Rotating words',
      'Text davor': 'Text before', 'Text danach': 'Text after',
      // Navigation
      'Navigation': 'Navigation', 'Leisten-Hintergrund': 'Bar background', 'Hover-Farbe': 'Hover colour',
      'Hover-Hintergrund': 'Hover background', 'Aktiv-Farbe': 'Active colour',
      'Abstand zw. Punkten (px)': 'Gap between items (px)', 'Link-Eckenradius (px)': 'Link corner radius (px)',
      'Trenner zw. Punkten': 'Separator between items', 'Trenner-Farbe': 'Separator colour',
      'Indikator': 'Indicator', 'Indikator-Farbe': 'Indicator colour', 'Indikator-Dicke (px)': 'Indicator thickness (px)',
      'Untermenü (Ebene 2)': 'Submenu (level 2)', 'Hintergrund (Panel)': 'Background (panel)',
      'Mindestbreite (px)': 'Min width (px)', 'Trennlinie zw. Einträgen': 'Divider between entries',
      'Responsive (Hamburger)': 'Responsive (hamburger)', 'Mobil-Modus': 'Mobile mode',
      'Breakpoint (px, darunter Hamburger)': 'Breakpoint (px, below = hamburger)', 'Einschub-Seite': 'Slide-in side',
      'Menü-Breite (px)': 'Menu width (px)', 'Menü-Hintergrund': 'Menu background',
      'Trennlinie zw. Punkten': 'Divider between items', 'Hintergrund-Abdunkelung': 'Backdrop dimming',
      'Hamburger-Icon': 'Hamburger icon', 'Icon-Farbe': 'Icon colour', 'Icon-Größe (px)': 'Icon size (px)',
      // Element-specific groups + controls
      'Icon': 'Icon', 'Logo': 'Logo', 'Bild': 'Image', 'Button': 'Button', 'Volle Breite': 'Full width',
      'Button-Eckenradius (px)': 'Button corner radius (px)', 'Icon-Abstand (px)': 'Icon gap (px)',
      'Button-Hintergrund': 'Button background', 'Button-Textfarbe': 'Button text colour',
      'Button-Hover-Hintergrund': 'Button hover background', 'Button-Hover-Textfarbe': 'Button hover text colour',
      'Hover-Textfarbe': 'Hover text colour', 'Icon-Box': 'Icon box', 'Bild-Box': 'Image box',
      'Abstand Medien ↔ Text (px)': 'Media ↔ text gap (px)', 'Icon-Hintergrund (Kreis)': 'Icon background (circle)',
      'Icon-Innenabstand (px)': 'Icon padding (px)', 'Bild-Höhe (px, 0 = auto)': 'Image height (px, 0 = auto)',
      'Medien-Eckenradius (px)': 'Media corner radius (px)', 'Hover-Effekt (anheben)': 'Hover effect (lift)',
      'Akkordeon': 'Accordion', 'Kopf-Hintergrund': 'Header background', 'Kopf-Textfarbe': 'Header text colour',
      'Aktiv-Hintergrund': 'Active background', 'Aktiv-Textfarbe': 'Active text colour',
      'Inhalt-Hintergrund': 'Content background', 'Abstand zw. Einträgen (px)': 'Gap between entries (px)',
      'Tabs': 'Tabs', 'Anordnung': 'Layout', 'Reiter-Farbe': 'Tab colour', 'Aktiver Reiter': 'Active tab',
      'Reiter-Ausrichtung': 'Tab alignment', 'Karussell': 'Carousel', 'Pfeil-Farbe': 'Arrow colour',
      'Pfeil-Hintergrund': 'Arrow background', 'Punkt-Farbe': 'Dot colour', 'Aktiver Punkt': 'Active dot',
      'Bild-Eckenradius (px)': 'Image corner radius (px)', 'Abstand zw. Folien (px)': 'Gap between slides (px)',
      'Feste Bildhöhe (px, 0 = auto)': 'Fixed image height (px, 0 = auto)', 'Galerie': 'Gallery',
      'Bildhöhe (px)': 'Image height (px)', 'Hover-Overlay-Farbe': 'Hover overlay colour',
      'Social-Icons': 'Social icons', 'Hintergrund': 'Background', 'Eckenradius (px)': 'Corner radius (px)',
      'Flip-Box': 'Flip box', 'Vorderseite Hintergrund': 'Front background',
      'Vorderseite Text (Standard)': 'Front text (default)', 'Rückseite Hintergrund': 'Back background',
      'Rückseite Text (Standard)': 'Back text (default)', 'Vorderseite – Titel': 'Front – title',
      'Vorderseite – Text': 'Front – text', 'Rückseite – Titel': 'Back – title', 'Rückseite – Text': 'Back – text',
      'Call-to-Action': 'Call to action', 'Karte': 'Map', 'Graustufen (Farbe bei Hover)': 'Greyscale (colour on hover)',
      'Hinweis': 'Alert', 'Typ-Icon anzeigen': 'Show type icon', 'Abstandhalter (responsive)': 'Spacer (responsive)',
      'Höhe Tablet (px)': 'Height tablet (px)', 'Höhe Mobil (px)': 'Height mobile (px)',
      'Hotspots': 'Hotspots', 'Punkt-Textfarbe': 'Dot text colour', 'Stack-Reveal': 'Stack reveal',
      'Karten-Hintergrund': 'Card background', 'Karten-Textfarbe': 'Card text colour',
      'Horizontal-Pin': 'Horizontal pin', 'Panel-Hintergrund': 'Panel background', 'Panel-Textfarbe': 'Panel text colour',
      'Text-Highlight': 'Text highlight', 'Farbe ungelesen': 'Colour (unread)', 'Farbe hervorgehoben': 'Colour (highlighted)',
      'Timeline': 'Timeline', 'Akzentfarbe (Linie/Punkt)': 'Accent colour (line/dot)', 'Spurfarbe': 'Track colour',
      'Parallax': 'Parallax', 'Zoom': 'Zoom', 'Reveal-Grid': 'Reveal grid', 'Linie': 'Line',
      'Linienfarbe': 'Line colour', 'Liniendicke': 'Line thickness', 'Fortschritt': 'Progress',
      'Balkenfarbe': 'Bar colour', 'Curtain': 'Curtain', 'Panel-Hintergrund (ohne Bild)': 'Panel background (no image)',
      'Tilt-Cards': 'Tilt cards', 'Bild-Abdunklung': 'Image dimming', 'Bild-Ausschnitt': 'Image crop',
      'Formular': 'Form', 'Beschriftungs-Farbe': 'Label colour', 'Feld-Hintergrund': 'Field background',
      'Feld-Textfarbe': 'Field text colour', 'Feld-Rahmenfarbe': 'Field border colour',
      'Fokus-/Akzentfarbe': 'Focus/accent colour', 'Feld-Eckenradius (px)': 'Field corner radius (px)',
      'Abstand zwischen Feldern (px)': 'Gap between fields (px)', 'Button volle Breite': 'Button full width',
      'Code': 'Code', 'Buttons': 'Buttons', 'Countdown': 'Countdown', 'Zahlenfarbe': 'Number colour',
      'Zahlengröße (px)': 'Number size (px)', 'Label-Farbe': 'Label colour', 'Abstand (px)': 'Gap (px)',
      'Öffnungsstatus': 'Open status', 'Farbe „geöffnet“': 'Colour "open"', 'Farbe „geschlossen“': 'Colour "closed"',
      'Hintergrund (Badge)': 'Background (badge)', 'Punkt-Größe (px)': 'Dot size (px)', 'Icon-Liste': 'Icon list',
      'Zeilenabstand (px)': 'Line spacing (px)', 'Schritte': 'Steps', 'Nummer-Hintergrund': 'Number background',
      'Nummer-Farbe': 'Number colour', 'Verbindungslinie': 'Connector line', 'Preistabelle': 'Price table',
      'Titelfarbe': 'Title colour', 'Preisfarbe': 'Price colour', 'Trennlinie': 'Divider',
      'Preisliste': 'Price list', 'Namensfarbe': 'Name colour', 'Punktlinie': 'Dotted line',
      'Zitat': 'Quote', 'Zitat-Text': 'Quote text', 'Balkenbreite (px)': 'Bar width (px)',
      'Breadcrumb': 'Breadcrumb', 'Aktive Seite': 'Active page', 'Trennzeichen': 'Separator',
      'Inhaltsverzeichnis': 'Table of contents', 'Akzentbalken': 'Accent bar',
      'Karten': 'Cards', 'Karten-Rahmen': 'Card border', 'Abstand zw. Karten (px)': 'Gap between cards (px)',
      'Titelgröße (px)': 'Title size (px)', 'Teaser-Farbe': 'Teaser colour', 'Mehr-Link-Farbe': 'More-link colour',
      'Höhe Mobil (px) ': 'Height mobile (px)', 'z-index': 'z-index', 'Wählen': 'Choose',
      // buildField hardcoded UI
      'Datei wählen': 'Choose file', 'Keine Datei': 'No file', 'Entfernen': 'Remove',
      'Icon wählen': 'Choose icon', 'Icon suchen …': 'Search icon …',
      // ── FieldRegistry content-field labels — de→en ───────────────────────
      'Quelltext': 'Source code', 'Linktext': 'Link text', 'URL': 'URL', 'Bildgröße': 'Image size',
      'Alt-Text': 'Alt text', 'Listentyp': 'List type', 'Einträge (eine pro Zeile)': 'Entries (one per line)',
      'Tabelle': 'Table', 'Zusammenfassung': 'Summary', 'Kopfzeile': 'Header row', 'Fußzeile': 'Footer row',
      'Erste Spalte als Kopf': 'First column as header', 'Sortierbar': 'Sortable',
      'Begriffe & Beschreibungen': 'Terms & descriptions', 'Titel': 'Title',
      'Dateien (ein Pfad pro Zeile)': 'Files (one path per line)', 'Sortierung': 'Sorting',
      'YouTube-ID/URL': 'YouTube ID/URL', 'Vimeo-ID/URL': 'Vimeo ID/URL', 'Bildunterschrift': 'Caption',
      'Mediendateien (ein Pfad pro Zeile)': 'Media files (one path per line)', 'Modul': 'Module', 'Formular': 'Form',
      'Karten (Titel · Text · Hintergrundbild)': 'Cards (title · text · background image)', 'Spalten (1–6)': 'Columns (1–6)',
      'Beschriftung (bei „Icon + Text")': 'Label (for "icon + text")', 'Artikel': 'Article',
      'Start-Seite': 'Start page', 'Ebenen': 'Levels', 'Design': 'Design',
      'Insert-Tag (z. B. date oder news_url::3)': 'Insert tag (e.g. date or news_url::3)',
      'Link (optional)': 'Link (optional)', 'Button-Text': 'Button text', 'Link (URL oder {{link_url::id}})': 'Link (URL or {{link_url::id}})',
      'In neuem Fenster öffnen': 'Open in new window', 'Icon-Position': 'Icon position',
      'Linien-Stil': 'Line style', 'Dicke (px)': 'Thickness (px)', 'Breite (%)': 'Width (%)', 'Farbe': 'Colour',
      'Text in der Mitte (optional)': 'Centre text (optional)', 'Icon (statt Bild)': 'Icon (instead of image)',
      'Icon / Bild': 'Icon / image', 'Alt-Text (Bild)': 'Alt text (image)', 'Medien-Position': 'Media position',
      'Einträge (Titel / Inhalt)': 'Entries (title / content)', 'Mehrere gleichzeitig offen': 'Multiple open at once',
      'Bilder': 'Images', 'Sichtbare Folien': 'Visible slides', 'Überblenden (Fade)': 'Cross-fade', 'Pfeile': 'Arrows',
      'Punkte': 'Dots', 'Autoplay': 'Autoplay', 'Pause bei Hover': 'Pause on hover', 'Autoplay-Intervall (Sek.)': 'Autoplay interval (sec.)',
      'Quelle': 'Source', 'News-Archiv': 'News archive', 'Kalender': 'Calendar', 'News-Kategorie (optional)': 'News category (optional)',
      'Nur hervorgehobene': 'Featured only', 'Start-Seite (Unterseiten anzeigen)': 'Start page (show subpages)',
      'Spalten (1–4)': 'Columns (1–4)', 'Anzahl (0 = alle)': 'Count (0 = all)', 'Beschreibung zeigen': 'Show description',
      'Mehr-Link zeigen': 'Show more-link', 'Typ': 'Type', 'Text': 'Text', 'Schließbar': 'Dismissable',
      'Zitat': 'Quote', 'Autor / Quelle': 'Author / source', 'Startwert': 'Start value', 'Zielwert': 'Target value',
      'Präfix (z. B. +)': 'Prefix (e.g. +)', 'Suffix (z. B. %)': 'Suffix (e.g. %)', 'Dauer (ms)': 'Duration (ms)',
      'Beschriftung': 'Label', 'Wert (0–100)': 'Value (0–100)', 'Prozent anzeigen': 'Show percent',
      'Icon (für alle Punkte)': 'Icon (for all items)', 'Punkte (einer pro Zeile)': 'Items (one per line)',
      'Button-Link': 'Button link', 'Netzwerke (Icon wählen + URL)': 'Networks (choose icon + URL)',
      'Icon (Vorderseite)': 'Icon (front)', 'Titel (Vorderseite)': 'Title (front)', 'Text (Vorderseite)': 'Text (front)',
      'Titel (Rückseite)': 'Title (back)', 'Text (Rückseite)': 'Text (back)', 'Button-Text (Rückseite)': 'Button text (back)',
      'Button-Link (Rückseite)': 'Button link (back)', 'Höhe (px)': 'Height (px)', 'Logo-Bild': 'Logo image',
      'Alternativtext': 'Alternative text', 'Verlinkung': 'Link target', 'Eigene URL': 'Custom URL',
      'HTML-Tag': 'HTML tag', 'Trennzeichen': 'Separator', 'Start-Seite (leer = aktuelle Website)': 'Start page (empty = current site)',
      'Ebenen (1–6)': 'Levels (1–6)', 'Datenquelle': 'Data source', 'Feld': 'Field', 'HTML-Tag (bei Titel)': 'HTML tag (for title)',
      'Einheit (Block)': 'Unit (block)', 'Zieldatum': 'Target date', 'Text nach Ablauf': 'Text after expiry',
      'Öffnungszeiten': 'Opening hours', 'Zeitzone': 'Time zone', 'Text „geöffnet"': 'Text "open"', 'Text „geschlossen"': 'Text "closed"',
      'Schließt-/Öffnet-Zeit ausblenden': 'Hide closes/opens time', 'schema.org-Daten ausblenden': 'Hide schema.org data',
      'Geschlossen': 'Closed', '↧ Montag auf alle Tage': '↧ Monday to all days',
      'Adresse / Ort': 'Address / place', 'Zoom (1–21)': 'Zoom (1–21)', 'DSGVO: erst nach Klick laden': 'GDPR: load only after click',
      'E-Mail': 'Email', 'Link kopieren': 'Copy link', 'Überschrift': 'Headline',
      'Titel (z. B. Basis)': 'Title (e.g. Basic)', 'Preis (z. B. 29 €)': 'Price (e.g. €29)', 'Zeitraum (z. B. /Monat)': 'Period (e.g. /month)',
      'Leistungen (eine pro Zeile)': 'Features (one per line)', 'Hervorheben': 'Highlight',
      'Einträge (Name / Preis)': 'Entries (name / price)', 'Schritte (Titel / Text)': 'Steps (title / text)',
      'Ergebnis-Seite': 'Results page', 'Platzhalter': 'Placeholder', 'Rotierende Wörter (eines pro Zeile)': 'Rotating words (one per line)',
      'Effekt': 'Effect', 'Punkte (x,y / Text)': 'Points (x,y / text)', 'Code': 'Code', 'Sprache (Label)': 'Language (label)',
      'Kopier-Button': 'Copy button', 'Videos (Titel / URL)': 'Videos (title / URL)', 'Seitenverhältnis': 'Aspect ratio',
      'Layout': 'Layout', 'Spalten (1–8)': 'Columns (1–8)', 'Abstand (px)': 'Gap (px)',
      'Kachel-Format (nur Kacheln)': 'Tile format (tiles only)', 'Hover-Effekt': 'Hover effect',
      // ── OPTIONS (select choice labels) — de→en ───────────────────────────
      'Aufzählung (•)': 'Bulleted (•)', 'Nummeriert (1.)': 'Numbered (1.)', 'Name A→Z': 'Name A→Z', 'Name Z→A': 'Name Z→A',
      'Datum ↑': 'Date ↑', 'Datum ↓': 'Date ↓', 'Meta-Reihenfolge': 'Meta order', 'Zufällig': 'Random',
      'Horizontal': 'Horizontal', 'Seiten-Navi': 'Side nav', 'Hamburger': 'Hamburger', 'Durchgezogen': 'Solid',
      'Gestrichelt': 'Dashed', 'Gepunktet': 'Dotted', 'Doppelt': 'Double', 'Links': 'Left', 'Mitte': 'Centre', 'Rechts': 'Right',
      'Nur Icon (Sonne/Mond)': 'Icon only (sun/moon)', 'Icon + Text': 'Icon + text', 'Schiebeschalter': 'Toggle switch',
      'Klein': 'Small', 'Mittel': 'Medium', 'Groß': 'Large', 'Vor dem Text': 'Before text', 'Nach dem Text': 'After text',
      'Oben': 'Top', 'Seiten (Unterseiten)': 'Pages (subpages)', 'Termine (Kalender)': 'Events (calendar)',
      'Standard (neueste / Reihenfolge)': 'Default (newest / order)', 'Älteste zuerst': 'Oldest first', 'Alphabetisch': 'Alphabetical',
      'Info (blau)': 'Info (blue)', 'Erfolg (grün)': 'Success (green)', 'Warnung (gelb)': 'Warning (yellow)', 'Fehler (rot)': 'Error (red)',
      'Startseite': 'Home page', 'Kein Link': 'No link', 'Seitentitel': 'Page title', 'Navigationstitel': 'Navigation title',
      'Beschreibung': 'Description', 'Rotieren': 'Rotate', 'Tippen': 'Typing', 'Raster (Grid)': 'Grid', 'Masonry': 'Masonry',
      'Kacheln (gleich groß)': 'Tiles (equal size)', '1:1 Quadrat': '1:1 square', '3:4 Hoch': '3:4 portrait',
      'Keiner': 'None', 'Verdunkeln': 'Darken', 'Graustufen→Farbe': 'Greyscale→colour', 'Keine': 'None', 'Bild öffnen': 'Open image',
      'Automatisch (News/Event)': 'Automatic (news/event)', 'Nur News': 'News only', 'Nur Termine': 'Events only',
      'Teaser': 'Teaser', 'Aufmacherbild': 'Featured image', 'Datum': 'Date', 'Autor': 'Author',
      'Antwort … (Strg+Enter sendet)': 'Reply … (Ctrl+Enter sends)', 'Lade …': 'Loading …',
      // Element editor tabs + common
      'Stil': 'Style', 'Erweitert': 'Advanced', 'Speichern': 'Save', 'Abbrechen': 'Cancel',
      'Duplizieren': 'Duplicate', 'Kopieren': 'Copy', 'Einfügen': 'Paste', 'Hier einfügen': 'Paste here', 'Als Komponente speichern': 'Save as component',
      'Nur in der Vollversion': 'Full version only', 'Dieses Spalten-Layout gehört zur Vollversion (v-t.one).': 'This column layout is part of the full version (v-t.one).', 'Dieses Element gehört zur Vollversion (v-t.one).': 'This element is part of the full version (v-t.one).',
      'Globale Design-Werte gehören zur Vollversion (v-t.one).': 'Global design values are part of the full version (v-t.one).',
      'Aus': 'Off', 'Immer': 'Always', 'Nur bei Hover': 'On hover only', 'Blocksatz': 'Justify', 'Gestapelt': 'Stacked', 'Verteilt': 'Spaced out', 'Wörter': 'Words', 'Unterstrich': 'Underline', 'klein': 'small', 'GROSS': 'UPPERCASE', 'Horizontal (Button rechts)': 'Horizontal (button right)', 'Hamburger + Slide-in': 'Hamburger + slide-in',
      'Design-Werte': 'Design values', 'Farben': 'Colors', 'Abstände': 'Spacings', 'Schriften': 'Fonts', 'Eckenradius': 'Corner radius', 'Schatten': 'Shadows', 'Schrift': 'Font', 'Abstand': 'Spacing',
      'Lege Farben und Werte einmal an und nutze sie überall. Änderst du einen Wert hier, ändert er sich auf der ganzen Website.': 'Define colors and values once and reuse them everywhere. Change a value here and it updates across the whole site.',
      'Noch keine Werte angelegt.': 'No values created yet.', 'löschen?': 'delete?', 'Neuen Wert anlegen': 'Create new value', 'Art': 'Type', 'Wert': 'Value', 'z. B. Primärfarbe': 'e.g. Primary color', 'z. B.': 'e.g.', 'Bitte Name und Wert angeben.': 'Please enter a name and a value.',
      'Breite': 'Width', 'Höhe': 'Height', 'Ausrichtung': 'Alignment', 'Abstände': 'Spacing',
      'Innenabstand': 'Padding', 'Außenabstand': 'Margin', 'Deckkraft (0–1)': 'Opacity (0–1)',
      'Nur für eingeloggte Mitglieder': 'Members only', 'Mitglieder-Gruppen': 'Member groups',
      // StyleSchema group titles
      'Typografie': 'Typography', 'Layout & Abstände': 'Layout & spacing', 'Größe & Rahmen': 'Size & border',
      'Effekte': 'Effects', 'Sichtbarkeit': 'Visibility', 'Navigation': 'Navigation', 'Untermenü (Ebene 2)': 'Submenu (level 2)',
      'Responsive (Hamburger)': 'Responsive (hamburger)', 'Button': 'Button', 'Icon': 'Icon', 'Logo': 'Logo', 'Bild': 'Image',
      'Icon-Box': 'Icon box', 'Bild-Box': 'Image box', 'Akkordeon': 'Accordion', 'Tabs': 'Tabs', 'Karussell': 'Carousel',
      'Social-Icons': 'Social icons', 'Flip-Box': 'Flip box', 'Karten': 'Cards', 'Fortschritt': 'Progress',
      'Galerie': 'Gallery', 'Karte': 'Map', 'Hinweis': 'Alert', 'Abstandhalter (responsive)': 'Spacer (responsive)',
      'Zitat': 'Quote', 'Schritte': 'Steps', 'Preistabelle': 'Price table', 'Preisliste': 'Price list',
      'Countdown': 'Countdown', 'Icon-Liste': 'Icon list', 'Code': 'Code', 'Buttons': 'Buttons',
      'Call-to-Action': 'Call to action', 'Breadcrumb': 'Breadcrumb', 'Inhaltsverzeichnis': 'Table of contents',
      // Common control labels
      'Schriftart': 'Font', 'Schriftgröße (px)': 'Font size (px)', 'Größe (px)': 'Size (px)', 'Gewicht': 'Weight',
      'Transform': 'Transform', 'Stil': 'Style', 'Dekoration': 'Decoration', 'Zeilenhöhe': 'Line height',
      'Buchstabenabstand (px)': 'Letter spacing (px)', 'Wortabstand (px)': 'Word spacing (px)', 'Linkfarbe': 'Link colour',
      'Textschatten': 'Text shadow', 'Verlaufstext': 'Gradient text', 'Verlauf von': 'Gradient from', 'Verlauf bis': 'Gradient to',
      'Innenabstand (oben/rechts/unten/links)': 'Padding (top/right/bottom/left)', 'Außenabstand (oben/rechts/unten/links)': 'Margin (top/right/bottom/left)',
      'Max-Breite': 'Max width', 'Min-Höhe': 'Min height', 'Max-Höhe': 'Max height',
      'Rahmen-Breite (px)': 'Border width (px)', 'Scroll-Animation': 'Scroll animation', 'Dauer (ms)': 'Duration (ms)',
      'Verzögerung (ms)': 'Delay (ms)', 'Sticky': 'Sticky', 'Sticky-Offset (px)': 'Sticky offset (px)',
      'Auf Desktop verbergen': 'Hide on desktop', 'Auf Tablet verbergen': 'Hide on tablet', 'Auf Mobil verbergen': 'Hide on mobile',
      'CSS-Klassen': 'CSS classes', 'Position': 'Position', 'Oben': 'Top', 'Rechts': 'Right', 'Unten': 'Bottom', 'Links': 'Left',
      'Drehen (deg)': 'Rotate (deg)', 'Skalieren': 'Scale', 'Verschieben X': 'Translate X', 'Verschieben Y': 'Translate Y',
      'Eigenes CSS': 'Custom CSS', 'Keiner': 'None', 'Klein': 'Small', 'Mittel': 'Medium', 'Groß': 'Large', 'Sehr groß': 'Very large',
      'Mitte': 'Centre', 'Standard': 'Default', 'Verlauf-Typ': 'Gradient type', 'Linear': 'Linear', 'Radial': 'Radial',
      'Winkel (°)': 'Angle (°)', 'Von-Farbe': 'From colour', 'Bis-Farbe': 'To colour', 'Overlay-Typ': 'Overlay type',
      'Farbe': 'Colour', 'Verlauf': 'Gradient', 'Deckkraft': 'Opacity', 'Mischmodus': 'Blend mode', 'Medien-Typ': 'Media type',
      'Video': 'Video', 'Slideshow': 'Slideshow', 'Effekt': 'Effect', 'Überblenden': 'Fade', 'Schieben': 'Slide',
      'Struktur · Tablet (≥768px)': 'Structure · tablet (≥768px)', 'Struktur · Mobil (<768px)': 'Structure · mobile (<768px)',
      'Wie Desktop': 'Same as desktop', 'Untereinander (1-spaltig)': 'Stacked (1 column)',
      'Container-Name (intern – kein Frontend)': 'Container name (internal – not shown on the site)', 'Nur zur Orientierung im Editor/Seitenbaum. Für eine sichtbare Überschrift ein „Überschrift"- oder Text-Element einfügen.': 'Editor/page-tree label only. For a visible heading, add a Headline or Text element.', 'Vollbreite': 'Full width', 'Boxed (zentriert)': 'Boxed (centred)',
      'Inhalt horizontal': 'Content horizontal', 'Inhalt vertikal': 'Content vertical', 'Anzeige': 'Display',
      // KI source strings (chat is excluded from the DOM sweep → translated here)
      'Hi! Ich baue mit dir ein eigenes Content-Element. Sag mir grob, was es sein soll (z. B. „Logo-Carousel") — ich schlage dir gleich einen kompletten Vorschlag mit allen sinnvollen Optionen vor.': 'Hi! Let’s build your own content element. Tell me roughly what it should be (e.g. "logo carousel") — I’ll propose a complete setup with all sensible options right away.',
      'Neues Element — sag mir grob, was es sein soll (z. B. „Team-Karten" oder „Bild-Slider"). Ich mache dir einen kompletten Vorschlag.': 'New element — tell me roughly what it should be (e.g. "team cards" or "image slider"). I’ll make a complete proposal.',
      'Bearbeitungsmodus für': 'Edit mode for', 'Was möchtest du ändern? (z. B. eine Option ergänzen, Farben, Layout, Felder)': 'What would you like to change? (e.g. add an option, colours, layout, fields)',
      'bearbeiten': 'edit', 'Entwurf abgelehnt:': 'Draft rejected:', 'Bitte korrigieren und erneut versuchen.': 'Please correct it and try again.',
      'aktualisiert.': 'updated.', 'erstellt.': 'created.',
      'in der Palette (Tab „Inhalt") unter „KI-Elemente". Noch etwas ändern oder ein neues bauen?': 'in the palette (Content tab) under "AI elements". Change something else or build a new one?',
      // Globals pane
      'Design-Werte': 'Design tokens', 'Neuen Wert anlegen': 'Add new token', 'Standard-Stile (global)': 'Default styles (global)',
      'Standard-Stile speichern': 'Save default styles', 'Art': 'Type', 'Wert': 'Value', 'Hinzufügen': 'Add', 'Fließtext': 'Body text',
      'FARBEN': 'COLOURS', 'z. B. Primärfarbe': 'e.g. Primary colour',
      'Lege Farben und Werte einmal an und nutze sie überall. Änderst du einen Wert hier, ändert er sich auf der ganzen Website.': 'Define colours and values once and use them everywhere. Change a value here and it changes across the whole site.',
      'Gelten überall. Pro Element überschreibbar. Werte leer = Theme-Standard. (Tokens erlaubt: var(--bld-color-… / --bld-font-…))': 'Apply everywhere. Overridable per element. Empty values = theme default. (Tokens allowed: var(--bld-color-… / --bld-font-…))',
      // Inspector / layout hints + head titles
      'Container-Kopf, Reihen-Kopf oder eine Spalte anklicken, um Hintergrund, Bild, Abstand, Farbe & Ausrichtung zu setzen.': 'Click the container header, row header or a column to set background, image, spacing, colour & alignment.',
      'Basis (gilt überall, sofern nicht überschrieben).': 'Base (applies everywhere unless overridden).',
      'Reihen-Layout bearbeiten': 'Edit row layout', 'Container-Layout bearbeiten': 'Edit container layout', 'Spalten-Layout bearbeiten': 'Edit column layout',
      'Neuer Eintrag': 'New entry', '+ Eintrag': '+ Entry',
      // FieldRegistry content-field labels
      'Überschrift': 'Headline', 'Quelltext': 'Source code', 'Linktext': 'Link text', 'Bild': 'Image', 'Bildgröße': 'Image size',
      'Alt-Text': 'Alt text', 'Alternativtext': 'Alternative text', 'Listentyp': 'List type', 'Einträge (eine pro Zeile)': 'Entries (one per line)',
      'Zusammenfassung': 'Summary', 'Kopfzeile': 'Header row', 'Fußzeile': 'Footer row', 'Erste Spalte als Kopf': 'First column as header',
      'Sortierbar': 'Sortable', 'Begriffe & Beschreibungen': 'Terms & descriptions', 'Titel': 'Title',
      'Dateien (ein Pfad pro Zeile)': 'Files (one path per line)', 'Sortierung': 'Order', 'Bildunterschrift': 'Caption',
      'Mediendateien (ein Pfad pro Zeile)': 'Media files (one path per line)', 'Modul': 'Module', 'Formular': 'Form', 'Artikel': 'Article',
      'Start-Seite': 'Start page', 'Ebenen': 'Levels', 'Design': 'Design', 'Einträge (Titel / Inhalt)': 'Entries (title / content)',
      'Mehrere gleichzeitig offen': 'Multiple open at once', 'Icon (für alle Punkte)': 'Icon (for all items)', 'Punkte (einer pro Zeile)': 'Items (one per line)',
      'Button-Text': 'Button text', 'Button-Link': 'Button link', 'Netzwerke (Icon wählen + URL)': 'Networks (pick icon + URL)',
      // Per-widget style labels
      'Hover-Farbe': 'Hover colour', 'Hover-Hintergrund': 'Hover background', 'Hover-Textfarbe': 'Hover text colour', 'Aktiv-Farbe': 'Active colour',
      'Abstand zw. Punkten (px)': 'Gap between items (px)', 'Innenabstand vertikal (px)': 'Vertical padding (px)', 'Innenabstand horizontal (px)': 'Horizontal padding (px)',
      'Indikator': 'Indicator', 'Indikator-Farbe': 'Indicator colour', 'Indikator-Dicke (px)': 'Indicator thickness (px)', 'Hintergrund (Panel)': 'Background (panel)',
      'Mobil-Modus': 'Mobile mode', 'Einschub-Seite': 'Drawer side', 'Menü-Breite (px)': 'Menu width (px)', 'Menü-Hintergrund': 'Menu background',
      'Hamburger-Icon': 'Hamburger icon', 'Icon-Farbe': 'Icon colour', 'Icon-Größe (px)': 'Icon size (px)', 'Volle Breite': 'Full width', 'Icon-Abstand (px)': 'Icon gap (px)',
      'Leisten-Hintergrund': 'Bar background', 'Link-Eckenradius (px)': 'Link corner radius (px)', 'Trenner zw. Punkten': 'Separator between items', 'Trenner-Farbe': 'Separator colour',
      'Linie': 'Line', 'Punkt': 'Dot', 'Mindestbreite (px)': 'Min width (px)', 'Trennlinie zw. Einträgen': 'Divider between entries', 'Text-Ausrichtung': 'Text alignment',
      'Trennlinie zw. Punkten': 'Divider between items', 'Hintergrund-Abdunkelung': 'Backdrop dim',
      'Medien-Breite (px)': 'Media width (px)', 'Abstand Medien ↔ Text (px)': 'Gap media ↔ text (px)', 'Icon-Hintergrund (Kreis)': 'Icon background (circle)',
      'Icon-Innenabstand (px)': 'Icon padding (px)', 'Titelfarbe': 'Title colour', 'Titelgröße (px)': 'Title size (px)', 'Medien-Eckenradius (px)': 'Media corner radius (px)',
      'Hover-Effekt (anheben)': 'Hover effect (lift)', 'Hover-Effekt': 'Hover effect',
      'Kopf-Hintergrund': 'Header background', 'Kopf-Textfarbe': 'Header text colour', 'Aktiv-Hintergrund': 'Active background', 'Aktiv-Textfarbe': 'Active text colour',
      'Inhalt-Hintergrund': 'Content background', 'Rahmenfarbe': 'Border colour', 'Abstand zw. Einträgen (px)': 'Gap between entries (px)',
      'Reiter-Farbe': 'Tab colour', 'Aktiver Reiter': 'Active tab', 'Trennlinie': 'Divider line', 'Reiter-Ausrichtung': 'Tab alignment',
      'Anordnung': 'Arrangement', 'Horizontal (oben)': 'Horizontal (top)', 'Vertikal (links)': 'Vertical (left)',
      'Pfeil-Farbe': 'Arrow colour', 'Pfeil-Hintergrund': 'Arrow background', 'Punkt-Farbe': 'Dot colour', 'Aktiver Punkt': 'Active dot',
      'Bild-Eckenradius (px)': 'Image corner radius (px)', 'Abstand zw. Folien (px)': 'Gap between slides (px)', 'Feste Bildhöhe (px, 0 = auto)': 'Fixed image height (px, 0 = auto)',
      'Hover-Overlay-Farbe': 'Hover overlay colour', 'Breite (px)': 'Width (px)', 'Max-Breite (px)': 'Max width (px)', 'Höhe (px, 0 = auto)': 'Height (px, 0 = auto)',
      'Skalierung': 'Scaling', 'Einpassen (contain)': 'Contain', 'Füllen (cover)': 'Cover',
      'Balkenfarbe': 'Bar colour', 'Balkenbreite (px)': 'Bar width (px)', 'Zitat-Größe (px)': 'Quote size (px)', 'Kursiv': 'Italic', 'Autor-Farbe': 'Author colour',
      'Graustufen (Farbe bei Hover)': 'Grayscale (colour on hover)', 'Typ-Icon anzeigen': 'Show type icon',
      'Höhe Tablet (px)': 'Tablet height (px)', 'Höhe Mobil (px)': 'Mobile height (px)',
      'Spurfarbe': 'Track colour', 'Höhe (px)': 'Height (px)', 'Hintergrund': 'Background',
      'Zahlenfarbe': 'Number colour', 'Zahlengröße (px)': 'Number size (px)', 'Label-Farbe': 'Label colour', 'Abstand (px)': 'Gap (px)',
      'Layout': 'Layout',
      // Common option values
      'Keine': 'None', 'Normal': 'Normal', 'Unterstrichen': 'Underlined', 'Durchgestrichen': 'Strikethrough',
      'Gestrichelt': 'Dashed', 'Gepunktet': 'Dotted', 'Doppelt': 'Double', 'Einblenden': 'Fade in', 'Hoch': 'Up', 'Runter': 'Down',
      'Von links': 'From left', 'Von rechts': 'From right', 'Relativ': 'Relative', 'Absolut': 'Absolute', 'Fixiert': 'Fixed',
      'Multiplizieren': 'Multiply', 'Abdunkeln': 'Darken', 'Aufhellen': 'Lighten', 'Kein': 'None', 'Kein Verlauf': 'No gradient',
      // Restore points
      'Wiederherstellungspunkte': 'Restore points', 'Punkt sichern': 'Save point', 'Noch keine Punkte.': 'No points yet.',
      'Wiederherstellen': 'Restore', 'Verlauf nicht verfügbar (contao:migrate?).': 'History unavailable (contao:migrate?).',
      'Diesen Stand wiederherstellen? Der aktuelle Stand wird vorher gesichert.': 'Restore this state? The current state is saved first.',
      'Wiederhergestellt.': 'Restored.', 'Name des Wiederherstellungspunkts:': 'Name of the restore point:', 'Punkt gesichert.': 'Point saved.',
      'Keine Berechtigung. Ein Administrator muss deiner Benutzergruppe das Draggo-Recht „KI-Element-Generator nutzen" geben.': 'No permission. An administrator must grant your user group the Draggo right "Use the AI element generator".',
      // Structure outline
      'Baum': 'Tree', 'Struktur': 'Structure', 'Struktur-Baum': 'Structure tree', 'Reihe': 'Row', 'Spalte': 'Column', 'Keine Container.': 'No containers.',
      'Klick = im Editor anspringen & auswählen.': 'Click = jump to & select in the editor.',
      // Whole page from brief
      'Ganze Seite aus Brief': 'Whole page from a brief', 'Seite aus eigenen Inhalten': 'Page from your own content', 'Aus meinen Inhalten bauen': 'Build from my content', 'Baue …': 'Building …', 'Seite generieren': 'Generate page', 'Generiere …': 'Generating …',
      'Erstellt mehrere Container aus passenden Vorlagen. Inhalte danach anpassen.': 'Creates several containers from matching templates. Adjust the content afterwards.',
      'z. B. Landingpage für ein Fitnessstudio mit Angeboten, Preisen, Team und Kontakt': 'e.g. landing page for a gym with offers, pricing, team and contact',
      'Konnte keine Seite planen — bitte Brief konkreter formulieren.': 'Could not plan a page — please make the brief more specific.',
      'Container erstellt.': 'containers created.',
      // Globals pane (sizes + headings)
      'Größe': 'Size', 'Links': 'Links',
      'Überschrift H1': 'Heading H1', 'Überschrift H2': 'Heading H2', 'Überschrift H3': 'Heading H3',
      'Überschrift H4': 'Heading H4', 'Überschrift H5': 'Heading H5', 'Überschrift H6': 'Heading H6',
      // Canvas chrome (row/column bars, empty hints, context menu) — translated at source via tr()
      'Reihe löschen': 'Delete row', 'Als Komponente': 'As component',
      'Ganze Reihe inkl. Inhalt löschen?': 'Delete the whole row including its content?',
      'Ganze Sektion als wiederverwendbare Komponente speichern': 'Save the whole section as a reusable component',
      'leer · Element hierher': 'empty · drop an element here',
      'noch kein Inhalt — ✎ bearbeiten': 'no content yet — ✎ edit',
      '+ Container hier einfügen': '+ Insert container here', 'Element löschen?': 'Delete element?',
      'Container löschen': 'Delete container', 'Container löschen?': 'Delete container?',
      // Foreign theme grid (SubColumns / bootstrap-grid / RockSolid) recognition + opt-in conversion
      'Theme-Grid erkannt — Struktur wird vom Theme verwaltet. Inhalte in den Spalten sind hier editierbar.': 'Theme grid detected — its structure is managed by the theme. Content inside the columns is editable here.',
      'In Draggo-Grid umwandeln': 'Convert to Draggo grid',
      'Wandelt diese Reihe in Draggos Grid um. Danach rendert das Frontend über Draggo — die Theme-Grid-Darstellung entfällt.': 'Converts this row to Draggo’s grid. The frontend then renders through Draggo — the theme grid presentation no longer applies.',
      'In Draggo-Grid umwandeln? Das Frontend nutzt danach Draggos Grid — die Theme-Grid-Darstellung entfällt.': 'Convert to Draggo grid? The frontend will then use Draggo’s grid — the theme grid presentation no longer applies.',
      'Umgewandelt.': 'Converted.',
    },
  };
  function tr(s) {
    if (DRAGGO_LANG === 'de' || !s) return s;
    var d = I18N[DRAGGO_LANG];
    return (d && d[s]) ? d[s] : s;
  }
  // Translate the editor chrome in place. Skips canvas + KI chat/preview.
  var _trBusy = false;
  function translateChrome(rootEl) {
    if (DRAGGO_LANG === 'de' || !I18N[DRAGGO_LANG]) return;
    var dict = I18N[DRAGGO_LANG];
    var scopes = (rootEl || document).querySelectorAll('.draggo-side, .draggo-toolbar');
    _trBusy = true;
    scopes.forEach(function (scope) {
      var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
        acceptNode: function (n) {
          if (!n.nodeValue || !n.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
          if (n.parentNode && n.parentNode.closest && n.parentNode.closest('.draggo-ki-log, .draggo-ki-preview-body')) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        },
      });
      var nodes = []; while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach(function (tn) { var k = tn.nodeValue.trim(); if (dict[k]) tn.nodeValue = tn.nodeValue.replace(k, dict[k]); });
      scope.querySelectorAll('[placeholder]').forEach(function (e) { var k = e.getAttribute('placeholder'); if (dict[k]) e.setAttribute('placeholder', dict[k]); });
      scope.querySelectorAll('[title]').forEach(function (e) { var k = e.getAttribute('title'); if (dict[k]) e.setAttribute('title', dict[k]); });
    });
    _trBusy = false;
  }

  function boot() {
    var root = document.getElementById('draggo-editor');
    if (!root) return;
    console.log('Draggo editor JS ' + DRAGGO_VERSION);

    DRAGGO_LANG = (root.dataset.lang === 'de' || root.dataset.lang === 'en') ? root.dataset.lang : 'en';

    var id = parseInt(root.dataset.id, 10);
    if (root.dataset.mode === undefined || isNaN(id)) {
      root.innerHTML = '<p style="padding:2rem;color:#b91c1c;font-family:sans-serif">' +
        'Draggo: veraltete Editor-Datei. <code>assets:install</code> + Strg+F5.</p>';
      return;
    }

    new DraggoEditor({
      root: root,
      mode: root.dataset.mode === 'unit' ? 'unit' : 'page',
      containerId: id,
      csrfToken: root.dataset.csrfToken,
      apiBase: root.dataset.apiBase,
      previewUrl: root.dataset.previewUrl || '',
    }).init();
  }

  class DraggoEditor {
    constructor(opts) {
      this.root = opts.root;
      this.mode = opts.mode;
      this.containerId = opts.containerId;
      this.csrfToken = opts.csrfToken;
      this.apiBase = opts.apiBase;
      this.previewUrl = opts.previewUrl || '';
      this.groups = {};
      this.structures = {};
      this.presetWidths = {};
      this.labels = {};
      this.active = null;   // {sectionId, after}
      this.drag = null;     // {kind:'new'|'structure'|'move'|'container', ...}
      // Active responsive viewport ('full'|'tablet'|'mobile'). Persisted so a
      // refresh keeps the view until the user switches it themselves.
      var savedVw = null;
      try { savedVw = window.localStorage.getItem('draggo_vw'); } catch (e) { savedVw = null; }
      this.vw = (savedVw === 'tablet' || savedVw === 'mobile') ? savedVw : 'full';
    }

    // Map between the canvas viewport key ('full'/'tablet'/'mobile') and the
    // responsive style breakpoint key ('desktop'/'tablet'/'mobile').
    vwToBp(vw) { return (vw === 'tablet' || vw === 'mobile') ? vw : 'desktop'; }
    bpToVw(bp) { return (bp === 'tablet' || bp === 'mobile') ? bp : 'full'; }
    bpKey() { return this.vw === 'tablet' ? 'tablet' : (this.vw === 'mobile' ? 'mobile' : null); }

    /**
     * Effective layout for the current viewport: the base layout overlaid with
     * its responsive[bp] override (empty/missing keys inherit base). Returns a
     * fresh object; never mutates the source. Mirrors the frontend so the canvas
     * is 1:1 incl. responsive.
     */
    effLayout(L) {
      L = L || {};
      var base = Object.assign({}, L);
      delete base.responsive;
      var bp = this.bpKey();
      if (!bp || !L.responsive || !L.responsive[bp]) return base;
      return Object.assign(base, L.responsive[bp]);
    }

    /**
     * Central viewport switch: updates the canvas width state, every viewport
     * control (toolbar + element-editor breakpoint switcher), the view badge,
     * persists the choice, and re-renders so structures reflect the breakpoint.
     */
    // Apply an editor theme: '' = follow OS, 'light'/'dark' = force. Sets
    // data-theme on <html> (CSS overrides the prefers-color-scheme block) and
    // updates the toggle icon.
    applyTheme(t) {
      this._theme = (t === 'light' || t === 'dark') ? t : '';
      var de = document.documentElement;
      if (this._theme) de.setAttribute('data-theme', this._theme); else de.removeAttribute('data-theme');
      try { localStorage.setItem('draggoTheme', this._theme); } catch (e) {}
      var btn = document.getElementById('draggo-theme-toggle');
      if (btn) {
        var ic = this._theme === 'light' ? 'fa-sun' : (this._theme === 'dark' ? 'fa-moon' : 'fa-adjust');
        btn.innerHTML = '<i class="fas ' + ic + '" aria-hidden="true"></i>';
        btn.title = this._theme === 'light' ? 'Hell — Klick für Dunkel' : (this._theme === 'dark' ? 'Dunkel — Klick für Automatisch' : 'Automatisch (OS) — Klick für Hell');
      }
    }

    cycleTheme() {
      this.applyTheme(this._theme === '' ? 'light' : (this._theme === 'light' ? 'dark' : ''));
    }

    setViewport(vw) {
      vw = (vw === 'tablet' || vw === 'mobile') ? vw : 'full';
      this.vw = vw;
      try { window.localStorage.setItem('draggo_vw', vw); } catch (e) {}
      var canvas = document.getElementById('draggo-canvas');
      if (canvas) canvas.dataset.vw = vw;
      // Toolbar buttons.
      this.root.querySelectorAll('.draggo-vp button').forEach(function (x) { x.classList.toggle('is-on', x.dataset.vw === vw); });
      // View badge / body marker.
      var badge = document.getElementById('draggo-vp-label');
      var names = { full: 'Desktop', tablet: 'Tablet', mobile: 'Mobil' };
      if (badge) badge.textContent = names[vw] || 'Desktop';
      this.root.dataset.vw = vw;
      // Notify an open element editor so its breakpoint switcher + style panel follow.
      if (this._onVwChange) this._onVwChange(vw);
      // Re-render the canvas so structure spacing/columns match the viewport,
      // and refresh the inspector to show this breakpoint's values.
      if (this._sections) this.renderSections(this._sections);
    }

    async init() {
      this.renderChrome();
      this.setupI18n();
      this.reflectClipboard();
      try {
        await this.loadPalette();
        await this.loadComponents();
        await this.loadFiles();
        await this.loadTokens();
        await this.loadContent();
      } catch (e) {
        this.fail(e);
        var c = document.getElementById('draggo-canvas');
        if (c) c.innerHTML = '<p style="padding:2rem;color:#b91c1c">Laden fehlgeschlagen: ' +
          esc((e && e.error && e.error.message) || 'Server-Fehler') +
          '<br><br>Tipp: <code>contao:migrate</code> ausführen (fehlende Spalte tl_article.draggo_layout?).</p>';
      }
    }

    // Translate the chrome now + on every future render (MutationObserver),
    // so we never have to wrap individual strings. German = no-op.
    setupI18n() {
      if (DRAGGO_LANG === 'de') return;
      var self = this;
      translateChrome(this.root);
      try {
        var pending = false;
        var obs = new MutationObserver(function () {
          if (_trBusy || pending) return;
          pending = true;
          (window.requestAnimationFrame || window.setTimeout)(function () { pending = false; translateChrome(self.root); }, 0);
        });
        obs.observe(this.root, { childList: true, subtree: true, characterData: true });
      } catch (e) { /* observer optional */ }
    }

    // ── API ──────────────────────────────────────────────────────
    api(path, method, body) {
      // X-Requested-With marks every call as an AJAX request → Contao's
      // RequestTokenListener skips its own token check (it only validates
      // non-XHR simple-CORS POSTs), leaving CSRF to Draggo's assertCsrf. Without
      // it, a text/plain POST (see below) falls INTO that listener, which reads
      // REQUEST_TOKEN only from real POST fields (empty here) → 400.
      var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      var opts = { method: method || 'GET', headers: headers };
      if (body !== undefined) {
        // Send the JSON payload as text/plain (NOT application/json) and carry
        // the request token IN the body (REQUEST_TOKEN). Some server WAFs
        // (mod_security/Imunify) 400-block JSON-content-type POSTs and/or strip
        // custom headers — this dodges both. Draggo reads the raw body +
        // REQUEST_TOKEN regardless of content-type.
        headers['Content-Type'] = 'text/plain;charset=UTF-8';
        opts.body = JSON.stringify(Object.assign({ REQUEST_TOKEN: this.csrfToken }, body));
      }
      return fetch(this.apiBase + path, opts).then(function (r) {
        if (!r.ok) return r.json().then(function (e) { return Promise.reject(e); }, function () { return Promise.reject({}); });
        return r.status === 204 ? null : r.json();
      });
    }

    // Multipart upload (FormData; browser sets the boundary Content-Type).
    uploadFile(file, folder) {
      var fd = new FormData();
      fd.append('file', file);
      // Send the CSRF token as a field too (Contao validates form posts via
      // the REQUEST_TOKEN field, not only the header).
      fd.append('REQUEST_TOKEN', this.csrfToken);
      if (folder) fd.append('folder', folder);
      return fetch(this.apiBase + '/files/upload', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Contao-Csrf-Token': this.csrfToken },
        body: fd,
      }).then(function (r) {
        if (r.ok) return r.json();
        // Surface the real reason (status + JSON message or raw body snippet).
        return r.text().then(function (t) {
          var msg = null;
          try { msg = JSON.parse(t).error.message; } catch (e) { /* not JSON */ }
          return Promise.reject({ error: { message: (msg || ('HTTP ' + r.status + (t ? ' · ' + t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200) : ''))) } });
        });
      });
    }

    // Same upload as uploadFile() but via XHR so we get real byte-level upload
    // progress (fetch() can't report request-body progress). onProgress(pct) is
    // called 0..100; resolves with the JSON {path,kind}, rejects with {error}.
    uploadFileXhr(file, folder, onProgress) {
      var self = this;
      return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('file', file);
        fd.append('REQUEST_TOKEN', self.csrfToken);
        if (folder) fd.append('folder', folder);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', self.apiBase + '/files/upload', true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Contao-Csrf-Token', self.csrfToken);
        if (xhr.upload && onProgress) {
          xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
          });
        }
        xhr.addEventListener('load', function () {
          var msg = null;
          try { var j = JSON.parse(xhr.responseText); } catch (e) { j = null; }
          if (xhr.status >= 200 && xhr.status < 300 && j && j.path) { resolve(j); return; }
          if (j && j.error && j.error.message) msg = j.error.message;
          reject({ error: { message: msg || ('HTTP ' + xhr.status) } });
        });
        xhr.addEventListener('error', function () { reject({ error: { message: 'Netzwerkfehler' } }); });
        xhr.addEventListener('abort', function () { reject({ error: { message: 'Abgebrochen', aborted: true } }); });
        xhr.send(fd);
      });
    }

    prefix(section) {
      return section.kind === 'unit' ? '/unit/' + section.id : '/article/' + section.id;
    }

    /** Flush any pending debounced layout save immediately. */
    _flushSave() {
      if (!this._saveT) return;
      clearTimeout(this._saveT); this._saveT = null;
      var self = this, q = this._saveQ || {}; this._saveQ = {};
      Object.keys(q).forEach(function (u) { self.api(u, 'POST', q[u]).then(function () {}, function (e) { self.fail(e); }); });
    }

    loadContent() {
      var self = this;
      this._flushSave();
      if (this.mode === 'unit') {
        return this.api('/unit/' + this.containerId + '/elements').then(function (d) {
          self.renderSections([{ id: self.containerId, kind: 'unit', title: 'Einheit', inColumn: '', layout: d.layout || {}, elements: d.elements || [] }]);
        });
      }
      return this.api('/page/' + this.containerId + '/elements').then(function (d) {
        var sections = (d.articles || []).map(function (a) {
          return { id: a.id, kind: 'article', title: a.title, inColumn: a.inColumn, layout: a.layout || {}, elements: a.elements || [] };
        });
        // Header/footer units preview (read-only) for realistic chrome.
        return self.api('/page/' + self.containerId + '/frame').then(function (f) { self._frame = f || {}; }, function () { self._frame = {}; })
          .then(function () { self.renderSections(sections); });
      });
    }

    loadFiles() {
      var self = this;
      return this.api('/files').then(function (d) { self._files = d.files || []; self._dirs = d.dirs || []; }, function () { self._files = []; self._dirs = []; });
    }

    loadTokens() {
      var self = this;
      return this.api('/tokens').then(function (d) { self._tokens = d.tokens || []; }, function () { self._tokens = []; })
        .then(function () { return self.api('/defaults').then(function (d) { self._defaults = d.defaults || {}; }, function () { self._defaults = {}; }); });
    }

    // Tokens of one type (for control dropdowns).
    tokensOfType(type) {
      return (this._tokens || []).filter(function (t) { return t.type === type; });
    }

    // Re-apply the globals CSS (token :root vars + canvas default styles) live,
    // so a saved font/colour shows immediately — no page refresh. The server
    // returns the freshly compiled CSS from every globals mutation.
    applyGlobalsCss(css) {
      if (typeof css !== 'string') return;
      var id = 'draggo-globals-live';
      var st = document.getElementById(id);
      if (!st) { st = document.createElement('style'); st.id = id; document.head.appendChild(st); }
      st.textContent = css; // appended after the server's initial <style> → wins by source order
    }

    // "Globals" tab: simple manager for reusable design values.
    renderGlobals() {
      var self = this;
      var el = document.getElementById('draggo-pane-globals');
      if (!el) return;
      el.innerHTML = '';

      // Paid feature: show what it is instead of hiding the tab (upsell), and
      // never render the editor — the token API rejects writes anyway.
      if (this.license && this.license.globals === false) {
        var lock = document.createElement('p');
        lock.className = 'draggo-hint';
        lock.textContent = tr('Globale Design-Werte gehören zur Vollversion (v-t.one).');
        el.appendChild(lock);
        return;
      }

      var typeLabels = { color: 'Farben', space: 'Abstände', font: 'Schriften', radius: 'Eckenradius', shadow: 'Schatten' };
      var typeLabel1 = { color: 'Farbe', space: 'Abstand', font: 'Schrift', radius: 'Eckenradius', shadow: 'Schatten' };
      var examples = { color: '#0a66ff', space: '1.5rem', font: 'Arial, sans-serif', radius: '8px', shadow: '0 4px 12px rgba(0,0,0,.15)' };

      var h = document.createElement('h2'); h.textContent = tr('Design-Werte'); el.appendChild(h);
      var p = document.createElement('p'); p.className = 'draggo-hint';
      p.textContent = tr('Lege Farben und Werte einmal an und nutze sie überall. Änderst du einen Wert hier, ändert er sich auf der ganzen Website.');
      el.appendChild(p);

      // ── Existing values, grouped ──────────────────────────────────
      var tokens = this._tokens || [];
      if (!tokens.length) {
        var none = document.createElement('p'); none.className = 'draggo-hint'; none.style.fontStyle = 'italic';
        none.textContent = tr('Noch keine Werte angelegt.'); el.appendChild(none);
      }
      ['color', 'space', 'font', 'radius', 'shadow'].forEach(function (tp) {
        var items = self.tokensOfType(tp);
        if (!items.length) return;
        var sec = document.createElement('div'); sec.className = 'draggo-tok-group';
        var st = document.createElement('h3'); st.textContent = tr(typeLabels[tp]); sec.appendChild(st);
        items.forEach(function (t) {
          var row = document.createElement('div'); row.className = 'draggo-tok';
          if (tp === 'color') { var sw = document.createElement('span'); sw.className = 'draggo-tok-sw'; sw.style.background = t.value; row.appendChild(sw); }
          var nm = document.createElement('span'); nm.className = 'draggo-tok-name'; nm.textContent = t.label || t.token; row.appendChild(nm);
          var vl = document.createElement('span'); vl.className = 'draggo-tok-val'; vl.textContent = t.value; row.appendChild(vl);
          // Dark-mode value picker (colour tokens only). Empty/= light means
          // "same in dark"; setting a different colour drives [data-draggo-theme].
          if (tp === 'color') {
            var dk = document.createElement('input'); dk.type = 'color'; dk.className = 'draggo-tok-dark';
            dk.title = tr('Dunkel-Modus-Farbe'); dk.value = /^#[0-9a-fA-F]{6}$/.test(t.darkValue || '') ? t.darkValue : (/^#[0-9a-fA-F]{6}$/.test(t.value) ? t.value : '#000000');
            dk.addEventListener('change', function () {
              self.api('/tokens', 'POST', { id: t.id, type: t.type, token: t.token, label: t.label, value: t.value, darkValue: dk.value }).then(function (res) {
                self.applyGlobalsCss(res && res.css); self.loadTokens();
              }, function (e) { self.fail(e); });
            });
            row.appendChild(dk);
          }
          var del = document.createElement('button'); del.type = 'button'; del.className = 'draggo-tok-del'; del.title = tr('Löschen'); del.textContent = '✕';
          del.addEventListener('click', function () {
            if (!window.confirm('„' + (t.label || t.token) + '" ' + tr('löschen?'))) return;
            self.api('/tokens/' + t.id, 'DELETE', {}).then(function (res) { self.applyGlobalsCss(res && res.css); self.loadTokens().then(function () { self.renderGlobals(); }); }, function (e) { self.fail(e); });
          });
          row.appendChild(del); sec.appendChild(row);
        });
        el.appendChild(sec);
      });

      // ── Add a new value (simple card) ─────────────────────────────
      var form = document.createElement('div'); form.className = 'draggo-tok-form';
      var ft = document.createElement('h3'); ft.textContent = tr('Neuen Wert anlegen'); form.appendChild(ft);

      var mkRow = function (labelText, control) {
        var l = document.createElement('label'); l.className = 'draggo-tok-field';
        var s = document.createElement('span'); s.textContent = labelText; l.appendChild(s); l.appendChild(control); form.appendChild(l); return control;
      };

      var tsel = document.createElement('select'); tsel.className = 'draggo-ins-select';
      ['color', 'space', 'font', 'radius', 'shadow'].forEach(function (tp) { var o = document.createElement('option'); o.value = tp; o.textContent = tr(typeLabel1[tp]); tsel.appendChild(o); });
      mkRow(tr('Art'), tsel);

      var nameI = document.createElement('input'); nameI.type = 'text'; nameI.placeholder = tr('z. B. Primärfarbe');
      mkRow(tr('Name'), nameI);

      var valHost = document.createElement('div'); valHost.className = 'draggo-tok-valhost';
      mkRow(tr('Wert'), valHost);
      var valColor, valText;
      var renderVal = function () {
        valHost.innerHTML = '';
        if (tsel.value === 'color') {
          valColor = document.createElement('input'); valColor.type = 'color'; valColor.value = '#0a66ff'; valHost.appendChild(valColor); valText = null;
        } else {
          valText = document.createElement('input'); valText.type = 'text'; valText.placeholder = tr('z. B.') + ' ' + examples[tsel.value]; valHost.appendChild(valText); valColor = null;
        }
      };
      tsel.addEventListener('change', renderVal); renderVal();

      var add = document.createElement('button'); add.type = 'button'; add.className = 'draggo-file-pick'; add.textContent = tr('Hinzufügen');
      add.addEventListener('click', function () {
        var name = nameI.value.trim();
        var value = valColor ? valColor.value : (valText ? valText.value.trim() : '');
        if (!name || !value) { window.alert(tr('Bitte Name und Wert angeben.')); return; }
        // token slug derived from name on the server; keep the name as label.
        self.api('/tokens', 'POST', { type: tsel.value, token: name, label: name, value: value }).then(function (res) {
          self.applyGlobalsCss(res && res.css);
          self.loadTokens().then(function () { self.renderGlobals(); });
        }, function (e) { self.fail(e); });
      });
      form.appendChild(add);
      el.appendChild(form);

      // ── Global default styles (body / headings / links), per breakpoint ──
      var def = JSON.parse(JSON.stringify(this._defaults || {}));
      var colorTokG = this.tokensOfType('color'); // palette / global colours
      var defBp = 'desktop';
      var ensureG = function (bp, group) {
        if (bp === 'desktop') { def[group] = def[group] || {}; return def[group]; }
        def.responsive = def.responsive || {}; def.responsive[bp] = def.responsive[bp] || {};
        def.responsive[bp][group] = def.responsive[bp][group] || {};
        return def.responsive[bp][group];
      };
      var readG = function (group, key) {
        if (defBp === 'desktop') return (def[group] && def[group][key] != null) ? def[group][key] : '';
        var r = def.responsive && def.responsive[defBp] && def.responsive[defBp][group];
        return (r && r[key] != null) ? r[key] : '';
      };
      var setG = function (group, key, v) { var t = ensureG(defBp, group); if (v !== '' && v != null) t[key] = v; else delete t[key]; };

      var dform = document.createElement('div'); dform.className = 'draggo-tok-form';
      var dh = document.createElement('h3'); dh.textContent = 'Standard-Stile (global)'; dform.appendChild(dh);
      var dhint = document.createElement('p'); dhint.className = 'draggo-hint'; dhint.style.margin = '0 0 .25rem';
      dhint.textContent = 'Gelten überall, pro Element überschreibbar. Leer = erbt. Tablet/Mobil: leer erbt Desktop. Farben aus der Palette wählbar.';
      dform.appendChild(dhint);

      var dsw = document.createElement('div'); dsw.className = 'draggo-bp-switch';
      [['desktop', 'fa-desktop', 'Desktop'], ['tablet', 'fa-tablet-alt', 'Tablet'], ['mobile', 'fa-mobile-alt', 'Mobil']].forEach(function (o) {
        var b = document.createElement('button'); b.type = 'button'; b.dataset.bp = o[0]; b.innerHTML = '<i class="draggo-fa fas ' + o[1] + '"></i> ' + o[2]; if (o[0] === defBp) b.className = 'is-on'; dsw.appendChild(b);
      });
      dform.appendChild(dsw);
      var dbody = document.createElement('div'); dform.appendChild(dbody);

      var WEIGHTS = [['', '–'], ['300', 'Leicht'], ['400', 'Normal'], ['500', 'Medium'], ['600', 'Halbfett'], ['700', 'Fett'], ['800', 'Extrafett'], ['900', 'Black']];
      var TFORM = [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']];
      var FSTYLE = [['', '–'], ['normal', 'Normal'], ['italic', 'Kursiv']];
      var DECO = [['', '–'], ['none', 'Keine'], ['underline', 'Unterstrichen'], ['line-through', 'Durchgestrichen']];

      var renderDefBody = function () {
        dbody.innerHTML = '';
        var sub = function (t) { var h = document.createElement('h4'); h.textContent = t; h.style.cssText = 'margin:.5rem 0 .15rem;font-size:.78rem'; dbody.appendChild(h); };
        var txt = function (group, key, label, ph) {
          var l = document.createElement('label'); l.className = 'draggo-tok-field';
          var s = document.createElement('span'); s.textContent = label; l.appendChild(s);
          var i = document.createElement('input'); i.type = 'text'; i.placeholder = ph || ''; i.value = readG(group, key);
          i.addEventListener('input', function () { setG(group, key, i.value.trim()); });
          l.appendChild(i); dbody.appendChild(l);
        };
        var sel = function (group, key, label, opts) {
          var l = document.createElement('label'); l.className = 'draggo-tok-field';
          var s = document.createElement('span'); s.textContent = label; l.appendChild(s);
          var sl = document.createElement('select'); sl.className = 'draggo-ins-select';
          opts.forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); if (String(readG(group, key)) === String(o[0])) op.selected = true; sl.appendChild(op); });
          sl.addEventListener('change', function () { setG(group, key, sl.value); });
          l.appendChild(sl); dbody.appendChild(l);
        };
        var col = function (group, key, label) {
          var l = document.createElement('label'); l.className = 'draggo-tok-field';
          var s = document.createElement('span'); s.textContent = label; l.appendChild(s);
          var host = document.createElement('span');
          var cc = colorControl(readG(group, key), colorTokG, function () { var v = cc.get(); setG(group, key, v == null ? '' : v); });
          host.appendChild(cc.el); l.appendChild(host); dbody.appendChild(l);
        };

        sub('Fließtext');
        txt('body', 'fontFamily', 'Schriftart', 'Arial, sans-serif / var(--bld-font-…)');
        txt('body', 'fontSize', 'Größe', '16px / 1rem');
        col('body', 'color', 'Farbe');
        txt('body', 'lineHeight', 'Zeilenhöhe', '1.6');
        sel('body', 'fontWeight', 'Gewicht', WEIGHTS);
        txt('body', 'letterSpacing', 'Buchstabenabstand (px)', '0');
        sel('body', 'textTransform', 'Groß/Klein', TFORM);
        sel('body', 'fontStyle', 'Stil', FSTYLE);

        ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].forEach(function (tag) {
          sub('Überschrift ' + tag.toUpperCase());
          txt(tag, 'fontFamily', 'Schriftart', '');
          txt(tag, 'fontSize', 'Größe', tag === 'h1' ? '2.5rem' : '');
          sel(tag, 'fontWeight', 'Gewicht', WEIGHTS);
          col(tag, 'color', 'Farbe');
          txt(tag, 'lineHeight', 'Zeilenhöhe', '1.2');
          txt(tag, 'letterSpacing', 'Buchstabenabstand (px)', '');
          sel(tag, 'textTransform', 'Groß/Klein', TFORM);
        });

        sub('Links');
        col('link', 'color', 'Farbe');
        col('link', 'hoverColor', 'Hover-Farbe');
        sel('link', 'fontWeight', 'Gewicht', WEIGHTS);
        sel('link', 'textDecoration', 'Dekoration', DECO);
      };
      dsw.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () { defBp = b.dataset.bp; dsw.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x.dataset.bp === defBp); }); renderDefBody(); });
      });
      renderDefBody();

      var dsave = document.createElement('button'); dsave.type = 'button'; dsave.className = 'draggo-file-pick'; dsave.textContent = 'Standard-Stile speichern';
      dsave.addEventListener('click', function () {
        self.api('/defaults', 'POST', { defaults: def }).then(function (res) {
          self._defaults = def; self.applyGlobalsCss(res && res.css);
          self.toast(tr('Gespeichert.'));
        }, function (e) { self.fail(e); });
      });
      dform.appendChild(dsave);
      el.appendChild(dform);
    }

    loadPalette() {
      var self = this;
      return this.api('/palette').then(function (d) {
        // Licence tier drives which palette items are placeable and which
        // panels (globals / component library) the editor offers at all.
        self.license = d.license || { state: 'licensed', globals: true, library: true, ai: true };
        self.groups = d.groups || {};
        // Drop the redundant " (Draggo)" suffix — every item already sits in the
        // Draggo category, so repeating it on each label is noise.
        Object.keys(self.groups).forEach(function (g) {
          (self.groups[g] || []).forEach(function (it) { if (it.label) it.label = it.label.replace(/\s*\(Draggo\)\s*$/, ''); });
        });
        self.structures = d.structures || {};
        self.structuresLocked = d.structuresLocked || {};
        // Prebuilt sections are a paid feature — drop them entirely on tiers
        // without the library, the server rejects inserting them anyway.
        self.templates = (self.license && self.license.library === false) ? [] : (d.templates || []);
        self._blockTypes = d.blockTypes || [];
        // Recognise third-party grid systems (SubColumns, bootstrap-grid, …) so
        // their wrappers group into editable rows/columns in the canvas.
        var wt = d.wrapperTriples || {};
        if (wt.start) WSTART = wt.start.slice();
        if (wt.separator) WSEP = wt.separator.slice();
        if (wt.stop) WSTOP = wt.stop.slice();
        self.presetWidths = d.presetWidths || {};
        self._googleFonts = d.googleFonts || [];
        self._dynamicTags = d.dynamicTags || [];
        self._icons = d.icons || [];
        self._faIcons = d.faIcons || [];
        self._gfLoaded = {};
        self.labels = {};
        Object.keys(self.groups).forEach(function (g) {
          self.groups[g].forEach(function (it) { self.labels[it.type] = it.label; });
        });
        self.renderPalette();
      });
    }

    // ── Mutations ────────────────────────────────────────────────
    fail(err) {
      console.error('Draggo API error', err);
      var m = (err && err.error && (err.error.message || err.error.code)) || (err && err.message) || 'unbekannter Fehler';
      window.alert('Aktion fehlgeschlagen: ' + m);
    }
    reload() { return this.loadContent(); }

    // Dynamically load Google Fonts referenced by the current elements so a
    // newly-chosen font shows immediately (no full editor reload needed).
    ensureGoogleFonts() {
      var fams = this._googleFonts || [];
      if (!fams.length) return;
      var set = {}; fams.forEach(function (f) { set[f.toLowerCase()] = f; });
      var loaded = this._gfLoaded || (this._gfLoaded = {});
      var want = {};
      var scan = function (css) {
        if (!css) return;
        var re = /font-family:\s*([^;}]+)/g, m;
        while ((m = re.exec(css))) {
          var first = m[1].split(',')[0].replace(/['"]/g, '').trim();
          if (set[first.toLowerCase()] && !loaded[first]) want[first] = true;
        }
      };
      Object.keys(this._elemById || {}).forEach((id) => {
        var e = this._elemById[id];
        scan(e && e.styleCss); scan(e && e.scopedCss);
      });
      var families = Object.keys(want);
      if (!families.length) return;
      families.forEach(function (f) { loaded[f] = true; });
      var href = 'https://fonts.googleapis.com/css2?' +
        families.map(function (f) { return 'family=' + f.replace(/ /g, '+') + ':wght@300;400;500;600;700;800;900'; }).join('&') + '&display=swap';
      var link = document.createElement('link'); link.rel = 'stylesheet'; link.href = href;
      document.head.appendChild(link);
    }

    createContainerAt(after) {
      return this.api('/page/' + this.containerId + '/article', 'POST', { title: 'Container', after: after })
        .then(() => this.loadContent(), (e) => this.fail(e));
    }

    deleteContainer(articleId) {
      return this.api('/article/' + articleId, 'DELETE', {}).then(() => {
        if (this.active && this.active.sectionId === articleId) this.active = null;
        return this.loadContent();
      }, (e) => this.fail(e));
    }

    duplicateContainer(articleId) {
      return this.api('/article/' + articleId + '/duplicate', 'POST', {})
        .then(() => { this.toast(tr('Container dupliziert.')); return this.loadContent(); }, (e) => this.fail(e));
    }

    copyContainer(articleId, title) {
      try {
        localStorage.setItem('draggo_clip_container', JSON.stringify({ source: articleId, label: title || 'Container' }));
        this.toast(tr('Container kopiert.'));
      } catch (e) { this.toast('Kopieren fehlgeschlagen.'); }
    }

    containerClipboard() {
      try { return JSON.parse(localStorage.getItem('draggo_clip_container') || 'null'); } catch (e) { return null; }
    }

    /** Paste the copied container into THIS page (after $after, or at the end). */
    pasteContainer(after) {
      var clip = this.containerClipboard();
      if (!clip || !clip.source) { window.alert(tr('Kein Container in der Zwischenablage. Erst einen Container kopieren.')); return; }
      var body = { source: clip.source };
      if (after != null) body.after = after;
      return this.api('/page/' + this.containerId + '/paste-container', 'POST', body)
        .then(() => { this.toast(tr('Container eingefügt.')); return this.loadContent(); }, (e) => this.fail(e));
    }

    createElement(section, type, after, blocktype) {
      var self = this;
      var body = { type: type, after: after };
      if (blocktype) body.blocktype = blocktype;
      return this.api(this.prefix(section) + '/element', 'POST', body)
        .then(function (res) {
          // Open the editor straight away so the user must add content.
          return self.loadContent().then(function () { if (res && res.id) self.editElement(res.id, true); });
        }, (e) => this.fail(e));
    }

    addStructure(section, preset, after) {
      var custom = null;
      if (preset === 'custom') {
        custom = window.prompt('Spaltenbreiten 1–12 (Summe 12), z. B. 6,6 · 4,4,4 · 3,6,3 · 8,4:', '6,6');
        if (!custom) return Promise.resolve();
      }
      return this.api(this.prefix(section) + '/structure', 'POST', { preset: preset, custom: custom, after: after })
        .then(() => this.loadContent(), (e) => this.fail(e));
    }

    deleteElement(id) {
      return this.api('/element/' + id, 'DELETE', {}).then(() => {
        // If the edit pane was showing the just-deleted element, close it and
        // return to the content tab (otherwise a stale, unsaveable edit bar
        // lingers — Rudra bug #5).
        if (this._editId === id) {
          this.destroyEditors();
          this.showElementTab(false);
          this.switchTab('content');
          this._editId = null;
        }
        return this.loadContent();
      }, (e) => this.fail(e));
    }

    // ── Duplicate / Copy / Paste (Elementor-style) ───────────────────
    duplicateElement(id) {
      return this.api('/element/' + id + '/duplicate', 'POST', {}).then(() => this.loadContent(), (e) => this.fail(e));
    }

    copyToClipboard(id, label) {
      try {
        localStorage.setItem('draggo_clip', JSON.stringify({ source: id, label: label || 'Element' }));
        this.reflectClipboard();
        this.toast('Kopiert: ' + (label || 'Element'));
      } catch (e) { this.toast('Kopieren fehlgeschlagen.'); }
    }

    clipboard() {
      try { return JSON.parse(localStorage.getItem('draggo_clip') || 'null'); } catch (e) { return null; }
    }

    /** Toggle body.draggo-has-clip so every element's visible paste button
     *  appears the instant an element is on the clipboard (CSS-driven, no reload). */
    reflectClipboard() {
      var clip = this.clipboard();
      document.body.classList.toggle('draggo-has-clip', !!(clip && clip.source));
    }

    /** Paste the clipboard element into the column of $node, after $afterId. */
    pasteAfter(node, afterId) {
      var clip = this.clipboard();
      if (!clip || !clip.source) { window.alert('Zwischenablage ist leer. Erst ein Element kopieren.'); return; }
      var base;
      if (this.mode === 'unit') {
        base = '/unit/' + this.containerId;
      } else {
        var sectionEl = node ? node.closest('[data-section]') : null;
        var sectionId = sectionEl ? parseInt(sectionEl.dataset.section, 10) : this.containerId;
        base = '/article/' + sectionId;
      }
      var body = { source: clip.source };
      if (afterId != null) body.after = afterId;
      return this.api(base + '/paste', 'POST', body).then(() => this.loadContent(), (e) => this.fail(e));
    }

    toast(msg) {
      var t = document.createElement('div');
      t.className = 'draggo-toast';
      t.textContent = msg;
      document.body.appendChild(t);
      setTimeout(function () { t.classList.add('is-out'); }, 1400);
      setTimeout(function () { t.remove(); }, 1900);
    }

    showElementMenu(x, y, elm, node) {
      var self = this;
      document.querySelectorAll('.draggo-ctxmenu').forEach((m) => m.remove());
      var menu = document.createElement('div');
      menu.className = 'draggo-ctxmenu';
      var clip = this.clipboard();
      var add = function (icon, label, fn, disabled) {
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = '<i class="draggo-ic ' + icon + '" aria-hidden="true"></i> ' + esc(label);
        if (disabled) b.disabled = true;
        b.addEventListener('click', function (e) { e.stopPropagation(); menu.remove(); fn(); });
        menu.appendChild(b);
      };
      add('fas fa-pen', tr('Bearbeiten'), function () { self.editElement(elm.id); });
      add('fas fa-clone', tr('Duplizieren'), function () { self.duplicateElement(elm.id); });
      add('fas fa-copy', tr('Kopieren'), function () { self.copyToClipboard(elm.id, self.labels[elm.type] || elm.type); });
      add('fas fa-paste', tr('Einfügen') + (clip ? ' (' + (clip.label || 'Element') + ')' : ''), function () { self.pasteAfter(node, elm.id); }, !clip);
      add('fas fa-save', tr('Als Komponente speichern'), function () { self.saveAsComponent(elm); });
      add('fas fa-trash', tr('Löschen'), function () { if (window.confirm(tr('Element löschen?'))) self.deleteElement(elm.id); });
      menu.style.left = x + 'px';
      menu.style.top = y + 'px';
      document.body.appendChild(menu);
      var r = menu.getBoundingClientRect();
      if (r.right > window.innerWidth) menu.style.left = Math.max(4, x - r.width) + 'px';
      if (r.bottom > window.innerHeight) menu.style.top = Math.max(4, y - r.height) + 'px';
      var close = function (ev) { if (!menu.contains(ev.target)) { menu.remove(); document.removeEventListener('mousedown', close, true); } };
      setTimeout(function () { document.addEventListener('mousedown', close, true); }, 0);
    }

    deleteMany(ids) {
      return ids.reduce((p, id) => p.then(() => this.api('/element/' + id, 'DELETE', {})), Promise.resolve())
        .then(() => this.loadContent(), (e) => this.fail(e));
    }

    // ── Component library (Tier 4) ───────────────────────────────────
    loadComponents() {
      var self = this;
      return this.api('/components').then(function (d) {
        self._components = d.components || [];
        self.renderPalette();
      }, function () { self._components = []; });
    }

    insertComponent(componentId) {
      var s = this.activeSection();
      if (!s) { window.alert('Bitte zuerst eine Spalte oder Sektion anklicken (oder die Komponente in eine Spalte ziehen).'); return; }
      return this.insertComponentInto(componentId, s, this.active && this.active.after != null ? this.active.after : null);
    }

    /** Insert a component into a specific section, after $after (drag&drop). */
    insertComponentInto(componentId, section, after) {
      if (!section) return;
      var body = { component: componentId };
      if (after != null) body.after = after;
      return this.api(this.prefix(section) + '/component', 'POST', body).then(() => this.loadContent(), (e) => this.fail(e));
    }

    // ── Section templates (Vorlagen) ─────────────────────────────────
    clickTemplate(key) {
      // Page mode: a template is a WHOLE container (new tl_article + tree +
      // container background). Inserted after the active container, else at end.
      if (this.mode === 'page') {
        var after = (this.active && this.active.sectionId) ? this.active.sectionId : null;
        return this.api('/page/' + this.containerId + '/container-template', 'POST', { template: key, after: after })
          .then(() => this.loadContent(), (e) => this.fail(e));
      }
      // Unit mode (header/footer): no containers → insert the tree into the unit.
      var s = this.activeSection() || (this._sections && this._sections[0]);
      if (!s) { window.alert('Bitte zuerst einen Bereich anklicken.'); return; }
      return this.insertTemplateInto(key, s, this.active && this.active.after != null ? this.active.after : null);
    }

    /** Insert a prebuilt template tree into a section, after $after (drag&drop). */
    insertTemplateInto(key, section, after) {
      if (!section) return;
      var body = { template: key };
      if (after != null) body.after = after;
      return this.api(this.prefix(section) + '/template', 'POST', body).then(() => this.loadContent(), (e) => this.fail(e));
    }

    saveAsComponent(elm) {
      var title = window.prompt('Name der Komponente:', this.labels[elm.type] || elm.label || elm.type);
      if (title === null) return;
      return this.api('/element/' + elm.id + '/save-component', 'POST', { title: title, category: '' })
        .then(() => { this.toast('Als Komponente gespeichert.'); return this.loadComponents(); }, (e) => this.fail(e));
    }

    deleteComponent(id) {
      if (!window.confirm('Komponente aus der Bibliothek löschen?')) return;
      return this.api('/component/' + id, 'DELETE', {}).then(() => this.loadComponents(), (e) => this.fail(e));
    }

    /** Persist the full element order of a section straight from the DOM. */
    persistSection(section) {
      var body = document.querySelector('.draggo-section[data-section="' + section.id + '"] > .draggo-section-body');
      if (!body) return Promise.resolve();
      // Content (rows + chips) lives in the .draggo-section-inner wrapper, NOT
      // directly in the body — serialize THAT, or the order comes back empty and
      // the reorder silently wipes the drag (elements snap back on reload).
      var inner = body.querySelector(':scope > .draggo-section-inner') || body;
      var order = serializeList(inner);
      if (!order.length) return Promise.resolve(); // nothing to persist (don't no-op the server)
      return this.api(this.prefix(section) + '/reorder', 'POST', { order: order });
    }

    section(id) { return this._sections ? this._sections.find((s) => s.id === id) : null; }
    activeSection() { return this.active ? this.section(this.active.sectionId) : null; }

    clickAdd(type, blocktype) {
      var s = this.activeSection();
      if (!s) { window.alert('Bitte zuerst eine Spalte oder Sektion anklicken.'); return; }
      this.createElement(s, type, this.active.after, blocktype || null);
    }

    clickStructure(preset) {
      var s = this.activeSection() || (this._sections && this._sections[0]);
      if (!s) { window.alert('Bitte zuerst einen Container anlegen.'); return; }
      this.addStructure(s, preset, this.active ? this.active.after : null);
    }

    warn() { window.alert('Bitte zuerst eine Spalte oder Sektion anklicken.'); }

    // ── Chrome + palette ─────────────────────────────────────────
    renderChrome() {
      this.root.innerHTML =
        '<aside class="draggo-side">' +
          '<div class="draggo-tabs">' +
            '<button type="button" class="is-on" data-tab="content">Inhalt</button>' +
            '<button type="button" data-tab="layout">Layout</button>' +
            '<button type="button" data-tab="globals">Globals</button>' +
            '<button type="button" data-tab="element" hidden>Element</button>' +
          '</div>' +
          '<div class="draggo-pane" id="draggo-pane-content"></div>' +
          '<div class="draggo-pane" id="draggo-pane-layout" hidden></div>' +
          '<div class="draggo-pane" id="draggo-pane-globals" hidden></div>' +
          '<div class="draggo-pane" id="draggo-pane-tree" hidden></div>' +
          '<div class="draggo-pane" id="draggo-pane-ki" hidden></div>' +
          '<div class="draggo-pane" id="draggo-pane-help" hidden></div>' +
          '<div class="draggo-pane" id="draggo-pane-element" hidden></div>' +
        '</aside>' +
        '<main class="draggo-main">' +
        '<div class="draggo-toolbar">' +
          '<button type="button" id="draggo-side-toggle" title="Leiste einklappen" aria-label="Leiste einklappen"><i class="fas fa-angle-double-left" aria-hidden="true"></i></button>' +
          (this.mode === 'page' ? '<select id="draggo-page-switch" class="draggo-page-switch" title="Seite im selben Site-Root wechseln" aria-label="Seite wechseln"></select>' : '') +
          (this.mode === 'page' ? '<button id="draggo-add-container"><i class="fas fa-plus" aria-hidden="true"></i> Container am Ende</button>' : '') +
          '<span class="draggo-vp">' +
            '<button type="button" data-vw="full" title="Desktop" aria-label="Desktop"><i class="draggo-fa fas fa-desktop"></i></button>' +
            '<button type="button" data-vw="tablet" title="Tablet" aria-label="Tablet"><i class="draggo-fa fas fa-tablet-alt"></i></button>' +
            '<button type="button" data-vw="mobile" title="Mobil" aria-label="Mobil"><i class="draggo-fa fas fa-mobile-alt"></i></button>' +
          '</span>' +
          '<span class="draggo-vp-label" id="draggo-vp-label">Desktop</span>' +
          '<span class="draggo-tools">' +
            (this.mode === 'page' && this.previewUrl ? '<a id="draggo-preview" class="draggo-preview-btn" target="_blank" rel="noopener noreferrer" title="Live-Vorschau in neuem Tab"><i class="fas fa-eye" aria-hidden="true"></i> Vorschau</a>' : '') +
            '<button type="button" data-tab="tree" title="Struktur-Baum"><i class="fas fa-stream" aria-hidden="true"></i> Baum</button>' +
            '<button type="button" data-tab="ki" title="KI-Element-Generator"><i class="fas fa-robot" aria-hidden="true"></i> KI</button>' +
            '<button type="button" id="draggo-theme-toggle" title="Hell / Dunkel / Automatisch"><i class="fas fa-adjust" aria-hidden="true"></i></button>' +
            '<button type="button" data-tab="help" title="Hilfe &amp; Doku"><i class="fas fa-question-circle" aria-hidden="true"></i> Hilfe</button>' +
          '</span>' +
        '</div>' +
        '<div class="draggo-canvas-wrap"><div class="draggo-canvas" id="draggo-canvas"></div></div></main>';

      // Tab triggers live both in the left strip and the toolbar (Baum/KI).
      this.root.querySelectorAll('[data-tab]').forEach((b) => {
        b.addEventListener('click', () => this.switchTab(b.dataset.tab));
      });

      var addBtn = document.getElementById('draggo-add-container');
      if (addBtn) {
        addBtn.addEventListener('click', () => this.createContainerAt(null));
        // Paste a copied container at the end (also reaches an empty page).
        var pasteEnd = document.createElement('button');
        pasteEnd.type = 'button';
        pasteEnd.id = 'draggo-paste-container';
        pasteEnd.className = addBtn.className;
        pasteEnd.title = tr('Kopierten Container einfügen');
        pasteEnd.innerHTML = '<i class="fas fa-paste" aria-hidden="true"></i>';
        pasteEnd.addEventListener('click', () => this.pasteContainer(null));
        if (addBtn.parentNode) addBtn.parentNode.insertBefore(pasteEnd, addBtn.nextSibling);
      }

      // Page switcher: jump straight to another page in the same site root
      // without leaving the editor for the Contao backend tree.
      if (this.mode === 'page') this.loadPageSwitcher();

      // Live preview: open the page's real frontend URL in a new tab. href is
      // assigned as a property (URL, not HTML) so it can't inject markup.
      var prevBtn = document.getElementById('draggo-preview');
      if (prevBtn && this.previewUrl) prevBtn.href = this.previewUrl;

      // Collapsible left sidebar → edit/preview the canvas full-width. State is
      // persisted so a refresh keeps the user's choice.
      var sideBtn = document.getElementById('draggo-side-toggle');
      if (this._sideCollapsed === undefined) {
        try { this._sideCollapsed = localStorage.getItem('draggoSideCollapsed') === '1'; } catch (e) { this._sideCollapsed = false; }
      }
      this.applySidebar(this._sideCollapsed);
      if (sideBtn) sideBtn.addEventListener('click', () => this.toggleSidebar());

      // Editor theme toggle (auto → light → dark). data-theme on <html> wins
      // over the OS prefers-color-scheme; persisted in localStorage.
      var themeBtn = document.getElementById('draggo-theme-toggle');
      if (themeBtn) themeBtn.addEventListener('click', () => this.cycleTheme());
      if (this._theme === undefined) { try { this._theme = localStorage.getItem('draggoTheme') || ''; } catch (e) { this._theme = ''; } }
      this.applyTheme(this._theme);

      // Viewport preview width (Desktop / Tablet / Mobile) → central switch.
      this.root.querySelectorAll('.draggo-vp button').forEach((b) => {
        b.addEventListener('click', () => this.setViewport(b.dataset.vw));
      });

      var canvas = document.getElementById('draggo-canvas');
      // Restore the persisted viewport (button highlight, canvas state, badge).
      if (canvas) canvas.dataset.vw = this.vw;
      this.root.dataset.vw = this.vw;
      this.root.querySelectorAll('.draggo-vp button').forEach((x) => x.classList.toggle('is-on', x.dataset.vw === this.vw));
      var vpLabel = document.getElementById('draggo-vp-label');
      if (vpLabel) vpLabel.textContent = ({ full: 'Desktop', tablet: 'Tablet', mobile: 'Mobil' })[this.vw] || 'Desktop';
      if (canvas && this.mode === 'page') this.enableContainerDnd(canvas);
    }

    // Collapse / expand the left sidebar (canvas goes full-width).
    applySidebar(collapsed) {
      this._sideCollapsed = !!collapsed;
      this.root.classList.toggle('is-side-collapsed', this._sideCollapsed);
      var btn = document.getElementById('draggo-side-toggle');
      if (btn) {
        btn.classList.toggle('is-collapsed', this._sideCollapsed);
        var lbl = this._sideCollapsed ? tr('Leiste ausklappen') : tr('Leiste einklappen');
        btn.title = lbl; btn.setAttribute('aria-label', lbl);
        var ic = btn.querySelector('i');
        if (ic) ic.className = 'fas ' + (this._sideCollapsed ? 'fa-angle-double-right' : 'fa-angle-double-left');
      }
    }

    toggleSidebar() {
      this.applySidebar(!this._sideCollapsed);
      try { localStorage.setItem('draggoSideCollapsed', this._sideCollapsed ? '1' : '0'); } catch (e) {}
    }

    // Populate the toolbar page switcher with every page in the current site
    // root (tree order). Only regular pages are selectable (Draggo edits those);
    // other types show as disabled context rows. Selecting → load that editor.
    loadPageSwitcher() {
      var self = this;
      var sel = document.getElementById('draggo-page-switch');
      if (!sel) return;
      this.api('/page/' + this.containerId + '/siblings').then(function (d) {
        var pages = (d && d.pages) || [];
        if (!pages.length) { sel.hidden = true; return; }
        sel.innerHTML = '';
        pages.forEach(function (p) {
          var o = document.createElement('option');
          o.value = String(p.id);
          var indent = new Array(p.level + 1).join('   ');
          var name = p.title || ('#' + p.id);
          var editable = (p.type === 'regular');
          o.textContent = indent + (editable ? '' : '• ') + name + (p.published ? '' : ' · ' + tr('versteckt'));
          if (!editable) o.disabled = true;
          if (p.id === self.containerId) o.selected = true;
          sel.appendChild(o);
        });
        sel.addEventListener('change', function () {
          var id = parseInt(sel.value, 10);
          if (id && id !== self.containerId) { window.location.href = '/contao/draggo/edit/' + id; }
        });
      }, function () { sel.hidden = true; });
    }

    switchTab(name) {
      this.root.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('is-on', b.dataset.tab === name));
      ['content', 'layout', 'globals', 'tree', 'ki', 'help', 'element'].forEach(function (n) {
        var p = document.getElementById('draggo-pane-' + n);
        if (p) p.hidden = n !== name;
      });
      if (name === 'globals') this.renderGlobals();
      if (name === 'ki') this.renderKi();
      if (name === 'tree') this.renderOutline();
      if (name === 'help') this.renderHelp();
    }

    // ── Help: searchable docs + grounded chatbot ─────────────────────
    renderHelp() {
      var self = this;
      var el = document.getElementById('draggo-pane-help');
      if (!el || el._built) { return; }
      el._built = true;
      el.innerHTML =
        '<div class="draggo-help">' +
          '<input type="search" class="draggo-help-search" placeholder="Doku durchsuchen …">' +
          '<div class="draggo-help-docs"><p class="draggo-help-empty">Lädt …</p></div>' +
          '<div class="draggo-help-chat">' +
            '<div class="draggo-help-log" id="draggo-help-log"></div>' +
            '<div class="draggo-help-ask">' +
              '<textarea class="draggo-help-q" rows="2" placeholder="Frag die KI – z. B. wie baue ich einen Horizontal-Scroll?"></textarea>' +
              '<button type="button" class="draggo-help-send">Fragen</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      var docsBox = el.querySelector('.draggo-help-docs');
      var search = el.querySelector('.draggo-help-search');
      var log = el.querySelector('#draggo-help-log');
      var q = el.querySelector('.draggo-help-q');
      var send = el.querySelector('.draggo-help-send');
      self._helpChat = self._helpChat || [];

      var renderDocs = function (filter) {
        var secs = self._helpDocs || [];
        var f = (filter || '').trim().toLowerCase();
        var list = f ? secs.filter(function (s) { return (s.title + ' ' + s.keywords + ' ' + s.body).toLowerCase().indexOf(f) >= 0; }) : secs;
        if (!list.length) { docsBox.innerHTML = '<p class="draggo-help-empty">Nichts gefunden.</p>'; return; }
        docsBox.innerHTML = list.map(function (s) {
          return '<details class="draggo-help-sec"><summary><span class="draggo-help-cat">' + esc(s.cat) + '</span>' + esc(s.title) + '</summary>' +
            '<div class="draggo-help-body">' + esc(s.body).replace(/\n/g, '<br>') + '</div></details>';
        }).join('');
      };

      // Load docs once.
      if (self._helpDocs) { renderDocs(''); }
      else {
        self.api('/docs', 'GET').then(function (d) {
          self._helpDocs = d.sections || [];
          self._helpChatReady = !!d.chatReady;
          if (!self._helpChatReady) { q.placeholder = 'KI nicht konfiguriert — Doku oben durchsuchbar.'; q.disabled = true; send.disabled = true; }
          renderDocs('');
        }, function () { docsBox.innerHTML = '<p class="draggo-help-empty">Doku konnte nicht geladen werden.</p>'; });
      }

      var renderLog = function () {
        log.innerHTML = self._helpChat.map(function (m) {
          var cls = m.role === 'user' ? 'user' : (m.role === 'error' ? 'err' : 'bot');
          var body = esc(m.content).replace(/\n/g, '<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
          var src = (m.sources && m.sources.length) ? '<div class="draggo-help-src">Quellen: ' + m.sources.map(esc).join(', ') + '</div>' : '';
          return '<div class="draggo-help-msg draggo-help-' + cls + '">' + body + src + '</div>';
        }).join('') + (self._helpBusy ? '<div class="draggo-help-msg draggo-help-bot draggo-help-typing">…</div>' : '');
        log.scrollTop = log.scrollHeight;
      };

      var ask = function () {
        var text = q.value.trim();
        if (!text || self._helpBusy) { return; }
        self._helpChat.push({ role: 'user', content: text });
        q.value = ''; self._helpBusy = true; renderLog();
        self.api('/docs/chat', 'POST', { history: self._helpChat.map(function (m) { return { role: m.role === 'user' ? 'user' : 'assistant', content: m.content }; }).filter(function (m) { return m.role !== 'error'; }) })
          .then(function (r) {
            self._helpBusy = false;
            self._helpChat.push({ role: 'assistant', content: r.text || '(keine Antwort)', sources: r.sources || [] });
            renderLog();
          }, function (e) {
            self._helpBusy = false;
            self._helpChat.push({ role: 'error', content: 'Fehler: ' + ((e && e.error && e.error.message) || 'Anfrage fehlgeschlagen') });
            renderLog();
          });
      };

      search.addEventListener('input', function () { renderDocs(search.value); });
      send.addEventListener('click', ask);
      q.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); ask(); } });
      renderLog();
    }

    // ── KI element generator (conversational) ────────────────────────
    renderKi() {
      var el = document.getElementById('draggo-pane-ki');
      if (!el) return;
      var self = this;

      // First open: probe availability, seed a greeting.
      if (this._kiReady === undefined) {
        el.innerHTML = '<h2>KI-Element-Generator</h2><p class="draggo-hint">Lade …</p>';
        this.api('/agent/status').then(function (d) {
          self._kiReady = !!d.ready;
          self._kiCanUse = d.canUse !== false;
          self._kiCanDelete = !!d.canDelete;
          self._kiTypes = d.types || [];
          // No permission → hide the whole KI tab.
          if (!self._kiCanUse) {
            var tabBtn = self.root.querySelector('[data-tab="ki"]');
            if (tabBtn) tabBtn.hidden = true;
          }
          self._kiHistory = self._kiHistory || [];
          if (self._kiReady && !self._kiHistory.length) {
            self._kiHistory.push({ role: 'assistant', content: tr('Hi! Ich baue mit dir ein eigenes Content-Element. Sag mir grob, was es sein soll (z. B. „Logo-Carousel") — ich schlage dir gleich einen kompletten Vorschlag mit allen sinnvollen Optionen vor.') });
          }
          self.renderKi();
        }, function () { self._kiReady = false; self.renderKi(); });
        return;
      }

      var h = '<h2>KI-Element-Generator</h2>';
      if (this._kiCanUse === false) {
        el.innerHTML = h + '<p class="draggo-hint">Keine Berechtigung. Ein Administrator muss deiner Benutzergruppe das Draggo-Recht „KI-Element-Generator nutzen" geben.</p>';
        return;
      }
      if (!this._kiReady) {
        el.innerHTML = h + '<p class="draggo-hint">KI ist nicht konfiguriert. Trage deinen Claude-API-Schlüssel im Backend unter <strong>Draggo → Einstellungen</strong> ein.</p>';
        return;
      }
      // Toolbar: new element + edit-mode banner.
      h += '<div class="draggo-ki-bar"><button type="button" id="draggo-ki-new"><i class="fas fa-plus" aria-hidden="true"></i> Neues Element</button>';
      if (this._kiEditType) {
        h += '<span class="draggo-ki-editing"><i class="fas fa-pen" aria-hidden="true"></i> Bearbeite: ' + esc(this._kiEditLabel || this._kiEditType) + '</span>';
      }
      h += '</div>';
      // Whole page from a brief (page mode only).
      if (this.mode === 'page') {
        h += '<details class="draggo-cat draggo-ins-acc draggo-ki-brief"><summary><i class="fas fa-magic" aria-hidden="true"></i> Ganze Seite aus Brief</summary><div class="draggo-ins-accbody">' +
          '<textarea id="draggo-brief" rows="3" placeholder="z. B. Landingpage für ein Fitnessstudio mit Angeboten, Preisen, Team und Kontakt"></textarea>' +
          '<button type="button" class="draggo-file-pick" id="draggo-brief-go">Seite generieren</button>' +
          '<p class="draggo-hint">KI wählt passende Vorlagen UND schreibt echte Texte. Inhalte danach anpassen.</p></div></details>';
        // Build a page from the user's OWN declared content.
        h += '<details class="draggo-cat draggo-ins-acc draggo-ki-inject"><summary><i class="fas fa-list" aria-hidden="true"></i> Seite aus eigenen Inhalten</summary><div class="draggo-ins-accbody">' +
          '<textarea id="draggo-inject" rows="6" placeholder="Füge deine Inhalte ein und beschrifte sie, z. B.:\n\nÜberschrift: Nachhaltige Möbel aus Münster\nÜber uns: Wir fertigen seit 2008 …\nLeistungen: Tischlerei, Restaurierung, Beratung\nKontakt: info@…, 0251 …"></textarea>' +
          '<button type="button" class="draggo-file-pick" id="draggo-inject-go">Aus meinen Inhalten bauen</button>' +
          '<p class="draggo-hint">Die KI ordnet GENAU deine Inhalte passenden Sektionen zu — erfindet nichts dazu.</p></div></details>';
      }
      h += '<div class="draggo-ki-log" id="draggo-ki-log"></div>';
      if (this._kiSpec) {
        h += '<div class="draggo-ki-preview"><div class="draggo-ki-preview-head">Vorschau: ' + esc(this._kiSpec.label || '') + '</div>' +
          '<div class="draggo-ki-preview-body">' + (this._kiPreviewHtml || '') + '</div>' +
          '<div class="draggo-ki-actions"><button type="button" id="draggo-ki-commit" class="draggo-file-pick"' + (this._kiBusy ? ' disabled' : '') + '>' + (this._kiBusy ? 'Speichert …' : '<i class="fas fa-check" aria-hidden="true"></i> Übernehmen') + '</button>' +
          '<button type="button" id="draggo-ki-discard"' + (this._kiBusy ? ' disabled' : '') + '>Verwerfen</button></div></div>';
      }
      h += '<div class="draggo-ki-thumbs" id="draggo-ki-thumbs"></div>';
      h += '<div class="draggo-ki-input">' +
        '<button type="button" id="draggo-ki-attach" class="draggo-ki-attachbtn" title="Screenshot/Bild anhängen"' + (this._kiBusy ? ' disabled' : '') + '><i class="fas fa-paperclip" aria-hidden="true"></i></button>' +
        '<input type="file" id="draggo-ki-file" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden>' +
        '<textarea id="draggo-ki-text" rows="2" placeholder="Antwort … (Strg+Enter sendet)"' + (this._kiBusy ? ' disabled' : '') + '></textarea>' +
        '<button type="button" id="draggo-ki-send" class="draggo-file-pick"' + (this._kiBusy ? ' disabled' : '') + '>' + (this._kiBusy ? '…' : 'Senden') + '</button></div>';
      // Existing AI element types → edit / delete.
      var tps = this._kiTypes || [];
      if (tps.length) {
        h += '<details class="draggo-cat draggo-ki-types"><summary>Meine KI-Elemente <span class="draggo-cat-n">' + tps.length + '</span></summary><div class="draggo-ki-typelist">';
        tps.forEach(function (t) {
          h += '<div class="draggo-ki-typerow"><span class="draggo-ki-typelabel">' + blockIcon(t.icon) + ' ' + esc(t.label || t.type) + '</span>' +
            '<button type="button" class="draggo-ki-edit" data-type="' + esc(t.type) + '" data-label="' + esc(t.label || t.type) + '" title="Bearbeiten"><i class="fas fa-pen" aria-hidden="true"></i></button>' +
            (self._kiCanDelete ? '<button type="button" class="draggo-ki-del" data-type="' + esc(t.type) + '" title="Löschen"><i class="fas fa-times" aria-hidden="true"></i></button>' : '') + '</div>';
        });
        h += '</div></details>';
      }
      el.innerHTML = h;

      var newBtn = el.querySelector('#draggo-ki-new');
      if (newBtn) newBtn.addEventListener('click', function () { self.kiNew(); });
      var briefGo = el.querySelector('#draggo-brief-go');
      if (briefGo) briefGo.addEventListener('click', function () { self.kiFromBrief(); });
      var injectGo = el.querySelector('#draggo-inject-go');
      if (injectGo) injectGo.addEventListener('click', function () { self.kiFromContent(); });
      el.querySelectorAll('.draggo-ki-edit').forEach(function (b) {
        b.addEventListener('click', function () { self.kiEdit(b.dataset.type, b.dataset.label); });
      });
      el.querySelectorAll('.draggo-ki-del').forEach(function (b) {
        b.addEventListener('click', function () { self.kiDeleteType(b.dataset.type); });
      });

      var log = el.querySelector('#draggo-ki-log');
      (this._kiHistory || []).forEach(function (m) {
        var d = document.createElement('div');
        var kind = m.role === 'user' ? 'user' : (m.role === 'error' ? 'err' : 'bot');
        d.className = 'draggo-ki-msg draggo-ki-' + kind;
        d.textContent = m.content;
        log.appendChild(d);
      });
      // Typing/working indicator while the model is thinking.
      if (this._kiBusy) {
        var t = document.createElement('div');
        t.className = 'draggo-ki-msg draggo-ki-bot draggo-ki-typing';
        t.innerHTML = '<span></span><span></span><span></span>';
        t.setAttribute('aria-label', 'KI arbeitet …');
        log.appendChild(t);
      }
      log.scrollTop = log.scrollHeight;

      var ta = el.querySelector('#draggo-ki-text');
      // Image attach (Claude vision: screenshot → element).
      self._kiImages = self._kiImages || [];
      var thumbs = el.querySelector('#draggo-ki-thumbs');
      var renderThumbs = function () {
        if (!thumbs) return;
        thumbs.innerHTML = '';
        self._kiImages.forEach(function (du, idx) {
          var t = document.createElement('span'); t.className = 'draggo-ki-thumb'; t.style.backgroundImage = 'url("' + du + '")';
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.title = tr('Entfernen');
          x.addEventListener('click', function () { self._kiImages.splice(idx, 1); renderThumbs(); });
          t.appendChild(x); thumbs.appendChild(t);
        });
      };
      renderThumbs();
      var fileInp = el.querySelector('#draggo-ki-file');
      var attachBtn = el.querySelector('#draggo-ki-attach');
      if (attachBtn && fileInp) {
        attachBtn.addEventListener('click', function () { fileInp.click(); });
        fileInp.addEventListener('change', function () {
          [].slice.call(fileInp.files).forEach(function (f) {
            if (!/^image\//.test(f.type)) return;
            if (self._kiImages.length >= 4) { self.toast(tr('Maximal 4 Bilder.')); return; }
            if (f.size > 5 * 1024 * 1024) { self.toast(tr('Bild zu groß (max 5 MB).')); return; }
            var rd = new FileReader();
            rd.onload = function () { self._kiImages.push(rd.result); renderThumbs(); };
            rd.readAsDataURL(f);
          });
          fileInp.value = '';
        });
      }
      var doSend = function () { var t = (ta.value || '').trim(); if (!t && !self._kiImages.length) return; ta.value = ''; self.kiSend(t); };
      el.querySelector('#draggo-ki-send').addEventListener('click', doSend);
      if (ta) ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); doSend(); } });
      var commit = el.querySelector('#draggo-ki-commit');
      if (commit) commit.addEventListener('click', function () { self.kiCommit(); });
      var discard = el.querySelector('#draggo-ki-discard');
      if (discard) discard.addEventListener('click', function () { self._kiSpec = null; self._kiPreviewHtml = ''; self.renderKi(); });

      // Run the element's own JS against the preview root so interactive blocks
      // (carousels, tabs, accordions …) actually lay out — mirrors the frontend
      // contract in BlockController (var el = <root>; try { spec.js } catch).
      // Without this a slider renders as un-initialised, collapsed slides.
      var pvRoot = el.querySelector('.draggo-blk-preview');
      if (pvRoot && this._kiSpec && this._kiSpec.js) {
        try { (new Function('el', this._kiSpec.js))(pvRoot); } catch (e) { /* preview only — ignore draft JS errors */ }
      }
    }

    kiSend(text) {
      var self = this;
      var imgs = (this._kiImages || []).slice();
      // Image attached without text → default "rebuild this" prompt.
      if (!text && imgs.length) text = tr('Bau dieses Element/diese Sektion aus dem angehängten Bild nach.');
      this._kiHistory.push({ role: 'user', content: text + (imgs.length ? ' (Bild ×' + imgs.length + ')' : '') });
      this._kiImages = [];
      this._kiBusy = true;
      this.renderKi();
      var body = { messages: this._kiHistory, lang: DRAGGO_LANG };
      if (this._kiEditType) body.editType = this._kiEditType;
      if (imgs.length) body.images = imgs;
      this.api('/agent/chat', 'POST', body).then(function (res) {
        self._kiBusy = false;
        if (res.status === 'spec') {
          if (res.text) self._kiHistory.push({ role: 'assistant', content: res.text });
          self._kiSpec = res.spec;
          self._kiPreviewHtml = res.preview || '';
        } else if (res.status === 'error') {
          if (res.text) self._kiHistory.push({ role: 'assistant', content: res.text });
          self._kiHistory.push({ role: 'error', content: '⚠ ' + tr('Entwurf abgelehnt:') + ' ' + (res.message || '?') + '. ' + tr('Bitte korrigieren und erneut versuchen.') });
        } else {
          self._kiHistory.push({ role: 'assistant', content: res.text || '…' });
        }
        self.renderKi();
      }, function (e) {
        self._kiBusy = false;
        self._kiHistory.push({ role: 'assistant', content: 'Fehler: ' + ((e && e.message) || e) });
        self.renderKi();
      });
    }

    kiCommit() {
      var self = this;
      if (!this._kiSpec || this._kiBusy) return;
      var wasEdit = !!this._kiEditType;
      this._kiBusy = true;
      this.renderKi();
      this.api('/agent/commit', 'POST', { spec: this._kiSpec }).then(function (res) {
        self._kiBusy = false;
        self.toast('„' + (res.label || '') + '" ' + (wasEdit ? tr('aktualisiert.') : tr('erstellt.')));
        self._kiHistory.push({ role: 'assistant', content: '✓ „' + (res.label || '') + '" — ' + tr('in der Palette (Tab „Inhalt") unter „KI-Elemente". Noch etwas ändern oder ein neues bauen?') });
        self._kiSpec = null; self._kiPreviewHtml = '';
        return self._refreshKiTypes();
      }, function (e) { self._kiBusy = false; self.renderKi(); self.fail(e); });
    }

    kiFromBrief() {
      var ta = document.getElementById('draggo-brief');
      if (!ta) return;
      var brief = (ta.value || '').trim();
      if (!brief) { return; }
      var btn = document.getElementById('draggo-brief-go');
      if (btn) { btn.disabled = true; btn.textContent = tr('Generiere …'); }
      var self = this;
      this.api('/page/' + this.containerId + '/from-brief', 'POST', { brief: brief, lang: DRAGGO_LANG }).then(function (d) {
        if (btn) { btn.disabled = false; btn.textContent = tr('Seite generieren'); }
        if (!d || !d.created) { self.toast(tr('Konnte keine Seite planen — bitte Brief konkreter formulieren.')); return; }
        self.toast(d.created + ' ' + tr('Container erstellt.'));
        self.switchTab('content');
        self.loadContent();
      }, function (e) { if (btn) { btn.disabled = false; btn.textContent = tr('Seite generieren'); } self.fail(e); });
    }

    kiFromContent() {
      var ta = document.getElementById('draggo-inject');
      if (!ta) return;
      var content = (ta.value || '').trim();
      if (!content) { return; }
      var btn = document.getElementById('draggo-inject-go');
      if (btn) { btn.disabled = true; btn.textContent = tr('Baue …'); }
      var self = this;
      this.api('/page/' + this.containerId + '/from-content', 'POST', { content: content, lang: DRAGGO_LANG }).then(function (d) {
        if (btn) { btn.disabled = false; btn.textContent = tr('Aus meinen Inhalten bauen'); }
        if (!d || !d.created) { self.toast(tr('Konnte keine Seite bauen — bitte Inhalte deutlicher beschriften.')); return; }
        self.toast(d.created + ' ' + tr('Container erstellt.'));
        self.switchTab('content');
        self.loadContent();
      }, function (e) { if (btn) { btn.disabled = false; btn.textContent = tr('Aus meinen Inhalten bauen'); } self.fail(e); });
    }

    kiNew() {
      this._kiEditType = null; this._kiEditLabel = null; this._kiSpec = null; this._kiPreviewHtml = '';
      this._kiHistory = [{ role: 'assistant', content: tr('Neues Element — sag mir grob, was es sein soll (z. B. „Team-Karten" oder „Bild-Slider"). Oder häng per 📎 einen Screenshot einer Sektion an, die ich nachbauen soll. Ich mache dir einen kompletten Vorschlag.') }];
      this.renderKi();
    }

    kiEdit(type, label) {
      this._kiEditType = type; this._kiEditLabel = label || type;
      this._kiSpec = null; this._kiPreviewHtml = '';
      this._kiHistory = [{ role: 'assistant', content: tr('Bearbeitungsmodus für') + ' „' + (label || type) + '". ' + tr('Was möchtest du ändern? (z. B. eine Option ergänzen, Farben, Layout, Felder)') }];
      this.renderKi();
    }

    kiDeleteType(type) {
      if (!window.confirm('Diesen KI-Element-Typ löschen? Bereits platzierte Instanzen werden dann nicht mehr gerendert.')) return;
      var self = this;
      this.api('/agent/type/' + encodeURIComponent(type), 'DELETE', {}).then(function () {
        if (self._kiEditType === type) { self._kiEditType = null; self._kiEditLabel = null; }
        return self._refreshKiTypes();
      }, function (e) { self.fail(e); });
    }

    _refreshKiTypes() {
      var self = this;
      return this.api('/agent/status').then(function (d) {
        self._kiTypes = d.types || [];
        return self.loadPalette().then(function () { self.renderKi(); });
      }, function () { self.renderKi(); });
    }

    // ── Restore points (container history) ───────────────────────────
    renderHistory(host, articleId) {
      var self = this;
      var box = document.createElement('details');
      box.className = 'draggo-cat draggo-ins-acc draggo-hist';
      box.innerHTML = '<summary>Wiederherstellungspunkte</summary><div class="draggo-ins-accbody">' +
        '<button type="button" class="draggo-file-pick" data-hist-save>Punkt sichern</button>' +
        '<div class="draggo-hist-list"><span class="draggo-hint">…</span></div></div>';
      host.appendChild(box);
      var listEl = box.querySelector('.draggo-hist-list');

      var load = function () {
        self.api('/article/' + articleId + '/history').then(function (d) {
          var pts = d.points || [];
          if (!pts.length) { listEl.innerHTML = '<span class="draggo-hint">Noch keine Punkte.</span>'; return; }
          listEl.innerHTML = '';
          pts.forEach(function (p) {
            var row = document.createElement('div'); row.className = 'draggo-hist-row';
            row.innerHTML = '<span class="draggo-hist-label">' + esc(p.label) + '<br><small>' + esc(new Date(p.tstamp * 1000).toLocaleString()) + '</small></span>' +
              '<button type="button" class="draggo-hist-restore" data-id="' + p.id + '">Wiederherstellen</button>';
            listEl.appendChild(row);
          });
          listEl.querySelectorAll('.draggo-hist-restore').forEach(function (b) {
            b.addEventListener('click', function () {
              if (!window.confirm(tr('Diesen Stand wiederherstellen? Der aktuelle Stand wird vorher gesichert.'))) return;
              self.api('/history/' + b.dataset.id + '/restore', 'POST', {}).then(function () { self.toast(tr('Wiederhergestellt.')); self.loadContent(); }, function (e) { self.fail(e); });
            });
          });
        }, function () { listEl.innerHTML = '<span class="draggo-hint">Verlauf nicht verfügbar (contao:migrate?).</span>'; });
      };

      box.querySelector('[data-hist-save]').addEventListener('click', function () {
        var label = window.prompt(tr('Name des Wiederherstellungspunkts:'), '');
        if (label === null) return;
        self.api('/article/' + articleId + '/history/snapshot', 'POST', { label: label }).then(function () { self.toast(tr('Punkt gesichert.')); if (box.open) load(); else box.open = true; }, function (e) { self.fail(e); });
      });
      box.addEventListener('toggle', function () { if (box.open) load(); });
    }

    // ── Structure outline / navigator ────────────────────────────────
    renderOutline() {
      var pane = document.getElementById('draggo-pane-tree');
      if (!pane) return;
      var self = this;
      pane.innerHTML = '<h2>Struktur</h2><p class="draggo-hint">Klick = im Editor anspringen & auswählen.</p>';
      var secs = this._sections || [];
      if (!secs.length) { pane.innerHTML += '<p class="draggo-hint">Keine Container.</p>'; return; }
      var tree = document.createElement('div');
      tree.className = 'draggo-tree';
      secs.forEach(function (section) {
        tree.appendChild(self._treeNode(faHtml('fas fa-folder'), section.title || ('Container #' + section.id), 0, function () {
          self._outlineGo(section.id, null, 'container', section.id);
        }));
        self._treeWalk(section, buildTree(section.elements || []), 1, tree);
      });
      pane.appendChild(tree);
    }

    _treeWalk(section, nodes, depth, tree) {
      var self = this;
      nodes.forEach(function (n) {
        if (n.k === 'el') {
          var lbl = self.labels[n.el.type] || n.el.type;
          if (n.el.type === 'draggo_block' && n.el.blocktype) {
            var bt = (self._blockTypes || []).filter(function (b) { return b.type === n.el.blocktype; })[0];
            if (bt) lbl = bt.label;
          }
          tree.appendChild(self._treeNode(iconHtml(n.el.type), lbl, depth, function () {
            self.switchTab('content'); self.editElement(n.el.id);
          }));
          return;
        }
        var r = n.r;
        tree.appendChild(self._treeNode(faHtml('fas fa-table'), tr('Reihe'), depth, function () { self._outlineGo(section.id, null, 'row', r.startId); }));
        r.columns.forEach(function (col, i) {
          var openId = r.colOpenIds[i];
          tree.appendChild(self._treeNode(faHtml('fas fa-columns'), tr('Spalte') + ' ' + (i + 1), depth + 1, function () { self._outlineGo(section.id, openId, 'col', openId); }));
          self._treeWalk(section, col, depth + 2, tree);
        });
      });
    }

    _treeNode(icon, label, depth, onClick) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'draggo-tree-node';
      b.style.paddingLeft = (0.4 + depth * 0.85) + 'rem';
      // icon is trusted FA markup (faHtml/iconHtml), label is user data → escaped.
      b.innerHTML = '<span class="draggo-tree-ic">' + icon + '</span><span class="draggo-tree-lb">' + esc(label) + '</span>';
      b.addEventListener('click', onClick);
      return b;
    }

    _outlineGo(sectionId, after, scope, targetId) {
      // selectTarget already highlights + scrolls the node into view.
      this.selectTarget(sectionId, after, scope, targetId);
    }

    showElementTab(show) {
      var b = this.root.querySelector('.draggo-tabs button[data-tab="element"]');
      if (b) b.hidden = !show;
    }

    enableContainerDnd(canvas) {
      canvas.addEventListener('dragover', (e) => {
        if (!this.drag || this.drag.kind !== 'container') return;
        e.preventDefault();
        var dragged = canvas.querySelector('.draggo-section.is-dragging');
        if (!dragged) return;
        var after = afterSection(canvas, e.clientY, dragged);
        if (after == null) {
          // place before the trailing insert bar if present
          var lastBar = canvas.querySelector('.draggo-insert:last-child');
          if (lastBar) canvas.insertBefore(dragged, lastBar); else canvas.appendChild(dragged);
        } else {
          canvas.insertBefore(dragged, after);
        }
      });
      canvas.addEventListener('drop', (e) => {
        if (!this.drag || this.drag.kind !== 'container') return;
        e.preventDefault();
      });
    }

    renderPalette() {
      var el = document.getElementById('draggo-pane-content');
      if (!el) return;

      var self = this;
      var view = this._paletteView || 'elements';
      var groupLabels = { draggo: 'Draggo', text: 'Text', texts: 'Text', links: 'Links', media: 'Medien', files: 'Dateien', includes: 'Einbindungen' };
      // Draggo group first, rest in registry order.
      var gkeys = Object.keys(this.groups).sort(function (x, y) { return x === 'draggo' ? -1 : (y === 'draggo' ? 1 : 0); });

      // Sub-tab bar (Elemente / Strukturen / Komponenten).
      var html = '<div class="draggo-subtabs">' +
        ['elements', 'Elemente', 'structures', 'Strukturen', 'components', 'Komponenten'].reduce(function (acc, _, i, arr) {
          if (i % 2) return acc;
          var v = arr[i]; return acc + '<button type="button" data-view="' + v + '"' + (view === v ? ' class="is-on"' : '') + '>' + arr[i + 1] + '</button>';
        }, '') + '</div>';

      // ── Elemente view: search + category accordions ──
      html += '<div class="draggo-pal-view" data-view="elements"' + (view !== 'elements' ? ' hidden' : '') + '>' +
        '<input type="search" class="draggo-pal-search" placeholder="Element suchen …">';
      gkeys.forEach(function (group) {
        var items = self.groups[group].filter(function (it) { return !isWrapper(it.type); });
        if (!items.length) return;
        var label = groupLabels[group] || group;
        html += '<details class="draggo-cat"' + (group === 'draggo' ? ' open' : '') + '><summary>' + esc(label) + ' <span class="draggo-cat-n">' + items.length + '</span></summary><div class="draggo-elements">';
        items.forEach(function (it) {
          // Locked = not covered by the current licence tier. Shown (not
          // hidden) so the user sees what the full version adds; the click is
          // intercepted below and the server rejects it regardless.
          var lock = it.locked ? ' is-locked' : '';
          html += '<button class="draggo-palette-item' + lock + '" draggable="' + (it.locked ? 'false' : 'true') + '" data-type="' + esc(it.type) + '"' +
            (it.locked ? ' data-locked="1"' : '') +
            ' data-label="' + esc((it.label || '').toLowerCase()) + '">' +
            '<span class="draggo-pi-icon">' + iconHtml(it.type) + '</span>' +
            '<span class="draggo-pi-label">' + esc(it.label) + '</span>' +
            (it.locked ? '<span class="draggo-pi-lock" title="' + esc(tr('Nur in der Vollversion')) + '">🔒</span>' : '') +
            '</button>';
        });
        html += '</div></details>';
      });
      // KI-generated element types (data-driven block types).
      var bts = this._blockTypes || [];
      if (bts.length) {
        html += '<details class="draggo-cat" open><summary>KI-Elemente <span class="draggo-cat-n">' + bts.length + '</span></summary><div class="draggo-elements">';
        bts.forEach(function (b) {
          html += '<button class="draggo-palette-item" draggable="true" data-type="draggo_block" data-blocktype="' + esc(b.type) + '" data-label="' + esc((b.label || '').toLowerCase()) + '">' +
            '<span class="draggo-pi-icon">' + blockIcon(b.icon) + '</span>' +
            '<span class="draggo-pi-label">' + esc(b.label) + '</span></button>';
        });
        html += '</div></details>';
      }
      html += '</div>';

      // ── Strukturen view: grid presets (Spalten-Layouts) + Templates ──
      html += '<div class="draggo-pal-view" data-view="structures"' + (view !== 'structures' ? ' hidden' : '') + '>';
      // 1) Bare column layouts.
      html += '<details class="draggo-cat" open><summary>Spalten-Layouts <span class="draggo-cat-n">' + Object.keys(this.structures).length + '</span></summary>' +
        '<p class="draggo-hint">Klick = in aktive Spalte/Container · oder auf eine Spalte ziehen.</p><div class="draggo-structures">';
      var lockedStructures = this.structuresLocked || {};
      Object.keys(this.structures).forEach(function (key) {
        var lk = !!lockedStructures[key];
        html += '<button class="draggo-structure-item' + (lk ? ' is-locked' : '') + '" draggable="' + (lk ? 'false' : 'true') + '" data-preset="' + esc(key) + '"' +
          (lk ? ' data-locked="1"' : '') + '>' + esc(self.structures[key]) + (lk ? ' 🔒' : '') + '</button>';
      });
      html += '</div></details>';
      // 2) Prebuilt container templates, grouped by category.
      var tpls = this.templates || [];
      if (tpls.length) {
        var cats = {};
        tpls.forEach(function (t) { (cats[t.category] = cats[t.category] || []).push(t); });
        Object.keys(cats).forEach(function (cat) {
          html += '<details class="draggo-cat"><summary>' + esc(cat) + ' <span class="draggo-cat-n">' + cats[cat].length + '</span></summary>' +
            '<p class="draggo-hint">Klick = fertiger Container · oder in eine Spalte ziehen (nur Inhalt).</p><div class="draggo-templates">';
          cats[cat].forEach(function (t) {
            html += '<button class="draggo-template-item" draggable="true" data-key="' + esc(t.key) + '" title="' + esc(t.desc || '') + '">' +
              '<span class="draggo-tpl-icon">' + faHtml(t.icon || 'fas fa-cube') + '</span>' +
              '<span class="draggo-tpl-label">' + esc(t.title) + '</span></button>';
          });
          html += '</div></details>';
        });
      }
      html += '</div>';

      // ── Komponenten view ──
      var comps = this._components || [];
      html += '<div class="draggo-pal-view" data-view="components"' + (view !== 'components' ? ' hidden' : '') + '>';
      if (!comps.length) {
        html += '<p class="draggo-hint">Noch keine. Rechtsklick auf ein Element → „Als Komponente speichern" (oder „Als Komponente" im Reihen-Kopf).</p>';
      } else {
        html += '<p class="draggo-hint">Spalte/Sektion wählen, dann klicken zum Einfügen.</p><div class="draggo-components">';
        comps.forEach(function (c) {
          html += '<div class="draggo-comp-item"><button class="draggo-comp-ins" draggable="true" data-cid="' + c.id + '" title="Einfügen oder in eine Spalte ziehen">' +
            '<span class="draggo-pi-icon">' + iconHtml(c.eltype) + '</span><span class="draggo-pi-label">' + esc(c.title) + '</span></button>' +
            '<button class="draggo-comp-del" data-cid="' + c.id + '" title="Löschen"><i class="fas fa-times" aria-hidden="true"></i></button></div>';
        });
        html += '</div>';
      }
      html += '</div>';

      el.innerHTML = html;

      // Sub-tab switching (remembers the active view across re-renders).
      el.querySelectorAll('.draggo-subtabs button').forEach(function (b) {
        b.addEventListener('click', function () {
          self._paletteView = b.dataset.view;
          el.querySelectorAll('.draggo-subtabs button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
          el.querySelectorAll('.draggo-pal-view').forEach(function (v) { v.hidden = v.dataset.view !== b.dataset.view; });
        });
      });

      // Live search — ONLY within the Elemente view. (Strukturen/Komponenten
      // also use .draggo-cat; filtering across all views hid their categories
      // and never restored them, since they hold no .draggo-palette-item.)
      var search = el.querySelector('.draggo-pal-search');
      var elemView = el.querySelector('.draggo-pal-view[data-view="elements"]');
      if (search && elemView) {
        search.addEventListener('input', function () {
          var q = search.value.trim().toLowerCase();
          elemView.querySelectorAll('.draggo-cat').forEach(function (cat) {
            var any = false;
            cat.querySelectorAll('.draggo-palette-item').forEach(function (it) {
              var match = !q || (it.dataset.label || '').indexOf(q) !== -1 || (it.dataset.type || '').indexOf(q) !== -1;
              it.style.display = match ? '' : 'none';
              if (match) any = true;
            });
            cat.hidden = !any;
            if (q) cat.open = any;
          });
        });
      }

      el.querySelectorAll('.draggo-comp-ins').forEach((b) => {
        b.addEventListener('click', () => this.insertComponent(parseInt(b.dataset.cid, 10)));
        b.addEventListener('dragstart', () => { this.drag = { kind: 'component', component: parseInt(b.dataset.cid, 10) }; });
        b.addEventListener('dragend', () => { this.drag = null; });
      });
      el.querySelectorAll('.draggo-comp-del').forEach((b) => {
        b.addEventListener('click', (e) => { e.stopPropagation(); this.deleteComponent(parseInt(b.dataset.cid, 10)); });
      });
      el.querySelectorAll('.draggo-structure-item').forEach((b) => {
        if (b.dataset.locked === '1') {
          b.addEventListener('click', (e) => {
            e.preventDefault();
            this.toast(tr('Dieses Spalten-Layout gehört zur Vollversion (v-t.one).'));
          });
          return;
        }
        b.addEventListener('click', () => this.clickStructure(b.dataset.preset));
        b.addEventListener('dragstart', () => { this.drag = { kind: 'structure', preset: b.dataset.preset }; });
        b.addEventListener('dragend', () => { this.drag = null; });
      });
      el.querySelectorAll('.draggo-template-item').forEach((b) => {
        b.addEventListener('click', () => this.clickTemplate(b.dataset.key));
        b.addEventListener('dragstart', () => { this.drag = { kind: 'template', template: b.dataset.key }; });
        b.addEventListener('dragend', () => { this.drag = null; });
      });
      el.querySelectorAll('.draggo-palette-item').forEach((b) => {
        if (b.dataset.locked === '1') {
          b.addEventListener('click', (e) => {
            e.preventDefault();
            this.toast(tr('Dieses Element gehört zur Vollversion (v-t.one).'));
          });
          return; // no drag source either
        }
        b.addEventListener('click', () => this.clickAdd(b.dataset.type, b.dataset.blocktype || null));
        b.addEventListener('dragstart', () => { this.drag = { kind: 'new', type: b.dataset.type, blocktype: b.dataset.blocktype || null }; });
        b.addEventListener('dragend', () => { this.drag = null; });
      });
    }

    // ── Canvas ───────────────────────────────────────────────────
    renderSections(sections) {
      this._sections = sections;
      // id → element (incl. layout) for the inspector.
      this._elemById = {};
      sections.forEach((s) => (s.elements || []).forEach((e) => { this._elemById[e.id] = e; }));

      var canvas = document.getElementById('draggo-canvas');
      if (!canvas) return;

      canvas.innerHTML = '';

      if (!sections.length) {
        if (this.mode === 'page') canvas.appendChild(this.renderInsertBar(0));
        else canvas.innerHTML = '<p class="draggo-empty">Leere Einheit.</p>';
        this.active = this.active || null;
      }

      if (sections.length && (!this.active || !sections.some((s) => s.id === this.active.sectionId))) {
        this.active = { sectionId: sections[0].id, after: null };
      }

      // Header preview (read-only global unit) for realistic context.
      var frame = this._frame || {};
      if (this.mode === 'page' && frame.header) canvas.appendChild(this.renderFramePreview('header', frame.header, frame.headerSticky));

      // Insert bars between containers removed (caused gaps). Add new
      // containers via the "+ Container am Ende" toolbar button; reorder by drag.
      sections.forEach((s) => { canvas.appendChild(this.renderSection(s)); });

      // Footer preview.
      if (this.mode === 'page' && frame.footer) canvas.appendChild(this.renderFramePreview('footer', frame.footer));

      this.highlightTarget();
      this.renderInspector();
      this.ensureGoogleFonts();
      if (window.draggoInitCarousels) window.draggoInitCarousels(document.getElementById('draggo-canvas'));
      if (window.draggoInitTier2) window.draggoInitTier2(document.getElementById('draggo-canvas'));
    }

    renderInsertBar(after) {
      var bar = document.createElement('div');
      bar.className = 'draggo-insert';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'draggo-insert-btn';
      btn.textContent = tr('+ Container hier einfügen');
      btn.addEventListener('click', () => this.createContainerAt(after));
      bar.appendChild(btn);
      return bar;
    }

    // Read-only header/footer unit preview (edit in the backend / unit editor).
    renderFramePreview(kind, html, sticky) {
      var el = document.createElement('div');
      el.className = 'draggo-frame draggo-frame--' + kind + (sticky ? ' is-sticky' : '');
      el.innerHTML = '<div class="draggo-frame-tag">' + (kind === 'header' ? 'Header' : 'Footer') + ' · globale Einheit (schreibgeschützt)</div>' +
        '<div class="draggo-frame-body">' + html + '</div>';
      return el;
    }

    // Apply background colour + image (with options) to an editor element so
    // the canvas matches the frontend.
    applyBg(el, l) {
      if (!l) return;
      if (l.bg) el.style.background = l.bg;
      if (l.image) {
        var u = /^https?:/i.test(l.image) ? l.image : '/' + l.image;
        el.style.backgroundImage = "url('" + u + "')";
        el.style.backgroundSize = l.bgSize || 'cover';
        el.style.backgroundPosition = l.bgPosition || 'center';
        el.style.backgroundRepeat = l.bgRepeat || 'no-repeat';
        el.style.backgroundAttachment = l.bgAttachment || 'scroll';
      } else if (l.bgGradFrom && l.bgGradTo) {
        // Gradient only when no image (mirrors LayoutStyleCompiler).
        if ((l.bgGradType || 'linear') === 'radial') {
          el.style.backgroundImage = 'radial-gradient(circle,' + l.bgGradFrom + ',' + l.bgGradTo + ')';
        } else {
          var ang = parseInt(l.bgGradAngle, 10); if (isNaN(ang) || ang < 0 || ang > 360) ang = 135;
          el.style.backgroundImage = 'linear-gradient(' + ang + 'deg,' + l.bgGradFrom + ',' + l.bgGradTo + ')';
        }
      }
    }

    // Inject/update an overlay layer in the editor canvas so it matches the
    // frontend (background overlay above bg, below content). Removes itself
    // when the layout has no overlay.
    applyOverlay(el, l) {
      var existing = el.querySelector(':scope > .draggo-bg-overlay');
      var bg = '';
      if (l && l.ovType === 'color' && l.ovColor) bg = 'background-color:' + l.ovColor + ';';
      else if (l && l.ovType === 'gradient' && l.ovGradFrom && l.ovGradTo) {
        if ((l.ovGradType || 'linear') === 'radial') bg = 'background-image:radial-gradient(circle,' + l.ovGradFrom + ',' + l.ovGradTo + ');';
        else { var a = parseInt(l.ovGradAngle, 10); if (isNaN(a) || a < 0 || a > 360) a = 135; bg = 'background-image:linear-gradient(' + a + 'deg,' + l.ovGradFrom + ',' + l.ovGradTo + ');'; }
      } else if (l && l.ovType === 'image' && l.ovImage) {
        var u = /^https?:/i.test(l.ovImage) ? l.ovImage : '/' + l.ovImage;
        bg = "background-image:url('" + u + "');background-size:cover;background-position:center;background-repeat:no-repeat;";
      }
      if (!bg) { if (existing) existing.remove(); return; }
      el.style.position = el.style.position || 'relative';
      el.style.isolation = 'isolate';
      var ov = existing || document.createElement('div');
      ov.className = 'draggo-bg-overlay';
      var op = (l.ovOpacity != null && l.ovOpacity !== '') ? l.ovOpacity : 0.5;
      var blend = (l.ovBlend && l.ovBlend !== 'normal') ? ('mix-blend-mode:' + l.ovBlend + ';') : '';
      ov.setAttribute('style', 'position:absolute;inset:0;pointer-events:none;z-index:-1;opacity:' + op + ';' + blend + bg);
      if (!existing) el.insertBefore(ov, el.firstChild);
    }

    // Inject/update the media background layer (video / slideshow) in the
    // canvas preview so it matches the frontend. Mirrors mediaBgLayerHtml().
    applyMediaBg(el, l) {
      var existing = el.querySelector(':scope > .draggo-bg-media');
      var url = function (p) { return /^https?:/i.test(p) ? p : '/' + p; };
      var html = '';
      if (l && l.bgMedia === 'video' && l.bgVideo) {
        var poster = l.bgVideoPoster ? ' poster="' + esc(url(l.bgVideoPoster)) + '"' : '';
        html = '<video class="draggo-bg-video" autoplay muted loop playsinline' + poster + '><source src="' + esc(url(l.bgVideo)) + '"></video>';
      } else if (l && l.bgMedia === 'slider' && l.bgSlides && l.bgSlides.length) {
        html = l.bgSlides.map(function (p, i) { return '<div class="draggo-bg-slide' + (i === 0 ? ' is-on' : '') + '" style="background-image:url(\'' + url(p) + '\')"></div>'; }).join('');
      }
      if (!html) { if (existing) existing.remove(); return; }
      el.style.position = el.style.position || 'relative';
      el.style.isolation = 'isolate';
      var media = existing || document.createElement('div');
      media.className = 'draggo-bg-media' + (l.bgMedia === 'slider' ? ' draggo-bg-slider' : '');
      media.innerHTML = html;
      if (!existing) el.insertBefore(media, el.firstChild);
    }

    // Apply spacing/colour/border to an editor element so the canvas matches
    // the frontend (padding/margin per-side or scale, border, radius, …).
    applyBoxStyle(el, l) {
      if (!l) return;
      var pads = { none: '0', xs: '.25rem', s: '.5rem', m: '1rem', l: '2rem', xl: '3rem' };
      var box = function (b, scale) {
        if (b && typeof b === 'object') {
          var u = b.unit || 'px';
          return ['top', 'right', 'bottom', 'left'].map(function (s) { var v = b[s]; return (v !== '' && v != null) ? (v + u) : '0'; }).join(' ');
        }
        if (scale && pads[scale] != null) return pads[scale];
        return null;
      };
      var p = box(l.paddingBox, l.padding); if (p) el.style.padding = p;
      var m = box(l.marginBox, l.margin); if (m) el.style.margin = m;
      if (l.color) el.style.color = l.color;
      if (l.borderStyle && l.borderWidth) el.style.border = (parseInt(l.borderWidth, 10) || 0) + 'px ' + l.borderStyle + ' ' + (l.borderColor || '#000');
      if (l.borderRadius) el.style.borderRadius = (parseInt(l.borderRadius, 10) || 0) + 'px';
      var shadows = { sm: '0 1px 3px rgba(0,0,0,.12)', md: '0 4px 12px rgba(0,0,0,.15)', lg: '0 10px 30px rgba(0,0,0,.2)', xl: '0 20px 50px rgba(0,0,0,.25)' };
      if (l.boxShadow && shadows[l.boxShadow]) el.style.boxShadow = shadows[l.boxShadow];
      if (l.opacity != null && l.opacity !== '') el.style.opacity = l.opacity;
      // Typography / size / position / transform — mirror compile() so the
      // canvas matches the frontend 1:1 for these props too.
      var lenRe = /^\d+(\.\d+)?(px|%|rem|em|vh|vw)$/;
      if (l.fontFamily) el.style.fontFamily = l.fontFamily;
      if (l.fontSize) el.style.fontSize = (parseInt(l.fontSize, 10) || 0) + 'px';
      if (l.fontWeight) el.style.fontWeight = l.fontWeight;
      if (l.textTransform) el.style.textTransform = l.textTransform;
      if (l.fontStyle) el.style.fontStyle = l.fontStyle;
      if (l.textDecoration) el.style.textDecoration = l.textDecoration;
      if (l.lineHeight) el.style.lineHeight = l.lineHeight;
      if (l.letterSpacing !== undefined && l.letterSpacing !== '') el.style.letterSpacing = parseFloat(l.letterSpacing) + 'px';
      if (l.wordSpacing !== undefined && l.wordSpacing !== '') el.style.wordSpacing = parseFloat(l.wordSpacing) + 'px';
      ['width', 'maxWidth', 'height', 'maxHeight'].forEach(function (k) {
        var cssk = k === 'maxWidth' ? 'maxWidth' : (k === 'maxHeight' ? 'maxHeight' : k);
        if (typeof l[k] === 'string' && lenRe.test(l[k])) el.style[cssk] = l[k];
      });
      var textShadows = { sm: '0 1px 2px rgba(0,0,0,.3)', md: '0 2px 4px rgba(0,0,0,.4)', lg: '0 4px 8px rgba(0,0,0,.5)' };
      if (l.textShadow && textShadows[l.textShadow]) el.style.textShadow = textShadows[l.textShadow];
      if (l.zIndex !== undefined && l.zIndex !== '' && !isNaN(parseInt(l.zIndex, 10))) el.style.zIndex = parseInt(l.zIndex, 10);
      if (['relative', 'absolute', 'fixed', 'sticky'].indexOf(l.position) !== -1) {
        el.style.position = l.position;
        [['posTop', 'top'], ['posRight', 'right'], ['posBottom', 'bottom'], ['posLeft', 'left']].forEach(function (o) {
          if (typeof l[o[0]] === 'string' && lenRe.test(l[o[0]])) el.style[o[1]] = l[o[0]];
        });
      }
      var tf = [];
      if (l.transformRotate && parseInt(l.transformRotate, 10)) tf.push('rotate(' + parseInt(l.transformRotate, 10) + 'deg)');
      if (l.transformScale && parseFloat(l.transformScale) && parseFloat(l.transformScale) !== 1) tf.push('scale(' + parseFloat(l.transformScale) + ')');
      if (typeof l.transformTranslateX === 'string' && lenRe.test(l.transformTranslateX)) tf.push('translateX(' + l.transformTranslateX + ')');
      if (typeof l.transformTranslateY === 'string' && lenRe.test(l.transformTranslateY)) tf.push('translateY(' + l.transformTranslateY + ')');
      if (tf.length) el.style.transform = tf.join(' ');
    }

    renderSection(section) {
      var el = document.createElement('section');
      el.className = 'draggo-section';
      el.dataset.section = section.id;

      var head = document.createElement('header');
      head.className = 'draggo-section-head';
      var title = section.title || (section.kind === 'unit' ? 'Einheit' : 'Container #' + section.id);
      head.innerHTML = '<span class="draggo-section-title">' + esc(title) + '</span>' +
        (section.inColumn ? '<span class="draggo-section-col">' + esc(section.inColumn) + '</span>' : '');

      if (this.mode === 'page') {
        var grip = document.createElement('span');
        grip.className = 'draggo-section-grip';
        grip.textContent = '⠿';
        grip.title = 'Zum Verschieben ziehen';
        grip.setAttribute('draggable', 'true');
        grip.addEventListener('dragstart', (e) => {
          this.drag = { kind: 'container', id: section.id };
          el.classList.add('is-dragging');
          if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'container'); }
        });
        grip.addEventListener('dragend', () => {
          el.classList.remove('is-dragging');
          this.drag = null;
          var canvas = document.getElementById('draggo-canvas');
          var order = Array.prototype.map.call(canvas.querySelectorAll('.draggo-section'), function (s) { return parseInt(s.dataset.section, 10); })
            .filter(function (n) { return !isNaN(n); });
          this.api('/page/' + this.containerId + '/articles/reorder', 'POST', { order: order }).then(() => this.loadContent());
        });
        grip.addEventListener('click', (e) => e.stopPropagation());
        head.insertBefore(grip, head.firstChild);

        // Insert a new container directly after this one (containers re-addable).
        var ins = document.createElement('button');
        ins.type = 'button';
        ins.className = 'draggo-section-op';
        ins.innerHTML = '<i class="fas fa-plus" aria-hidden="true"></i>';
        ins.title = 'Container darunter einfügen';
        ins.style.marginLeft = 'auto';
        ins.addEventListener('click', (e) => { e.stopPropagation(); this.createContainerAt(section.id); });
        head.appendChild(ins);

        var dup = document.createElement('button');
        dup.type = 'button';
        dup.className = 'draggo-section-op';
        dup.innerHTML = '<i class="fas fa-clone" aria-hidden="true"></i>';
        dup.title = tr('Container duplizieren');
        dup.addEventListener('click', (e) => { e.stopPropagation(); this.duplicateContainer(section.id); });
        head.appendChild(dup);

        var cpy = document.createElement('button');
        cpy.type = 'button';
        cpy.className = 'draggo-section-op';
        cpy.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
        cpy.title = tr('Container kopieren');
        cpy.addEventListener('click', (e) => { e.stopPropagation(); this.copyContainer(section.id, title); });
        head.appendChild(cpy);

        var pst = document.createElement('button');
        pst.type = 'button';
        pst.className = 'draggo-section-op';
        pst.innerHTML = '<i class="fas fa-paste" aria-hidden="true"></i>';
        pst.title = tr('Kopierten Container hier einfügen');
        pst.addEventListener('click', (e) => { e.stopPropagation(); this.pasteContainer(section.id); });
        head.appendChild(pst);

        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'draggo-section-op';
        del.textContent = '✕';
        del.title = tr('Container löschen');
        del.addEventListener('click', (e) => {
          e.stopPropagation();
          if (window.confirm(tr('Container löschen?') + ' „' + title + '"')) this.deleteContainer(section.id);
        });
        head.appendChild(del);
      }

      head.addEventListener('click', () => this.selectTarget(section.id, null, 'container', section.id));
      el.appendChild(head);

      var body = document.createElement('div');
      body.className = 'draggo-section-body';
      this.makeDropZone(body, (e) => this.onDropSection(section, e));
      // Click in the container area (outside any row/column/element) selects it.
      body.addEventListener('click', (e) => {
        if (e.target.closest('.draggo-row') || e.target.closest('.draggo-col') || e.target.closest('.draggo-item')) return;
        this.selectTarget(section.id, null, 'container', section.id);
      });

      // Inner wrapper holds the content; background stays on the full-width body.
      var inner = document.createElement('div');
      inner.className = 'draggo-section-inner';

      var nodes = buildTree(section.elements);
      if (!nodes.length) {
        var hint = document.createElement('p');
        hint.className = 'draggo-empty';
        hint.textContent = 'Leerer Container — Element/Struktur hierher ziehen oder oben anklicken.';
        inner.appendChild(hint);
      }
      this.renderNodes(section, nodes, inner);
      body.appendChild(inner);
      el.appendChild(body);

      // Live preview of container-level layout (bg full width, content boxed).
      var cl = this.effLayout(section.layout);
      var flexMap = { start: 'flex-start', center: 'center', end: 'flex-end' };
      var boxed = cl.width === 'boxed';
      this.applyBg(body, cl);
      this.applyOverlay(body, cl);
      this.applyMediaBg(body, cl);
      this.applyBoxStyle(body, cl);
      if (cl.minHeight) body.style.minHeight = (parseInt(cl.minHeight, 10) || 0) + 'px';

      var gapMap = { none: '0', xs: '.25rem', s: '.5rem', m: '1rem', l: '2rem', xl: '3rem' };
      if (cl.display === 'grid') {
        // Grid mode applies to the inner content wrapper (children = columns).
        inner.style.display = 'grid';
        inner.style.gridTemplateColumns = 'repeat(' + (parseInt(cl.gridColumns, 10) || 2) + ',1fr)';
        if (cl.gap && gapMap[cl.gap] != null) inner.style.gap = gapMap[cl.gap];
        body.style.display = 'block';
      } else if (cl.display === 'flex') {
        inner.style.display = 'flex';
        inner.style.flexDirection = cl.flexDirection || 'row';
        inner.style.justifyContent = cl.flexJustify || 'flex-start';
        inner.style.alignItems = cl.flexAlign || 'stretch';
        inner.style.flexWrap = cl.flexWrap || 'nowrap';
        if (cl.gap && gapMap[cl.gap] != null) inner.style.gap = gapMap[cl.gap];
        body.style.display = 'block';
      } else {
        body.style.display = 'flex';
        body.style.flexDirection = 'column';
        body.style.alignItems = cl.alignX ? flexMap[cl.alignX] : (boxed ? 'center' : 'stretch');
        body.style.justifyContent = cl.alignY ? flexMap[cl.alignY] : 'flex-start';
      }

      inner.style.width = '100%';
      if (boxed && !cl.display) inner.style.maxWidth = '1140px';

      return el;
    }

    renderNodes(section, nodes, listEl) {
      nodes.forEach((node) => {
        if (node.k === 'el') listEl.appendChild(this.renderChip(node.el));
        else listEl.appendChild(this.renderRow(section, node.r));
      });
    }

    renderRow(section, r) {
      var widths = this.gridWidths(r);
      var rowEl = document.createElement('div');
      rowEl.className = 'draggo-row';
      rowEl.dataset.startId = r.startId;
      rowEl.dataset.stopId = r.stopId != null ? r.stopId : '';

      var ratio = widths.map(function (w) { return Math.round(w / 12 * 100) + '%'; }).join(' · ');
      var head = document.createElement('div');
      head.className = 'draggo-row-head';
      head.innerHTML = '<span>▦ ' + esc(tr('Reihe')) + ' · ' + esc(ratio) + '</span>';
      var rdel = document.createElement('button');
      rdel.className = 'draggo-row-del';
      rdel.textContent = tr('Reihe löschen');
      rdel.addEventListener('click', (e) => { e.stopPropagation(); if (window.confirm(tr('Ganze Reihe inkl. Inhalt löschen?'))) this.deleteMany(rowIds(r)); });
      var rsave = document.createElement('button');
      rsave.className = 'draggo-row-save';
      rsave.textContent = tr('Als Komponente');
      rsave.title = tr('Ganze Sektion als wiederverwendbare Komponente speichern');
      rsave.addEventListener('click', (e) => { e.stopPropagation(); this.saveAsComponent({ id: r.startId, type: 'draggo_row_start', label: 'Sektion' }); });
      head.appendChild(rsave);
      head.appendChild(rdel);
      head.style.cursor = 'pointer';
      head.title = tr('Reihen-Layout bearbeiten');
      head.addEventListener('click', () => this.selectTarget(section.id, null, 'row', r.startId));
      rowEl.appendChild(head);

      // Click anywhere in the row (outside a column/chip) selects the row.
      rowEl.addEventListener('click', (e) => {
        if (e.target.closest('.draggo-col') || e.target.closest('.draggo-item')) return;
        if (e.target.closest('.draggo-row') !== rowEl) return; // nested row handles itself
        this.selectTarget(section.id, null, 'row', r.startId);
      });

      var flexMap = { start: 'flex-start', center: 'center', end: 'flex-end' };
      var cols = document.createElement('div');
      cols.className = 'draggo-cols';
      cols.style.display = 'flex';
      cols.style.flexWrap = 'wrap';
      cols.style.gap = '0';
      cols.style.alignItems = 'stretch';

      r.columns.forEach((colNodes, i) => {
        var pct = (widths[i] || Math.floor(12 / r.columns.length) || 12) / 12 * 100;
        var colEl = document.createElement('div');
        colEl.className = 'draggo-col';
        colEl.style.flex = '0 0 ' + pct + '%';
        colEl.style.maxWidth = pct + '%';
        colEl.style.boxSizing = 'border-box';
        colEl.dataset.openId = r.colOpenIds[i];
        colEl.dataset.section = section.id;

        var label = document.createElement('div');
        label.className = 'draggo-col-label';
        label.textContent = tr('Spalte') + ' · ' + Math.round(pct) + '%';
        colEl.appendChild(label);

        var list = document.createElement('div');
        list.className = 'draggo-col-list';
        this.renderNodes(section, colNodes, list);
        this.enableSort(list, section);
        colEl.appendChild(list);

        // Live layout preview from the column-opener element. The first column
        // (row-start opener) stores its layout namespaced under .col; separate
        // draggo_col elements store it flat — mirror currentLayout().
        var opener = (this._elemById && this._elemById[r.colOpenIds[i]]) || {};
        var rawColL = opener.layout || {};
        var openerLayout = this.effLayout((opener.type === 'draggo_row_start') ? (rawColL.col || {}) : rawColL);
        this.applyBg(colEl, openerLayout);
        this.applyOverlay(colEl, openerLayout);
        this.applyMediaBg(colEl, openerLayout);
        this.applyBoxStyle(colEl, openerLayout);
        if (openerLayout.color) list.style.color = openerLayout.color;
        if (openerLayout.align) list.style.textAlign = openerLayout.align;
        if (openerLayout.minHeight) colEl.style.minHeight = (parseInt(openerLayout.minHeight, 10) || 0) + 'px';
        // Centre elements inside the column (horizontal/vertical).
        if (openerLayout.alignX || openerLayout.alignY) {
          list.style.display = 'flex'; list.style.flexDirection = 'column'; list.style.flex = '1';
          list.style.alignItems = openerLayout.alignX ? flexMap[openerLayout.alignX] : 'stretch';
          list.style.justifyContent = openerLayout.alignY ? flexMap[openerLayout.alignY] : 'flex-start';
        }

        var hint = document.createElement('div');
        hint.className = 'draggo-col-hint';
        hint.textContent = colNodes.length ? '+ ' + tr('Element') : tr('leer · Element hierher');
        colEl.appendChild(hint);

        colEl.addEventListener('click', (e) => {
          // Ignore clicks that land in a NESTED column or on a chip.
          if (e.target.closest('.draggo-col') !== colEl) return;
          if (e.target.closest('.draggo-item')) return;
          var lastChip = list.querySelector(':scope > .draggo-item:last-of-type');
          var openId = parseInt(colEl.dataset.openId, 10);
          var after = lastChip ? parseInt(lastChip.dataset.id, 10) : openId;
          this.selectTarget(section.id, after, 'col', openId);
        });
        this.makeDropZone(colEl, (e) => this.onDropColumn(section, list, parseInt(colEl.dataset.openId, 10), e));

        cols.appendChild(colEl);
      });
      rowEl.appendChild(cols);

      // Live preview of row-level layout.
      var rowLayout = this.effLayout(((this._elemById && this._elemById[r.startId] && this._elemById[r.startId].layout) || {}).row || {});
      this.applyBg(rowEl, rowLayout);
      this.applyOverlay(rowEl, rowLayout);
      this.applyMediaBg(rowEl, rowLayout);
      this.applyBoxStyle(rowEl, rowLayout);
      if (rowLayout.color) rowEl.style.color = rowLayout.color;
      if (rowLayout.minHeight) rowEl.style.minHeight = (parseInt(rowLayout.minHeight, 10) || 0) + 'px';
      // Centre the columns inside the row (horizontal/vertical).
      if (rowLayout.alignX) cols.style.justifyContent = flexMap[rowLayout.alignX];
      if (rowLayout.alignY) cols.style.alignItems = flexMap[rowLayout.alignY];

      return rowEl;
    }

    wrapperChip(elm) {
      var node = document.createElement('div');
      node.className = 'draggo-item draggo-item-wrap';
      node.dataset.id = elm.id;
      var lbl = this.labels[elm.type] || elm.type;
      node.innerHTML = '<span class="draggo-wrap-mark">⟍</span><span class="draggo-wrap-label">' + esc(lbl) + '</span><span class="draggo-wrap-note">' + esc(tr('Struktur (im Theme bearbeiten)')) + '</span>';
      return node;
    }

    renderChip(elm) {
      // Foreign structural wrappers (e.g. PCT autogrid start/stop) Draggo does
      // NOT manage → a slim, inert marker instead of a full empty chip. Not
      // draggable/editable (moving it would corrupt the foreign grid); the leaf
      // content between markers stays fully editable.
      if (elm.isWrapper) {
        return this.wrapperChip(elm);
      }
      var node = document.createElement('div');
      node.className = 'draggo-item' + (elm.html ? ' has-preview' : ' no-preview');
      node.draggable = true;
      node.dataset.id = elm.id;
      var label = this.labels[elm.type] || elm.type;
      var preview = elm.html
        ? '<div class="draggo-item-preview">' + elm.html + '</div>'
        : '<div class="draggo-item-empty">' + esc(label) + (elm.headline ? ' · ' + esc(elm.headline) : '') + ' <span>(' + esc(tr('noch kein Inhalt — ✎ bearbeiten')) + ')</span></div>';
      node.innerHTML =
        '<div class="draggo-item-bar">' +
          '<span class="draggo-item-grip">⠿</span>' +
          '<span class="draggo-item-type">' + esc(label) + '</span>' +
          '<span class="draggo-item-spacer"></span>' +
          '<button class="draggo-item-edit" title="' + esc(tr('Bearbeiten')) + '"><i class="fas fa-pen" aria-hidden="true"></i></button>' +
          '<button class="draggo-item-dup" title="' + esc(tr('Duplizieren')) + '">⧉</button>' +
          '<button class="draggo-item-copy" title="' + esc(tr('Kopieren')) + '">⎘</button>' +
          // Paste-below: hidden until something is on the clipboard (body gets
          // .draggo-has-clip on copy), so EVERY element exposes a visible paste
          // affordance the moment one is copied — not just the right-click menu.
          '<button class="draggo-item-paste" title="' + esc(tr('Hier einfügen')) + '"><i class="fas fa-paste" aria-hidden="true"></i></button>' +
          '<button class="draggo-item-delete" title="' + esc(tr('Löschen')) + '">✕</button>' +
        '</div>' +
        preview;
      // Apply the element's own compiled styles so the preview matches the FE.
      if (elm.html) {
        var pv = node.querySelector('.draggo-item-preview');
        // The canvas scrolls in a nested container; a loading="lazy" image in a
        // collapsed/offscreen chip (e.g. the scroll-zoom frame) gets stuck at
        // 0×0 and never loads → the element renders empty. Force eager loading
        // inside the canvas so every preview image shows.
        if (pv) pv.querySelectorAll('img[loading="lazy"]').forEach(function (img) { img.loading = 'eager'; });
        // Scope styles to .draggo-el-{id} (the class Contao puts on the
        // rendered element / our custom controllers put on <a>/<nav>), exactly
        // like the frontend — so button/nav styling lands on the right node.
        var styles = '';
        if (pv && elm.styleCss) styles += '.draggo-el-' + elm.id + '{' + elm.styleCss + '}';
        if (pv && elm.scopedCss) styles += elm.scopedCss;
        // Per-viewport overrides (data-vw-keyed) → chip reflects tablet/mobile.
        if (pv && elm.respCss) styles += elm.respCss;
        if (pv && styles) { var st = document.createElement('style'); st.textContent = styles; pv.appendChild(st); }
        // A font-size on the wrapper must not be multiplied by a NESTED heading's
        // em — but only for headings INSIDE the scoped root, never the root
        // element itself (a headline element IS the h1 → must keep its size).
        // Mirrors the frontend rule `.draggo-el-{id} h1{font-size:inherit}`.
        if (pv && elm.styleCss && /font-size/.test(elm.styleCss)) {
          var root = pv.querySelector('.draggo-el-' + elm.id) || pv;
          root.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(function (h) {
            if (h !== root) h.style.fontSize = 'inherit';
          });
        }
        if (pv && elm.linkColor) pv.querySelectorAll('a').forEach(function (a) { a.style.color = elm.linkColor; });
      }
      node.querySelector('.draggo-item-edit').addEventListener('click', (e) => {
        e.stopPropagation();
        this.editElement(elm.id);
      });
      // Single click anywhere on the element (except the toolbar buttons) opens
      // the editor — no need to hit the small bar. Links in the preview are
      // inert previews, so intercepting the click is fine.
      // EXCEPTION: interactive preview controls (accordion heads, nav toggle,
      // tabs, carousel arrows, flip/share/alert/hotspot, native <details>) must
      // stay clickable so inner areas can be opened/inspected — let those clicks
      // through (no stopPropagation/preventDefault → frontend.js + native
      // behaviour run).
      var INTERACTIVE = '.draggo-acc-head, summary, .draggo-nav-toggle, .draggo-nav-close, .draggo-nav-backdrop, .draggo-tabw-btn, .draggo-car-prev, .draggo-car-next, .draggo-car-dots, .draggo-flip-btn, .draggo-share-link, .draggo-share-copy, .draggo-alert-close, .draggo-vp-item, .draggo-vp-play, .draggo-hs-point, .draggo-hs-dot, .draggo-map-load, .draggo-code-copy';
      node.addEventListener('click', (e) => {
        if (e.target.closest('.draggo-item-bar')) return; // toolbar buttons handle themselves
        if (e.target.closest(INTERACTIVE)) return;         // let inner controls work
        e.stopPropagation();
        e.preventDefault();
        this.editElement(elm.id);
      });
      node.addEventListener('dblclick', (e) => { e.stopPropagation(); this.editElement(elm.id); });
      node.querySelector('.draggo-item-dup').addEventListener('click', (e) => {
        e.stopPropagation();
        this.duplicateElement(elm.id);
      });
      node.querySelector('.draggo-item-copy').addEventListener('click', (e) => {
        e.stopPropagation();
        this.copyToClipboard(elm.id, label);
      });
      node.querySelector('.draggo-item-paste').addEventListener('click', (e) => {
        e.stopPropagation();
        this.pasteAfter(node, elm.id);
      });
      node.querySelector('.draggo-item-delete').addEventListener('click', (e) => {
        e.stopPropagation();
        if (window.confirm(tr('Element löschen?'))) this.deleteElement(elm.id);
      });
      node.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.showElementMenu(e.clientX, e.clientY, elm, node);
      });
      node.addEventListener('dragstart', (e) => {
        this.drag = { kind: 'move', id: elm.id };
        node.classList.add('is-dragging');
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
      });
      node.addEventListener('dragend', () => { this.drag = null; node.classList.remove('is-dragging'); });
      return node;
    }

    // ── Inline element editing (in the left "Element" tab) ───────
    destroyEditors() {
      (this._editorDestroyers || []).forEach(function (d) { try { d(); } catch (e) {} });
      this._editorDestroyers = [];
    }

    closeElementTab(reload) {
      this.destroyEditors();
      this._onVwChange = null; // stop the closed editor's breakpoint switcher from reacting
      document.querySelectorAll('.draggo-item.is-target').forEach((n) => n.classList.remove('is-target'));
      var pane = document.getElementById('draggo-pane-element');
      if (pane) pane.innerHTML = '';
      this.showElementTab(false);
      this.switchTab('content');
      if (reload) this.loadContent();
    }

    editElement(id, isNew) {
      var self = this;
      this._editId = id;         // track which element the edit pane shows
      this._highlightChip(id);   // persistent border + scroll the chip into view
      this.api('/element/' + id + '/fields').then(function (d) {
        var pane = document.getElementById('draggo-pane-element');
        if (!pane) return;
        var hasFields = d.fields && d.fields.length;
        var hasStyle = d.styleControls && d.styleControls.length;
        if (!hasFields && !hasStyle) {
          // Truly nothing to configure → don't strand an empty new element.
          if (isNew) self.deleteElement(id);
          self.toast(tr('Dieses Element hat keine Einstellungen.'));
          self.showElementTab(false); self.switchTab('content');
          return;
        }
        // No content fields but still styleable → render Stil/Erweitert + a note
        // (no scary "remove?" prompt). d.fields = [] flows through harmlessly.
        if (!hasFields) d.fields = [];

        self.destroyEditors();
        self._editorDestroyers = [];
        pane.innerHTML = '';

        var h = document.createElement('h2');
        h.textContent = (self.labels[d.type] || d.type) + ' ' + tr('bearbeiten');
        pane.appendChild(h);

        // Tab bar: Inhalt / Stil / Erweitert.
        var etabs = document.createElement('div'); etabs.className = 'draggo-etabs';
        [['content', 'Inhalt'], ['style', 'Stil'], ['advanced', 'Erweitert']].forEach(function (t) {
          var b = document.createElement('button'); b.type = 'button'; b.dataset.etab = t[0]; b.textContent = t[1];
          if (t[0] === 'content') b.className = 'is-on';
          etabs.appendChild(b);
        });
        pane.appendChild(etabs);

        // Phase F: responsive style. Working copy per breakpoint; the style/
        // advanced panels are re-rendered when the device switcher changes.
        var base = Object.assign({}, d.style || {});
        var resp = base.responsive || {};
        delete base.responsive;
        var work = { desktop: base, tablet: Object.assign({}, resp.tablet || {}), mobile: Object.assign({}, resp.mobile || {}) };
        // Follow the canvas viewport so you edit the breakpoint you're viewing.
        var bp = self.vwToBp(self.vw);

        var sw = document.createElement('div'); sw.className = 'draggo-bp-switch';
        [['desktop', 'fa-desktop', 'Desktop'], ['tablet', 'fa-tablet-alt', 'Tablet'], ['mobile', 'fa-mobile-alt', 'Mobil']].forEach(function (o) {
          var b = document.createElement('button'); b.type = 'button'; b.dataset.bp = o[0];
          b.innerHTML = '<i class="draggo-fa fas ' + o[1] + '"></i> ' + o[2];
          if (o[0] === bp) b.className = 'is-on';
          sw.appendChild(b);
        });
        var hint = document.createElement('p'); hint.className = 'draggo-bp-hint';
        pane.appendChild(sw); pane.appendChild(hint);

        // Three views.
        var viewContent = document.createElement('div'); viewContent.dataset.etab = 'content';
        var viewStyle = document.createElement('div'); viewStyle.dataset.etab = 'style'; viewStyle.hidden = true;
        var viewAdvanced = document.createElement('div'); viewAdvanced.dataset.etab = 'advanced'; viewAdvanced.hidden = true;
        pane.appendChild(viewContent); pane.appendChild(viewStyle); pane.appendChild(viewAdvanced);

        // Content panel — built once (bucket 'field') so TinyMCE survives.
        var metaByName = {};
        var labelByName = {};
        var contentGroup = {
          title: 'Inhalt', open: true, bucket: 'field',
          controls: d.fields.map(function (f) {
            metaByName[f.name] = f.widget; labelByName[f.name] = f.label;
            return { key: f.name, type: f.widget, label: f.label, value: f.value, options: f.options, units: f.units, unit: f.unit, accept: f.accept, condition: f.condition };
          }),
        };
        var contentPanel = self.buildControls([contentGroup], {});
        viewContent.appendChild(contentPanel.wrap);
        self._editorDestroyers = contentPanel.destroyers;

        // Field-less but styleable element (e.g. a pure toggle/divider): point the
        // user to the Stil/Erweitert tabs instead of showing a blank Inhalt pane.
        if (!d.fields.length) {
          var note = document.createElement('p');
          note.className = 'draggo-edit-note';
          note.textContent = tr('Dieses Element hat keine Inhalts-Felder — über „Stil" und „Erweitert" gestalten und positionieren.');
          viewContent.appendChild(note);
        }

        // Visual form builder (Elementor-style) for the Draggo form element:
        // create / bind a native Contao form, then add & configure its fields.
        if (d.type === 'draggo_form') {
          self.mountFormBuilder(viewContent, contentPanel, id);
        }

        // Split style groups: general (advanced flag) → Erweitert tab,
        // element-specific modifiers → Stil tab.
        var allGroups = d.styleControls || [];
        var advGroups = allGroups.filter(function (g) { return g.advanced; });
        var styleGroups = allGroups.filter(function (g) { return !g.advanced; });
        var stylePanel = null, advPanel = null;
        var captureStyle = function () {
          work[bp] = Object.assign({}, stylePanel ? stylePanel.get().style : {}, advPanel ? advPanel.get().style : {});
        };
        // Live-preview the element's root-level style on the canvas chip as the
        // user edits (colour/bg/typography/border/…), exactly like the container
        // inspector — so changes are visible WITHOUT having to hit Speichern
        // first. Sub-part scoped CSS (per-part typography) still resolves fully
        // on save+re-render; this gives instant feedback for the common props.
        var previewStyle = function () {
          captureStyle();
          var L = Object.assign({}, work.desktop, (bp !== 'desktop' ? work[bp] : {}));
          var chip = self.root.querySelector('.draggo-item[data-id="' + id + '"]');
          if (!chip) return;
          var node = chip.querySelector('.draggo-el-' + id) || chip.querySelector('.draggo-item-preview') || chip;
          // Reset the props we manage so cleared values disappear too.
          ['padding', 'margin', 'color', 'background', 'backgroundColor', 'backgroundImage', 'border', 'borderRadius', 'boxShadow', 'opacity', 'fontFamily', 'fontSize', 'fontWeight', 'textTransform', 'fontStyle', 'textDecoration', 'lineHeight', 'letterSpacing', 'wordSpacing', 'textShadow', 'transform'].forEach(function (p) { node.style[p] = ''; });
          if (L.bg) node.style.backgroundColor = L.bg;
          self.applyBoxStyle(node, L);
        };
        var renderStyle = function () {
          stylePanel = self.buildControls(styleGroups, work[bp]);
          viewStyle.innerHTML = ''; viewStyle.appendChild(stylePanel.wrap);
          advPanel = self.buildControls(advGroups, work[bp]);
          viewAdvanced.innerHTML = ''; viewAdvanced.appendChild(advPanel.wrap);
          // Instant canvas feedback on every change in the Stil / Erweitert tab.
          stylePanel.wrap.addEventListener('input', previewStyle);
          stylePanel.wrap.addEventListener('change', previewStyle);
          advPanel.wrap.addEventListener('input', previewStyle);
          advPanel.wrap.addEventListener('change', previewStyle);
          hint.textContent = bp === 'desktop' ? 'Basis (gilt überall, sofern nicht überschrieben).'
            : (bp === 'tablet' ? 'Tablet ≤991px — leere Felder erben Desktop.' : 'Mobil ≤767px — leere Felder erben Desktop.');
        };
        // Switch the edited breakpoint AND the canvas viewport together.
        var selectBp = function (newBp) {
          if (newBp === bp) return;
          captureStyle(); bp = newBp;
          sw.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x.dataset.bp === bp); });
          renderStyle();
        };
        sw.querySelectorAll('button').forEach(function (b) {
          b.addEventListener('click', function () { self.setViewport(self.bpToVw(b.dataset.bp)); });
        });
        // Follow viewport changes coming from the toolbar / inspector switcher.
        self._onVwChange = function (vw) { selectBp(self.vwToBp(vw)); };
        renderStyle();

        // Tab switching (device switcher only relevant for Stil/Erweitert).
        var setEtab = function (name) {
          etabs.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x.dataset.etab === name); });
          [viewContent, viewStyle, viewAdvanced].forEach(function (v) { v.hidden = v.dataset.etab !== name; });
          var styleish = (name === 'style' || name === 'advanced');
          sw.hidden = !styleish; hint.hidden = !styleish;
        };
        etabs.querySelectorAll('button').forEach(function (b) { b.addEventListener('click', function () { setEtab(b.dataset.etab); }); });
        setEtab('content');

        var required = d.required || [];
        var actions = document.createElement('div');
        actions.className = 'draggo-edit-actions';
        actions.innerHTML = '<button type="button" class="draggo-edit-save">Speichern</button><button type="button" class="draggo-edit-cancel">Abbrechen</button>';
        pane.appendChild(actions);

        actions.querySelector('.draggo-edit-save').addEventListener('click', function () {
          var fields = contentPanel.get().field;

          // Enforce: every required content field must be filled.
          var missing = required.filter(function (name) { return valueIsEmpty(metaByName[name], fields[name]); });
          if (missing.length) {
            window.alert('Bitte Inhalt einfügen: ' + missing.map(function (n) { return labelByName[n] || n; }).join(', '));
            return;
          }

          captureStyle();
          var layout = Object.assign({}, work.desktop);
          var ro = {};
          if (Object.keys(work.tablet).length) ro.tablet = work.tablet;
          if (Object.keys(work.mobile).length) ro.mobile = work.mobile;
          if (Object.keys(ro).length) layout.responsive = ro;

          self.api('/element/' + id + '/fields', 'POST', { fields: fields }).then(function () {
            return self.api('/element/' + id + '/layout', 'POST', { layout: layout, scope: 'flat' });
          }).then(function () {
            // Keep the editor OPEN after saving (user closes explicitly via
            // Abbrechen/×) — only refresh the canvas preview. A saved element is
            // no longer a throwaway, so Cancel must not delete it.
            isNew = false;
            self.loadContent().then(function () { self._highlightChip(id); }); // re-render drops the chip border → restore it
            self.toast(tr('Gespeichert.'));
          }, function (e) { self.fail(e); });
        });
        actions.querySelector('.draggo-edit-cancel').addEventListener('click', function () {
          // Abandoning a brand-new element removes it (no empty leftovers).
          if (isNew) { self.destroyEditors(); self.deleteElement(id).then(function () { self.showElementTab(false); self.switchTab('content'); }); }
          else { self.closeElementTab(false); }
        });

        self.showElementTab(true);
        self.switchTab('element');
      }, function (e) { self.fail(e); });
    }

    // ── Visual form builder ───────────────────────────────────────────
    // Editor-kind → German label + which extra controls a field of that kind
    // exposes. Mirrors FormBuilder::KINDS on the PHP side.
    formKinds() {
      return {
        text:        { label: 'Text',            ph: true, len: true, ac: true },
        email:       { label: 'E-Mail',          ph: true, len: true, ac: true },
        phone:       { label: 'Telefon',         ph: true, len: true, ac: true },
        number:      { label: 'Zahl',            ph: true, ac: true },
        url:         { label: 'URL',             ph: true, len: true, ac: true },
        date:        { label: 'Datum',           ph: true, ac: true },
        textarea:    { label: 'Textfeld',        ph: true, len: true },
        select:      { label: 'Dropdown',        opts: true },
        radio:       { label: 'Auswahl (radio)', opts: true },
        checkbox:    { label: 'Checkboxen',      opts: true },
        upload:      { label: 'Datei-Upload' },
        hidden:      { label: 'Verstecktes Feld', val: true },
        explanation: { label: 'Text / Hinweis',  text: true },
        html:        { label: 'HTML',            text: true },
        captcha:     { label: 'Spamschutz' },
        submit:      { label: 'Absenden-Button', slabel: true },
      };
    }

    // Mount the builder under the form element's content panel. The element
    // itself only stores WHICH form (draggo_form_id); the builder edits the
    // real tl_form / tl_form_field rows live via the form API.
    mountFormBuilder(view, contentPanel, elementId) {
      var self = this;
      var sel = contentPanel.wrap.querySelector('select'); // draggo_form_id (first field)

      var box = document.createElement('div');
      box.className = 'draggo-fb';
      view.appendChild(box);

      // "New form" button right beside the binding select.
      if (sel && sel.closest('.draggo-edit-field')) {
        var nb = document.createElement('button');
        nb.type = 'button'; nb.className = 'draggo-fb-new';
        nb.textContent = '+ ' + tr('Neues Formular');
        sel.closest('.draggo-edit-field').appendChild(nb);
        nb.addEventListener('click', function () {
          var title = window.prompt(tr('Name des Formulars:'), 'Formular');
          if (title === null) return;
          self.api('/forms', 'POST', { title: title }).then(function (res) {
            var id = String(res.id);
            if (sel) {
              if (!Array.prototype.some.call(sel.options, function (o) { return o.value === id; })) {
                var o = document.createElement('option'); o.value = id; o.textContent = title || ('#' + id); sel.appendChild(o);
              }
              sel.value = id;
            }
            // Persist the binding immediately so a reload keeps it.
            self.api('/element/' + elementId + '/fields', 'POST', { fields: { draggo_form_id: id } });
            render(id);
          }, function (e) { self.fail(e); });
        });
      }

      var render = function (formId) {
        formId = parseInt(formId, 10) || 0;
        box.innerHTML = '';
        if (!formId) {
          box.innerHTML = '<p class="draggo-fb-empty">' + esc(tr('Wähle oben ein Formular oder lege ein neues an.')) + '</p>';
          return;
        }
        box.innerHTML = '<p class="draggo-fb-empty">…</p>';
        self.api('/form/' + formId).then(function (d) {
          box.innerHTML = '';
          self.renderFormBuilder(box, formId, d);
        }, function (e) { box.innerHTML = ''; self.fail(e); });
      };

      if (sel) sel.addEventListener('change', function () { render(sel.value); });
      render(sel ? sel.value : 0);
    }

    // Render the full builder for one form: field list (add/edit/reorder/
    // delete) + delivery settings. Every action persists instantly.
    renderFormBuilder(box, formId, data) {
      var self = this;
      var KINDS = self.formKinds();
      var debounce = function (fn) { var t; return function () { clearTimeout(t); t = setTimeout(fn, 400); }; };

      // — Fields —
      var fh = document.createElement('h3'); fh.className = 'draggo-fb-h'; fh.textContent = tr('Felder'); box.appendChild(fh);
      var list = document.createElement('div'); list.className = 'draggo-fb-list'; box.appendChild(list);

      var reload = function () { self.api('/form/' + formId).then(function (d) { renderFields(d.fields || []); }, function (e) { self.fail(e); }); };

      var renderFields = function (fields) {
        list.innerHTML = '';
        if (!fields.length) {
          var em = document.createElement('p'); em.className = 'draggo-fb-empty'; em.textContent = tr('Noch keine Felder — unten hinzufügen.'); list.appendChild(em);
        }
        fields.forEach(function (f, idx) {
          list.appendChild(self.formFieldRow(formId, f, KINDS, fields, idx, reload, debounce));
        });
      };
      renderFields(data.fields || []);

      // — Add-field palette —
      var ah = document.createElement('h3'); ah.className = 'draggo-fb-h'; ah.textContent = tr('Feld hinzufügen'); box.appendChild(ah);
      var pal = document.createElement('div'); pal.className = 'draggo-fb-palette'; box.appendChild(pal);
      (data.kinds || Object.keys(KINDS)).forEach(function (kind) {
        if (!KINDS[kind]) return;
        var b = document.createElement('button'); b.type = 'button'; b.className = 'draggo-fb-add';
        b.textContent = KINDS[kind].label;
        b.addEventListener('click', function () {
          self.api('/form/' + formId + '/field', 'POST', { kind: kind }).then(function (d) { renderFields(d.fields || []); }, function (e) { self.fail(e); });
        });
        pal.appendChild(b);
      });

      // — Delivery / settings —
      var sh = document.createElement('h3'); sh.className = 'draggo-fb-h'; sh.textContent = tr('Versand & Einstellungen'); box.appendChild(sh);
      var meta = data.meta || {};
      var mwrap = document.createElement('div'); mwrap.className = 'draggo-fb-meta'; box.appendChild(mwrap);

      var saveMeta = function (patch) { self.api('/form/' + formId + '/meta', 'POST', patch).then(function () {}, function (e) { self.fail(e); }); };

      var emailChk = self.fbBool(mwrap, tr('Per E-Mail versenden'), meta.sendViaEmail, function (v) { saveMeta({ sendViaEmail: v }); });
      var rec = self.fbText(mwrap, tr('Empfänger (E-Mail, mit Komma trennen)'), meta.recipient || '', 'name@domain.de');
      rec.addEventListener('input', debounce(function () { saveMeta({ recipient: rec.value }); }));
      var sub = self.fbText(mwrap, tr('Betreff'), meta.subject || '', tr('Neue Formular-Eingabe'));
      sub.addEventListener('input', debounce(function () { saveMeta({ subject: sub.value }); }));
      var conf = self.fbArea(mwrap, tr('Bestätigungstext (nach dem Absenden)'), meta.confirmation || '');
      conf.addEventListener('input', debounce(function () { saveMeta({ confirmation: conf.value }); }));
    }

    // One editable field row in the builder.
    formFieldRow(formId, f, KINDS, allFields, idx, reload, debounce) {
      var self = this;
      var def = KINDS[f.kind] || { label: f.kind };
      var row = document.createElement('div'); row.className = 'draggo-fb-field';
      // Per-field collapse, remembered across reloads (default: collapsed).
      self._fbOpen = self._fbOpen || {};
      var isOpen = !!self._fbOpen[f.id];
      if (!isOpen) row.classList.add('is-collapsed');

      var head = document.createElement('div'); head.className = 'draggo-fb-field-head';
      var caret = document.createElement('i'); caret.className = 'fas fa-chevron-' + (isOpen ? 'down' : 'right') + ' draggo-fb-caret'; caret.setAttribute('aria-hidden', 'true');
      var badge = document.createElement('span'); badge.className = 'draggo-fb-badge';
      var summary = f.label || f.name || (def.slabel ? (f.slabel || tr('Absenden')) : '');
      badge.innerHTML = '<span class="draggo-fb-kind">' + esc(def.label) + '</span>' + (summary ? '<span class="draggo-fb-sum">' + esc(summary) + '</span>' : '');
      head.appendChild(caret); head.appendChild(badge);

      var tools = document.createElement('span'); tools.className = 'draggo-fb-tools';
      var up = document.createElement('button'); up.type = 'button'; up.title = tr('Nach oben'); up.innerHTML = '<i class="fas fa-arrow-up" aria-hidden="true"></i>'; up.disabled = idx === 0;
      var dn = document.createElement('button'); dn.type = 'button'; dn.title = tr('Nach unten'); dn.innerHTML = '<i class="fas fa-arrow-down" aria-hidden="true"></i>'; dn.disabled = idx === allFields.length - 1;
      var dup = document.createElement('button'); dup.type = 'button'; dup.className = 'draggo-fb-dup'; dup.title = tr('Duplizieren'); dup.innerHTML = '<i class="fas fa-clone" aria-hidden="true"></i>';
      var del = document.createElement('button'); del.type = 'button'; del.className = 'draggo-fb-del'; del.title = tr('Löschen'); del.innerHTML = '<i class="fas fa-trash" aria-hidden="true"></i>';
      tools.appendChild(up); tools.appendChild(dn); tools.appendChild(dup); tools.appendChild(del);
      head.appendChild(tools); row.appendChild(head);

      // Click the header (not a tool button) to expand/collapse.
      head.addEventListener('click', function (e) {
        if (e.target.closest('.draggo-fb-tools')) return;
        row.classList.toggle('is-collapsed');
        var open = !row.classList.contains('is-collapsed');
        self._fbOpen[f.id] = open;
        caret.className = 'fas fa-chevron-' + (open ? 'down' : 'right') + ' draggo-fb-caret';
      });

      var move = function (dir) {
        var ids = allFields.map(function (x) { return x.id; });
        var ni = idx + dir;
        if (ni < 0 || ni >= ids.length) return;
        var tmp = ids[idx]; ids[idx] = ids[ni]; ids[ni] = tmp;
        self.api('/form/' + formId + '/reorder', 'POST', { ids: ids }).then(reload, function (e) { self.fail(e); });
      };
      up.addEventListener('click', function () { move(-1); });
      dn.addEventListener('click', function () { move(1); });
      dup.addEventListener('click', function () {
        self._fbOpen[f.id] = true; // keep the source open so the copy is visible next to it
        self.api('/form-field/' + f.id + '/duplicate', 'POST', {}).then(reload, function (e) { self.fail(e); });
      });
      del.addEventListener('click', function () {
        if (!window.confirm(tr('Dieses Feld löschen?'))) return;
        self.api('/form-field/' + f.id, 'DELETE', {}).then(reload, function (e) { self.fail(e); });
      });

      var save = function (patch) { self.api('/form-field/' + f.id, 'POST', patch).then(function () {}, function (e) { self.fail(e); }); };
      var body = document.createElement('div'); body.className = 'draggo-fb-field-body'; row.appendChild(body);

      // Submit button: only its label matters.
      if (def.slabel) {
        var sl = self.fbText(body, tr('Button-Text'), f.slabel || tr('Absenden'), tr('Absenden'));
        sl.addEventListener('input', debounce(function () { save({ slabel: sl.value }); }));
        return row;
      }
      // Static blocks (html / explanation): a text body, no label/required.
      if (def.text) {
        var tx = self.fbArea(body, tr('Inhalt'), f.text || '');
        tx.addEventListener('input', debounce(function () { save({ text: tx.value }); }));
        return row;
      }

      // Regular inputs: label + name + required (+ placeholder / options /
      // validation / help / autocomplete).
      if (f.kind !== 'hidden') {
        var lab = self.fbText(body, tr('Beschriftung'), f.label || '', '');
        lab.addEventListener('input', debounce(function () { save({ label: lab.value }); }));
      }
      // Field name (submission key) — user-managed, not auto-random.
      var nm = self.fbText(body, tr('Feldname (Übermittlungs-Schlüssel)'), f.name || '', 'z. B. vorname');
      nm.addEventListener('change', function () { save({ name: nm.value }); });
      if (f.kind !== 'hidden') {
        self.fbBool(body, tr('Pflichtfeld'), f.mandatory, function (v) { save({ mandatory: v }); });
      }
      if (def.ph) {
        var ph = self.fbText(body, tr('Platzhalter'), f.placeholder || '', '');
        ph.addEventListener('input', debounce(function () { save({ placeholder: ph.value }); }));
      }
      if (def.val) {
        var dv = self.fbText(body, tr('Wert'), f.value || '', '');
        dv.addEventListener('input', debounce(function () { save({ value: dv.value }); }));
      }
      if (def.opts) {
        // Options as a 2-column repeater (Label + Wert) — no more "Label|wert".
        var ow = document.createElement('div'); ow.className = 'draggo-fb-opts';
        var collect = function () {
          var out = [];
          Array.prototype.forEach.call(ow.querySelectorAll('.draggo-fb-opt'), function (r) {
            var ins = r.querySelectorAll('input');
            out.push({ label: ins[0].value, value: ins[1].value });
          });
          return out;
        };
        var saveOpts = debounce(function () { save({ options: collect() }); });
        var addOpt = function (o) {
          var r = document.createElement('div'); r.className = 'draggo-fb-opt';
          var li = document.createElement('input'); li.type = 'text'; li.placeholder = tr('Label'); li.value = (o && o.label) || '';
          var vi = document.createElement('input'); vi.type = 'text'; vi.placeholder = tr('Wert'); vi.value = (o && o.value) || '';
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.title = tr('Option entfernen');
          x.addEventListener('click', function () { r.remove(); saveOpts(); });
          li.addEventListener('input', saveOpts); vi.addEventListener('input', saveOpts);
          r.appendChild(li); r.appendChild(vi); r.appendChild(x); ow.appendChild(r);
        };
        var olab = document.createElement('span'); olab.className = 'draggo-fb-opts-lbl'; olab.textContent = tr('Optionen (Label + Wert)'); body.appendChild(olab);
        ((f.options && f.options.length) ? f.options : [{ label: '', value: '' }]).forEach(addOpt);
        body.appendChild(ow);
        var oadd = document.createElement('button'); oadd.type = 'button'; oadd.className = 'draggo-fb-optadd'; oadd.textContent = '+ ' + tr('Option');
        oadd.addEventListener('click', function () { addOpt(null); });
        body.appendChild(oadd);
      }
      if (def.len) {
        var lenRow = document.createElement('div'); lenRow.className = 'draggo-fb-row2';
        var mn = self.fbNum(lenRow, tr('Min. Länge'), f.minlength || '');
        var mx = self.fbNum(lenRow, tr('Max. Länge'), f.maxlength || '');
        mn.addEventListener('input', debounce(function () { save({ minlength: mn.value }); }));
        mx.addEventListener('input', debounce(function () { save({ maxlength: mx.value }); }));
        body.appendChild(lenRow);
      }
      var help = self.fbText(body, tr('Hilfetext'), f.help || '', tr('kleiner Hinweis unter dem Feld'));
      help.addEventListener('input', debounce(function () { save({ help: help.value }); }));
      if (def.ac) {
        var ac = self.fbText(body, tr('Autocomplete'), f.autocomplete || '', 'z. B. name, email, tel, off');
        ac.addEventListener('input', debounce(function () { save({ autocomplete: ac.value }); }));
      }

      // Responsive column width (Desktop base / Tablet ≤991 / Mobil ≤767;
      // leer = vom breiteren Breakpoint erben). Only for kinds that render a
      // real, sizeable widget — captcha/hidden excluded (layout-sensitive /
      // invisible). All three are sent together so updateField rebuilds the
      // class string from the full {d,t,m} (a partial patch would wipe the rest).
      if (['text','email','phone','number','textarea','select','radio','checkbox','upload'].indexOf(f.kind) !== -1) {
        var W = [['', tr('Erben / 100%')], ['100','100%'], ['75','75%'], ['66','66%'], ['50','50%'], ['33','33%'], ['25','25%']];
        var w = f.width || { d: '', t: '', m: '' };
        var saveW = function () { save({ width: { d: wD.value, t: wT.value, m: wM.value } }); };
        var wD = self.fbSelect(body, tr('Breite Desktop'), w.d, W);
        var wT = self.fbSelect(body, tr('Breite Tablet (≤991px)'), w.t, W);
        var wM = self.fbSelect(body, tr('Breite Mobil (≤767px)'), w.m, W);
        wD.addEventListener('change', saveW);
        wT.addEventListener('change', saveW);
        wM.addEventListener('change', saveW);
      }
      return row;
    }

    // Small labelled control helpers for the builder.
    fbText(parent, label, value, placeholder) {
      var l = document.createElement('label'); l.className = 'draggo-fb-row';
      l.innerHTML = '<span>' + esc(label) + '</span>';
      var i = document.createElement('input'); i.type = 'text'; i.value = value || ''; if (placeholder) i.placeholder = placeholder;
      l.appendChild(i); parent.appendChild(l); return i;
    }
    fbArea(parent, label, value) {
      var l = document.createElement('label'); l.className = 'draggo-fb-row';
      l.innerHTML = '<span>' + esc(label) + '</span>';
      var t = document.createElement('textarea'); t.rows = 3; t.value = value || '';
      l.appendChild(t); parent.appendChild(l); return t;
    }
    fbBool(parent, label, checked, onChange) {
      var l = document.createElement('label'); l.className = 'draggo-fb-row draggo-fb-bool';
      var c = document.createElement('input'); c.type = 'checkbox'; c.checked = !!checked;
      var s = document.createElement('span'); s.textContent = label;
      l.appendChild(c); l.appendChild(s); parent.appendChild(l);
      c.addEventListener('change', function () { onChange(c.checked ? '1' : ''); });
      return c;
    }
    fbSelect(parent, label, value, opts) {
      var l = document.createElement('label'); l.className = 'draggo-fb-row';
      l.innerHTML = '<span>' + esc(label) + '</span>';
      var s = document.createElement('select');
      opts.forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); s.appendChild(op); });
      s.value = value || '';
      l.appendChild(s); parent.appendChild(l); return s;
    }
    fbNum(parent, label, value) {
      var l = document.createElement('label'); l.className = 'draggo-fb-row';
      l.innerHTML = '<span>' + esc(label) + '</span>';
      var i = document.createElement('input'); i.type = 'number'; i.min = '0'; i.value = (value === 0 || value === '0') ? '' : (value || '');
      l.appendChild(i); parent.appendChild(l); return i;
    }

    // Generic control renderer (Phase A control-system). Takes a declarative
    // schema (groups → controls) and renders the whole panel. Each group has a
    // `bucket` ('field' = saved to tl_content via buildField; 'style' = saved
    // to draggo_layout). get() returns {field, style}. Conditions show/hide
    // live. destroyers carries TinyMCE teardown callbacks.
    // Returns {wrap, get(), destroyers}.
    buildControls(groups, values) {
      var self = this;
      values = values || {};
      var wrap = document.createElement('div');
      wrap.className = 'draggo-style-sec';
      var reg = {};
      var destroyers = [];

      var curRaw = function (key) { return reg[key] ? reg[key].raw() : undefined; };
      var condOk = function (c) {
        if (!c) return true;
        var v = curRaw(c.field); var op = c.op || 'truthy';
        if (op === 'truthy') return !!v;
        if (op === 'eq') return String(v == null ? '' : v) === String(c.value);
        if (op === 'neq') return String(v == null ? '' : v) !== String(c.value);
        return true;
      };
      var refresh = function () {
        Object.keys(reg).forEach(function (k) { var r = reg[k]; if (r.cond) r.row.style.display = condOk(r.cond) ? '' : 'none'; });
      };

      var rowEl = function (label, control) {
        // div (not label) so clicking the row doesn't auto-open the colour picker.
        var l = document.createElement('div'); l.className = 'draggo-ins-row';
        var sp = document.createElement('span'); sp.textContent = label; l.appendChild(sp); l.appendChild(control);
        return l;
      };

      // Content controls reuse the existing per-widget builders (TinyMCE,
      // picker, table/pairs editors) via buildField.
      var buildField1 = function (c, val) {
        var b = self.buildField({ widget: c.type, label: c.label, name: c.key, value: val, options: c.options, units: c.units, unit: c.unit, accept: c.accept });
        if (b.destroy) destroyers.push(b.destroy);
        return {
          row: b.wrap,
          getv: b.get.value,
          raw: function () { var v = b.get.value(); return (v && typeof v === 'object' && 'value' in v) ? v.value : v; },
          bucket: 'field',
        };
      };

      var buildStyle1 = function (c, val) {
        var t = c.type, el, get, raw;
        if (t === 'select') {
          el = document.createElement('select'); el.className = 'draggo-ins-select';
          (c.options || []).forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); if (String(val == null ? '' : val) === o[0]) op.selected = true; el.appendChild(op); });
          // Font family: offer global font tokens too.
          if (c.key === 'fontFamily') {
            self.tokensOfType('font').forEach(function (tok) {
              var op = document.createElement('option'); op.value = 'var(--bld-font-' + tok.token + ')'; op.textContent = '★ ' + (tok.label || tok.token);
              if (String(val == null ? '' : val) === op.value) op.selected = true; el.appendChild(op);
            });
          }
          get = function () { return el.value || undefined; }; raw = function () { return el.value; };
        } else if (t === 'number') {
          el = document.createElement('input'); el.type = 'number'; el.className = 'draggo-ins-num';
          el.value = (val === 0 || val) ? val : ''; if (c.placeholder) el.placeholder = c.placeholder;
          if (c.step != null) el.step = c.step; if (c.min != null) el.min = c.min; if (c.max != null) el.max = c.max;
          get = function () { return el.value !== '' ? parseFloat(el.value) : undefined; }; raw = function () { return el.value; };
        } else if (t === 'color') {
          var cctrl = colorControl(val, self.tokensOfType('color'));
          el = cctrl.el; get = cctrl.get; raw = function () { return cctrl.get() || ''; };
        } else if (t === 'length') {
          var lc = document.createElement('span'); lc.className = 'draggo-ins-color';
          var ln = document.createElement('input'); ln.type = 'number'; ln.className = 'draggo-ins-num';
          var lu = document.createElement('select'); lu.className = 'draggo-ins-select';
          (c.units || ['px', '%', 'rem', 'em', 'vh', 'vw']).forEach(function (u) { var o = document.createElement('option'); o.value = u; o.textContent = u; lu.appendChild(o); });
          var mm = val ? String(val).match(/^(\d+(?:\.\d+)?)(px|%|rem|em|vh|vw)$/) : null;
          if (mm) { ln.value = mm[1]; lu.value = mm[2]; }
          lc.appendChild(ln); lc.appendChild(lu); el = lc;
          get = function () { return ln.value !== '' ? (ln.value + lu.value) : undefined; }; raw = function () { return ln.value; };
        } else if (t === 'switcher') {
          el = document.createElement('input'); el.type = 'checkbox'; el.className = 'draggo-ins-chk'; el.checked = !!val;
          get = function () { return el.checked ? 1 : undefined; }; raw = function () { return el.checked; };
        } else if (t === 'icon') {
          var iconVal = val || '';
          var ibyKey = {}; (self._icons || []).forEach(function (it) { ibyKey[it.key] = it; });
          var iwrap = document.createElement('span'); iwrap.className = 'draggo-ins-color';
          var iprev = document.createElement('span'); iprev.className = 'draggo-iconpick-prev';
          var idraw = function () {
            if (iconVal && /(?:^|\s)fa[bsrl]?-|fa-/.test(iconVal)) iprev.innerHTML = '<i class="draggo-fa ' + esc(iconVal) + '"></i>';
            else if (iconVal && ibyKey[iconVal]) iprev.innerHTML = ibyKey[iconVal].svg;
            else iprev.innerHTML = '<span class="draggo-iconpick-none">—</span>';
          };
          idraw();
          var ipb = document.createElement('button'); ipb.type = 'button'; ipb.className = 'draggo-file-pick'; ipb.textContent = 'Icon';
          var icb = document.createElement('button'); icb.type = 'button'; icb.className = 'draggo-file-clr'; icb.textContent = '✕';
          ipb.addEventListener('click', function () { self.iconPicker(iconVal, function (k) { iconVal = k; idraw(); }); });
          icb.addEventListener('click', function () { iconVal = ''; idraw(); });
          iwrap.appendChild(iprev); iwrap.appendChild(ipb); iwrap.appendChild(icb);
          el = iwrap;
          get = function () { return iconVal || undefined; }; raw = function () { return iconVal; };
        } else if (t === 'choose') {
          var aw = document.createElement('span'); aw.className = 'draggo-ins-aligns'; aw._v = val || '';
          (c.choices || []).forEach(function (ch) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'draggo-ins-align' + (aw._v === ch[0] ? ' is-on' : '');
            b.innerHTML = svgIcon(ch[1]);
            b.addEventListener('click', function () {
              aw.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-on'); });
              if (aw._v === ch[0] && c.toggle) { aw._v = ''; } else { aw._v = ch[0]; b.classList.add('is-on'); }
            });
            aw.appendChild(b);
          });
          el = aw; get = function () { return aw._v || undefined; }; raw = function () { return aw._v; };
        } else if (t === 'dimensions') {
          var v = val || {};
          var dw = document.createElement('span'); dw.className = 'draggo-ins-dim';
          var ins = {};
          ['top', 'right', 'bottom', 'left'].forEach(function (side) {
            var i = document.createElement('input'); i.type = 'number'; i.className = 'draggo-ins-num'; i.placeholder = side.charAt(0).toUpperCase();
            i.value = (v[side] === 0 || v[side]) ? v[side] : '';
            ins[side] = i; dw.appendChild(i);
          });
          var du = document.createElement('select'); du.className = 'draggo-ins-select';
          (c.units || ['px', '%', 'rem', 'em']).forEach(function (u) { var o = document.createElement('option'); o.value = u; o.textContent = u; if ((v.unit || 'px') === u) o.selected = true; du.appendChild(o); });
          dw.appendChild(du);
          el = dw;
          var dimGet = function () {
            var any = ['top', 'right', 'bottom', 'left'].some(function (s) { return ins[s].value !== ''; });
            if (!any) return undefined;
            return { top: ins.top.value, right: ins.right.value, bottom: ins.bottom.value, left: ins.left.value, unit: du.value };
          };
          get = dimGet; raw = function () { return dimGet() ? '1' : ''; };
        } else if (t === 'textarea') {
          el = document.createElement('textarea'); el.className = 'draggo-ins-area'; el.rows = c.rows || 4; el.value = val || ''; if (c.placeholder) el.placeholder = c.placeholder;
          get = function () { return el.value.trim() || undefined; }; raw = function () { return el.value; };
        } else {
          el = document.createElement('input'); el.type = 'text'; el.value = val || '';
          get = function () { return el.value.trim() || undefined; }; raw = function () { return el.value; };
        }
        return { row: rowEl(tr(c.label), el), getv: get, raw: raw, bucket: 'style' };
      };

      (groups || []).forEach(function (g) {
        var bucket = g.bucket || 'style';
        var d = document.createElement('details'); d.className = 'draggo-grp'; if (g.open) d.open = true;
        var sm = document.createElement('summary'); sm.textContent = tr(g.title); d.appendChild(sm);
        var body = document.createElement('div'); body.className = 'draggo-grp-body'; d.appendChild(body);
        (g.controls || []).forEach(function (c) {
          var val = (c.value !== undefined) ? c.value : values[c.key];
          var built = (bucket === 'field') ? buildField1(c, val) : buildStyle1(c, val);
          body.appendChild(built.row);
          reg[c.key] = { row: built.row, getv: built.getv, raw: built.raw, cond: c.condition, bucket: built.bucket, widget: c.type };
        });
        wrap.appendChild(d);
      });

      wrap.addEventListener('input', refresh);
      wrap.addEventListener('change', refresh);
      refresh();

      return {
        wrap: wrap,
        destroyers: destroyers,
        controls: reg,
        get: function () {
          var o = { field: {}, style: {} };
          Object.keys(reg).forEach(function (k) {
            var r = reg[k]; if (!condOk(r.cond)) return;
            var v = r.getv();
            if (r.bucket === 'field') {
              o.field[k] = v; // content saved even when empty (allows clearing)
            } else if (v !== undefined && v !== null && v !== '') {
              o.style[k] = v;
            }
          });
          return o;
        },
      };
    }

    // DEPRECATED (Phase A): replaced by buildControls + server StyleSchema.
    // Kept temporarily, no caller. Remove once content migration is verified.
    buildStyleSection(s, type) {
      s = s || {};
      var showTypo = ['text', 'headline', 'html', 'code', 'markdown', 'unfiltered_html', 'hyperlink'].indexOf(type) >= 0;
      var isImage = type === 'image' || type === 'gallery';
      var wrap = document.createElement('div');
      wrap.className = 'draggo-style-sec';

      var ctrls = {};
      // Collapsible group; subsequent row()s land in the active group body.
      var box = wrap;
      var group = function (title, open) {
        var d = document.createElement('details'); d.className = 'draggo-grp'; if (open) d.open = true;
        var sm = document.createElement('summary'); sm.textContent = title; d.appendChild(sm);
        var body = document.createElement('div'); body.className = 'draggo-grp-body'; d.appendChild(body);
        wrap.appendChild(d); box = body; return body;
      };
      var row = function (label, el) {
        var l = document.createElement('label'); l.className = 'draggo-ins-row';
        var sp = document.createElement('span'); sp.textContent = label; l.appendChild(sp); l.appendChild(el);
        box.appendChild(l); return el;
      };
      var chk = function (val) { var e = document.createElement('input'); e.type = 'checkbox'; e.className = 'draggo-ins-chk'; e.checked = !!val; return e; };
      var sel = function (opts, val) {
        var e = document.createElement('select'); e.className = 'draggo-ins-select';
        opts.forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); if (String(val || '') === o[0]) op.selected = true; e.appendChild(op); });
        return e;
      };
      var num = function (val, ph) { var e = document.createElement('input'); e.type = 'number'; e.className = 'draggo-ins-num'; e.value = (val === 0 || val) ? val : ''; if (ph) e.placeholder = ph; return e; };
      var colorCtrl = function (val) {
        var s2 = document.createElement('span'); s2.className = 'draggo-ins-color';
        var c = document.createElement('input'); c.type = 'color'; c.value = val || '#000000';
        var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕';
        s2.appendChild(c); s2.appendChild(x); s2._c = c; s2._set = !!val; x.addEventListener('click', function () { s2._set = false; c.value = '#000000'; });
        c.addEventListener('input', function () { s2._set = true; });
        return s2;
      };

      var lenCtrl = function (val) {
        var c = document.createElement('span'); c.className = 'draggo-ins-color';
        var n = document.createElement('input'); n.type = 'number'; n.className = 'draggo-ins-num';
        var u = document.createElement('select'); u.className = 'draggo-ins-select';
        ['px', '%', 'rem', 'em', 'vh', 'vw'].forEach(function (x) { var o = document.createElement('option'); o.value = x; o.textContent = x; u.appendChild(o); });
        var mm = val ? String(val).match(/^(\d+(?:\.\d+)?)(px|%|rem|em|vh|vw)$/) : null;
        if (mm) { n.value = mm[1]; u.value = mm[2]; }
        c.appendChild(n); c.appendChild(u);
        c._get = function () { return n.value !== '' ? (n.value + u.value) : ''; };
        return c;
      };

      var fonts = [['', 'Standard'], ['Arial', 'Arial'], ['Helvetica', 'Helvetica'], ['Georgia', 'Georgia'], ['Times New Roman', 'Times'], ['Courier New', 'Courier'], ['Verdana', 'Verdana'], ['Tahoma', 'Tahoma'], ['Trebuchet MS', 'Trebuchet'], ['system-ui', 'System']];
      var weights = [['', 'Standard'], ['300', 'Light'], ['400', 'Normal'], ['500', 'Medium'], ['600', 'Semibold'], ['700', 'Bold'], ['800', 'Extrabold'], ['900', 'Black']];

      // ── Gruppe: Typografie (nur Text-Typen) ─────────────────────
      if (showTypo) {
        group('Typografie', true);
        ctrls.fontFamily = row('Schriftart', sel(fonts, s.fontFamily));
        ctrls.fontSize = row('Größe (px)', num(s.fontSize, 'px'));
        ctrls.fontWeight = row('Gewicht', sel(weights, s.fontWeight));
        ctrls.textTransform = row('Transform', sel([['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']], s.textTransform));
        ctrls.fontStyle = row('Stil', sel([['', '–'], ['normal', 'Normal'], ['italic', 'Kursiv']], s.fontStyle));
        ctrls.textDecoration = row('Dekoration', sel([['', '–'], ['none', 'Keine'], ['underline', 'Unterstrichen'], ['line-through', 'Durchgestrichen']], s.textDecoration));
        ctrls.lineHeight = row('Zeilenhöhe', (function () { var e = num(s.lineHeight, '1.5'); e.step = '0.1'; return e; })());
        ctrls.letterSpacing = row('Buchstabenabstand (px)', num(s.letterSpacing, 'px'));
        ctrls.wordSpacing = row('Wortabstand (px)', num(s.wordSpacing, 'px'));
        ctrls.color = row('Textfarbe', colorCtrl(s.color));
        ctrls.linkColor = row('Linkfarbe', colorCtrl(s.linkColor));
        ctrls.textShadow = row('Textschatten', sel([['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß']], s.textShadow));
        ctrls.textGradient = row('Verlaufstext', chk(s.textGradient));
        ctrls.gradFrom = row('Verlauf von', colorCtrl(s.gradFrom));
        ctrls.gradTo = row('Verlauf bis', colorCtrl(s.gradTo));
      }

      // ── Gruppe: Layout & Abstände (alle) ────────────────────────
      group('Layout & Abstände', !showTypo);
      ctrls.padding = row('Innenabstand (padding)', sel(PADDINGS, s.padding));
      ctrls.margin = row('Außenabstand (margin)', sel(PADDINGS, s.margin));
      var alignWrap = document.createElement('span'); alignWrap.className = 'draggo-ins-aligns';
      ['left', 'center', 'right'].forEach(function (v) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'draggo-ins-align' + (s.align === v ? ' is-on' : ''); b.dataset.al = v;
        b.innerHTML = svgIcon(v === 'left' ? 'tleft' : v === 'center' ? 'tcenter' : 'tright');
        b.addEventListener('click', function () { alignWrap.querySelectorAll('button').forEach(function (x) { x.classList.remove('is-on'); }); if (alignWrap._v === v) { alignWrap._v = ''; } else { alignWrap._v = v; b.classList.add('is-on'); } });
        alignWrap.appendChild(b);
      });
      alignWrap._v = s.align || '';
      row('Ausrichtung', alignWrap);

      // ── Gruppe: Größe & Rahmen (alle) ───────────────────────────
      group('Größe & Rahmen', false);
      ctrls.width = row('Breite', lenCtrl(s.width));
      ctrls.maxWidth = row('Max-Breite', lenCtrl(s.maxWidth));
      ctrls.height = row('Höhe', lenCtrl(s.height));
      ctrls.opacity = row('Deckkraft (0–1)', (function () { var e = num(s.opacity, '1'); e.step = '0.1'; e.min = '0'; e.max = '1'; return e; })());
      ctrls.borderStyle = row('Rahmen-Stil', sel([['', 'Keiner'], ['solid', 'Solid'], ['dashed', 'Gestrichelt'], ['dotted', 'Gepunktet'], ['double', 'Doppelt']], s.borderStyle));
      ctrls.borderWidth = row('Rahmen-Breite (px)', num(s.borderWidth, 'px'));
      ctrls.borderColor = row('Rahmen-Farbe', colorCtrl(s.borderColor));
      ctrls.borderRadius = row('Eckenradius (px)', num(s.borderRadius, 'px'));
      ctrls.boxShadow = row('Schatten', sel([['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß'], ['xl', 'Sehr groß']], s.boxShadow));

      // ── Gruppe: Effekte (alle; Lightbox/Zoom nur Bild) ──────────
      group('Effekte', false);
      ctrls.animation = row('Scroll-Animation', sel([['', 'Keine'], ['fade', 'Einblenden'], ['fade-up', 'Hoch'], ['fade-down', 'Runter'], ['zoom', 'Zoom'], ['slide-left', 'Von links'], ['slide-right', 'Von rechts']], s.animation));
      ctrls.animDuration = row('Dauer (ms)', num(s.animDuration, '600'));
      ctrls.animDelay = row('Verzögerung (ms)', num(s.animDelay, '0'));
      ctrls.sticky = row('Sticky', chk(s.sticky));
      ctrls.stickyOffset = row('Sticky-Offset (px)', num(s.stickyOffset, '0'));
      if (isImage) {
        ctrls.lightbox = row('Lightbox', chk(s.lightbox));
        ctrls.hoverZoom = row('Hover-Zoom', chk(s.hoverZoom));
      }

      // ── Gruppe: Sichtbarkeit (alle) ─────────────────────────────
      group('Sichtbarkeit', false);
      ctrls.hideDesktop = row('Auf Desktop verbergen', chk(s.hideDesktop));
      ctrls.hideTablet = row('Auf Tablet verbergen', chk(s.hideTablet));
      ctrls.hideMobile = row('Auf Mobil verbergen', chk(s.hideMobile));

      return {
        wrap: wrap,
        get: function () {
          var o = {};
          ['width', 'maxWidth', 'height'].forEach(function (k) { var v = ctrls[k]._get ? ctrls[k]._get() : ''; if (v) o[k] = v; });
          if (ctrls.opacity.value !== '') o.opacity = parseFloat(ctrls.opacity.value);
          if (ctrls.borderStyle.value) o.borderStyle = ctrls.borderStyle.value;
          if (ctrls.borderWidth.value) o.borderWidth = parseInt(ctrls.borderWidth.value, 10);
          if (ctrls.borderColor._set) o.borderColor = ctrls.borderColor._c.value;
          if (ctrls.borderRadius.value) o.borderRadius = parseInt(ctrls.borderRadius.value, 10);
          if (ctrls.boxShadow.value) o.boxShadow = ctrls.boxShadow.value;
          if (showTypo) {
            if (ctrls.fontFamily.value) o.fontFamily = ctrls.fontFamily.value;
            if (ctrls.fontSize.value) o.fontSize = parseInt(ctrls.fontSize.value, 10);
            if (ctrls.fontWeight.value) o.fontWeight = ctrls.fontWeight.value;
            if (ctrls.textTransform.value) o.textTransform = ctrls.textTransform.value;
            if (ctrls.fontStyle.value) o.fontStyle = ctrls.fontStyle.value;
            if (ctrls.textDecoration.value) o.textDecoration = ctrls.textDecoration.value;
            if (ctrls.lineHeight.value) o.lineHeight = parseFloat(ctrls.lineHeight.value);
            if (ctrls.letterSpacing.value !== '') o.letterSpacing = parseFloat(ctrls.letterSpacing.value);
            if (ctrls.wordSpacing.value !== '') o.wordSpacing = parseFloat(ctrls.wordSpacing.value);
            if (ctrls.color._set) o.color = ctrls.color._c.value;
            if (ctrls.linkColor._set) o.linkColor = ctrls.linkColor._c.value;
            if (ctrls.textShadow.value) o.textShadow = ctrls.textShadow.value;
            if (ctrls.textGradient.checked) {
              o.textGradient = 1;
              if (ctrls.gradFrom._set) o.gradFrom = ctrls.gradFrom._c.value;
              if (ctrls.gradTo._set) o.gradTo = ctrls.gradTo._c.value;
            }
          }
          if (ctrls.padding.value) o.padding = ctrls.padding.value;
          if (ctrls.margin.value) o.margin = ctrls.margin.value;
          if (alignWrap._v) o.align = alignWrap._v;
          if (ctrls.animation.value) {
            o.animation = ctrls.animation.value;
            if (ctrls.animDuration.value) o.animDuration = parseInt(ctrls.animDuration.value, 10);
            if (ctrls.animDelay.value) o.animDelay = parseInt(ctrls.animDelay.value, 10);
          }
          if (ctrls.sticky.checked) {
            o.sticky = 1;
            if (ctrls.stickyOffset.value) o.stickyOffset = parseInt(ctrls.stickyOffset.value, 10);
          }
          if (isImage) {
            if (ctrls.lightbox.checked) o.lightbox = 1;
            if (ctrls.hoverZoom.checked) o.hoverZoom = 1;
          }
          if (ctrls.hideDesktop.checked) o.hideDesktop = 1;
          if (ctrls.hideTablet.checked) o.hideTablet = 1;
          if (ctrls.hideMobile.checked) o.hideMobile = 1;
          return o;
        },
      };
    }

    // Modal file picker with folder navigation (like the Contao file manager).
    // accept = 'any' or a comma list of kinds. multiple → checkbox select.
    filePicker(accept, multiple, selected, onPick) {
      var self = this;
      var kinds = (accept && accept !== 'any') ? accept.split(',') : null;
      var all = (this._files || []).filter(function (f) { return !kinds || kinds.indexOf(f.kind) >= 0; });
      var dirs = (this._dirs || []).slice();
      var sel = {}; (selected || []).forEach(function (p) { sel[p] = true; });
      var cwd = 'files';
      var parentOf = function (p) { var i = p.lastIndexOf('/'); return i < 0 ? '' : p.slice(0, i); };
      var nameOf = function (p) { var i = p.lastIndexOf('/'); return i < 0 ? p : p.slice(i + 1); };

      var ov = document.createElement('div'); ov.className = 'draggo-fp';
      var box = document.createElement('div'); box.className = 'draggo-fp-box';
      var head = document.createElement('div'); head.className = 'draggo-fp-head';
      head.innerHTML = '<strong>Datei wählen</strong>';
      var nf = document.createElement('button'); nf.type = 'button'; nf.className = 'draggo-fp-nf'; nf.textContent = '+ Ordner';
      var up = document.createElement('button'); up.type = 'button'; up.className = 'draggo-fp-up';
      up.textContent = multiple ? '⤒ Hochladen (mehrere)' : '⤒ Hochladen';
      up.title = multiple ? 'Mehrere Dateien wählen oder in die Liste ziehen' : 'Datei wählen oder in die Liste ziehen';
      var fileInput = document.createElement('input'); fileInput.type = 'file'; fileInput.style.display = 'none';
      // Uploading is ALWAYS multi-capable (drop a whole batch into the library
      // in one go) — independent of how many values the field stores. A
      // single-value field just assigns one of them afterwards.
      fileInput.multiple = true;
      if (kinds) {
        var acc = []; kinds.forEach(function (k) { if (k === 'image') acc.push('image/*'); else if (k === 'video') acc.push('video/*'); else if (k === 'audio') acc.push('audio/*'); });
        if (acc.length) fileInput.accept = acc.join(',');
      }
      var x = document.createElement('button'); x.type = 'button'; x.className = 'draggo-fp-x'; x.textContent = '✕';
      head.appendChild(nf); head.appendChild(up); head.appendChild(x);
      box.appendChild(fileInput);

      var crumb = document.createElement('div'); crumb.className = 'draggo-fp-crumb';
      var search = document.createElement('input'); search.type = 'search'; search.className = 'draggo-fp-search'; search.placeholder = 'Suchen…';
      var grid = document.createElement('div'); grid.className = 'draggo-fp-grid';
      // Upload-progress panel (hidden until an upload starts).
      var prog = document.createElement('div'); prog.className = 'draggo-fp-prog'; prog.hidden = true;
      var progHead = document.createElement('div'); progHead.className = 'draggo-fp-prog-head';
      var progList = document.createElement('div'); progList.className = 'draggo-fp-prog-list';
      var progDone = document.createElement('button'); progDone.type = 'button'; progDone.className = 'draggo-fp-prog-done'; progDone.textContent = 'Fertig'; progDone.hidden = true;
      prog.appendChild(progHead); prog.appendChild(progList); prog.appendChild(progDone);
      box.appendChild(head); box.appendChild(crumb); box.appendChild(search); box.appendChild(prog); box.appendChild(grid);
      if (multiple) {
        var foot = document.createElement('div'); foot.className = 'draggo-fp-foot';
        var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'draggo-fp-ok'; ok.textContent = 'Übernehmen';
        ok.addEventListener('click', function () { onPick(Object.keys(sel)); destroy(); });
        foot.appendChild(ok); box.appendChild(foot);
      }
      ov.appendChild(box);
      (this.root || document.body).appendChild(ov);

      function destroy() { ov.remove(); document.removeEventListener('keydown', onKey); }
      function onKey(e) { if (e.key === 'Escape') destroy(); }
      document.addEventListener('keydown', onKey);
      x.addEventListener('click', destroy);
      ov.addEventListener('click', function (e) { if (e.target === ov) destroy(); });

      up.addEventListener('click', function () { fileInput.click(); });
      fileInput.addEventListener('change', function () {
        if (!fileInput.files.length) return;
        runUploads(Array.prototype.slice.call(fileInput.files));
        fileInput.value = '';
      });

      // Drag & drop onto the grid uploads too (multi when the field allows it).
      grid.addEventListener('dragover', function (e) { e.preventDefault(); grid.classList.add('is-drop'); });
      grid.addEventListener('dragleave', function () { grid.classList.remove('is-drop'); });
      grid.addEventListener('drop', function (e) {
        e.preventDefault(); grid.classList.remove('is-drop');
        var fl = e.dataTransfer && e.dataTransfer.files ? Array.prototype.slice.call(e.dataTransfer.files) : [];
        if (!fl.length) return;
        runUploads(fl);
      });

      // Upload a list of files with a 3-wide concurrency pool, rendering a live
      // progress row per file (queued → % → ✓ done / ✕ error).
      function runUploads(files) {
        if (!files.length) return;
        var firstPath = null, okCount = 0, errCount = 0, pending = files.length;
        prog.hidden = false; progDone.hidden = true; progList.innerHTML = '';
        up.disabled = true;

        var rows = files.map(function (file) {
          var row = document.createElement('div'); row.className = 'draggo-fp-prog-row';
          var nm = document.createElement('span'); nm.className = 'draggo-fp-prog-name'; nm.textContent = file.name;
          var bar = document.createElement('span'); bar.className = 'draggo-fp-prog-bar';
          var fill = document.createElement('span'); fill.className = 'draggo-fp-prog-fill'; bar.appendChild(fill);
          var st = document.createElement('span'); st.className = 'draggo-fp-prog-st'; st.textContent = 'Warte…';
          row.appendChild(nm); row.appendChild(bar); row.appendChild(st);
          progList.appendChild(row);
          return { file: file, fill: fill, st: st, row: row };
        });

        function updateHead() {
          progHead.textContent = 'Lade ' + files.length + ' Datei' + (files.length > 1 ? 'en' : '')
            + ' hoch · ' + okCount + ' fertig' + (errCount ? ' · ' + errCount + ' fehlgeschlagen' : '');
        }
        updateHead();

        function finish() {
          up.disabled = false;
          // Refresh the grid to show freshly uploaded files; keep panel open so
          // the user sees the result, with a Done button to dismiss it.
          render();
          // Single-value field + exactly one file → assign it and close (the
          // old one-shot flow). If several were uploaded into a single-value
          // field, stay open so the user picks which one to use.
          if (errCount === 0 && !multiple && firstPath && files.length === 1) { onPick(firstPath); destroy(); return; }
          progHead.textContent = okCount + ' Datei' + (okCount !== 1 ? 'en' : '') + ' hochgeladen'
            + (errCount ? ' · ' + errCount + ' fehlgeschlagen' : '') + '.';
          progDone.hidden = false;
        }

        var queue = rows.slice();
        function pump() {
          if (!queue.length) return;
          var item = queue.shift();
          item.st.textContent = '0 %';
          self.uploadFileXhr(item.file, cwd, function (pct) {
            item.fill.style.width = pct + '%'; item.st.textContent = pct + ' %';
          }).then(function (res) {
            item.fill.style.width = '100%'; item.row.classList.add('is-ok'); item.st.textContent = '✓';
            self._files.push(res);
            if (!kinds || kinds.indexOf(res.kind) >= 0) { all.push(res); if (multiple) sel[res.path] = true; }
            if (firstPath === null) firstPath = res.path;
            okCount++;
          }, function (err) {
            item.row.classList.add('is-err'); item.st.textContent = '✕';
            item.st.title = (err && err.error && err.error.message) || 'Fehler';
            errCount++;
          }).then(function () {
            updateHead();
            if (--pending === 0) finish(); else pump();
          });
        }
        // Kick off up to 3 in parallel.
        for (var i = 0; i < Math.min(3, queue.length); i++) pump();
      }

      progDone.addEventListener('click', function () {
        if (multiple) { prog.hidden = true; progDone.hidden = true; render(); }
        else { destroy(); }
      });

      nf.addEventListener('click', function () {
        var name = window.prompt('Name des neuen Ordners:');
        if (!name) return;
        self.api('/files/folder', 'POST', { parent: cwd, name: name }).then(function (res) {
          if (res && res.path) { dirs.push(res.path); cwd = res.path; search.value = ''; render(); }
        }, function (err) { window.alert('Ordner anlegen fehlgeschlagen: ' + ((err && err.error && err.error.message) || 'Fehler')); });
      });

      function go(dir) { cwd = dir; search.value = ''; render(); }

      function renderCrumb() {
        crumb.innerHTML = '';
        var segs = cwd.split('/');
        var acc2 = '';
        segs.forEach(function (s, i) {
          acc2 = i === 0 ? s : acc2 + '/' + s;
          var path = acc2;
          var b = document.createElement('button'); b.type = 'button'; b.className = 'draggo-fp-cr'; b.textContent = i === 0 ? 'Dateien' : s;
          b.addEventListener('click', function () { go(path); });
          crumb.appendChild(b);
          if (i < segs.length - 1) { var sep = document.createElement('span'); sep.className = 'draggo-fp-crsep'; sep.textContent = '›'; crumb.appendChild(sep); }
        });
      }

      function fileCell(f) {
        var cell = document.createElement('button'); cell.type = 'button';
        cell.className = 'draggo-fp-item' + (sel[f.path] ? ' is-sel' : '');
        var thumb = f.kind === 'image'
          ? '<span class="draggo-fp-thumb" style="background-image:url(/' + esc(f.path) + ')"></span>'
          : '<span class="draggo-fp-thumb draggo-fp-ic">' + esc(f.kind.slice(0, 3).toUpperCase()) + '</span>';
        cell.innerHTML = thumb + '<span class="draggo-fp-name">' + esc(nameOf(f.path)) + '</span>';
        cell.addEventListener('click', function () {
          if (multiple) { if (sel[f.path]) { delete sel[f.path]; cell.classList.remove('is-sel'); } else { sel[f.path] = true; cell.classList.add('is-sel'); } }
          else { onPick(f.path); destroy(); }
        });
        return cell;
      }

      function render() {
        renderCrumb();
        grid.innerHTML = '';
        var q = search.value.toLowerCase();

        if (q) {
          // Global flat search across all matching files.
          all.forEach(function (f) { if (f.path.toLowerCase().indexOf(q) >= 0) grid.appendChild(fileCell(f)); });
          if (!grid.children.length) grid.innerHTML = '<p class="draggo-fp-empty">Nichts gefunden.</p>';
          return;
        }

        // Subfolders of the current directory.
        dirs.filter(function (d) { return parentOf(d) === cwd; }).sort().forEach(function (d) {
          var cell = document.createElement('button'); cell.type = 'button'; cell.className = 'draggo-fp-item draggo-fp-dir';
          cell.innerHTML = '<span class="draggo-fp-thumb draggo-fp-folder">📁</span><span class="draggo-fp-name">' + esc(nameOf(d)) + '</span>';
          cell.addEventListener('click', function () { go(d); });
          grid.appendChild(cell);
        });

        // Files in the current directory.
        all.filter(function (f) { return parentOf(f.path) === cwd; }).forEach(function (f) { grid.appendChild(fileCell(f)); });

        if (!grid.children.length) grid.innerHTML = '<p class="draggo-fp-empty">Ordner ist leer.</p>';
      }
      search.addEventListener('input', render);
      render();
    }

    // Icon picker (Tier 1): modal grid with TWO sources — the built-in inline
    // SVG library and (optional) Font Awesome. Live search; onPick(value) where
    // value is an SVG key or an FA class string.
    iconPicker(current, onPick) {
      var self = this;
      var svgIcons = this._icons || [];
      var faIcons = this._faIcons || [];
      var src = (current && /(?:^|\s)fa[bsrl]?-|fa-/.test(current)) ? 'fa' : 'svg';

      var overlay = document.createElement('div'); overlay.className = 'draggo-iconmodal';
      var box = document.createElement('div'); box.className = 'draggo-iconmodal-box';
      var head = document.createElement('div'); head.className = 'draggo-iconmodal-head';
      var search = document.createElement('input'); search.type = 'text'; search.placeholder = tr('Icon suchen …'); search.className = 'draggo-iconmodal-search';
      var close = document.createElement('button'); close.type = 'button'; close.className = 'draggo-iconmodal-x'; close.textContent = '✕';
      head.appendChild(search); head.appendChild(close);

      var tabs = document.createElement('div'); tabs.className = 'draggo-iconmodal-tabs';
      var tabSvg = document.createElement('button'); tabSvg.type = 'button'; tabSvg.textContent = 'Draggo';
      var tabFa = document.createElement('button'); tabFa.type = 'button'; tabFa.textContent = 'Font Awesome' + (faIcons.length ? '' : ' (—)');
      tabs.appendChild(tabSvg); tabs.appendChild(tabFa);

      var grid = document.createElement('div'); grid.className = 'draggo-iconmodal-grid';
      box.appendChild(head); box.appendChild(tabs); box.appendChild(grid); overlay.appendChild(box);
      var done = function () { overlay.remove(); };
      close.addEventListener('click', done);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) done(); });

      var render = function () {
        var q = search.value.trim().toLowerCase();
        tabSvg.className = src === 'svg' ? 'is-on' : '';
        tabFa.className = src === 'fa' ? 'is-on' : '';
        grid.innerHTML = '';
        if (src === 'svg') {
          svgIcons.filter(function (it) { return !q || it.key.indexOf(q) !== -1 || (it.label || '').toLowerCase().indexOf(q) !== -1; })
            .forEach(function (it) {
              var b = document.createElement('button'); b.type = 'button';
              b.className = 'draggo-iconmodal-it' + (it.key === current ? ' is-on' : '');
              b.title = it.label; b.innerHTML = it.svg + '<span>' + esc(it.label) + '</span>';
              b.addEventListener('click', function () { onPick(it.key); done(); });
              grid.appendChild(b);
            });
        } else {
          if (!faIcons.length) { grid.innerHTML = '<p class="draggo-fp-empty">Font Awesome nicht installiert. Paket in <code>Resources/public/vendor/fontawesome/</code> ablegen.</p>'; return; }
          var matches = faIcons.filter(function (it) { return !q || it.class.indexOf(q) !== -1 || (it.label || '').toLowerCase().indexOf(q) !== -1; });
          // Cap when not searching (1600+ icons) for performance.
          var capped = !q && matches.length > 300;
          matches.slice(0, q ? 600 : 300).forEach(function (it) {
            var b = document.createElement('button'); b.type = 'button';
            b.className = 'draggo-iconmodal-it' + (it.class === current ? ' is-on' : '');
            b.title = it.class; b.innerHTML = '<i class="draggo-fa ' + esc(it.class) + '"></i><span>' + esc(it.label) + '</span>';
            b.addEventListener('click', function () { onPick(it.class); done(); });
            grid.appendChild(b);
          });
          if (capped) { var note = document.createElement('p'); note.className = 'draggo-fp-empty'; note.textContent = matches.length + ' Icons — tippe zum Suchen.'; grid.appendChild(note); }
        }
        if (!grid.children.length) grid.innerHTML = '<p class="draggo-fp-empty">Kein Icon gefunden.</p>';
      };
      tabSvg.addEventListener('click', function () { src = 'svg'; render(); });
      tabFa.addEventListener('click', function () { src = 'fa'; render(); });
      search.addEventListener('input', render);
      render();
      document.body.appendChild(overlay);
      search.focus();
    }

    // Dynamic-tag picker (Tier 3): a small button that drops a Contao insert
    // tag (page title, member name, date …) into a text input/textarea at the
    // caret. Resolution is server-side via replaceInsertTags. Returns the
    // button element, or null when no catalogue is loaded.
    dynTag(input) {
      var self = this;
      var groups = this._dynamicTags || [];
      if (!groups.length) return null;
      var btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'draggo-dyntag'; btn.title = 'Dynamischen Wert einfügen'; btn.textContent = '⚡';
      var menu = null;
      var onDoc = function (e) { if (menu && !menu.contains(e.target) && e.target !== btn) close(); };
      function close() { if (menu) { menu.remove(); menu = null; document.removeEventListener('click', onDoc, true); } }
      var insert = function (txt) {
        var s = input.selectionStart, e = input.selectionEnd;
        if (typeof s === 'number') {
          input.value = input.value.slice(0, s) + txt + input.value.slice(e);
          input.selectionStart = input.selectionEnd = s + txt.length;
        } else { input.value += txt; }
        input.focus();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      };
      btn.addEventListener('click', function (ev) {
        ev.preventDefault(); ev.stopPropagation();
        if (menu) { close(); return; }
        menu = document.createElement('div'); menu.className = 'draggo-dyntag-menu';
        groups.forEach(function (g) {
          var hd = document.createElement('div'); hd.className = 'draggo-dyntag-grp'; hd.textContent = g.label; menu.appendChild(hd);
          (g.tags || []).forEach(function (t) {
            var it = document.createElement('button'); it.type = 'button'; it.className = 'draggo-dyntag-it';
            it.innerHTML = '<span>' + esc(t.label) + '</span><code>' + esc(t.insert) + '</code>';
            if (t.hint) it.title = t.hint;
            it.addEventListener('click', function () { insert(t.insert); close(); });
            menu.appendChild(it);
          });
        });
        btn.parentNode.appendChild(menu);
        setTimeout(function () { document.addEventListener('click', onDoc, true); }, 0);
      });
      return btn;
    }

    // Build one editor field; returns { wrap, get:{name,value()} }.
    buildField(f) {
      var self = this;
      var wrap = document.createElement('label');
      wrap.className = 'draggo-edit-field';
      var lab = document.createElement('span');
      lab.textContent = tr(f.label);
      wrap.appendChild(lab);

      if (f.widget === 'headline') {
        var row = document.createElement('div');
        row.className = 'draggo-edit-inline';
        var inp = document.createElement('input');
        inp.type = 'text'; inp.value = f.value || '';
        var sel = document.createElement('select');
        (f.units || ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div']).forEach(function (u) {
          var o = document.createElement('option'); o.value = u; o.textContent = u.toUpperCase(); if (u === f.unit) o.selected = true; sel.appendChild(o);
        });
        row.appendChild(inp); row.appendChild(sel);
        var dth = self.dynTag(inp); if (dth) { row.classList.add('draggo-dyntag-host'); row.appendChild(dth); }
        wrap.appendChild(row);
        return { wrap: wrap, get: { name: f.name, value: function () { return { value: inp.value, unit: sel.value }; } } };
      }

      if (f.widget === 'rte') {
        var rte = buildRte(f.value || '');
        wrap.appendChild(rte.el);
        return { wrap: wrap, get: { name: f.name, value: rte.getHtml }, destroy: rte.destroy };
      }

      if (f.widget === 'file') {
        var fr = document.createElement('div'); fr.className = 'draggo-edit-inline';
        var fi = document.createElement('input'); fi.type = 'text'; fi.value = f.value || ''; fi.placeholder = tr('Keine Datei'); fi.readOnly = true;
        var pk = document.createElement('button'); pk.type = 'button'; pk.className = 'draggo-file-pick'; pk.textContent = tr('Datei wählen');
        var cl = document.createElement('button'); cl.type = 'button'; cl.className = 'draggo-file-clr'; cl.title = tr('Entfernen'); cl.textContent = '✕';
        pk.addEventListener('click', function () { self.filePicker(f.accept || 'any', false, fi.value ? [fi.value] : [], function (p) { fi.value = p; }); });
        cl.addEventListener('click', function () { fi.value = ''; });
        fr.appendChild(fi); fr.appendChild(pk); fr.appendChild(cl); wrap.appendChild(fr);
        return { wrap: wrap, get: { name: f.name, value: function () { return fi.value.trim(); } } };
      }

      if (f.widget === 'icon') {
        var iv = f.value || '';
        var byKey = {}; (self._icons || []).forEach(function (it) { byKey[it.key] = it; });
        var ico = document.createElement('div'); ico.className = 'draggo-edit-inline draggo-iconpick';
        var prev = document.createElement('span'); prev.className = 'draggo-iconpick-prev';
        var setPrev = function () {
          if (iv && /(?:^|\s)fa[bsrl]?-|fa-/.test(iv)) { prev.innerHTML = '<i class="draggo-fa ' + esc(iv) + '"></i>'; }
          else if (iv && byKey[iv]) { prev.innerHTML = byKey[iv].svg; }
          else { prev.innerHTML = '<span class="draggo-iconpick-none">—</span>'; }
        };
        setPrev();
        var ipk = document.createElement('button'); ipk.type = 'button'; ipk.className = 'draggo-file-pick'; ipk.textContent = tr('Icon wählen');
        var icl = document.createElement('button'); icl.type = 'button'; icl.className = 'draggo-file-clr'; icl.title = tr('Entfernen'); icl.textContent = '✕';
        ipk.addEventListener('click', function () { self.iconPicker(iv, function (k) { iv = k; setPrev(); }); });
        icl.addEventListener('click', function () { iv = ''; setPrev(); });
        ico.appendChild(prev); ico.appendChild(ipk); ico.appendChild(icl); wrap.appendChild(ico);
        return { wrap: wrap, get: { name: f.name, value: function () { return iv; } } };
      }

      if (f.widget === 'datetime') {
        var di = document.createElement('input'); di.type = 'datetime-local'; di.value = f.value || '';
        wrap.appendChild(di);
        return { wrap: wrap, get: { name: f.name, value: function () { return di.value; } } };
      }

      if (f.widget === 'imgsize') {
        var v = f.value || {};
        var sr = document.createElement('div'); sr.className = 'draggo-edit-inline';
        var w = document.createElement('input'); w.type = 'number'; w.placeholder = tr('Breite'); w.value = v.width || '';
        var h = document.createElement('input'); h.type = 'number'; h.placeholder = tr('Höhe'); h.value = v.height || '';
        var m = document.createElement('select');
        [['', 'Auto'], ['proportional', 'Proportional'], ['box', 'Einpassen'], ['crop', 'Zuschneiden']].forEach(function (o) {
          var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); if (o[0] === v.mode) op.selected = true; m.appendChild(op);
        });
        sr.appendChild(w); sr.appendChild(h); sr.appendChild(m); wrap.appendChild(sr);
        return { wrap: wrap, get: { name: f.name, value: function () { return { width: w.value, height: h.value, mode: m.value }; } } };
      }

      if (f.widget === 'code') {
        var ta = document.createElement('textarea'); ta.rows = 6; ta.value = f.value || '';
        wrap.appendChild(ta);
        return { wrap: wrap, get: { name: f.name, value: function () { return ta.value; } } };
      }

      if (f.widget === 'color') {
        // Real colour picker (swatch + hex + design-token dropdown), same as the
        // Style tab — not a bare hex text input.
        var cc = colorControl(f.value || '', self.tokensOfType('color'));
        wrap.appendChild(cc.el);
        return { wrap: wrap, get: { name: f.name, value: function () { var v = cc.get(); return v == null ? '' : v; } } };
      }

      if (f.widget === 'hours') {
        // Weekly opening-hours grid: 7 day rows, each with a "closed" toggle and
        // up to two time ranges (the 2nd covers a lunch break). Value = 7 arrays
        // of {o,c}; a closed day = []. Server validates via OpenHours.
        var DAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        var val = Array.isArray(f.value) ? f.value : [];
        var grid = document.createElement('div'); grid.className = 'draggo-hours';
        var rows = [];
        var dayRow = function (di) {
          var dayVal = Array.isArray(val[di]) ? val[di] : [];
          var row = document.createElement('div'); row.className = 'draggo-hours-day';
          var nm = document.createElement('span'); nm.className = 'draggo-hours-name'; nm.textContent = DAYS[di]; row.appendChild(nm);
          var ranges = document.createElement('div'); ranges.className = 'draggo-hours-ranges';
          var inputs = [];
          for (var ri = 0; ri < 2; ri++) {
            var rg = dayVal[ri] || {};
            var span = document.createElement('span'); span.className = 'draggo-hours-range';
            var o = document.createElement('input'); o.type = 'time'; o.className = 'draggo-hours-o'; o.value = rg.o || '';
            var sep = document.createElement('span'); sep.className = 'draggo-hours-sep'; sep.textContent = '–';
            var c = document.createElement('input'); c.type = 'time'; c.className = 'draggo-hours-c'; c.value = rg.c || '';
            span.appendChild(o); span.appendChild(sep); span.appendChild(c); ranges.appendChild(span);
            inputs.push([o, c]);
          }
          var clb = document.createElement('label'); clb.className = 'draggo-hours-closed';
          var cl = document.createElement('input'); cl.type = 'checkbox'; cl.checked = dayVal.length === 0;
          var clt = document.createElement('span'); clt.textContent = tr('Geschlossen');
          clb.appendChild(cl); clb.appendChild(clt);
          var sync = function () { ranges.style.opacity = cl.checked ? '.35' : '1'; inputs.forEach(function (p) { p[0].disabled = cl.checked; p[1].disabled = cl.checked; }); };
          cl.addEventListener('change', sync);
          // Typing a time auto-unchecks "closed".
          inputs.forEach(function (p) { p.forEach(function (i) { i.addEventListener('input', function () { if (i.value && cl.checked) { cl.checked = false; sync(); } }); }); });
          sync();
          row.appendChild(clb); row.appendChild(ranges);
          rows.push({ closed: cl, inputs: inputs });
          return row;
        };
        for (var di = 0; di < 7; di++) { grid.appendChild(dayRow(di)); }
        // "Copy Monday to all" convenience.
        var copy = document.createElement('button'); copy.type = 'button'; copy.className = 'draggo-hours-copy'; copy.textContent = tr('↧ Montag auf alle Tage');
        copy.addEventListener('click', function () {
          var src = rows[0];
          for (var d = 1; d < 7; d++) {
            rows[d].closed.checked = src.closed.checked;
            for (var r = 0; r < 2; r++) { rows[d].inputs[r][0].value = src.inputs[r][0].value; rows[d].inputs[r][1].value = src.inputs[r][1].value; }
            rows[d].closed.dispatchEvent(new Event('change'));
          }
        });
        wrap.appendChild(grid); wrap.appendChild(copy);
        return { wrap: wrap, get: { name: f.name, value: function () {
          return rows.map(function (rw) {
            if (rw.closed.checked) return [];
            var out = [];
            rw.inputs.forEach(function (p) { var o = p[0].value, c = p[1].value; if (o && c) out.push({ o: o, c: c }); });
            return out;
          });
        } } };
      }

      if (f.widget === 'select') {
        var ss = document.createElement('select'); ss.className = 'draggo-ins-select';
        (f.options || []).forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); if (String(f.value || '') === o[0]) op.selected = true; ss.appendChild(op); });
        wrap.appendChild(ss);
        return { wrap: wrap, get: { name: f.name, value: function () { return ss.value; } } };
      }

      if (f.widget === 'bool') {
        var cb = document.createElement('input'); cb.type = 'checkbox'; cb.className = 'draggo-ins-chk'; cb.checked = !!f.value;
        wrap.classList.add('draggo-edit-bool'); wrap.appendChild(cb);
        return { wrap: wrap, get: { name: f.name, value: function () { return cb.checked ? '1' : ''; } }, input: cb };
      }

      if (f.widget === 'mselect') {
        var sel0 = Array.isArray(f.value) ? f.value.map(String) : [];
        var mbox = document.createElement('div'); mbox.className = 'draggo-mselect';
        var boxes = [];
        (f.options || []).forEach(function (o) {
          if (o[0] === '') return; // skip the '—' placeholder
          var row = document.createElement('label'); row.className = 'draggo-mselect-row';
          var c = document.createElement('input'); c.type = 'checkbox'; c.value = o[0]; c.checked = sel0.indexOf(o[0]) !== -1;
          var t = document.createElement('span'); t.textContent = tr(o[1]);
          row.appendChild(c); row.appendChild(t); mbox.appendChild(row); boxes.push(c);
        });
        if (!boxes.length) { mbox.innerHTML = '<span class="draggo-files-empty">Keine Mitglieder-Gruppen angelegt.</span>'; }
        wrap.appendChild(mbox);
        return { wrap: wrap, get: { name: f.name, value: function () { return boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; }); } } };
      }

      if (f.widget === 'lines') {
        var la = document.createElement('textarea'); la.rows = 5; la.value = f.value || ''; la.placeholder = 'Ein Eintrag pro Zeile';
        wrap.appendChild(la);
        return { wrap: wrap, get: { name: f.name, value: function () { return la.value; } } };
      }

      if (f.widget === 'files') {
        var flist = (f.value ? String(f.value).split('\n') : []).filter(Boolean);
        var fbox = document.createElement('div'); fbox.className = 'draggo-files';
        var ful = document.createElement('div'); ful.className = 'draggo-files-list';
        var renderFiles = function () {
          ful.innerHTML = '';
          flist.forEach(function (p, idx) {
            var chip = document.createElement('span'); chip.className = 'draggo-file-chip';
            chip.appendChild(document.createTextNode(p.replace(/^files\//, '')));
            var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕';
            x.addEventListener('click', function () { flist.splice(idx, 1); renderFiles(); });
            chip.appendChild(x); ful.appendChild(chip);
          });
          if (!flist.length) { var e = document.createElement('span'); e.className = 'draggo-files-empty'; e.textContent = 'Keine Dateien'; ful.appendChild(e); }
        };
        var fadd = document.createElement('button'); fadd.type = 'button'; fadd.className = 'draggo-file-pick'; fadd.textContent = 'Dateien wählen';
        fadd.addEventListener('click', function () { self.filePicker(f.accept || 'any', true, flist.slice(), function (arr) { flist = arr.slice(); renderFiles(); }); });
        fbox.appendChild(ful); fbox.appendChild(fadd); wrap.appendChild(fbox);
        renderFiles();
        return { wrap: wrap, get: { name: f.name, value: function () { return flist.join('\n'); } } };
      }

      if (f.widget === 'table') {
        var data = (f.value && f.value.length) ? f.value : [['', '']];
        var cols = 0; data.forEach(function (r) { if (r.length > cols) cols = r.length; });
        if (cols < 1) cols = 1;
        var tw = document.createElement('div'); tw.className = 'draggo-tbl';
        var tbl = document.createElement('table'); var tb = document.createElement('tbody'); tbl.appendChild(tb);
        var mkCell = function (val) { var td = document.createElement('td'); var i = document.createElement('input'); i.type = 'text'; i.value = val || ''; td.appendChild(i); return td; };
        var mkRow = function (vals) { var tr = document.createElement('tr'); for (var c = 0; c < cols; c++) tr.appendChild(mkCell(vals ? vals[c] : '')); return tr; };
        data.forEach(function (r) { tb.appendChild(mkRow(r)); });
        var bar = document.createElement('div'); bar.className = 'draggo-tbl-bar';
        var mkBtn = function (label, fn) { var b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.addEventListener('click', fn); return b; };
        bar.appendChild(mkBtn('+ Zeile', function () { tb.appendChild(mkRow(null)); }));
        bar.appendChild(mkBtn('− Zeile', function () { if (tb.rows.length > 1) tb.deleteRow(-1); }));
        bar.appendChild(mkBtn('+ Spalte', function () { cols++; Array.prototype.forEach.call(tb.rows, function (r) { r.appendChild(mkCell('')); }); }));
        bar.appendChild(mkBtn('− Spalte', function () { if (cols > 1) { cols--; Array.prototype.forEach.call(tb.rows, function (r) { if (r.cells.length > 1) r.deleteCell(-1); }); } }));
        tw.appendChild(tbl); tw.appendChild(bar); wrap.appendChild(tw);
        return { wrap: wrap, get: { name: f.name, value: function () {
          var out = [];
          Array.prototype.forEach.call(tb.rows, function (r) { var row = []; Array.prototype.forEach.call(r.querySelectorAll('input'), function (i) { row.push(i.value); }); out.push(row); });
          return out;
        } } };
      }

      if (f.widget === 'accitems') {
        // Collapsible repeater: each entry is its own accordion (title summary +
        // rich-text body). Stores pairs {key:title, value:html}.
        var aiRows = [];
        var aiBox = document.createElement('div'); aiBox.className = 'draggo-accitems';
        var aiAdd = function (kv, openNew) {
          var det = document.createElement('details'); det.className = 'draggo-accitem'; if (openNew) det.open = true;
          var sum = document.createElement('summary'); sum.textContent = (kv && kv.key) || 'Neuer Eintrag';
          det.appendChild(sum);
          var body = document.createElement('div'); body.className = 'draggo-accitem-body';
          var ti = document.createElement('input'); ti.type = 'text'; ti.className = 'draggo-accitem-title'; ti.placeholder = 'Titel'; ti.value = (kv && kv.key) || '';
          ti.addEventListener('input', function () { sum.textContent = ti.value || 'Eintrag'; });
          var rte = buildRte((kv && kv.value) || '');
          var del = document.createElement('button'); del.type = 'button'; del.className = 'draggo-accitem-del'; del.textContent = 'Eintrag entfernen';
          del.addEventListener('click', function () {
            try { rte.destroy && rte.destroy(); } catch (e) {}
            det.remove();
            aiRows = aiRows.filter(function (r) { return r.det !== det; });
          });
          body.appendChild(ti); body.appendChild(rte.el); body.appendChild(del);
          det.appendChild(body); aiBox.appendChild(det);
          aiRows.push({ det: det, ti: ti, rte: rte });
        };
        ((f.value && f.value.length) ? f.value : [{ key: '', value: '' }]).forEach(function (kv) { aiAdd(kv, false); });
        var aiBtn = document.createElement('button'); aiBtn.type = 'button'; aiBtn.className = 'draggo-pair-add'; aiBtn.textContent = '+ Eintrag';
        aiBtn.addEventListener('click', function () { aiAdd(null, true); });
        wrap.appendChild(aiBox); wrap.appendChild(aiBtn);
        return {
          wrap: wrap,
          get: { name: f.name, value: function () { return aiRows.map(function (r) { return { key: r.ti.value, value: r.rte.getHtml() }; }); } },
          destroy: function () { aiRows.forEach(function (r) { try { r.rte.destroy && r.rte.destroy(); } catch (e) {} }); },
        };
      }

      if (f.widget === 'iconpairs') {
        var ipByKey = {}; (self._icons || []).forEach(function (it) { ipByKey[it.key] = it; });
        var ipPreview = function (val) {
          if (val && /(?:^|\s)fa[bsrl]?-|fa-/.test(val)) return '<i class="draggo-fa ' + esc(val) + '"></i>';
          if (val && ipByKey[val]) return ipByKey[val].svg;
          return '<span class="draggo-iconpick-none">+</span>';
        };
        var ipData = (f.value && f.value.length) ? f.value : [{ key: '', value: '' }];
        var ipw = document.createElement('div'); ipw.className = 'draggo-pairs';
        var ipAdd = function (kv) {
          var r = document.createElement('div'); r.className = 'draggo-pair-row draggo-iconpair-row';
          var iv = (kv && kv.key) || '';
          var pick = document.createElement('button'); pick.type = 'button'; pick.className = 'draggo-iconpick-prev draggo-iconpair-ico'; pick.title = 'Icon wählen';
          var draw = function () { pick.innerHTML = ipPreview(iv); };
          draw();
          pick.addEventListener('click', function () { self.iconPicker(iv, function (k) { iv = k; draw(); }); });
          var v = document.createElement('input'); v.type = 'text'; v.placeholder = 'URL (https://…)'; v.value = (kv && kv.value) || '';
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.addEventListener('click', function () { if (ipw.querySelectorAll('.draggo-pair-row').length > 1) r.remove(); });
          r._geticon = function () { return iv; };
          r.appendChild(pick); r.appendChild(v); r.appendChild(x); ipw.appendChild(r);
        };
        ipData.forEach(ipAdd);
        var ipBtn = document.createElement('button'); ipBtn.type = 'button'; ipBtn.className = 'draggo-pair-add'; ipBtn.textContent = '+ Icon'; ipBtn.addEventListener('click', function () { ipAdd(null); });
        wrap.appendChild(ipw); wrap.appendChild(ipBtn);
        return { wrap: wrap, get: { name: f.name, value: function () {
          var out = [];
          Array.prototype.forEach.call(ipw.querySelectorAll('.draggo-pair-row'), function (r) {
            var url = r.querySelector('input');
            out.push({ key: r._geticon(), value: url ? url.value : '' });
          });
          return out;
        } } };
      }

      if (f.widget === 'pairs') {
        var pdata = (f.value && f.value.length) ? f.value : [{ key: '', value: '' }];
        var pw = document.createElement('div'); pw.className = 'draggo-pairs';
        var addRow = function (kv) {
          var r = document.createElement('div'); r.className = 'draggo-pair-row';
          var k = document.createElement('input'); k.type = 'text'; k.placeholder = 'Begriff'; k.value = (kv && kv.key) || '';
          var v = document.createElement('input'); v.type = 'text'; v.placeholder = 'Beschreibung'; v.value = (kv && kv.value) || '';
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '✕'; x.addEventListener('click', function () { if (pw.querySelectorAll('.draggo-pair-row').length > 1) r.remove(); });
          r.appendChild(k); r.appendChild(v); r.appendChild(x); pw.appendChild(r);
        };
        pdata.forEach(addRow);
        var pbtn = document.createElement('button'); pbtn.type = 'button'; pbtn.className = 'draggo-pair-add'; pbtn.textContent = '+ Eintrag'; pbtn.addEventListener('click', function () { addRow(null); });
        wrap.appendChild(pw); wrap.appendChild(pbtn);
        return { wrap: wrap, get: { name: f.name, value: function () {
          var out = [];
          Array.prototype.forEach.call(pw.querySelectorAll('.draggo-pair-row'), function (r) { var ins = r.querySelectorAll('input'); out.push({ key: ins[0].value, value: ins[1].value }); });
          return out;
        } } };
      }

      if (f.widget === 'cards') {
        // Repeater: each card = background image + title + rich-text body, in a
        // clearly-labelled bordered block with a header (number + remove).
        var cdata = (f.value && f.value.length) ? f.value : [{ key: '', value: '', img: '' }];
        // "Karte" collides with the 'Karte'→'Map' editor translation, so pick the
        // card label by language directly instead of via tr().
        var cardWord = (typeof DRAGGO_LANG !== 'undefined' && DRAGGO_LANG === 'de') ? 'Karte' : 'Card';
        var cw = document.createElement('div'); cw.className = 'draggo-cards-ed';
        var renumber = function () {
          Array.prototype.forEach.call(cw.querySelectorAll('.draggo-card-row'), function (r, i) {
            var n = r.querySelector('.draggo-card-num'); if (n) n.textContent = cardWord + ' ' + (i + 1);
          });
        };
        var field = function (labelText, control) {
          var fc = document.createElement('div'); fc.className = 'draggo-card-field';
          var l = document.createElement('span'); l.className = 'draggo-card-lbl'; l.textContent = labelText;
          fc.appendChild(l); fc.appendChild(control); return fc;
        };
        var cAdd = function (kv) {
          var img = (kv && kv.img) || '';
          var r = document.createElement('div'); r.className = 'draggo-card-row';

          // Header: "Karte N" + remove button (top-right).
          var head = document.createElement('div'); head.className = 'draggo-card-head';
          var num = document.createElement('span'); num.className = 'draggo-card-num';
          var del = document.createElement('button'); del.type = 'button'; del.className = 'draggo-card-del'; del.textContent = '✕ ' + tr('Entfernen'); del.title = tr('Karte entfernen');
          del.addEventListener('click', function () {
            if (cw.querySelectorAll('.draggo-card-row').length > 1) { if (r._rte && r._rte.destroy) r._rte.destroy(); r.remove(); renumber(); }
          });
          head.appendChild(num); head.appendChild(del); r.appendChild(head);

          // Background image: preview + pick + clear (same pattern as file widget).
          var imgRow = document.createElement('div'); imgRow.className = 'draggo-card-img';
          var prev = document.createElement('span'); prev.className = 'draggo-card-prev';
          var pick = document.createElement('button'); pick.type = 'button'; pick.className = 'draggo-file-pick'; pick.textContent = tr('Bild wählen');
          var clr = document.createElement('button'); clr.type = 'button'; clr.className = 'draggo-file-clr'; clr.title = tr('Bild entfernen'); clr.textContent = '✕';
          var drawThumb = function () {
            prev.style.backgroundImage = img ? 'url("/' + img.replace(/^\//, '') + '")' : '';
            prev.classList.toggle('is-empty', !img); prev.innerHTML = img ? '' : '<i class="fas fa-image" aria-hidden="true"></i>';
          };
          drawThumb();
          pick.addEventListener('click', function () { self.filePicker('image', false, img ? [img] : [], function (p) { img = p; drawThumb(); }); });
          clr.addEventListener('click', function () { img = ''; drawThumb(); });
          imgRow.appendChild(prev); imgRow.appendChild(pick); imgRow.appendChild(clr);
          r.appendChild(field(tr('Hintergrundbild'), imgRow));

          // Title (labelled text input).
          var k = document.createElement('input'); k.type = 'text'; k.className = 'draggo-card-title'; k.value = (kv && kv.key) || '';
          r.appendChild(field(tr('Titel'), k));

          // Body: real rich-text editor (not a bare textarea).
          var rte = buildRte((kv && kv.value) || '');
          r.appendChild(field(tr('Text'), rte.el));

          r._rte = rte; r._getimg = function () { return img; }; r._gettitle = function () { return k.value; };
          cw.appendChild(r);
        };
        cdata.forEach(cAdd); renumber();
        var cbtn = document.createElement('button'); cbtn.type = 'button'; cbtn.className = 'draggo-pair-add'; cbtn.textContent = '+ ' + cardWord;
        cbtn.addEventListener('click', function () { cAdd(null); renumber(); });
        wrap.appendChild(cw); wrap.appendChild(cbtn);
        return { wrap: wrap, get: { name: f.name, value: function () {
          var out = [];
          Array.prototype.forEach.call(cw.querySelectorAll('.draggo-card-row'), function (r) {
            out.push({ key: r._gettitle(), value: r._rte.getHtml(), img: r._getimg() });
          });
          return out;
        } } };
      }

      var input = document.createElement('input'); input.type = 'text'; input.value = f.value || '';
      var dt = self.dynTag(input);
      if (dt) {
        var ir = document.createElement('div'); ir.className = 'draggo-edit-inline draggo-dyntag-host';
        ir.appendChild(input); ir.appendChild(dt); wrap.appendChild(ir);
      } else {
        wrap.appendChild(input);
      }
      return { wrap: wrap, get: { name: f.name, value: function () { return input.value; } } };
    }

    // ── Drop zones ───────────────────────────────────────────────
    makeDropZone(el, onDrop) {
      el.addEventListener('dragover', (e) => {
        if (!this.drag || this.drag.kind === 'container') return; // container drag bubbles to canvas
        e.preventDefault();
        e.stopPropagation();
        el.classList.add('is-dragover');
      });
      el.addEventListener('dragleave', () => el.classList.remove('is-dragover'));
      el.addEventListener('drop', (e) => {
        if (!this.drag || this.drag.kind === 'container') return;
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove('is-dragover');
        onDrop(e);
      });
    }

    onDropColumn(section, list, openerId, e) {
      var d = this.drag;
      if (!d) return;
      if (d.kind === 'new') {
        this.createElement(section, d.type, dropAfterId(list, afterElement(list, e.clientY), openerId), d.blocktype || null);
        return;
      }
      if (d.kind === 'structure') {
        this.addStructure(section, d.preset, dropAfterId(list, afterElement(list, e.clientY), openerId));
        return;
      }
      if (d.kind === 'component') {
        this.insertComponentInto(d.component, section, dropAfterId(list, afterElement(list, e.clientY), openerId));
        return;
      }
      if (d.kind === 'template') {
        this.insertTemplateInto(d.template, section, dropAfterId(list, afterElement(list, e.clientY), openerId));
        return;
      }
      if (d.kind === 'move') {
        var chip = this.root.querySelector('.draggo-item[data-id="' + d.id + '"]');
        if (!chip) return;
        var before = afterElement(list, e.clientY);
        if (before) list.insertBefore(chip, before); else list.appendChild(chip);
        this.persistSection(section).then(() => this.loadContent());
      }
    }

    onDropSection(section, e) {
      var d = this.drag;
      if (!d) return;
      if (d.kind === 'structure') { this.addStructure(section, d.preset, null); return; }
      if (d.kind === 'new') { this.createElement(section, d.type, null, d.blocktype || null); return; }
      if (d.kind === 'component') { this.insertComponentInto(d.component, section, null); return; }
      if (d.kind === 'template') { this.insertTemplateInto(d.template, section, null); return; }
    }

    // ── Target selection ─────────────────────────────────────────
    selectTarget(sectionId, after, scope, targetId) {
      this.active = { sectionId: sectionId, after: after, scope: scope || null, targetId: targetId != null ? targetId : null };
      this.highlightTarget();
      this.scrollToTarget();   // bring the selected node into view (canvas + tree)
      this.renderInspector();
      if (scope) this.switchTab('layout');
    }

    /** CSS selector for the currently active structural target's canvas node. */
    _targetSelector() {
      var a = this.active;
      if (!a) return null;
      if (a.scope === 'col') return '.draggo-col[data-open-id="' + a.targetId + '"][data-section="' + a.sectionId + '"]';
      if (a.scope === 'row') return '.draggo-row[data-start-id="' + a.targetId + '"]';
      return '.draggo-section[data-section="' + a.sectionId + '"]';
    }

    highlightTarget() {
      document.querySelectorAll('.is-target').forEach((n) => n.classList.remove('is-target'));
      if (!this.active) return;
      var sel = this._targetSelector();
      var n = sel ? document.querySelector(sel) : null;
      if (n) n.classList.add('is-target');
    }

    /** Scroll the selected node into view, but only when it isn't already fully visible. */
    scrollToTarget() {
      var sel = this._targetSelector();
      this._scrollNodeIntoView(sel ? document.querySelector(sel) : null);
    }

    _scrollNodeIntoView(node) {
      if (!node || !node.getBoundingClientRect) return;
      var wrap = document.querySelector('.draggo-canvas-wrap') || document.scrollingElement;
      var r = node.getBoundingClientRect();
      var vr = wrap.getBoundingClientRect ? wrap.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
      if (r.top < vr.top + 8 || r.bottom > vr.bottom - 8) {
        // Instant, not smooth: a concurrent pane render cancels smooth scrolls.
        node.scrollIntoView({ block: 'center' });
      }
    }

    /** Persistently outline the element chip being edited + scroll it into view. */
    _highlightChip(id) {
      document.querySelectorAll('.is-target').forEach((n) => n.classList.remove('is-target'));
      var chip = document.querySelector('.draggo-item[data-id="' + id + '"]');
      if (chip) { chip.classList.add('is-target'); this._scrollNodeIntoView(chip); }
    }

    // ── Inspector (column / row / container layout) ──────────────
    currentLayout() {
      var a = this.active;
      if (!a || !a.scope) return null;
      if (a.scope === 'container') {
        var s = this.section(a.sectionId);
        return s ? (s.layout || {}) : {};
      }
      var elem = this._elemById[a.targetId] || {};
      var L = elem.layout || {};
      if (a.scope === 'row') return L.row || {};
      // col: row-start uses L.col (or legacy flat); draggo_col is flat
      if (elem.type === 'draggo_row_start') return L.col || ((L.row || L.col) ? {} : L);
      return L;
    }

    /**
     * Persist an edited (merged) layout honouring the active viewport: at
     * Desktop it becomes the base (existing responsive overrides preserved); at
     * tablet/mobile only the keys that DIFFER from base are stored under
     * responsive[bp] (empty/equal keys inherit Desktop), matching the frontend.
     */
    saveLayoutForVw(merged, render) {
      var base = Object.assign({}, this.currentLayout() || {});
      var bp = this.bpKey();
      var full;
      if (!bp) {
        full = Object.assign({}, merged);
        delete full.responsive;
        if (base.responsive && Object.keys(base.responsive).length) full.responsive = base.responsive;
      } else {
        var baseFlat = Object.assign({}, base); delete baseFlat.responsive;
        var ov = {};
        Object.keys(merged).forEach(function (k) {
          if (k === 'responsive' || merged[k] === undefined) return;
          if (JSON.stringify(merged[k]) !== JSON.stringify(baseFlat[k])) ov[k] = merged[k];
        });
        full = Object.assign({}, baseFlat);
        var resp = Object.assign({}, base.responsive || {});
        if (Object.keys(ov).length) resp[bp] = ov; else delete resp[bp];
        if (Object.keys(resp).length) full.responsive = resp;
      }
      this.saveLayout(full);
      // Structural props (align/flex/display/width/gap/grid) aren't applied by
      // applyLivePreview — re-render the canvas so they take effect instantly
      // (no tab-switch needed). Light: renders from the in-memory model.
      if (render && this._sections) this.renderSections(this._sections);
    }

    saveLayout(layout) {
      var a = this.active;
      if (!a || !a.scope) return;
      var url, body;
      if (a.scope === 'container') {
        var sec = this.section(a.sectionId); if (sec) sec.layout = layout; // optimistic
        url = (this.mode === 'unit') ? ('/unit/' + a.targetId + '/layout') : ('/article/' + a.targetId + '/layout');
        body = { layout: layout };
      } else {
        var elem = this._elemById[a.targetId] || {};
        var scope = a.scope === 'row' ? 'row' : (elem.type === 'draggo_row_start' ? 'col' : 'flat');
        // Optimistic local model update so currentLayout()/live preview stay in sync.
        elem.layout = elem.layout || {};
        if (scope === 'row') { elem.layout = Object.assign({}, elem.layout, { row: layout }); }
        else if (scope === 'col') { elem.layout = Object.assign({}, elem.layout, { col: layout }); }
        else { elem.layout = layout; }
        url = '/element/' + a.targetId + '/layout';
        body = { layout: layout, scope: scope };
      }
      // Apply visually right away (no full reload → no lag, picker/focus kept).
      this.applyLivePreview();
      // Persist debounced so rapid slider/typing/picker changes coalesce.
      this._queueSave(url, body);
    }

    /** Coalesce layout saves; last write per target wins. */
    _queueSave(url, body) {
      var self = this;
      this._saveQ = this._saveQ || {};
      this._saveQ[url] = body;
      if (this._saveT) clearTimeout(this._saveT);
      this._saveT = setTimeout(function () {
        var q = self._saveQ; self._saveQ = {}; self._saveT = null;
        Object.keys(q).forEach(function (u) { self.api(u, 'POST', q[u]).then(function () {}, function (e) { self.fail(e); }); });
      }, 350);
    }

    /** Re-apply the active target's layout to its canvas node, no re-render. */
    applyLivePreview() {
      var a = this.active;
      if (!a || !a.scope) return;
      var L = this.currentLayout() || {};
      var node;
      if (a.scope === 'container') {
        node = document.querySelector('.draggo-section[data-section="' + a.sectionId + '"] > .draggo-section-body');
      } else if (a.scope === 'row') {
        node = document.querySelector('.draggo-row[data-start-id="' + a.targetId + '"]');
      } else {
        node = document.querySelector('.draggo-col[data-open-id="' + a.targetId + '"]');
      }
      if (!node) return;
      // Show the EFFECTIVE values for the active viewport (base + bp override).
      L = this.effLayout(L);
      // Reset the props we manage so cleared values visually disappear too.
      ['padding', 'margin', 'minHeight', 'textAlign', 'background', 'backgroundImage', 'border', 'borderRadius', 'boxShadow', 'opacity', 'color'].forEach(function (p) { node.style[p] = ''; });
      this.applyBg(node, L);
      this.applyOverlay(node, L);
      this.applyMediaBg(node, L);
      this.applyBoxStyle(node, L);
      if (L.minHeight) node.style.minHeight = (parseInt(L.minHeight, 10) || 0) + 'px';
      if (L.align) node.style.textAlign = L.align;
    }

    renderInspector() {
      var el = document.getElementById('draggo-pane-layout');
      if (!el) return;

      var a = this.active;
      if (!a || !a.scope) {
        el.innerHTML = '<h2>Layout</h2><p class="draggo-hint">Container-Kopf, Reihen-Kopf oder eine Spalte anklicken, um Hintergrund, Bild, Abstand, Farbe & Ausrichtung zu setzen.</p>';
        return;
      }

      var titles = { container: 'Container-Layout', row: 'Reihen-Layout', col: 'Spalten-Layout' };
      // Per-viewport editing: controls show the EFFECTIVE values for the active
      // viewport (base overlaid with the breakpoint override). Saves write only
      // the changed keys into responsive[bp] (empty inherits base/Desktop).
      var vw = this.vw;
      var l = this.effLayout(this.currentLayout() || {});
      var padOpts = function (val) {
        return PADDINGS.map(function (p) {
          return '<option value="' + p[0] + '"' + (String(val || '') === p[0] ? ' selected' : '') + '>' + p[1] + '</option>';
        }).join('');
      };
      // Per-side spacing control (top/right/bottom/left + unit) for the inspector.
      var dimRow = function (label, key, val) {
        val = val || {};
        var inputs = ['top', 'right', 'bottom', 'left'].map(function (s) {
          var v = (val[s] === 0 || val[s]) ? val[s] : '';
          return '<input type="number" data-side="' + s + '" placeholder="' + s.charAt(0).toUpperCase() + '" value="' + v + '">';
        }).join('');
        var units = ['px', '%', 'rem', 'em'].map(function (u) { return '<option' + ((val.unit || 'px') === u ? ' selected' : '') + '>' + u + '</option>'; }).join('');
        return '<div class="draggo-ins-row draggo-ins-col"><span>' + label + '</span>' +
          '<span class="draggo-ins-dim" data-dim="' + key + '">' + inputs + '<select data-unit>' + units + '</select></span></div>';
      };
      var mkAlign = (v, lbl) => '<button type="button" class="draggo-ins-align' + (l.align === v ? ' is-on' : '') + '" data-align="' + v + '">' + lbl + '</button>';
      var fileOpts = (this._files || []).filter(function (f) { return f.kind === 'image'; }).map(function (f) { return '<option value="' + esc(f.path) + '">'; }).join('');
      // Colour helpers (hex + alpha → #rrggbbaa, transparency support).
      var col6 = function (v, def) { return (v && String(v).indexOf('var(') !== 0) ? String(v).slice(0, 7) : def; };
      var colA = function (v) { return (v && String(v).length === 9) ? Math.round(parseInt(String(v).slice(7, 9), 16) / 255 * 100) : 100; };
      var a2hex = function (p) { var h = Math.round(p / 100 * 255).toString(16); return h.length < 2 ? '0' + h : h; };
      // Global colour tokens as a dropdown next to the colour pickers.
      var colorTok = this.tokensOfType('color');
      var colorTokSel = function (id) {
        if (!colorTok.length) return '';
        return '<select id="' + id + '" class="draggo-ins-select draggo-tok-pick" title="Globale Farbe"><option value="">—</option>' +
          colorTok.map(function (t) { return '<option value="var(--bld-color-' + esc(t.token) + ')">' + esc(t.label || t.token) + '</option>'; }).join('') + '</select>';
      };

      var mkBtn = function (group, v, lbl, on) { return '<button type="button" class="draggo-ins-align' + (on === v ? ' is-on' : '') + '" data-' + group + '="' + v + '">' + lbl + '</button>'; };

      var widthRow = '';
      if (a.scope === 'container') {
        var w = l.width || 'full';
        var secObj = this.section(a.sectionId) || {};
        widthRow = '<label class="draggo-ins-row draggo-ins-col"><span>' + esc(tr('Container-Name (intern – kein Frontend)')) + '</span>' +
          '<input type="text" id="ins-cname" class="draggo-ins-text" value="' + esc(secObj.title || '') + '" placeholder="z. B. Hero, Footer …"></label>' +
          '<p class="draggo-ins-hint">' + esc(tr('Nur zur Orientierung im Editor/Seitenbaum. Für eine sichtbare Überschrift ein „Überschrift"- oder Text-Element einfügen.')) + '</p>';
        widthRow += '<label class="draggo-ins-row"><span>Inhaltsbreite</span><select id="ins-width" class="draggo-ins-select">' +
          '<option value="full"' + (w === 'full' ? ' selected' : '') + '>Vollbreite</option>' +
          '<option value="boxed"' + (w === 'boxed' ? ' selected' : '') + '>Boxed (zentriert)</option></select></label>' +
          '<p class="draggo-hint">Container/Hintergrund immer voll breit. „Boxed" zentriert nur den Inhalt (max. 1140px).</p>' +
          '<div class="draggo-ins-row"><span>Inhalt horizontal</span><span class="draggo-ins-aligns">' +
            mkBtn('alignx', 'start', svgIcon('xstart'), l.alignX) + mkBtn('alignx', 'center', svgIcon('xcenter'), l.alignX) + mkBtn('alignx', 'end', svgIcon('xend'), l.alignX) + '</span></div>' +
          '<div class="draggo-ins-row"><span>Inhalt vertikal</span><span class="draggo-ins-aligns">' +
            mkBtn('aligny', 'start', svgIcon('ytop'), l.alignY) + mkBtn('aligny', 'center', svgIcon('ymid'), l.alignY) + mkBtn('aligny', 'end', svgIcon('ybot'), l.alignY) + '</span></div>';

        // Phase D: container display mode (Flexbox / Grid).
        var opt = function (v, lbl, cur) { return '<option value="' + v + '"' + (String(cur || '') === v ? ' selected' : '') + '>' + lbl + '</option>'; };
        var disp = l.display || '';
        var gapsC = [['', 'Standard'], ['none', 'Kein'], ['xs', 'XS'], ['s', 'S'], ['m', 'M'], ['l', 'L'], ['xl', 'XL']];
        widthRow += '<label class="draggo-ins-row"><span>Anzeige</span><select id="ins-display" class="draggo-ins-select">' +
          opt('', 'Standard (Spalte)', disp) + opt('flex', 'Flexbox', disp) + opt('grid', 'Grid', disp) + '</select></label>';
        if (disp === 'flex') {
          widthRow += '<label class="draggo-ins-row"><span>Richtung</span><select id="ins-fdir" class="draggo-ins-select">' +
              opt('row', 'Reihe →', l.flexDirection) + opt('column', 'Spalte ↓', l.flexDirection) + opt('row-reverse', 'Reihe ←', l.flexDirection) + opt('column-reverse', 'Spalte ↑', l.flexDirection) + '</select></label>' +
            '<label class="draggo-ins-row"><span>Verteilen (justify)</span><select id="ins-fjustify" class="draggo-ins-select">' +
              opt('flex-start', 'Anfang', l.flexJustify) + opt('center', 'Mitte', l.flexJustify) + opt('flex-end', 'Ende', l.flexJustify) + opt('space-between', 'Zwischenraum', l.flexJustify) + opt('space-around', 'Umgebend', l.flexJustify) + opt('space-evenly', 'Gleichmäßig', l.flexJustify) + '</select></label>' +
            '<label class="draggo-ins-row"><span>Ausrichten (align)</span><select id="ins-falign" class="draggo-ins-select">' +
              opt('stretch', 'Strecken', l.flexAlign) + opt('flex-start', 'Anfang', l.flexAlign) + opt('center', 'Mitte', l.flexAlign) + opt('flex-end', 'Ende', l.flexAlign) + '</select></label>' +
            '<label class="draggo-ins-row"><span>Umbruch</span><select id="ins-fwrap" class="draggo-ins-select">' +
              opt('nowrap', 'Kein Umbruch', l.flexWrap) + opt('wrap', 'Umbruch', l.flexWrap) + '</select></label>' +
            '<label class="draggo-ins-row"><span>Abstand (gap)</span><select id="ins-dgap" class="draggo-ins-select">' +
              gapsC.map(function (g) { return opt(g[0], g[1], l.gap); }).join('') + '</select></label>';
        } else if (disp === 'grid') {
          widthRow += '<label class="draggo-ins-row"><span>Spalten</span><input type="number" id="ins-gcols" class="draggo-ins-num" min="1" max="12" value="' + (parseInt(l.gridColumns, 10) || 2) + '"></label>' +
            '<label class="draggo-ins-row"><span>Abstand (gap)</span><select id="ins-dgap" class="draggo-ins-select">' +
              gapsC.map(function (g) { return opt(g[0], g[1], l.gap); }).join('') + '</select></label>';
        }
      }

      var gapRow = '';
      if (a.scope === 'row') {
        var structs = this.structures || {};
        var rsEl = this._elemById[a.targetId] || {};
        // Foreign grid (theme: SubColumns / bootstrap-grid / RockSolid) — Draggo
        // recognises it for editing but its structure is owned by the theme.
        var rowForeign = rsEl.type && rsEl.type !== 'draggo_row_start';
        if (rowForeign) {
          gapRow = '<p class="draggo-hint">Theme-Grid erkannt — Struktur wird vom Theme verwaltet. Inhalte in den Spalten sind hier editierbar.</p>' +
            '<button type="button" id="ins-convert-grid" class="draggo-file-pick">In Draggo-Grid umwandeln</button>' +
            '<p class="draggo-hint">Wandelt diese Reihe in Draggos Grid um. Danach rendert das Frontend über Draggo — die Theme-Grid-Darstellung entfällt.</p>';
        } else {
        var curPreset = rsEl.gridPreset || '1';
        var curCustom = rsEl.gridCustom || '';
        var curTablet = rsEl.gridTablet || '';
        var curMobile = rsEl.gridMobile || '';
        var bpOpts = function (cur, autoLbl) {
          return '<option value="">' + autoLbl + '</option>' +
            Object.keys(structs).map(function (k) { return '<option value="' + esc(k) + '"' + (cur === k ? ' selected' : '') + '>' + esc(structs[k]) + '</option>'; }).join('');
        };
        gapRow = '<label class="draggo-ins-row draggo-ins-col"><span>Struktur · Desktop</span><select id="ins-struct" class="draggo-ins-select">' +
          Object.keys(structs).map(function (k) { return '<option value="' + esc(k) + '"' + (curPreset === k ? ' selected' : '') + '>' + esc(structs[k]) + '</option>'; }).join('') +
          '</select></label>' +
          '<label class="draggo-ins-row draggo-ins-col" id="ins-struct-custom-row"' + (curPreset === 'custom' ? '' : ' hidden') + '><span>Eigene Spalten (z. B. 6,6 oder 4,4,4)</span><input type="text" id="ins-struct-custom" class="draggo-ins-text" value="' + esc(curCustom) + '" placeholder="6,6"></label>' +
          '<label class="draggo-ins-row draggo-ins-col"><span>Struktur · Tablet (≥768px)</span><select id="ins-struct-tablet" class="draggo-ins-select">' + bpOpts(curTablet, 'Wie Desktop') + '</select></label>' +
          '<label class="draggo-ins-row draggo-ins-col"><span>Struktur · Mobil (&lt;768px)</span><select id="ins-struct-mobile" class="draggo-ins-select">' + bpOpts(curMobile, 'Untereinander (1-spaltig)') + '</select></label>' +
          '<p class="draggo-hint">Desktop bestimmt die Spaltenanzahl. Tablet/Mobil verteilen dieselben Spalten neu (z. B. Desktop 3 → Tablet 2 → Mobil 1).</p>';
        var gaps = [['', 'Standard'], ['none', 'Kein'], ['xs', 'XS'], ['s', 'S'], ['m', 'M'], ['l', 'L'], ['xl', 'XL']];
        gapRow += '<label class="draggo-ins-row"><span>Spalten-Abstand (gap)</span><select id="ins-gap" class="draggo-ins-select">' +
          gaps.map(function (g) { return '<option value="' + g[0] + '"' + (String(l.gap || '') === g[0] ? ' selected' : '') + '>' + g[1] + '</option>'; }).join('') +
          '</select></label>';
        }
      }

      // Centre child elements horizontally/vertically (row + column scopes).
      var alignBlock = '';
      if (a.scope === 'row' || a.scope === 'col') {
        alignBlock =
          '<div class="draggo-ins-row"><span>Inhalt horizontal</span><span class="draggo-ins-aligns">' +
            mkBtn('alignx', 'start', svgIcon('xstart'), l.alignX) + mkBtn('alignx', 'center', svgIcon('xcenter'), l.alignX) + mkBtn('alignx', 'end', svgIcon('xend'), l.alignX) + '</span></div>' +
          '<div class="draggo-ins-row"><span>Inhalt vertikal</span><span class="draggo-ins-aligns">' +
            mkBtn('aligny', 'start', svgIcon('ytop'), l.alignY) + mkBtn('aligny', 'center', svgIcon('ymid'), l.alignY) + mkBtn('aligny', 'end', svgIcon('ybot'), l.alignY) + '</span></div>';
      }

      var bStyles = [['', 'Keiner'], ['solid', 'Solid'], ['dashed', 'Gestrichelt'], ['dotted', 'Gepunktet'], ['double', 'Doppelt']];
      var bShadows = [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß'], ['xl', 'Sehr groß']];
      var borderRows =
        '<label class="draggo-ins-row"><span>Rahmen-Stil</span><select id="ins-bstyle" class="draggo-ins-select">' +
          bStyles.map(function (o) { return '<option value="' + o[0] + '"' + ((l.borderStyle || '') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
        '<label class="draggo-ins-row"><span>Rahmen-Breite (px)</span><input type="number" id="ins-bwidth" class="draggo-ins-num" min="0" max="50" value="' + (parseInt(l.borderWidth, 10) || '') + '"></label>' +
        '<div class="draggo-ins-row"><span>Rahmen-Farbe</span><span id="ins-bcolor-host"></span></div>' +
        '<label class="draggo-ins-row"><span>Eckenradius (px)</span><input type="number" id="ins-bradius" class="draggo-ins-num" min="0" max="400" value="' + (parseInt(l.borderRadius, 10) || '') + '"></label>' +
        '<label class="draggo-ins-row"><span>Schatten</span><select id="ins-bshadow" class="draggo-ins-select">' +
          bShadows.map(function (o) { return '<option value="' + o[0] + '"' + ((l.boxShadow || '') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>';

      var cssRows =
        '<label class="draggo-ins-row draggo-ins-col"><span>CSS-Klasse</span><input type="text" id="ins-class" class="draggo-ins-text" placeholder="z. B. my-class" value="' + esc(l.cssClass || '') + '"></label>' +
        '<label class="draggo-ins-row draggo-ins-col"><span>Element-ID</span><input type="text" id="ins-cssid" class="draggo-ins-text" placeholder="z. B. my-id" value="' + esc(l.cssId || '') + '"></label>';

      // Collapsible accordion group (matches the palette category style).
      // Open state persists across re-renders (so a save/re-render doesn't
      // collapse the section the user is working in).
      var insOpen = this._insOpen = this._insOpen || {};
      var accGroup = function (title, open, inner) {
        var isOpen = (insOpen[title] != null) ? insOpen[title] : open;
        return '<details class="draggo-cat draggo-ins-acc"' + (isOpen ? ' open' : '') + '><summary>' + esc(title) + '</summary><div class="draggo-ins-accbody">' + inner + '</div></details>';
      };

      var bgGroup =
        '<div class="draggo-ins-row"><span>Hintergrundfarbe</span>' +
        '<span id="ins-bg-host"></span></div>' +
        '<label class="draggo-ins-row draggo-ins-col"><span>Hintergrundbild</span>' +
        '<span class="draggo-ins-color"><input type="text" id="ins-image" class="draggo-ins-text" readonly placeholder="keine" value="' + esc(l.image || '') + '">' +
        '<button type="button" id="ins-image-pick" class="draggo-file-pick">Wählen</button>' +
        '<button type="button" id="ins-image-clear" title="entfernen">✕</button></span></label>' +
        (l.image ? (
          '<label class="draggo-ins-row"><span>Bild-Größe</span><select id="ins-bgsize" class="draggo-ins-select">' +
            ['cover', 'contain', 'auto'].map(function (o) { return '<option value="' + o + '"' + ((l.bgSize || 'cover') === o ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></label>' +
          '<label class="draggo-ins-row"><span>Bild-Position</span><select id="ins-bgpos" class="draggo-ins-select">' +
            ['center', 'top', 'bottom', 'left', 'right'].map(function (o) { return '<option value="' + o + '"' + ((l.bgPosition || 'center') === o ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></label>' +
          '<label class="draggo-ins-row"><span>Wiederholung</span><select id="ins-bgrep" class="draggo-ins-select">' +
            [['no-repeat', 'keine'], ['repeat', 'kacheln'], ['repeat-x', 'horizontal'], ['repeat-y', 'vertikal']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.bgRepeat || 'no-repeat') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
          '<label class="draggo-ins-row"><span>Parallax (fixiert)</span><select id="ins-bgatt" class="draggo-ins-select">' +
            [['scroll', 'aus'], ['fixed', 'an']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.bgAttachment || 'scroll') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>'
        ) : '') +
        // ── Verlauf (Gradient) — wirkt wenn kein Bild gesetzt ist ──
        '<div class="draggo-ins-sep">Verlauf (Hintergrund)</div>' +
        '<label class="draggo-ins-row"><span>Verlauf-Typ</span><select id="ins-grad-type" class="draggo-ins-select">' +
          [['', 'Kein'], ['linear', 'Linear'], ['radial', 'Radial']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.bgGradType || (l.bgGradFrom && l.bgGradTo ? 'linear' : '')) === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
        '<div class="draggo-ins-row" id="ins-grad-from-row"' + (l.bgGradFrom || l.bgGradTo || l.bgGradType ? '' : ' hidden') + '><span>Von-Farbe</span><span id="ins-grad-from-host"></span></div>' +
        '<div class="draggo-ins-row" id="ins-grad-to-row"' + (l.bgGradFrom || l.bgGradTo || l.bgGradType ? '' : ' hidden') + '><span>Bis-Farbe</span><span id="ins-grad-to-host"></span></div>' +
        '<label class="draggo-ins-row" id="ins-grad-angle-row"' + ((l.bgGradType || 'linear') === 'radial' || !(l.bgGradFrom || l.bgGradTo || l.bgGradType) ? ' hidden' : '') + '><span>Winkel (°)</span><input type="number" id="ins-grad-angle" class="draggo-ins-num" min="0" max="360" value="' + (l.bgGradAngle != null && l.bgGradAngle !== '' ? l.bgGradAngle : 135) + '"></label>' +
        '<p class="draggo-hint">Verlauf greift nur, wenn KEIN Hintergrundbild gesetzt ist. Beide Farben wählen.</p>' +
        // ── Hintergrund-Medien: Video / Slideshow ──
        '<div class="draggo-ins-sep">Hintergrund-Medien</div>' +
        '<label class="draggo-ins-row"><span>Medien-Typ</span><select id="ins-media-type" class="draggo-ins-select">' +
          [['', 'Kein'], ['video', 'Video'], ['slider', 'Slideshow']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.bgMedia || '') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
        (l.bgMedia === 'video' ? (
          '<label class="draggo-ins-row draggo-ins-col"><span>Video-Datei (MP4/WebM)</span>' +
          '<span class="draggo-ins-color"><input type="text" id="ins-bgvideo" class="draggo-ins-text" readonly placeholder="keine" value="' + esc(l.bgVideo || '') + '">' +
          '<button type="button" id="ins-bgvideo-pick" class="draggo-file-pick">Wählen</button>' +
          '<button type="button" id="ins-bgvideo-clear" title="entfernen">✕</button></span></label>' +
          '<label class="draggo-ins-row draggo-ins-col"><span>Poster-Bild (optional)</span>' +
          '<span class="draggo-ins-color"><input type="text" id="ins-bgvposter" class="draggo-ins-text" readonly placeholder="keins" value="' + esc(l.bgVideoPoster || '') + '">' +
          '<button type="button" id="ins-bgvposter-pick" class="draggo-file-pick">Wählen</button>' +
          '<button type="button" id="ins-bgvposter-clear" title="entfernen">✕</button></span></label>' +
          '<p class="draggo-hint">Video läuft stumm in Schleife (Autoplay). Tipp: kleine, komprimierte Datei.</p>'
        ) : '') +
        (l.bgMedia === 'slider' ? (
          '<div class="draggo-ins-row draggo-ins-col"><span>Bilder (' + ((l.bgSlides && l.bgSlides.length) || 0) + ')</span>' +
          '<span class="draggo-ins-color"><button type="button" id="ins-bgslides-pick" class="draggo-file-pick">Bilder wählen …</button>' +
          '<button type="button" id="ins-bgslides-clear" title="leeren">✕</button></span></div>' +
          '<label class="draggo-ins-row"><span>Intervall (ms)</span><input type="number" id="ins-bgslide-int" class="draggo-ins-num" min="1000" max="20000" step="500" value="' + (parseInt(l.bgSlideInterval, 10) || 5000) + '"></label>' +
          '<label class="draggo-ins-row"><span>Effekt</span><select id="ins-bgslide-fx" class="draggo-ins-select">' +
            [['fade', 'Überblenden'], ['slide', 'Schieben']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.bgSlideFx || 'fade') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>'
        ) : '') +
        // ── Hintergrund-Overlay (Farbe | Verlauf | Bild, über dem Hintergrund, unter dem Text) ──
        '<div class="draggo-ins-sep">Hintergrund-Overlay</div>' +
        '<label class="draggo-ins-row"><span>Overlay-Typ</span><select id="ins-ov-type" class="draggo-ins-select">' +
          [['', 'Kein'], ['color', 'Farbe'], ['gradient', 'Verlauf'], ['image', 'Bild']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.ovType || '') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
        (l.ovType === 'color' ? '<div class="draggo-ins-row"><span>Overlay-Farbe</span><span id="ins-ov-color-host"></span></div>' : '') +
        (l.ovType === 'gradient' ? (
          '<div class="draggo-ins-row"><span>Von-Farbe</span><span id="ins-ov-from-host"></span></div>' +
          '<div class="draggo-ins-row"><span>Bis-Farbe</span><span id="ins-ov-to-host"></span></div>' +
          '<label class="draggo-ins-row"><span>Verlauf-Typ</span><select id="ins-ov-gtype" class="draggo-ins-select">' +
            [['linear', 'Linear'], ['radial', 'Radial']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.ovGradType || 'linear') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>' +
          '<label class="draggo-ins-row"' + ((l.ovGradType || 'linear') === 'radial' ? ' hidden' : '') + ' id="ins-ov-gangle-row"><span>Winkel (°)</span><input type="number" id="ins-ov-gangle" class="draggo-ins-num" min="0" max="360" value="' + (l.ovGradAngle != null && l.ovGradAngle !== '' ? l.ovGradAngle : 135) + '"></label>'
        ) : '') +
        (l.ovType === 'image' ? (
          '<label class="draggo-ins-row draggo-ins-col"><span>Overlay-Bild</span>' +
          '<span class="draggo-ins-color"><input type="text" id="ins-ov-image" class="draggo-ins-text" readonly placeholder="keins" value="' + esc(l.ovImage || '') + '">' +
          '<button type="button" id="ins-ov-image-pick" class="draggo-file-pick">Wählen</button>' +
          '<button type="button" id="ins-ov-image-clear" title="entfernen">✕</button></span></label>'
        ) : '') +
        (l.ovType ? (
          '<label class="draggo-ins-row"><span>Deckkraft</span><input type="range" id="ins-ov-opacity" min="0" max="100" value="' + (l.ovOpacity != null && l.ovOpacity !== '' ? Math.round(l.ovOpacity * 100) : 50) + '"></label>' +
          '<label class="draggo-ins-row"><span>Mischmodus</span><select id="ins-ov-blend" class="draggo-ins-select">' +
            [['normal', 'Normal'], ['multiply', 'Multiplizieren'], ['screen', 'Negativ mult.'], ['overlay', 'Ineinanderkopieren'], ['darken', 'Abdunkeln'], ['lighten', 'Aufhellen']].map(function (o) { return '<option value="' + o[0] + '"' + ((l.ovBlend || 'normal') === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></label>'
        ) : '') +
        '<div class="draggo-ins-row"><span>Textfarbe</span>' +
        '<span id="ins-color-host"></span></div>';

      var spacingGroup =
        dimRow('Innenabstand (oben/rechts/unten/links)', 'paddingBox', l.paddingBox) +
        dimRow('Außenabstand (oben/rechts/unten/links)', 'marginBox', l.marginBox) +
        '<label class="draggo-ins-row"><span>Mindesthöhe (px)</span><input type="number" id="ins-minh" class="draggo-ins-num" min="0" max="4000" value="' + (parseInt(l.minHeight, 10) || '') + '"></label>' +
        '<div class="draggo-ins-row"><span>Text-Ausrichtung</span><span class="draggo-ins-aligns">' + mkAlign('left', svgIcon('tleft')) + mkAlign('center', svgIcon('tcenter')) + mkAlign('right', svgIcon('tright')) + '</span></div>';

      // ── Typography / Size / Position groups (mirror the element StyleSchema;
      // compile() already emits these + responsiveBlock makes them per-device) ──
      var optX = function (v, lbl, cur) { return '<option value="' + v + '"' + (String(cur == null ? '' : cur) === String(v) ? ' selected' : '') + '>' + lbl + '</option>'; };
      var numR = function (id, lbl, val, mn, mx, step) { return '<label class="draggo-ins-row"><span>' + lbl + '</span><input type="number" id="' + id + '" class="draggo-ins-num"' + (mn != null ? ' min="' + mn + '"' : '') + (mx != null ? ' max="' + mx + '"' : '') + (step ? ' step="' + step + '"' : '') + ' value="' + (val == null || val === '' ? '' : val) + '"></label>'; };
      var txtR = function (id, lbl, val, ph) { return '<label class="draggo-ins-row draggo-ins-col"><span>' + lbl + '</span><input type="text" id="' + id + '" class="draggo-ins-text" placeholder="' + (ph || '') + '" value="' + esc(val || '') + '"></label>'; };
      var selR = function (id, lbl, opts, cur) { return '<label class="draggo-ins-row"><span>' + lbl + '</span><select id="' + id + '" class="draggo-ins-select">' + opts.map(function (o) { return optX(o[0], o[1], cur); }).join('') + '</select></label>'; };

      var typoGroup =
        txtR('ins-fontfam', 'Schriftart (CSS)', l.fontFamily, 'z. B. Arial, sans-serif') +
        numR('ins-fontsize', 'Schriftgröße (px)', parseInt(l.fontSize, 10) || '', 8, 400) +
        selR('ins-fontweight', 'Schriftstärke', [['', '–'], ['300', 'Leicht'], ['400', 'Normal'], ['500', 'Medium'], ['600', 'Halbfett'], ['700', 'Fett'], ['800', 'Extrafett'], ['900', 'Black']], l.fontWeight) +
        selR('ins-texttransform', 'Groß/Klein', [['', '–'], ['none', 'Keine'], ['uppercase', 'GROSS'], ['lowercase', 'klein'], ['capitalize', 'Wörter']], l.textTransform) +
        selR('ins-fontstyle', 'Stil', [['', '–'], ['normal', 'Normal'], ['italic', 'Kursiv']], l.fontStyle) +
        selR('ins-textdeco', 'Dekoration', [['', '–'], ['none', 'Keine'], ['underline', 'Unterstrichen'], ['line-through', 'Durchgestrichen']], l.textDecoration) +
        numR('ins-lineheight', 'Zeilenhöhe (z. B. 1.5)', l.lineHeight != null ? l.lineHeight : '', 0, 5, 0.1) +
        numR('ins-letterspacing', 'Buchstabenabstand (px)', l.letterSpacing != null ? l.letterSpacing : '', -50, 100, 0.5) +
        selR('ins-textshadow', 'Textschatten', [['', 'Keiner'], ['sm', 'Klein'], ['md', 'Mittel'], ['lg', 'Groß']], l.textShadow) +
        '<label class="draggo-ins-row"><span>Farbverlauf-Text</span><input type="checkbox" id="ins-textgrad"' + (l.textGradient ? ' checked' : '') + '></label>' +
        (l.textGradient ? '<div class="draggo-ins-row"><span>Von-Farbe</span><span id="ins-gradfrom-host"></span></div><div class="draggo-ins-row"><span>Bis-Farbe</span><span id="ins-gradto-host"></span></div>' : '');

      var sizeGroup =
        txtR('ins-w', 'Breite (z. B. 300px / 50%)', l.width, 'auto') +
        txtR('ins-maxw', 'Max-Breite', l.maxWidth, 'keine') +
        txtR('ins-h', 'Höhe', l.height, 'auto') +
        txtR('ins-maxh', 'Max-Höhe', l.maxHeight, 'keine') +
        '<label class="draggo-ins-row"><span>Deckkraft (0–1)</span><input type="number" id="ins-opacity" class="draggo-ins-num" min="0" max="1" step="0.05" value="' + (l.opacity != null && l.opacity !== '' ? l.opacity : '') + '"></label>';

      var posGroup =
        selR('ins-position', 'Position', [['', 'Standard'], ['relative', 'Relativ'], ['absolute', 'Absolut'], ['fixed', 'Fixiert'], ['sticky', 'Klebend']], l.position) +
        (l.position ? (txtR('ins-postop', 'Oben', l.posTop, 'z. B. 0 / 10px') + txtR('ins-posright', 'Rechts', l.posRight, '') + txtR('ins-posbottom', 'Unten', l.posBottom, '') + txtR('ins-posleft', 'Links', l.posLeft, '')) : '') +
        numR('ins-zindex', 'Z-Index', l.zIndex != null ? l.zIndex : '', -999, 9999) +
        numR('ins-rotate', 'Drehung (°)', l.transformRotate != null ? l.transformRotate : '', -360, 360) +
        numR('ins-scale', 'Skalierung (z. B. 1.1)', l.transformScale != null ? l.transformScale : '', 0, 5, 0.05) +
        txtR('ins-translatex', 'Verschieben X', l.transformTranslateX, 'z. B. 10px') +
        txtR('ins-translatey', 'Verschieben Y', l.transformTranslateY, '');

      // Extend the CSS/Advanced group with custom CSS + per-device visibility.
      cssRows +=
        '<label class="draggo-ins-row draggo-ins-col"><span>Eigenes CSS</span><textarea id="ins-customcss" class="draggo-ins-text" rows="4" placeholder="selector{ ... } oder bare Regeln">' + esc(l.customCss || '') + '</textarea></label>' +
        '<div class="draggo-ins-sep">Sichtbarkeit pro Gerät</div>' +
        '<label class="draggo-ins-row"><span>Auf Desktop verstecken</span><input type="checkbox" id="ins-hide-d"' + (l.hideDesktop ? ' checked' : '') + '></label>' +
        '<label class="draggo-ins-row"><span>Auf Tablet verstecken</span><input type="checkbox" id="ins-hide-t"' + (l.hideTablet ? ' checked' : '') + '></label>' +
        '<label class="draggo-ins-row"><span>Auf Mobil verstecken</span><input type="checkbox" id="ins-hide-m"' + (l.hideMobile ? ' checked' : '') + '></label>';

      // Viewport switcher + context banner (shared FA icons with the toolbar).
      var vpBtns = [['full', 'fa-desktop', 'Desktop'], ['tablet', 'fa-tablet-alt', 'Tablet'], ['mobile', 'fa-mobile-alt', 'Mobil']].map(function (o) {
        return '<button type="button" data-vw="' + o[0] + '"' + (vw === o[0] ? ' class="is-on"' : '') + '><i class="draggo-fa fas ' + o[1] + '"></i> ' + o[2] + '</button>';
      }).join('');
      var vpHint = vw === 'full' ? 'Basis – gilt für alle Geräte.' : (vw === 'tablet' ? 'Tablet ≤991px – leere Felder erben Desktop.' : 'Mobil ≤767px – leere Felder erben Desktop.');
      var vpBanner = '<div class="draggo-bp-switch draggo-ins-bp">' + vpBtns + '</div><p class="draggo-bp-hint">' + esc(vpHint) + '</p>';

      el.innerHTML =
        '<h2>' + (titles[a.scope] || 'Layout') + '</h2>' +
        vpBanner +
        accGroup('Layout & Struktur', true, widthRow + gapRow + alignBlock) +
        accGroup('Hintergrund', false, bgGroup) +
        accGroup('Abstände & Größe', false, spacingGroup) +
        accGroup('Typografie', false, typoGroup) +
        accGroup('Größe & Deckkraft', false, sizeGroup) +
        accGroup('Position & Transform', false, posGroup) +
        accGroup('Rahmen & Schatten', false, borderRows) +
        accGroup('Erweitert (CSS)', false, cssRows);

      var self = this;
      // Remember which accordions are open across re-renders.
      el.querySelectorAll('details.draggo-ins-acc').forEach(function (d) {
        var sm = d.querySelector('summary'); var key = sm ? sm.textContent : '';
        d.addEventListener('toggle', function () { insOpen[key] = d.open; });
      });
      el.querySelectorAll('.draggo-ins-bp button').forEach(function (b) {
        b.addEventListener('click', function () { self.setViewport(b.dataset.vw); });
      });
      // cur() = effective merged values for the active viewport; save() routes
      // base vs. responsive[bp] so each breakpoint stores only its overrides.
      var cur = function () { return Object.assign({}, self.effLayout(self.currentLayout() || {})); };
      var save = function (L) { self.saveLayoutForVw(L); };
      // Structural props need a re-render to show (align/flex/display/width/gap).
      var saveR = function (L) { self.saveLayoutForVw(L, true); };

      var bgHost = el.querySelector('#ins-bg-host');
      if (bgHost) { var bgCtrl = colorControl(l.bg, colorTok, function () { var L = cur(); var v = bgCtrl.get(); if (v) L.bg = v; else delete L.bg; save(L); }); bgHost.appendChild(bgCtrl.el); }
      el.querySelector('#ins-image-pick').addEventListener('click', function () { self.filePicker('image', false, l.image ? [l.image] : [], function (p) { var L = cur(); L.image = p; saveR(L); }); });
      el.querySelector('#ins-image-clear').addEventListener('click', function () { var L = cur(); delete L.image; delete L.bgSize; delete L.bgPosition; delete L.bgRepeat; delete L.bgAttachment; saveR(L); });
      var bindBg = function (id, key) { var n = el.querySelector(id); if (n) n.addEventListener('change', function (e) { var L = cur(); L[key] = e.target.value; save(L); }); };
      bindBg('#ins-bgsize', 'bgSize'); bindBg('#ins-bgpos', 'bgPosition'); bindBg('#ins-bgrep', 'bgRepeat'); bindBg('#ins-bgatt', 'bgAttachment');
      // Gradient background.
      var gFromHost = el.querySelector('#ins-grad-from-host');
      if (gFromHost) { var gFromCtrl = colorControl(l.bgGradFrom, colorTok, function () { var L = cur(); var v = gFromCtrl.get(); if (v) L.bgGradFrom = v; else delete L.bgGradFrom; if (!L.bgGradType && L.bgGradFrom && L.bgGradTo) L.bgGradType = 'linear'; save(L); }); gFromHost.appendChild(gFromCtrl.el); }
      var gToHost = el.querySelector('#ins-grad-to-host');
      if (gToHost) { var gToCtrl = colorControl(l.bgGradTo, colorTok, function () { var L = cur(); var v = gToCtrl.get(); if (v) L.bgGradTo = v; else delete L.bgGradTo; if (!L.bgGradType && L.bgGradFrom && L.bgGradTo) L.bgGradType = 'linear'; save(L); }); gToHost.appendChild(gToCtrl.el); }
      var gType = el.querySelector('#ins-grad-type');
      if (gType) gType.addEventListener('change', function (e) {
        var L = cur();
        if (e.target.value) { L.bgGradType = e.target.value; }
        else { delete L.bgGradType; delete L.bgGradFrom; delete L.bgGradTo; delete L.bgGradAngle; }
        saveR(L);
      });
      var gAngle = el.querySelector('#ins-grad-angle');
      if (gAngle) gAngle.addEventListener('change', function (e) { var L = cur(); var n = parseInt(e.target.value, 10); if (!isNaN(n) && n >= 0 && n <= 360) L.bgGradAngle = n; else delete L.bgGradAngle; save(L); });
      // ── Background overlay ──
      var ovType = el.querySelector('#ins-ov-type');
      if (ovType) ovType.addEventListener('change', function (e) {
        var L = cur();
        if (e.target.value) { L.ovType = e.target.value; if (L.ovOpacity == null) L.ovOpacity = 0.5; }
        else { ['ovType', 'ovColor', 'ovGradFrom', 'ovGradTo', 'ovGradType', 'ovGradAngle', 'ovImage', 'ovOpacity', 'ovBlend'].forEach(function (k) { delete L[k]; }); }
        saveR(L);
      });
      var ovColorHost = el.querySelector('#ins-ov-color-host');
      if (ovColorHost) { var ovColorCtrl = colorControl(l.ovColor, colorTok, function () { var L = cur(); var v = ovColorCtrl.get(); if (v) L.ovColor = v; else delete L.ovColor; save(L); }); ovColorHost.appendChild(ovColorCtrl.el); }
      var ovFromHost = el.querySelector('#ins-ov-from-host');
      if (ovFromHost) { var ovFromCtrl = colorControl(l.ovGradFrom, colorTok, function () { var L = cur(); var v = ovFromCtrl.get(); if (v) L.ovGradFrom = v; else delete L.ovGradFrom; save(L); }); ovFromHost.appendChild(ovFromCtrl.el); }
      var ovToHost = el.querySelector('#ins-ov-to-host');
      if (ovToHost) { var ovToCtrl = colorControl(l.ovGradTo, colorTok, function () { var L = cur(); var v = ovToCtrl.get(); if (v) L.ovGradTo = v; else delete L.ovGradTo; save(L); }); ovToHost.appendChild(ovToCtrl.el); }
      var ovGType = el.querySelector('#ins-ov-gtype');
      if (ovGType) ovGType.addEventListener('change', function (e) { var L = cur(); L.ovGradType = e.target.value; saveR(L); });
      var ovGAngle = el.querySelector('#ins-ov-gangle');
      if (ovGAngle) ovGAngle.addEventListener('change', function (e) { var L = cur(); var n = parseInt(e.target.value, 10); if (!isNaN(n) && n >= 0 && n <= 360) L.ovGradAngle = n; else delete L.ovGradAngle; save(L); });
      var ovImgPick = el.querySelector('#ins-ov-image-pick');
      if (ovImgPick) ovImgPick.addEventListener('click', function () { self.filePicker('image', false, l.ovImage ? [l.ovImage] : [], function (p) { var L = cur(); L.ovImage = p; saveR(L); }); });
      var ovImgClear = el.querySelector('#ins-ov-image-clear');
      if (ovImgClear) ovImgClear.addEventListener('click', function () { var L = cur(); delete L.ovImage; saveR(L); });
      var ovOpacity = el.querySelector('#ins-ov-opacity');
      if (ovOpacity) ovOpacity.addEventListener('change', function (e) { var L = cur(); L.ovOpacity = Math.max(0, Math.min(100, parseInt(e.target.value, 10) || 0)) / 100; save(L); });
      var ovBlend = el.querySelector('#ins-ov-blend');
      if (ovBlend) ovBlend.addEventListener('change', function (e) { var L = cur(); if (e.target.value && e.target.value !== 'normal') L.ovBlend = e.target.value; else delete L.ovBlend; save(L); });
      // ── Media background (video / slider) ──
      var mType = el.querySelector('#ins-media-type');
      if (mType) mType.addEventListener('change', function (e) {
        var L = cur();
        if (e.target.value === 'video') { L.bgMedia = 'video'; delete L.bgSlides; delete L.bgSlideInterval; delete L.bgSlideFx; }
        else if (e.target.value === 'slider') { L.bgMedia = 'slider'; delete L.bgVideo; delete L.bgVideoPoster; if (!L.bgSlideInterval) L.bgSlideInterval = 5000; }
        else { ['bgMedia', 'bgVideo', 'bgVideoPoster', 'bgSlides', 'bgSlideInterval', 'bgSlideFx'].forEach(function (k) { delete L[k]; }); }
        saveR(L);
      });
      var bgvPick = el.querySelector('#ins-bgvideo-pick');
      if (bgvPick) bgvPick.addEventListener('click', function () { self.filePicker('video', false, l.bgVideo ? [l.bgVideo] : [], function (p) { var L = cur(); L.bgVideo = p; saveR(L); }); });
      var bgvClear = el.querySelector('#ins-bgvideo-clear');
      if (bgvClear) bgvClear.addEventListener('click', function () { var L = cur(); delete L.bgVideo; saveR(L); });
      var bgvpPick = el.querySelector('#ins-bgvposter-pick');
      if (bgvpPick) bgvpPick.addEventListener('click', function () { self.filePicker('image', false, l.bgVideoPoster ? [l.bgVideoPoster] : [], function (p) { var L = cur(); L.bgVideoPoster = p; saveR(L); }); });
      var bgvpClear = el.querySelector('#ins-bgvposter-clear');
      if (bgvpClear) bgvpClear.addEventListener('click', function () { var L = cur(); delete L.bgVideoPoster; saveR(L); });
      var bgsPick = el.querySelector('#ins-bgslides-pick');
      if (bgsPick) bgsPick.addEventListener('click', function () { self.filePicker('image', true, (l.bgSlides || []).slice(), function (arr) { var L = cur(); if (arr && arr.length) L.bgSlides = arr.slice(0, 12); else delete L.bgSlides; saveR(L); }); });
      var bgsClear = el.querySelector('#ins-bgslides-clear');
      if (bgsClear) bgsClear.addEventListener('click', function () { var L = cur(); delete L.bgSlides; saveR(L); });
      var bgsInt = el.querySelector('#ins-bgslide-int');
      if (bgsInt) bgsInt.addEventListener('change', function (e) { var L = cur(); var n = parseInt(e.target.value, 10); L.bgSlideInterval = (n >= 1000 && n <= 20000) ? n : 5000; save(L); });
      var bgsFx = el.querySelector('#ins-bgslide-fx');
      if (bgsFx) bgsFx.addEventListener('change', function (e) { var L = cur(); L.bgSlideFx = e.target.value === 'slide' ? 'slide' : 'fade'; save(L); });
      var colHost = el.querySelector('#ins-color-host');
      if (colHost) { var colCtrl = colorControl(l.color, colorTok, function () { var L = cur(); var v = colCtrl.get(); if (v) L.color = v; else delete L.color; save(L); }); colHost.appendChild(colCtrl.el); }
      el.querySelectorAll('.draggo-ins-dim[data-dim]').forEach(function (box) {
        var key = box.dataset.dim;
        box.addEventListener('change', function () {
          var L = cur();
          var unit = (box.querySelector('[data-unit]') || {}).value || 'px';
          var obj = { unit: unit }; var any = false;
          box.querySelectorAll('input[data-side]').forEach(function (i) {
            if (i.value !== '') { obj[i.dataset.side] = parseFloat(i.value); any = true; } else { obj[i.dataset.side] = ''; }
          });
          if (any) L[key] = obj; else delete L[key];
          // Drop the legacy scale value so it can't override the per-side box.
          delete L[key === 'paddingBox' ? 'padding' : 'margin'];
          save(L);
        });
      });
      el.querySelector('#ins-minh').addEventListener('change', function (e) { var L = cur(); var n = parseInt(e.target.value, 10); if (n > 0) L.minHeight = n; else delete L.minHeight; save(L); });
      // Container name (tl_article.title) — backend overview.
      var cname = el.querySelector('#ins-cname');
      if (cname) {
        cname.addEventListener('change', function (e) {
          var t = e.target.value;
          self.api('/article/' + a.sectionId + '/rename', 'POST', { title: t }).then(function () {
            var s = self.section(a.sectionId); if (s) s.title = t;
            var hd = document.querySelector('.draggo-section[data-section="' + a.sectionId + '"] .draggo-section-title');
            if (hd) hd.textContent = t;
          }, function (err) { self.fail(err); });
        });
      }
      // Restore points (history) — container scope only, in page mode.
      if (a.scope === 'container' && this.mode === 'page' && a.targetId) {
        this.renderHistory(el, a.targetId);
      }
      // Border + shadow.
      var insSelKey = function (id, key) { var n = el.querySelector(id); if (n) n.addEventListener('change', function (e) { var L = cur(); if (e.target.value) L[key] = e.target.value; else delete L[key]; save(L); }); };
      var insNumKey = function (id, key) { var n = el.querySelector(id); if (n) n.addEventListener('change', function (e) { var L = cur(); var v = parseInt(e.target.value, 10); if (v > 0) L[key] = v; else delete L[key]; save(L); }); };
      insSelKey('#ins-bstyle', 'borderStyle'); insSelKey('#ins-bshadow', 'boxShadow');
      insNumKey('#ins-bwidth', 'borderWidth'); insNumKey('#ins-bradius', 'borderRadius');
      var bcHost = el.querySelector('#ins-bcolor-host');
      if (bcHost) { var bcCtrl = colorControl(l.borderColor, colorTok, function () { var L = cur(); var v = bcCtrl.get(); if (v) L.borderColor = v; else delete L.borderColor; save(L); }); bcHost.appendChild(bcCtrl.el); }
      var widthSel = el.querySelector('#ins-width');
      if (widthSel) widthSel.addEventListener('change', function (e) { var L = cur(); L.width = e.target.value; saveR(L); });
      var gapSel = el.querySelector('#ins-gap');
      if (gapSel) gapSel.addEventListener('change', function (e) { var L = cur(); if (e.target.value) L.gap = e.target.value; else delete L.gap; saveR(L); });
      // Restructure a row's column layout (preset). Reloads on apply.
      var structSel = el.querySelector('#ins-struct');
      if (structSel) {
        var customRow = el.querySelector('#ins-struct-custom-row');
        var customInp = el.querySelector('#ins-struct-custom');
        var tabletSel = el.querySelector('#ins-struct-tablet');
        var mobileSel = el.querySelector('#ins-struct-mobile');
        var applyStruct = function () {
          self.api('/element/' + a.targetId + '/restructure', 'POST', {
            preset: structSel.value,
            custom: customInp ? customInp.value : '',
            tablet: tabletSel ? tabletSel.value : '',
            mobile: mobileSel ? mobileSel.value : '',
          }).then(function () { self.loadContent(); }, function (err) { self.fail(err); });
        };
        structSel.addEventListener('change', function () {
          var isCustom = structSel.value === 'custom';
          if (customRow) customRow.hidden = !isCustom;
          if (!isCustom) applyStruct(); // custom waits for the width input
        });
        if (customInp) customInp.addEventListener('change', function () { if (structSel.value === 'custom') applyStruct(); });
        if (tabletSel) tabletSel.addEventListener('change', applyStruct);
        if (mobileSel) mobileSel.addEventListener('change', applyStruct);
      }
      // Opt-in: convert a recognised foreign theme grid into Draggo wrappers.
      var convBtn = el.querySelector('#ins-convert-grid');
      if (convBtn) convBtn.addEventListener('click', function () {
        if (!window.confirm(tr('In Draggo-Grid umwandeln? Das Frontend nutzt danach Draggos Grid — die Theme-Grid-Darstellung entfällt.'))) return;
        convBtn.disabled = true;
        self.api('/element/' + a.targetId + '/convert-grid', 'POST', {}).then(function () {
          self.toast(tr('Umgewandelt.'));
          self.loadContent();
        }, function (err) { convBtn.disabled = false; self.fail(err); });
      });
      // Phase D: container display-mode wiring.
      var dispSel = el.querySelector('#ins-display');
      if (dispSel) dispSel.addEventListener('change', function (e) { var L = cur(); if (e.target.value) L.display = e.target.value; else delete L.display; saveR(L); });
      var bind = function (id, key) { var n = el.querySelector(id); if (n) n.addEventListener('change', function (e) { var L = cur(); var v = e.target.value; if (v) L[key] = v; else delete L[key]; saveR(L); }); };
      bind('#ins-fdir', 'flexDirection'); bind('#ins-fjustify', 'flexJustify'); bind('#ins-falign', 'flexAlign'); bind('#ins-fwrap', 'flexWrap'); bind('#ins-dgap', 'gap');
      var gcols = el.querySelector('#ins-gcols');
      if (gcols) gcols.addEventListener('change', function (e) { var L = cur(); var n = parseInt(e.target.value, 10); if (n >= 1 && n <= 12) L.gridColumns = n; else delete L.gridColumns; saveR(L); });
      el.querySelector('#ins-class').addEventListener('change', function (e) { var L = cur(); var v = e.target.value.trim(); if (v) L.cssClass = v; else delete L.cssClass; save(L); });
      el.querySelector('#ins-cssid').addEventListener('change', function (e) { var L = cur(); var v = e.target.value.trim(); if (v) L.cssId = v; else delete L.cssId; save(L); });
      el.querySelectorAll('.draggo-ins-align[data-align]').forEach(function (b) {
        b.addEventListener('click', function () { var L = cur(); if (L.align === b.dataset.align) delete L.align; else L.align = b.dataset.align; saveR(L); });
      });
      el.querySelectorAll('[data-alignx]').forEach(function (b) {
        b.addEventListener('click', function () { var L = cur(); if (L.alignX === b.dataset.alignx) delete L.alignX; else L.alignX = b.dataset.alignx; saveR(L); });
      });
      el.querySelectorAll('[data-aligny]').forEach(function (b) {
        b.addEventListener('click', function () { var L = cur(); if (L.alignY === b.dataset.aligny) delete L.alignY; else L.alignY = b.dataset.aligny; saveR(L); });
      });

      // ── Typography / Size / Position bindings (saveR → re-render preview) ──
      var bindLen = function (id, key) { var n = el.querySelector(id); if (!n) return; n.addEventListener('change', function () { var L = cur(); var v = n.value.trim(); if (/^\d+(\.\d+)?$/.test(v)) v += 'px'; if (v) L[key] = v; else delete L[key]; saveR(L); }); };
      var bindNumK = function (id, key, parse) { var n = el.querySelector(id); if (!n) return; n.addEventListener('change', function () { var L = cur(); if (n.value === '') { delete L[key]; } else { var v = parse(n.value); if (v === null || isNaN(v)) delete L[key]; else L[key] = v; } saveR(L); }); };
      var bindSelK = function (id, key) { var n = el.querySelector(id); if (!n) return; n.addEventListener('change', function () { var L = cur(); if (n.value) L[key] = n.value; else delete L[key]; saveR(L); }); };
      var bindTxtK = function (id, key) { var n = el.querySelector(id); if (!n) return; n.addEventListener('change', function () { var L = cur(); var v = n.value.trim(); if (v) L[key] = v; else delete L[key]; saveR(L); }); };
      bindTxtK('#ins-fontfam', 'fontFamily');
      bindNumK('#ins-fontsize', 'fontSize', function (r) { return parseInt(r, 10); });
      bindSelK('#ins-fontweight', 'fontWeight'); bindSelK('#ins-texttransform', 'textTransform'); bindSelK('#ins-fontstyle', 'fontStyle');
      bindSelK('#ins-textdeco', 'textDecoration'); bindSelK('#ins-textshadow', 'textShadow'); bindSelK('#ins-position', 'position');
      bindNumK('#ins-lineheight', 'lineHeight', function (r) { return parseFloat(r); });
      bindNumK('#ins-letterspacing', 'letterSpacing', function (r) { return parseFloat(r); });
      bindNumK('#ins-opacity', 'opacity', function (r) { return parseFloat(r); });
      bindNumK('#ins-zindex', 'zIndex', function (r) { return parseInt(r, 10); });
      bindNumK('#ins-rotate', 'transformRotate', function (r) { return parseInt(r, 10); });
      bindNumK('#ins-scale', 'transformScale', function (r) { return parseFloat(r); });
      bindLen('#ins-w', 'width'); bindLen('#ins-maxw', 'maxWidth'); bindLen('#ins-h', 'height'); bindLen('#ins-maxh', 'maxHeight');
      bindLen('#ins-postop', 'posTop'); bindLen('#ins-posright', 'posRight'); bindLen('#ins-posbottom', 'posBottom'); bindLen('#ins-posleft', 'posLeft');
      bindLen('#ins-translatex', 'transformTranslateX'); bindLen('#ins-translatey', 'transformTranslateY');
      var tgN = el.querySelector('#ins-textgrad');
      if (tgN) tgN.addEventListener('change', function () { var L = cur(); if (tgN.checked) L.textGradient = true; else delete L.textGradient; saveR(L); });
      var gfHost = el.querySelector('#ins-gradfrom-host');
      if (gfHost) { var gfC = colorControl(l.gradFrom, colorTok, function () { var L = cur(); var v = gfC.get(); if (v) L.gradFrom = v; else delete L.gradFrom; saveR(L); }); gfHost.appendChild(gfC.el); }
      var gtHost = el.querySelector('#ins-gradto-host');
      if (gtHost) { var gtC = colorControl(l.gradTo, colorTok, function () { var L = cur(); var v = gtC.get(); if (v) L.gradTo = v; else delete L.gradTo; saveR(L); }); gtHost.appendChild(gtC.el); }
      var ccN = el.querySelector('#ins-customcss');
      if (ccN) ccN.addEventListener('change', function () { var L = cur(); var v = ccN.value.trim(); if (v) L.customCss = v; else delete L.customCss; save(L); });
      [['#ins-hide-d', 'hideDesktop'], ['#ins-hide-t', 'hideTablet'], ['#ins-hide-m', 'hideMobile']].forEach(function (o) {
        var n = el.querySelector(o[0]); if (!n) return;
        n.addEventListener('change', function () { var L = cur(); if (n.checked) L[o[1]] = true; else delete L[o[1]]; save(L); });
      });
    }

    enableSort(list, section) {
      var self = this;
      var dragged = null;
      list.addEventListener('dragstart', function (e) {
        var t = e.target.closest('.draggo-item');
        if (t && t.parentNode === list) { dragged = t; t.classList.add('is-dragging'); }
      });
      list.addEventListener('dragend', function () {
        if (!dragged) return;
        dragged.classList.remove('is-dragging');
        dragged = null;
        self.persistSection(section).then(function () { self.loadContent(); });
      });
      list.addEventListener('dragover', function (e) {
        if (!self.drag || self.drag.kind !== 'move' || !dragged) return;
        e.preventDefault();
        var after = afterElement(list, e.clientY);
        if (after == null) list.appendChild(dragged);
        else list.insertBefore(dragged, after);
      });
    }

    gridWidths(r) {
      // Per-viewport column distribution: at tablet/mobile use the row's
      // gridTablet/gridMobile preset (if set) so columns stack/redistribute
      // exactly like the frontend — otherwise the desktop preset/custom.
      var presetKey = r.preset, custom = r.custom || '';
      if (this.vw === 'tablet' && r.gridTablet) { presetKey = r.gridTablet; custom = ''; }
      else if (this.vw === 'mobile' && r.gridMobile) { presetKey = r.gridMobile; custom = ''; }
      var widths = (this.presetWidths[presetKey] || []).slice();
      if (presetKey === 'custom' || widths.length === 0) {
        widths = (custom || r.custom || '').match(/\d+/g) || [];
        widths = widths.map(Number).filter(function (n) { return n >= 1 && n <= 12; });
      }
      var n = r.columns.length;
      if (widths.length === 0) { var even = Math.floor(12 / n) || 12; widths = []; for (var i = 0; i < n; i++) widths.push(even); }
      // Fewer widths than columns (e.g. mobile "1" → every column 100%): cycle
      // the pattern so each column gets a width and the row wraps naturally.
      else if (widths.length < n) { var src = widths.slice(); widths = []; for (var j = 0; j < n; j++) widths.push(src[j % src.length]); }
      return widths;
    }
  }

  // ── tree (recursive: grid-in-grid) ─────────────────────────────
  function buildTree(elements) {
    var root = [];
    var stack = []; // [{r, col}]
    function current() { return stack.length ? stack[stack.length - 1].col : root; }
    elements.forEach(function (el) {
      if (isRowStart(el.type)) {
        var r = { preset: el.gridPreset, custom: el.gridCustom, gridTablet: el.gridTablet, gridMobile: el.gridMobile, startId: el.id, stopId: null, colOpenIds: [el.id], columns: [[]], foreign: el.type !== 'draggo_row_start' };
        current().push({ k: 'row', r: r });
        stack.push({ r: r, col: r.columns[0] });
      } else if (isRowSep(el.type)) {
        if (stack.length) {
          var top = stack[stack.length - 1];
          top.r.colOpenIds.push(el.id);
          top.r.columns.push([]);
          top.col = top.r.columns[top.r.columns.length - 1];
        }
      } else if (isRowStop(el.type)) {
        if (stack.length) { stack[stack.length - 1].r.stopId = el.id; stack.pop(); }
      } else {
        current().push({ k: 'el', el: el });
      }
    });
    return root;
  }

  // Serialise a content list DOM (recursive) back to the full ordered id list.
  function serializeList(listEl) {
    var ids = [];
    Array.prototype.forEach.call(listEl.children, function (node) {
      if (node.classList.contains('draggo-item')) {
        ids.push(parseInt(node.dataset.id, 10));
      } else if (node.classList.contains('draggo-row')) {
        ids.push(parseInt(node.dataset.startId, 10));
        var colsWrap = node.querySelector(':scope > .draggo-cols');
        var colEls = colsWrap ? colsWrap.querySelectorAll(':scope > .draggo-col') : [];
        Array.prototype.forEach.call(colEls, function (colEl, i) {
          if (i > 0) ids.push(parseInt(colEl.dataset.openId, 10));
          var sub = colEl.querySelector(':scope > .draggo-col-list');
          if (sub) ids.push.apply(ids, serializeList(sub));
        });
        if (node.dataset.stopId) ids.push(parseInt(node.dataset.stopId, 10));
      }
    });
    return ids.filter(function (n) { return !isNaN(n); });
  }

  function rowIds(r) {
    var ids = [];
    function walk(nodes) {
      nodes.forEach(function (n) {
        if (n.k === 'el') { ids.push(n.el.id); return; }
        ids.push(n.r.startId);
        n.r.columns.forEach(function (col, i) { if (i > 0) ids.push(n.r.colOpenIds[i]); walk(col); });
        if (n.r.stopId != null) ids.push(n.r.stopId);
      });
    }
    ids.push(r.startId);
    r.columns.forEach(function (col, i) { if (i > 0) ids.push(r.colOpenIds[i]); walk(col); });
    if (r.stopId != null) ids.push(r.stopId);
    return ids;
  }

  function dropAfterId(list, beforeNode, openerId) {
    if (beforeNode) {
      var prev = beforeNode.previousElementSibling;
      return prev && prev.dataset.id ? parseInt(prev.dataset.id, 10) : openerId;
    }
    var items = list.querySelectorAll(':scope > .draggo-item');
    return items.length ? parseInt(items[items.length - 1].dataset.id, 10) : openerId;
  }

  function afterElement(list, y) {
    var items = Array.prototype.slice.call(list.querySelectorAll(':scope > .draggo-item:not(.is-dragging)'));
    var closest = { offset: Number.NEGATIVE_INFINITY, node: null };
    items.forEach(function (node) {
      var box = node.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) closest = { offset: offset, node: node };
    });
    return closest.node;
  }

  function afterSection(canvas, y, dragged) {
    var items = Array.prototype.slice.call(canvas.querySelectorAll('.draggo-section')).filter(function (s) { return s !== dragged; });
    var closest = { offset: Number.NEGATIVE_INFINITY, node: null };
    items.forEach(function (node) {
      var box = node.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) closest = { offset: offset, node: node };
    });
    return closest.node;
  }

  function esc(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  // Reusable colour control: picker + alpha (RGBA) + editable hex + clear
  // (= transparent) + optional global-colour token dropdown. Default = unset
  // (transparent). Value out = #rrggbb / #rrggbbaa / var(--bld-color-…) / undefined.
  function colorControl(val, tokens, onChange) {
    tokens = tokens || [];
    var isVar = typeof val === 'string' && val.indexOf('var(--bld-') === 0;
    var hex6 = (val && !isVar) ? String(val).slice(0, 7) : '#000000';
    var aPct = (val && !isVar && String(val).length === 9) ? Math.round(parseInt(String(val).slice(7, 9), 16) / 255 * 100) : 100;
    var set = !!val && !isVar;
    var a2 = function (p) { var h = Math.round(p / 100 * 255).toString(16); return h.length < 2 ? '0' + h : h; };

    var wrap = document.createElement('span'); wrap.className = 'draggo-ins-color';
    var cc = document.createElement('input'); cc.type = 'color'; cc.value = hex6;
    var av = document.createElement('input'); av.type = 'range'; av.min = '0'; av.max = '100'; av.value = aPct; av.className = 'draggo-ins-alpha'; av.title = 'Deckkraft (Alpha)';
    var ht = document.createElement('input'); ht.type = 'text'; ht.className = 'draggo-ins-hex'; ht.placeholder = 'transparent';
    var cx = document.createElement('button'); cx.type = 'button'; cx.textContent = '✕'; cx.title = 'transparent';
    var tk = null;
    var norm = function () { var a = parseInt(av.value, 10); return a >= 100 ? cc.value : (cc.value + a2(a)); };
    ht.value = set ? norm() : '';
    var fire = function () { if (onChange) onChange(); };
    cc.addEventListener('input', function () { set = true; if (tk) tk.value = ''; ht.value = norm(); fire(); });
    av.addEventListener('input', function () { set = true; if (tk) tk.value = ''; ht.value = norm(); fire(); });
    ht.addEventListener('input', function () {
      var v = ht.value.trim();
      if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(v)) {
        set = true; if (tk) tk.value = '';
        if (v.length >= 7) cc.value = v.slice(0, 7);
        av.value = (v.length === 9) ? Math.round(parseInt(v.slice(7, 9), 16) / 255 * 100) : 100;
      } else if (v === '') { set = false; }
      fire();
    });
    cx.addEventListener('click', function () { set = false; ht.value = ''; cc.value = '#000000'; av.value = 100; if (tk) tk.value = ''; fire(); });

    wrap.appendChild(cc); wrap.appendChild(av); wrap.appendChild(ht); wrap.appendChild(cx);
    if (tokens.length) {
      tk = document.createElement('select'); tk.className = 'draggo-ins-select draggo-tok-pick';
      var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; tk.appendChild(o0);
      tokens.forEach(function (t) { var op = document.createElement('option'); op.value = 'var(--bld-color-' + t.token + ')'; op.textContent = t.label || t.token; if (isVar && val === op.value) op.selected = true; tk.appendChild(op); });
      tk.addEventListener('change', function () { if (tk.value) { set = false; } fire(); });
      wrap.appendChild(tk);
    }
    return { el: wrap, get: function () { if (tk && tk.value) return tk.value; return set ? norm() : undefined; } };
  }

  // True when a field value carries no content (per widget shape).
  function valueIsEmpty(widget, val) {
    if (val == null) return true;
    if (widget === 'headline') { return !val.value || !String(val.value).trim(); }
    if (widget === 'files' || widget === 'lines') {
      return String(val).split('\n').filter(function (l) { return l.trim() !== ''; }).length === 0;
    }
    if (widget === 'table') {
      return !(Array.isArray(val) && val.some(function (r) { return Array.isArray(r) && r.some(function (c) { return String(c).trim() !== ''; }); }));
    }
    if (widget === 'pairs' || widget === 'iconpairs' || widget === 'accitems') {
      return !(Array.isArray(val) && val.some(function (p) { return (p && (String(p.key).trim() !== '' || String(p.value).trim() !== '')); }));
    }
    return String(val).trim() === '';
  }

  // Rich-text editor: full TinyMCE if Contao shipped it, else a built-in toolbar.
  function buildRte(html) {
    if (window.tinymce) {
      var w = document.createElement('div');
      var ta = document.createElement('textarea');
      var tid = 'bmce-' + Math.random().toString(36).slice(2);
      ta.id = tid; ta.value = html || '';
      w.appendChild(ta);
      setTimeout(function () {
        try {
          window.tinymce.init({
            selector: '#' + tid,
            license_key: 'gpl', // self-hosted GPL build (TinyMCE 6/7)
            menubar: 'edit insert format table',
            plugins: 'lists link image table code fullscreen searchreplace charmap',
            toolbar: 'styles | fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | removeformat code fullscreen',
            height: 340,
            branding: false,
            promotion: false,
            convert_urls: false,
            relative_urls: false,
          });
        } catch (e) { if (window.console) console.warn('TinyMCE init', e); }
      }, 0);
      return {
        el: w,
        getHtml: function () { var ed = window.tinymce.get(tid); return ed ? ed.getContent() : ta.value; },
        destroy: function () { var ed = window.tinymce.get(tid); if (ed) ed.remove(); },
      };
    }

    var el = document.createElement('div');
    el.className = 'draggo-rte';
    var bar = document.createElement('div');
    bar.className = 'draggo-rte-bar';
    var area = document.createElement('div');
    area.className = 'draggo-rte-area';
    area.contentEditable = 'true';
    area.innerHTML = html || '';

    var cmd = function (c, v) {
      area.focus();
      try { document.execCommand('styleWithCSS', false, true); } catch (e) {}
      document.execCommand(c, false, v === undefined ? null : v);
    };
    var btn = function (label, title, fn) {
      var b = document.createElement('button');
      b.type = 'button'; b.title = title; b.innerHTML = label; b.className = 'draggo-rte-btn';
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function (e) { e.preventDefault(); fn(); });
      bar.appendChild(b);
      return b;
    };

    // Block format
    var fmt = document.createElement('select'); fmt.className = 'draggo-rte-sel'; fmt.title = 'Format';
    [['p', 'Absatz'], ['h1', 'H1'], ['h2', 'H2'], ['h3', 'H3'], ['h4', 'H4'], ['h5', 'H5'], ['h6', 'H6']].forEach(function (o) {
      var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); fmt.appendChild(op);
    });
    fmt.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    fmt.addEventListener('change', function () { cmd('formatBlock', fmt.value); });
    bar.appendChild(fmt);

    // Font family
    var font = document.createElement('select'); font.className = 'draggo-rte-sel'; font.title = 'Schriftart';
    [['', 'Schrift'], ['Arial', 'Arial'], ['Helvetica', 'Helvetica'], ['Georgia', 'Georgia'], ['Times New Roman', 'Times'], ['Courier New', 'Courier'], ['Verdana', 'Verdana'], ['Tahoma', 'Tahoma'], ['Trebuchet MS', 'Trebuchet'], ['system-ui', 'System']].forEach(function (o) {
      var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); font.appendChild(op);
    });
    font.addEventListener('change', function () { if (font.value) cmd('fontName', font.value); });
    bar.appendChild(font);

    // Font size (1–7)
    var size = document.createElement('select'); size.className = 'draggo-rte-sel'; size.title = 'Größe';
    [['', 'Größe'], ['1', 'XS'], ['2', 'S'], ['3', 'M'], ['4', 'L'], ['5', 'XL'], ['6', 'XXL'], ['7', 'XXXL']].forEach(function (o) {
      var op = document.createElement('option'); op.value = o[0]; op.textContent = tr(o[1]); size.appendChild(op);
    });
    size.addEventListener('change', function () { if (size.value) cmd('fontSize', size.value); });
    bar.appendChild(size);

    btn('<b>B</b>', 'Fett', function () { cmd('bold'); });
    btn('<i>I</i>', 'Kursiv', function () { cmd('italic'); });
    btn('<u>U</u>', 'Unterstrichen', function () { cmd('underline'); });

    var color = document.createElement('input'); color.type = 'color'; color.className = 'draggo-rte-color'; color.title = 'Textfarbe';
    color.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    color.addEventListener('input', function () { cmd('foreColor', color.value); });
    bar.appendChild(color);

    btn('•', 'Liste', function () { cmd('insertUnorderedList'); });
    btn('1.', 'Nummerierte Liste', function () { cmd('insertOrderedList'); });
    btn('⯇', 'Links', function () { cmd('justifyLeft'); });
    btn('≡', 'Zentriert', function () { cmd('justifyCenter'); });
    btn('⯈', 'Rechts', function () { cmd('justifyRight'); });
    btn('🔗', 'Link', function () { var u = window.prompt('URL:'); if (u) cmd('createLink', u); });
    btn('⌫', 'Format entfernen', function () { cmd('removeFormat'); });

    el.appendChild(bar);
    el.appendChild(area);
    return { el: el, getHtml: function () { return area.innerHTML; }, destroy: function () {} };
  }

  // Clean inline align icons (Elementor-style).
  function svgIcon(name) {
    var m = {
      xstart: '<rect x="2" y="2" width="4" height="12" rx="1"/>',
      xcenter: '<rect x="6" y="2" width="4" height="12" rx="1"/>',
      xend: '<rect x="10" y="2" width="4" height="12" rx="1"/>',
      ytop: '<rect x="2" y="2" width="12" height="4" rx="1"/>',
      ymid: '<rect x="2" y="6" width="12" height="4" rx="1"/>',
      ybot: '<rect x="2" y="10" width="12" height="4" rx="1"/>',
      tleft: '<rect x="2" y="3" width="12" height="2"/><rect x="2" y="7" width="8" height="2"/><rect x="2" y="11" width="12" height="2"/>',
      tcenter: '<rect x="2" y="3" width="12" height="2"/><rect x="4" y="7" width="8" height="2"/><rect x="2" y="11" width="12" height="2"/>',
      tright: '<rect x="2" y="3" width="12" height="2"/><rect x="6" y="7" width="8" height="2"/><rect x="2" y="11" width="12" height="2"/>'
    };
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">' + (m[name] || '') + '</svg>';
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
