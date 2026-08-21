# Draggo

*[English version → README.en.md](README.en.md)*

Draggo ist ein visueller Frontend-Seitenersteller für Contao 5 im Stil bekannter
Page-Builder: Administratoren gestalten Seiten per Drag & Drop, ohne den
Quellcode einer Seite zu berühren. Ergänzend generiert ein KI-gestützter
Assistent auf Wunsch eigene Content-Elemente und ganze Landingpages — innerhalb
fest definierter, geprüfter Grenzen.

Der Quellcode dieses Bundles ist Open Source (LGPL-3.0-or-later). Die Nutzung
der Draggo-eigenen Funktionen (Editor, Content-Elemente, KI-Generator) setzt
zusätzlich eine aktive, kostenpflichtige Pro-Lizenz von v-t.one voraus — siehe
[Lizenzmodell und Aktivierung](#lizenzmodell-und-aktivierung). Contao selbst
und alle nicht von Draggo stammenden Inhalte funktionieren unabhängig davon
immer normal weiter.

## Inhalt

1. [Überblick](#überblick)
2. [Systemvoraussetzungen](#systemvoraussetzungen)
3. [Installation](#installation)
4. [Konfiguration](#konfiguration)
5. [Backend-Zugriff und Navigation](#backend-zugriff-und-navigation)
6. [Der visuelle Editor](#der-visuelle-editor)
7. [Content-Elemente](#content-elemente)
8. [Grid-System](#grid-system)
9. [Design-Tokens („Globals")](#design-tokens-globals)
10. [KI-Element-Generator](#ki-element-generator)
11. [KI-Dokumentations-Chat](#ki-dokumentations-chat)
12. [Verlauf / Wiederherstellungspunkte](#verlauf--wiederherstellungspunkte)
13. [Formulare](#formulare)
14. [Icons, Schriften und Bilder](#icons-schriften-und-bilder)
15. [Berechtigungen und Zugriffskontrolle](#berechtigungen-und-zugriffskontrolle)
16. [Lizenzmodell und Aktivierung](#lizenzmodell-und-aktivierung)
17. [Funktionsstatus-Tabelle](#funktionsstatus-tabelle)
18. [Sicherheitsmodell](#sicherheitsmodell)
19. [Laufzeitverzeichnisse](#laufzeitverzeichnisse)
20. [Externe Kommunikation](#externe-kommunikation)
21. [Protokollierung](#protokollierung)
22. [Deployment und Cache-Leerung](#deployment-und-cache-leerung)
23. [Tests](#tests)
24. [Fehlerbehebung](#fehlerbehebung)
25. [Bekannte Einschränkungen](#bekannte-einschränkungen)
26. [Lizenz- und Urheberrechtsinformationen](#lizenz--und-urheberrechtsinformationen)

## Überblick

Draggo fügt sich vollständig in Contaos eigene Content-Element-Architektur
ein: jedes Draggo-Element ist ein reguläres `tl_content`-Element und wird über
Contaos native Rendering-Pipeline ausgegeben. Es gibt kein Schatten-Datenmodell
und keine separate Ausgabe-Engine. Der visuelle Editor greift direkt auf die
echten Contao-DCA-Felder zu — auch Nicht-Draggo-Elemente lassen sich darin
bearbeiten.

Kernbestandteile:

- Ein Bootstrap-5-kompatibles, verschachtelbares Grid-System.
- Über 55 fertige Content-Elemente (Layout, Typografie, Medien, interaktive
  Scroll-Effekte, Navigation, Formulare u. v. m.).
- Ein einheitliches Stil-System (Farbe, Typografie, Abstand, Schatten,
  Sichtbarkeit) für jedes Element, kompiliert zu einem gecachten Stylesheet
  pro Seite.
- Global wiederverwendbare Einheiten (Header, Footer, Sektionen).
- Seitenweite Design-Tokens („Globals") für Farben, Schriften, Abstände.
- Ein KI-gestützter Element- und Landingpage-Generator auf Basis von Claude
  (Anthropic).
- Ein optionaler, retrieval-gestützter Dokumentations-Chat im Backend.

## Systemvoraussetzungen

| Komponente | Anforderung |
|---|---|
| PHP | ^8.2 (getestet gegen 8.2, 8.3, 8.4) |
| PHP-Erweiterungen | `ext-json`, `ext-sodium` |
| Contao | ^5.3 |
| Doctrine DBAL | ^3.6 oder ^4.0 |
| Symfony-Komponenten | Console, HttpClient, HttpFoundation (^6.4 oder ^7.0) |
| Twig | ^3.0 |

`ext-sodium` wird für die kryptografische Prüfung der Lizenzdaten benötigt;
ohne diese Erweiterung bleibt Draggo gesperrt (siehe
[Lizenzmodell und Aktivierung](#lizenzmodell-und-aktivierung)). Für die
KI-Funktionen und optionale Bildquellen ist ausgehender HTTPS-Zugriff des
Servers erforderlich (siehe [Externe Kommunikation](#externe-kommunikation)).

## Installation

```bash
composer require vtinnovations/draggo
```

Das Bundle registriert sich automatisch über den Contao Manager
(`Vtinnovations\Draggo\ContaoManager\Plugin`) und bindet seine Routen ein.
Anschließend die Datenbank aktualisieren, entweder über den Contao Manager
oder direkt:

```bash
vendor/bin/contao-console contao:migrate
```

`contao:migrate` legt alle benötigten Tabellen an und führt automatisch
sämtliche Datenmigrationen des Bundles aus (siehe
[Bekannte Einschränkungen](#bekannte-einschränkungen) für Hinweise zu
Bestandsinstallationen).

## Konfiguration

Die Bundle-Konfiguration erfolgt optional über `config/packages/draggo.yaml`
und wird, wo vorhanden, von den Backend-Einstellungen überschrieben (siehe
unten). Verfügbare Optionen mit ihren Standardwerten:

```yaml
draggo:
    ai:
        provider: claude          # aktuell nur "claude" funktionsfähig
        model: claude-opus-4-8
        api_key: ''                # alternativ im Backend hinterlegbar
        max_tokens: 4096
        max_rounds: 8
        rate_limit_per_hour: 30
        cost_cap_deci_cents: 5000
        telemetry: false            # anonyme Struktur-Telemetrie, standardmäßig aus
        telemetry_url: ''
    editor:
        max_columns: 6              # max. Spaltenzahl im Grid-Editor
    bootstrap:
        mode: grid                  # full | grid | off
        css_url: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
        js_url: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
```

**Grid-Modus (`bootstrap.mode`):**

| Wert | Verhalten |
|---|---|
| `grid` (Standard) | Bundle-eigenes, lokales Grid-CSS ohne JavaScript und ohne externe Anfrage. |
| `full` | Lädt Bootstrap 5.3.3 (CSS + JS) von der konfigurierten Quelle (Standard: jsDelivr-CDN). |
| `off` | Das Theme liefert Bootstrap bereits selbst; Draggo bindet nichts zusätzlich ein. |

Die im Frontend erzeugten CSS-Klassen sind in allen drei Modi identisch, ein
Wechsel erfordert keine Template-Änderungen.

Der Großteil der laufenden Konfiguration — KI-API-Schlüssel, Modell,
Bildquelle, Telemetrie — wird jedoch im Backend unter **Draggo → Einstellungen**
gepflegt (Tabelle `tl_draggo_settings`, ein einzelner Datensatz, sofort wirksam
ohne Cache-Leerung):

| Feld | Bedeutung |
|---|---|
| Claude API-Schlüssel | Eigener Anthropic/Claude-API-Key für den KI-Generator. |
| Modell | Überschreibt `ai.model`, leer = Standardwert. |
| Max. Rückfragen | Wie viele Frage-Runden der Assistent maximal stellt. |
| Selbst-Review deaktivieren | Schaltet die automatische Zweitprüfung jedes KI-Entwurfs ab (spart einen zusätzlichen API-Aufruf). |
| Telemetrie aktivieren | Sendet ausschließlich anonyme Struktur-Kennzahlen erzeugter Elemente (Typ, Feldanzahl) — niemals Inhalte, Vorlagen oder Seiteninhalte. |
| Bildquelle | Aus / Unsplash / Pexels / OpenAI (Bildgenerierung) — siehe [Icons, Schriften und Bilder](#icons-schriften-und-bilder). |
| Unsplash Access-Key / Pexels API-Key / OpenAI API-Key | Eigene Zugangsdaten der jeweils gewählten Bildquelle. |

## Backend-Zugriff und Navigation

Draggo erscheint im Contao-Backend als eigene Modulgruppe **Draggo** mit drei
Modulen, in dieser Reihenfolge:

1. **Globale Einheiten** — Header, Footer und globale Sektionen verwalten.
2. **KI-Element-Typen** — per KI generierte eigene Content-Elemente verwalten
   (Übersicht, Veröffentlichung, Löschen).
3. **Einstellungen** — KI-API-Schlüssel, Modell und Telemetrie.

Zusätzlich erscheint in der Seitenstruktur bei jeder Seite die Aktion
„Mit Draggo bearbeiten" sowie ein schwebender Editor-Startknopf im Frontend
für angemeldete Backend-Benutzer.

## Der visuelle Editor

Der Editor arbeitet seitenbezogen: eine Sitzung bearbeitet eine komplette
Contao-Seite mit allen ihren Artikeln und Content-Elementen, oder alternativ
eine globale Einheit. Elemente werden per Drag & Drop hinzugefügt, verschoben,
dupliziert und gelöscht; jedes Element erhält einen eigenen Stil-Tab mit den
in [Content-Elemente](#content-elemente) und
[Design-Tokens](#design-tokens-globals) beschriebenen Optionen.

Der Editor liest die verfügbaren Elementtypen direkt aus Contaos eigener
Registrierung — es werden also nie Elemente angeboten, die tatsächlich nicht
existieren. Für native Draggo-Elemente stehen kuratierte Inline-Felder zur
Verfügung; bei allen anderen Content-Elementen (auch von Drittanbietern)
werden die bearbeitbaren Felder automatisch aus der jeweiligen Contao-DCA
abgeleitet, sodass auch fremde Elemente im selben Editor bearbeitet werden
können.

## Content-Elemente

Alle Draggo-Content-Elemente liegen in der Backend-Kategorie **Draggo** und
erfordern eine aktive Pro-Lizenz (siehe
[Lizenzmodell und Aktivierung](#lizenzmodell-und-aktivierung)). Contao-eigene
und andere Content-Elemente sind davon nicht betroffen.

**Grid & Struktur**

| Element | Beschreibung |
|---|---|
| Grid: Reihe Anfang | Öffnet eine Bootstrap-Grid-Reihe mit Spalten. |
| Grid: Spalte | Trennt zur nächsten Spalte der Reihe. |
| Grid: Reihe Ende | Schließt die Grid-Reihe. |
| Globaler Block | Bindet eine wiederverwendbare „Sektion"-Einheit ein — zentral editierbar (Live-Link). |
| KI-Element | Per KI generiertes, eigenes Content-Element (siehe [KI-Element-Generator](#ki-element-generator)). |

**Text & Typografie**

| Element | Beschreibung |
|---|---|
| Trennlinie | Horizontale Linie. |
| Abstand | Vertikaler Leerraum. |
| Button | Schaltfläche mit Link. |
| Icon | Einzelnes SVG-Icon aus der Bibliothek. |
| Code-Block | Formatierter Code mit Sprache und Kopier-Button. |
| Zitat | Blockzitat mit optionalem Autor. |
| Text-Highlight | Großer Satz, färbt sich beim Scrollen Wort für Wort ein. |
| Text-Mask | Großer Schriftzug, durch den ein Bild sichtbar ist. |
| Animierte Überschrift | Überschrift mit rotierendem/tippendem Wort. |

**Medien**

| Element | Beschreibung |
|---|---|
| Galerie | Bildergalerie mit Grid/Masonry/Kacheln, Hover-Effekten und Lightbox. |
| Bild-Karussell | Slider aus mehreren Bildern. |
| Video-Playlist | Player + Liste; YouTube/Vimeo/MP4, lädt erst bei Klick (datenschutzfreundlich). |
| Hotspot-Bild | Bild mit interaktiven Punkten und Tooltips. |
| Before/After | Zwei Bilder mit Schieberegler zum Vergleichen. |
| Logo | Bild, verlinkt zur Startseite oder eigener URL. |

**Boxen & Inhaltsblöcke**

| Element | Beschreibung |
|---|---|
| Icon-Box | Icon + Titel + Text. |
| Bild-Box | Bild + Titel + Text. |
| Flip-Box | Karte, die beim Hover auf die Rückseite dreht. |
| Call to Action | Titel + Text + Button als Handlungsaufforderung. |
| Hinweisbox | Info-/Erfolg-/Warnung-/Fehler-Box, optional schließbar. |
| Akkordeon | Auf-/zuklappbare Titel/Inhalt-Einträge. |
| Tabs | Reiter mit Titel/Inhalt. |
| Ablauf-Schritte | Nummerierte Schritte mit Titel und Text. |
| Preisliste | Menü-/Speisekarten-Liste: Name … Preis. |
| Preistabelle | Preis-Karte mit Leistungen und Button. |
| Icon-Liste | Aufzählung mit Icon als Aufzählungszeichen. |
| Social-Icons | Verlinkte Icons zu sozialen Netzwerken. |

**Interaktiv / Scrollytelling**

| Element | Beschreibung |
|---|---|
| Zähler | Animierte Zahl, zählt beim Scrollen hoch. |
| Fortschrittsbalken | Skill-/Fortschrittsbalken, animiert beim Scrollen. |
| Countdown | Zählt bis zu einem Zieldatum herunter. |
| Parallax-Layers | Ebenen bewegen sich beim Scrollen unterschiedlich schnell. |
| Curtain-Cover | Vollflächige Panels, das nächste schiebt sich übers vorige. |
| Horizontal-Pin | Sektion rastet ein und scrollt seitwärts, Panels tauchen auf. |
| Stack-Reveal | Überlappende Karten, die beim Scrollen übereinander gleiten. |
| Sticky-Split Story | Fixiertes Bild, das pro Text-Abschnitt wechselt. |
| Scroll-Zoom | Bild zoomt beim Scrollen auf Vollbild. |
| Reveal-Grid | Karten steigen beim Scrollen gestaffelt ein. |
| Tilt-3D Cards | Karten kippen zur Maus und erscheinen beim Scrollen. |
| Pinned-Timeline | Fortschrittslinie füllt sich, Schritte aktivieren sich beim Scrollen. |
| Scroll-Progress | Lese-Fortschrittsbalken, fixiert oben/unten. |
| SVG-Path-Draw | Eine Linie/Form zeichnet sich beim Scrollen selbst. |

**Navigation & Seitenstruktur**

| Element | Beschreibung |
|---|---|
| Navigation | Seitenbaum-Navigation mit Design-Preset (horizontal / Seiten-Navi / Hamburger). |
| Breadcrumb | Pfad von der Startseite zur aktuellen Seite. |
| Sitemap | Verschachtelte Liste veröffentlichter Seiten. |
| Inhaltsverzeichnis | Sprunglinks aus den Überschriften der Seite. |
| Suche | Suchfeld zur Contao-Suchseite. |

**Daten & Dynamisch**

| Element | Beschreibung |
|---|---|
| Loop-Grid | Unterseiten einer Seite als Karten (datengetrieben). |
| Seitentitel | Titel der aktuellen Seite (dynamisch). |
| Reader-Feld | Feld der aktuellen News-/Termin-Detailseite (Titel/Teaser/Bild/Datum/Autor), sofern die entsprechende Contao-Erweiterung installiert ist. |
| Insert-Tag | Beliebiges Contao-Insert-Tag an dieser Stelle ausgeben. |
| Google Maps | Karte für eine Adresse, datenschutzfreundlich (Klick zum Laden). |
| Öffnungsstatus | Zeigt live „Jetzt geöffnet / Geschlossen" — zeitzonensicher, mit schema.org-Öffnungszeiten. |
| Hell/Dunkel-Umschalter | Besucher schalten zwischen Hell- und Dunkelmodus (nutzt die Dunkel-Werte der Farb-Tokens). |
| Teilen-Buttons | Aktuelle Seite teilen (Facebook/LinkedIn/E-Mail/Link). |

**Formulare**

| Element | Beschreibung |
|---|---|
| Formular | Visuell gebautes Formular — Versand, Validierung und Spamschutz übernimmt Contaos eigene Formular-Engine. |

Bei einigen Elementen (Formular, Reader-Feld, Seitentitel, Suche) zeigt die
Editor-Arbeitsfläche bewusst eine statische Vorschau, da dort kein echter
Seitenkontext vorliegt — die Ausgabe auf der veröffentlichten Seite ist voll
funktionsfähig.

## Grid-System

Das Grid basiert auf Bootstrap-5-kompatiblen Spaltenklassen mit responsiven
Overrides für Tablet und Mobilgerät. Reihen lassen sich aus vordefinierten
Layout-Vorlagen (z. B. 50/50, Drittel, 3-6-3, zentrierte Seitenspalten) oder
mit frei gewählten Spaltenbreiten aufbauen. Reihen können ineinander
verschachtelt werden (Grid in Grid). Grid-Strukturen bekannter Drittanbieter
(z. B. SubColumns, contao-bootstrap, RockSolid) werden erkannt, sodass deren
bestehende Seiten ohne Konvertierung im Editor bearbeitbar sind.

## Design-Tokens („Globals")

Unter „Globals" lassen sich seitenweite Design-Tokens (Farben, Abstände,
Schriften, Rahmenradien, Schatten) sowie globale Standard-Typografie (Body,
Überschriften, Links) zentral pflegen. Elemente referenzieren diese Werte;
eine Änderung wirkt sich sofort auf alle Elemente aus, die sie verwenden,
ohne jedes Element einzeln anpassen zu müssen. Diese Funktion ist Teil der
Pro-Lizenz.

## KI-Element-Generator

Der KI-Generator lässt Administratoren neue Content-Elemente per
Konversation, aus einem eigenen Fließtext-Briefing, aus vorhandenem Inhalt
oder aus einem hochgeladenen Screenshot erzeugen — als Einzelelement oder als
komplette Landingpage aus bewährten Abschnittsvorlagen.

Ablauf: Die Beschreibung wird an den konfigurierten KI-Anbieter gesendet; der
Entwurf wird automatisch gegen ein festes Schema geprüft und bei Bedarf in
mehreren Runden automatisch korrigiert, in einer isolierten Vorlagen-Umgebung
mit eingeschränktem Befehlsumfang serverseitig gerendert und dem
Administrator als Live-Vorschau angezeigt. **Gespeichert wird erst, wenn der
Administrator den Entwurf ausdrücklich bestätigt** — vor der Bestätigung wird
nichts in der Datenbank abgelegt. Erzeugtes CSS wird automatisch auf das
jeweilige Element beschränkt, sodass es keine anderen Seitenbereiche
beeinflussen kann.

Als KI-Anbieter ist derzeit ausschließlich **Claude (Anthropic)**
funktionsfähig; das verwendete Modell ist über die Backend-Einstellungen
konfigurierbar. Für die Bildbeschaffung von Landingpages steht wahlweise
Unsplash, Pexels oder eine KI-Bildgenerierung über OpenAI zur Verfügung —
Claude selbst erzeugt keine Bilder.

Der KI-Generator ist Teil der Pro-Lizenz und zusätzlich über eine eigene
Backend-Berechtigung steuerbar (siehe
[Berechtigungen und Zugriffskontrolle](#berechtigungen-und-zugriffskontrolle)).

## KI-Dokumentations-Chat

Im Backend steht ein Dokumentations-Chat zur Verfügung, der Fragen zu Draggo
ausschließlich auf Basis der eigenen, im Bundle enthaltenen Dokumentation
beantwortet: Die Anfrage wird zunächst gegen die vorhandenen Dokumentations-
abschnitte abgeglichen, nur die relevantesten Treffer werden dem KI-Modell als
Kontext mitgegeben, mit der ausdrücklichen Anweisung, ausschließlich daraus zu
antworten oder andernfalls zu sagen, dass keine Antwort bekannt ist. Der Chat
verwendet denselben, im Backend hinterlegten KI-Zugang wie der Element-
Generator.

## Verlauf / Wiederherstellungspunkte

Für Artikel und globale Einheiten lassen sich Wiederherstellungspunkte
anlegen: vollständige Momentaufnahmen von Inhalt und Layout, aus denen sich
ein früherer Zustand wiederherstellen lässt. Es werden die letzten 25
Wiederherstellungspunkte je Container aufbewahrt. Dies ist **kein**
Undo für einzelne Bearbeitungsschritte, sondern eine gezielt auslösbare
Sicherung ganzer Container.

## Formulare

Das Formular-Element bindet ein echtes Contao-Formular ein; Versand,
Validierung und Spamschutz laufen vollständig über Contaos eigene
Formular-Engine. Formularfelder lassen sich im Editor hinzufügen, umsortieren
und bearbeiten.

## Icons, Schriften und Bilder

- **Icons:** Eine eigene, abhängigkeitsfreie SVG-Icon-Bibliothek ist immer
  verfügbar. Zusätzlich ist Font Awesome Free als Klassennamen-Icon-Satz
  enthalten und wird lokal ausgeliefert (keine externe Anfrage). Die
  Font-Awesome-Dateien unterliegen ihrer eigenen, im Verzeichnis mitgelieferten
  Lizenz.
- **Schriften:** Eine kuratierte Auswahl gängiger Google-Schriftfamilien steht
  zur Verfügung. Es wird je Seite ausschließlich die dort tatsächlich
  verwendete Schrift geladen.
- **Bilder für den Landingpage-Generator:** Wahlweise Unsplash, Pexels oder
  eine KI-Bildgenerierung über OpenAI (jeweils mit eigenem, vom
  Administrator hinterlegtem API-Schlüssel) oder deaktiviert — dann wählt der
  Administrator Bilder manuell aus der Mediathek.

## Berechtigungen und Zugriffskontrolle

- Die Lizenzverwaltung (siehe unten) ist ausschließlich Administratoren
  vorbehalten.
- Alle übrigen Draggo-Funktionen setzen die üblichen Contao-Bearbeitungsrechte
  auf Artikel und Seiten voraus.
- Für den KI-Generator existieren zwei eigene, pro Benutzergruppe vergebbare
  Berechtigungen unter **Benutzergruppen → Draggo**:

  | Berechtigung | Bedeutung |
  |---|---|
  | KI-Element-Generator nutzen | Erlaubt die Nutzung des KI-Generators. |
  | KI-Element-Typen löschen | Erlaubt das Löschen per KI erzeugter Elementtypen. |

  Administratoren besitzen unabhängig von diesen Einstellungen stets vollen
  Zugriff.
- Sämtliche Draggo-Backend-Schnittstellen sind an Contaos Backend-Firewall
  gebunden (Anmeldung erforderlich) und gegen Cross-Site-Request-Forgery
  abgesichert.

## Lizenzmodell und Aktivierung

Draggo wird als eine einzige, kostenpflichtige Pro-Lizenz vertrieben — es
gibt keine kostenlose Stufe, keine Testversion und keinen automatischen
Rückfall auf eine kostenlose Nutzung nach Ablauf. Ist keine gültige Lizenz
hinterlegt, sind sämtliche Draggo-Funktionen (Editor, Content-Elemente,
globale Einheiten, Design-Tokens, KI-Generator) gesperrt; Contao und alle
nicht von Draggo stammenden Inhalte funktionieren davon unberührt weiter.

Die Lizenzverwaltung erfolgt an genau einer Stelle: **Contao → Einstellungen
→ Draggo Licence management**, sichtbar und bedienbar nur für Administratoren.
Ein Administrator trägt den Lizenzschlüssel ein und wählt „Prüfen und
aktivieren"; der Server kontaktiert daraufhin serverseitig über eine
authentifizierte HTTPS-Verbindung den v-t.one-Registrierungsdienst. Der
Browser selbst hat zu keinem Zeitpunkt direkten Kontakt zum
Registrierungsdienst. Mit „Lizenz aktualisieren" wird der bereits
hinterlegte Schlüssel erneut geprüft, mit „Lizenz entfernen" die Aktivierung
gelöscht. Eine tägliche, automatische Hintergrundprüfung hält den Status
aktuell.

Der Aktivierungsstatus wird im Backend in Klartext angezeigt, unter anderem:

- Lizenz aktiv — mit Paketname, freigeschalteter Domain und Gültigkeitsdatum.
- Nicht lizenziert.
- Lizenz abgelaufen.
- Die Lizenz gilt nicht für eine der eingerichteten Domains.
- Das hinterlegte Paket berechtigt nicht zur Nutzung von Draggo.
- Die Lizenz ist noch nicht gültig.
- Die gespeicherte Lizenz muss einmalig aktualisiert werden.
- Die Signaturprüfung ist auf diesem Server nicht verfügbar (PHP-Sodium
  fehlt).
- Die gespeicherte Lizenz konnte nicht verifiziert werden.

Lizenzdaten werden lokal geprüft (kryptografische Signaturprüfung) und
zusätzlich serverseitig bestätigt; sie werden außerhalb des öffentlich
erreichbaren Web-Verzeichnisses gespeichert. Der Registrierungsdienst kann
Lizenzänderungen zusätzlich aktiv an die Installation übermitteln; auch diese
Übermittlung ist authentifiziert und wird vor jeder Anwendung geprüft.

## Funktionsstatus-Tabelle

| Funktionsbereich | Status |
|---|---|
| Visueller Editor | Nur Pro |
| Content-Elemente (Draggo-Kategorie) | Nur Pro |
| Grid-System | Nur Pro |
| Globale Einheiten (Header/Footer/Sektionen) | Nur Pro |
| Design-Tokens („Globals") | Nur Pro |
| KI-Element- und Landingpage-Generator | Nur Pro |
| KI-Dokumentations-Chat | Nur Pro |
| Verlauf / Wiederherstellungspunkte | Nur Pro |
| Contao-Kernfunktionen und Nicht-Draggo-Inhalte | Nicht zutreffend (nicht Teil der Draggo-Lizenz) |

## Sicherheitsmodell

- **Zugriffskontrolle:** Alle Draggo-Backend-Schnittstellen laufen hinter
  Contaos Backend-Firewall und sind gegen Cross-Site-Request-Forgery
  abgesichert.
- **Serverseitige Rechteprüfung:** Lizenz- und Berechtigungsprüfung erfolgen
  bei jeder Aktion serverseitig, nicht nur in der Oberfläche.
- **Authentifizierte Server-Kommunikation:** Sowohl die vom Administrator
  ausgelöste Lizenzprüfung als auch vom Registrierungsdienst initiierte
  Aktualisierungen sind kryptografisch authentifiziert und werden vor jeder
  Anwendung vollständig verifiziert; nicht authentifizierte oder wiederholte
  Anfragen werden abgelehnt.
- **Private Speicherung:** Lizenzdaten liegen außerhalb des öffentlich
  erreichbaren Web-Verzeichnisses.
- **Geprüfte KI-Ausgaben:** Von der KI erzeugte Element-Definitionen werden
  gegen ein festes Schema geprüft, in einer eingeschränkten,
  serverseitig laufenden Vorlagen-Umgebung mit begrenztem Befehlsumfang
  gerendert und ihr CSS automatisch auf das jeweilige Element beschränkt.
- **Sicheres Fehlverhalten:** Eine ungültige, abgelaufene oder nicht
  verifizierbare Lizenz sperrt die betroffenen Funktionen vollständig, statt
  in einen unsicheren Standardzustand zu wechseln.

Aus Sicherheitsgründen werden an dieser Stelle bewusst keine weiteren
Implementierungsdetails der Lizenz- und Signaturprüfung veröffentlicht.

## Laufzeitverzeichnisse

| Verzeichnis | Zweck |
|---|---|
| `var/draggo/state/` | Privater Lizenzdatensatz. Muss für den Webserver-Prozess beschreibbar sein; darf nicht über den Webserver erreichbar gemacht werden. |
| `assets/draggo/` | Automatisch erzeugtes, inhaltsadressiertes CSS-Cache-Verzeichnis. Muss beschreibbar sein. |
| `files/draggo/ai/` | Von Unsplash/Pexels/OpenAI heruntergeladene oder KI-generierte Bilder. Muss beschreibbar sein. |

## Externe Kommunikation

Draggo nimmt je nach Konfiguration und Nutzung folgende ausgehende
HTTPS-Verbindungen auf:

| Ziel | Wann | Zweck |
|---|---|---|
| v-t.one-Registrierungsdienst | Lizenzaktivierung, -aktualisierung, tägliche Hintergrundprüfung | Lizenzprüfung |
| Anthropic (Claude API) | Bei Nutzung des KI-Generators oder Dokumentations-Chats | KI-Anfragen |
| Unsplash / Pexels / OpenAI | Wenn als Bildquelle konfiguriert | Bildbeschaffung für den Landingpage-Generator |
| jsDelivr-CDN | Nur im Grid-Modus `full` | Laden von Bootstrap CSS/JS |
| Konfigurierbarer Telemetrie-Endpunkt | Nur wenn Telemetrie aktiviert (standardmäßig aus) | Anonyme Struktur-Kennzahlen erzeugter Elementtypen — niemals Inhalte |

Für die KI-Funktionen gesendete Daten (Chat-Text, ggf. hochgeladene
Screenshots oder eingefügter Inhalt) werden unverändert an den konfigurierten
KI-Anbieter übermittelt; eine automatische Schwärzung findet nicht statt.
Administratoren sollten dies bei der Nutzung berücksichtigen.

## Protokollierung

Fehlgeschlagene Lizenzprüfungen und vom Registrierungsdienst initiierte
Aktualisierungen werden protokolliert (Ergebnis, HTTP-Status, Zeitpunkt).
Aus welchem konkreten Grund eine Prüfung fehlgeschlagen ist, wird bewusst
nicht im Detail protokolliert oder an den Aufrufer zurückgegeben, um keine
Angriffsfläche für gezieltes Austesten zu bieten. Zugangsdaten und
Lizenzinhalte erscheinen nicht im Klartext in regulären Logs.

## Deployment und Cache-Leerung

```bash
composer require vtinnovations/draggo
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear --env=prod
```

Das von Draggo erzeugte Seiten-CSS wird inhaltsadressiert unter
`assets/draggo/` abgelegt: identischer Inhalt ergibt dieselbe Datei, jede
Änderung erzeugt automatisch eine neue — eine manuelle Leerung dieses
Verzeichnisses ist im Normalbetrieb nicht notwendig. Nach einem Bundle-Update
genügt das reguläre Contao-Cache-Leeren.

## Tests

Die automatisierten Tests werden mit PHPUnit ausgeführt:

```bash
composer install
vendor/bin/phpunit
```

Die kontinuierliche Integration prüft das Bundle gegen PHP 8.2, 8.3 und 8.4
(`composer validate --strict`, PHP-Syntaxprüfung, vollständige PHPUnit-Suite).

## Fehlerbehebung

| Symptom | Mögliche Ursache |
|---|---|
| Draggo-Module fehlen im Backend, alle Draggo-Elemente sind blockiert | Keine aktive Pro-Lizenz — Status unter Contao → Einstellungen → Draggo Licence management prüfen. |
| KI-Generator ist für einen Benutzer nicht sichtbar | Fehlende Berechtigung „KI-Element-Generator nutzen" in dessen Benutzergruppe, oder keine aktive Lizenz. |
| KI-Generator liefert einen Fehler statt eines Entwurfs | Kein oder ungültiger Claude-API-Schlüssel unter Draggo → Einstellungen hinterlegt. |
| Bilder werden im Landingpage-Generator nicht automatisch geladen | Bildquelle unter Draggo → Einstellungen steht auf „Aus" oder der hinterlegte API-Schlüssel der gewählten Quelle ist ungültig. |
| Grid-Layout kollidiert mit dem eigenen Theme | Grid-Modus in der Bundle-Konfiguration prüfen (`grid`/`full`/`off`) und ggf. auf `off` stellen, wenn das Theme bereits Bootstrap liefert. |
| Lizenzprüfung schlägt dauerhaft fehl | `ext-sodium` auf dem Server prüfen; Domain der Installation mit der freigeschalteten Domain der Lizenz abgleichen. |

## Bekannte Einschränkungen

- Draggo hat keine kostenlose Stufe: ohne aktive Pro-Lizenz sind sämtliche
  Draggo-Funktionen gesperrt.
- Als KI-Anbieter ist derzeit ausschließlich Claude (Anthropic) funktionsfähig.
- An den KI-Generator übermittelte Texte, Inhalte und Screenshots werden ohne
  automatische Schwärzung an den externen KI-Anbieter gesendet.
- Von der KI erzeugtes JavaScript wird nicht in einer Sandbox ausgeführt (im
  Gegensatz zu KI-erzeugten Vorlagen und CSS); der KI-Generator ist daher auf
  vertrauenswürdige Backend-Benutzer auszurichten.
- „Verlauf" bietet Wiederherstellungspunkte für ganze Container, kein Undo
  einzelner Bearbeitungsschritte.
- Das Loop-Grid-Element listet aktuell Unterseiten einer gewählten Seite;
  weitere Datenquellen sind nicht enthalten.
- Das Reader-Feld-Element setzt eine installierte News- oder
  Terminverwaltung von Contao voraus und bleibt sonst leer.

## Lizenz- und Urheberrechtsinformationen

Der Quellcode dieses Bundles steht unter der **LGPL-3.0-or-later** (siehe
[LICENSE](LICENSE) und [COPYING](COPYING)). Die Nutzung der im Bundle
enthaltenen Draggo-Funktionen erfordert zusätzlich eine gültige, separat
erhältliche Pro-Lizenz von v-t.one (siehe
[Lizenzmodell und Aktivierung](#lizenzmodell-und-aktivierung)).

Copyright © V&T Innovations Team.
Website: [https://v-t.one](https://v-t.one)
