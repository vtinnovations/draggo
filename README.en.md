# Draggo

*[Deutsche Version → README.md](README.md)*

Draggo is a visual frontend page builder for Contao 5 in the style of
well-known page builders: administrators design pages by drag and drop
without touching a page's source code. On top of that, an AI-assisted
assistant can generate custom content elements and entire landing pages on
request — within fixed, validated boundaries.

The source code of this bundle is open source (LGPL-3.0-or-later). Using
Draggo's own functionality (editor, content elements, AI generator)
additionally requires an active, paid Pro licence from v-t.one — see
[Licensing Model and Activation](#licensing-model-and-activation). Contao
itself and any content not originating from Draggo keep working normally
regardless of licence status.

## Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Backend Access and Navigation](#backend-access-and-navigation)
6. [The Visual Editor](#the-visual-editor)
7. [Content Elements](#content-elements)
8. [Grid System](#grid-system)
9. [Design Tokens ("Globals")](#design-tokens-globals)
10. [AI Element Generator](#ai-element-generator)
11. [AI Documentation Chat](#ai-documentation-chat)
12. [History / Restore Points](#history--restore-points)
13. [Forms](#forms)
14. [Icons, Fonts and Images](#icons-fonts-and-images)
15. [Permissions and Access Control](#permissions-and-access-control)
16. [Licensing Model and Activation](#licensing-model-and-activation)
17. [Feature Status Table](#feature-status-table)
18. [Security Model](#security-model)
19. [Runtime Directories](#runtime-directories)
20. [External Communication](#external-communication)
21. [Logging](#logging)
22. [Deployment and Cache Clearing](#deployment-and-cache-clearing)
23. [Tests](#tests)
24. [Troubleshooting](#troubleshooting)
25. [Known Limitations](#known-limitations)
26. [Licence and Copyright Information](#licence-and-copyright-information)

## Overview

Draggo integrates fully into Contao's own content-element architecture: every
Draggo element is a regular `tl_content` element rendered through Contao's
native rendering pipeline. There is no shadow data model and no separate
output engine. The visual editor reads and writes the real Contao DCA fields
directly — non-Draggo elements can be edited in it as well.

Core building blocks:

- A Bootstrap-5-compatible, nestable grid system.
- Over 55 ready-made content elements (layout, typography, media, interactive
  scroll effects, navigation, forms, and more).
- A unified style system (colour, typography, spacing, shadows, visibility)
  for every element, compiled into one cached stylesheet per page.
- Globally reusable units (headers, footers, sections).
- Site-wide design tokens ("Globals") for colours, fonts and spacing.
- An AI-assisted element and landing-page generator built on Claude
  (Anthropic).
- An optional, retrieval-grounded documentation chat in the backend.

## System Requirements

| Component | Requirement |
|---|---|
| PHP | ^8.2 (tested against 8.2, 8.3, 8.4) |
| PHP extensions | `ext-json`, `ext-sodium` |
| Contao | ^5.3 |
| Doctrine DBAL | ^3.6 or ^4.0 |
| Symfony components | Console, HttpClient, HttpFoundation (^6.4 or ^7.0) |
| Twig | ^3.0 |

`ext-sodium` is required for the cryptographic verification of licence data;
without this extension, Draggo stays locked (see
[Licensing Model and Activation](#licensing-model-and-activation)). The AI
features and optional image sources require outbound HTTPS access from the
server (see [External Communication](#external-communication)).

## Installation

```bash
composer require vtinnovations/draggo
```

The bundle registers itself automatically via the Contao Manager
(`Vtinnovations\Draggo\ContaoManager\Plugin`) and wires up its routes.
Afterwards, update the database, either through the Contao Manager or
directly:

```bash
vendor/bin/contao-console contao:migrate
```

`contao:migrate` creates all required tables and automatically runs all of
the bundle's data migrations (see [Known Limitations](#known-limitations) for
notes on upgrading existing installations).

## Configuration

The bundle configuration is optionally set via `config/packages/draggo.yaml`
and, where present, is overridden by the backend settings (see below).
Available options with their defaults:

```yaml
draggo:
    ai:
        provider: claude          # currently only "claude" is functional
        model: claude-opus-4-8
        api_key: ''                # can also be set in the backend
        max_tokens: 4096
        max_rounds: 8
        rate_limit_per_hour: 30
        cost_cap_deci_cents: 5000
        telemetry: false            # anonymous structure telemetry, off by default
        telemetry_url: ''
    editor:
        max_columns: 6              # max. number of columns in the grid editor
    bootstrap:
        mode: grid                  # full | grid | off
        css_url: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
        js_url: 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
```

**Grid mode (`bootstrap.mode`):**

| Value | Behaviour |
|---|---|
| `grid` (default) | The bundle's own local grid CSS, no JavaScript, no external request. |
| `full` | Loads Bootstrap 5.3.3 (CSS + JS) from the configured source (default: jsDelivr CDN). |
| `off` | The theme already provides Bootstrap; Draggo loads nothing additional. |

The CSS classes produced in the frontend are identical across all three
modes, so switching modes requires no template changes.

Most of the day-to-day configuration — AI API key, model, image source,
telemetry — is instead maintained in the backend under **Draggo → Settings**
(table `tl_draggo_settings`, a single record, effective immediately without
clearing the cache):

| Field | Meaning |
|---|---|
| Claude API key | Your own Anthropic/Claude API key for the AI generator. |
| Model | Overrides `ai.model`; empty means the default value. |
| Max. follow-up questions | Maximum number of question rounds the assistant asks. |
| Disable self-review | Turns off the automatic second pass that reviews every AI draft (saves one extra API call). |
| Enable telemetry | Sends only anonymous structural metrics of generated elements (type, field count) — never content, templates or page data. |
| Image source | Off / Unsplash / Pexels / OpenAI (image generation) — see [Icons, Fonts and Images](#icons-fonts-and-images). |
| Unsplash access key / Pexels API key / OpenAI API key | Your own credentials for the selected image source. |

## Backend Access and Navigation

Draggo appears in the Contao backend as its own module group **Draggo** with
three modules, in this order:

1. **Global units** — manage headers, footers and global sections.
2. **AI element types** — manage your AI-generated custom content elements
   (overview, publishing, deletion).
3. **Settings** — AI API key, model and telemetry.

The page structure additionally shows an "Edit with Draggo" action on every
page, plus a floating editor launch button in the frontend for logged-in
backend users.

## The Visual Editor

The editor works page by page: one session edits a complete Contao page with
all of its articles and content elements, or alternatively a global unit.
Elements are added, moved, duplicated and deleted by drag and drop; every
element gets its own Style tab with the options described in
[Content Elements](#content-elements) and
[Design Tokens](#design-tokens-globals).

The editor reads the available element types directly from Contao's own
registry, so it never offers an element that does not actually exist. Native
Draggo elements ship with curated inline fields; for every other content
element (including third-party ones), the editable fields are derived
automatically from that element's own Contao DCA, so foreign elements can be
edited in the same editor as well.

## Content Elements

All Draggo content elements live in the backend category **Draggo** and
require an active Pro licence (see
[Licensing Model and Activation](#licensing-model-and-activation)). Contao's
own and other content elements are not affected.

**Grid & Structure**

| Element | Description |
|---|---|
| Grid: row start | Opens a Bootstrap grid row with columns. |
| Grid: column | Separator to the next column of the row. |
| Grid: row end | Closes the grid row. |
| Global block | Embeds a reusable "section" unit — centrally editable (live link). |
| AI element | An AI-generated custom content element (see [AI Element Generator](#ai-element-generator)). |

**Text & Typography**

| Element | Description |
|---|---|
| Divider | Horizontal line. |
| Spacer | Vertical empty space. |
| Button | Button with a link. |
| Icon | Single SVG icon from the library. |
| Code block | Formatted code with language and copy button. |
| Quote | Block quote with optional author. |
| Text highlight | Large statement that fills in word by word as you scroll. |
| Text mask | Large headline with an image visible through it. |
| Animated headline | Headline with a rotating/typing word. |

**Media**

| Element | Description |
|---|---|
| Gallery | Image gallery with grid/masonry/tiles, hover effects and lightbox. |
| Image carousel | Slider of multiple images. |
| Video playlist | Player + list; YouTube/Vimeo/MP4, loads only on click (privacy-friendly). |
| Hotspot image | Image with interactive points and tooltips. |
| Before/After | Two images with a slider to compare. |
| Logo | Image linked to the home page or a custom URL. |

**Boxes & Content Blocks**

| Element | Description |
|---|---|
| Icon box | Icon + title + text. |
| Image box | Image + title + text. |
| Flip box | Card that flips to its back on hover. |
| Call to action | Title + text + button as a call to action. |
| Alert box | Info/success/warning/error box, optionally dismissible. |
| Accordion | Collapsible title/content entries. |
| Tabs | Tabs with title/content. |
| Process steps | Numbered steps with title and text. |
| Price list | Menu-style list: name … price. |
| Price table | Price card with features and button. |
| Icon list | List with an icon as the bullet. |
| Social icons | Linked icons to social networks. |

**Interactive / Scrollytelling**

| Element | Description |
|---|---|
| Counter | Animated number, counts up on scroll. |
| Progress bar | Skill/progress bar, animated on scroll. |
| Countdown | Counts down to a target date. |
| Parallax layers | Layers move at different speeds as you scroll. |
| Curtain cover | Full-bleed panels; the next slides over the previous. |
| Horizontal pin | Section pins and scrolls sideways, panels reveal one by one. |
| Stack reveal | Overlapping cards that slide over each other as you scroll. |
| Sticky-split story | Pinned image that swaps per text step as you scroll. |
| Scroll zoom | Image zooms to full as you scroll into view. |
| Reveal grid | Cards rise in staggered as you scroll. |
| Tilt 3D cards | Cards tilt toward the cursor and reveal on scroll. |
| Pinned timeline | Progress line fills and steps activate as you scroll. |
| Scroll progress | Reading-progress bar fixed at top/bottom. |
| SVG path draw | A line/shape draws itself as you scroll. |

**Navigation & Site Structure**

| Element | Description |
|---|---|
| Navigation | Page-tree navigation with a design preset (horizontal / side nav / hamburger). |
| Breadcrumb | Path from the home page to the current page. |
| Sitemap | Nested list of published pages. |
| Table of contents | Jump links built from the page headings. |
| Search | Search field for the Contao search page. |

**Data & Dynamic**

| Element | Description |
|---|---|
| Loop grid | Subpages of a page as cards (data-driven). |
| Page title | Title of the current page (dynamic). |
| Reader field | Field of the current news/event detail page (title/teaser/image/date/author), provided the corresponding Contao extension is installed. |
| Insert tag | Output any Contao insert tag at this position. |
| Google Maps | Map for an address, privacy-friendly (click to load). |
| Open-now status | Live "Open now / Closed" badge — timezone-safe, with schema.org opening hours. |
| Light/Dark toggle | Visitors switch between light and dark mode (uses the dark values of the colour tokens). |
| Share buttons | Share the current page (Facebook/LinkedIn/email/link). |

**Forms**

| Element | Description |
|---|---|
| Form | Visually built form — delivery, validation and spam protection are handled by Contao's own form engine. |

For a few elements (form, reader field, page title, search), the editor
canvas intentionally shows a static preview because no real page context is
available there — output on the published page is fully functional.

## Grid System

The grid is based on Bootstrap-5-compatible column classes with responsive
overrides for tablet and mobile. Rows can be built from predefined layout
presets (e.g. 50/50, thirds, 3-6-3, centered side columns) or with freely
chosen column widths. Rows can be nested inside each other (grid within
grid). Grid structures from well-known third-party extensions (e.g.
SubColumns, contao-bootstrap, RockSolid) are recognised, so their existing
pages are editable without conversion.

## Design Tokens ("Globals")

Under "Globals", site-wide design tokens (colours, spacing, fonts, corner
radii, shadows) and global default typography (body, headings, links) can be
maintained centrally. Elements reference these values; a change takes effect
immediately across every element using it, without editing each element
individually. This feature is part of the Pro licence.

## AI Element Generator

The AI generator lets administrators create new content elements through
conversation, from a free-text brief, from existing content, or from an
uploaded screenshot — as a single element or as a complete landing page built
from proven section templates.

Flow: the description is sent to the configured AI provider; the draft is
automatically validated against a fixed schema and, if necessary,
auto-corrected over several rounds, rendered server-side inside an isolated
template environment with a restricted command set, and shown to the
administrator as a live preview. **Nothing is saved until the administrator
explicitly confirms the draft** — nothing is written to the database before
that confirmation. Generated CSS is automatically scoped to its own element
so it cannot affect any other part of the page.

Currently, **Claude (Anthropic)** is the only functional AI provider; the
model used is configurable in the backend settings. For sourcing landing-page
images, Unsplash, Pexels or AI image generation via OpenAI is available —
Claude itself does not generate images.

The AI generator is part of the Pro licence and is additionally controllable
through a dedicated backend permission (see
[Permissions and Access Control](#permissions-and-access-control)).

## AI Documentation Chat

The backend offers a documentation chat that answers questions about Draggo
exclusively from the bundle's own included documentation: the question is
first matched against the existing documentation sections, only the most
relevant matches are passed to the AI model as context, with an explicit
instruction to answer only from that context or otherwise state that no
answer is known. The chat uses the same AI credentials configured in the
backend as the element generator.

## History / Restore Points

Restore points can be created for articles and global units: complete
snapshots of content and layout that a previous state can be restored from.
The last 25 restore points per container are kept. This is **not** an undo
for individual editing steps, but a deliberately triggered backup of an
entire container.

## Forms

The form element embeds a real Contao form; delivery, validation and spam
protection run entirely through Contao's own form engine. Form fields can be
added, reordered and edited in the editor.

## Icons, Fonts and Images

- **Icons:** A dedicated, dependency-free SVG icon library is always
  available. Font Awesome Free is additionally included as a class-name icon
  set and is served locally (no external request). The Font Awesome files
  are subject to their own licence, shipped alongside them in the same
  directory.
- **Fonts:** A curated selection of popular Google font families is
  available. Only the font actually used on a given page is loaded for that
  page.
- **Images for the landing-page generator:** Unsplash, Pexels or AI image
  generation via OpenAI (each with its own API key set by the administrator),
  or disabled — in which case the administrator picks images manually from
  the media library.

## Permissions and Access Control

- Licence management (see below) is reserved for administrators only.
- All other Draggo functionality requires the usual Contao editing rights on
  articles and pages.
- The AI generator has two dedicated permissions, assignable per user group
  under **User groups → Draggo**:

  | Permission | Meaning |
  |---|---|
  | Use the AI element generator | Allows using the AI generator. |
  | Delete AI element types | Allows deleting AI-generated element types. |

  Administrators always have full access regardless of these settings.
- All Draggo backend interfaces are bound to Contao's backend firewall
  (login required) and protected against cross-site request forgery.

## Licensing Model and Activation

Draggo is sold as a single, paid Pro licence — there is no free tier, no
trial, and no automatic fallback to free usage after expiry. Without a valid
licence in place, all Draggo functionality (editor, content elements, global
units, design tokens, AI generator) is locked; Contao and any content not
originating from Draggo continue to work unaffected.

Licence management happens in exactly one place: **Contao → Settings →
Draggo Licence management**, visible and usable only to administrators. An
administrator enters the licence key and selects "Check and activate"; the
server then contacts the v-t.one registration service server-side over an
authenticated HTTPS connection. The browser itself never contacts the
registration service directly. "Update licence" re-checks the already-stored
key, and "Remove licence" deletes the activation. A daily automatic
background check keeps the status current.

The activation status is shown in the backend in plain language, including:

- Licence active — with package name, activated domain and validity date.
- Not licensed.
- Licence expired.
- The licence does not apply to any of the configured domains.
- The stored package does not entitle use of Draggo.
- The licence is not yet valid.
- The stored licence needs a one-time refresh.
- Signature verification is unavailable on this server (PHP Sodium is
  missing).
- The stored licence could not be verified.

Licence data is checked locally (cryptographic signature verification) and
additionally confirmed server-side; it is stored outside the publicly
reachable web directory. The registration service can also actively push
licence changes to the installation; that transmission is likewise
authenticated and fully verified before anything is applied.

## Feature Status Table

| Feature area | Status |
|---|---|
| Visual editor | Pro only |
| Content elements (Draggo category) | Pro only |
| Grid system | Pro only |
| Global units (headers/footers/sections) | Pro only |
| Design tokens ("Globals") | Pro only |
| AI element and landing-page generator | Pro only |
| AI documentation chat | Pro only |
| History / restore points | Pro only |
| Contao core functionality and non-Draggo content | Not applicable (not part of the Draggo licence) |

## Security Model

- **Access control:** every Draggo backend interface runs behind Contao's
  backend firewall and is protected against cross-site request forgery.
- **Server-side permission checks:** licence and permission checks are
  enforced server-side on every action, not only in the interface.
- **Authenticated server communication:** both administrator-triggered
  licence checks and updates initiated by the registration service are
  cryptographically authenticated and fully verified before anything is
  applied; unauthenticated or replayed requests are rejected.
- **Private storage:** licence data lives outside the publicly reachable web
  directory.
- **Validated AI output:** AI-generated element definitions are validated
  against a fixed schema, rendered inside a restricted, server-side template
  environment with a limited command set, and have their CSS automatically
  scoped to their own element.
- **Safe failure behaviour:** an invalid, expired or unverifiable licence
  fully locks the affected functionality instead of falling back to an
  insecure default state.

For security reasons, further implementation details of the licence and
signature verification are deliberately not published here.

## Runtime Directories

| Directory | Purpose |
|---|---|
| `var/draggo/state/` | Private licence record. Must be writable by the web server process; must never be made reachable through the web server. |
| `assets/draggo/` | Automatically generated, content-addressed CSS cache directory. Must be writable. |
| `files/draggo/ai/` | Images downloaded from Unsplash/Pexels/OpenAI or AI-generated. Must be writable. |

## External Communication

Depending on configuration and usage, Draggo makes the following outbound
HTTPS connections:

| Destination | When | Purpose |
|---|---|---|
| v-t.one registration service | Licence activation, refresh, daily background check | Licence verification |
| Anthropic (Claude API) | When using the AI generator or documentation chat | AI requests |
| Unsplash / Pexels / OpenAI | When configured as the image source | Image sourcing for the landing-page generator |
| jsDelivr CDN | Only in grid mode `full` | Loading Bootstrap CSS/JS |
| Configurable telemetry endpoint | Only if telemetry is enabled (off by default) | Anonymous structural metrics of generated element types — never content |

Data sent to the AI features (chat text, any uploaded screenshots or pasted
content) is transmitted to the configured AI provider unmodified; no
automatic redaction takes place. Administrators should take this into account
when using these features.

## Logging

Failed licence checks and updates initiated by the registration service are
logged (result, HTTP status, timestamp). The specific reason a check failed
is deliberately not logged in detail or returned to the caller, to avoid
providing an attack surface for targeted probing. Credentials and licence
contents do not appear in plain text in regular logs.

## Deployment and Cache Clearing

```bash
composer require vtinnovations/draggo
vendor/bin/contao-console contao:migrate
vendor/bin/contao-console cache:clear --env=prod
```

The page CSS generated by Draggo is stored content-addressed under
`assets/draggo/`: identical content produces the same file, and any change
automatically produces a new one — manually clearing this directory is not
necessary in normal operation. After a bundle update, the regular Contao
cache clear is sufficient.

## Tests

The automated tests run with PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

Continuous integration checks the bundle against PHP 8.2, 8.3 and 8.4
(`composer validate --strict`, PHP syntax check, the full PHPUnit suite).

## Troubleshooting

| Symptom | Possible cause |
|---|---|
| Draggo modules are missing from the backend, all Draggo elements are blocked | No active Pro licence — check the status under Contao → Settings → Draggo Licence management. |
| The AI generator is not visible to a user | Missing "Use the AI element generator" permission on their user group, or no active licence. |
| The AI generator returns an error instead of a draft | No or an invalid Claude API key set under Draggo → Settings. |
| Images are not fetched automatically in the landing-page generator | The image source under Draggo → Settings is set to "Off", or the stored API key for the selected source is invalid. |
| The grid layout conflicts with your own theme | Check the grid mode in the bundle configuration (`grid`/`full`/`off`) and set it to `off` if the theme already provides Bootstrap. |
| Licence verification keeps failing | Check that `ext-sodium` is available on the server; compare the installation's domain against the licence's activated domain. |

## Known Limitations

- Draggo has no free tier: without an active Pro licence, all Draggo
  functionality is locked.
- Currently, Claude (Anthropic) is the only functional AI provider.
- Text, content and screenshots sent to the AI generator are transmitted to
  the external AI provider without automatic redaction.
- AI-generated JavaScript does not run in a sandbox (unlike AI-generated
  templates and CSS); the AI generator should therefore be limited to
  trusted backend users.
- "History" provides restore points for entire containers, not an undo for
  individual editing steps.
- The loop grid element currently lists subpages of a chosen page; no
  additional data sources are included.
- The reader field element requires an installed Contao news or event
  extension and otherwise stays empty.

## Licence and Copyright Information

The source code of this bundle is licensed under the **LGPL-3.0-or-later**
(see [LICENSE](LICENSE) and [COPYING](COPYING)). Using the Draggo
functionality contained in this bundle additionally requires a valid,
separately obtained Pro licence from v-t.one (see
[Licensing Model and Activation](#licensing-model-and-activation)).

Copyright © V&T Innovations Team.
Website: [https://v-t.one](https://v-t.one)
