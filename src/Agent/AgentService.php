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

namespace Vtinnovations\Draggo\Agent;

use Vtinnovations\Draggo\Ai\AiClientInterface;
use Vtinnovations\Draggo\Block\BlockSpec;
use Vtinnovations\Draggo\Block\BlockSpecValidator;
use Vtinnovations\Draggo\Block\CssScoper;
use Vtinnovations\Draggo\Block\TwigSandbox;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Twig\Markup;

/**
 * Drives the conversational element generator. The model LEADS: it asks focused
 * questions (plain text) with recommendations until it has enough, then calls
 * the single emit_blockspec tool. History is kept as plain {role,content}
 * strings on the client — no tool_result plumbing — so it stays stateless and
 * robust. emit_blockspec output is validated + sandbox-rendered into a preview;
 * nothing is persisted until the user commits.
 */
final class AgentService
{
    public function __construct(
        private readonly AiClientInterface $ai,
        private readonly BlockSpecValidator $validator,
        private readonly TwigSandbox $sandbox,
        private readonly \Vtinnovations\Draggo\Settings\AiSettings $settings,
    ) {
    }

    public function isReady(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * One agent turn. $history = list<array{role:string,content:string}>.
     * $editSpec = the existing BlockSpec being revised (edit mode), or null.
     *
     * @param array<string,mixed>|null $editSpec
     * @return array<string,mixed>
     */
    public function turn(array $history, ?array $editSpec = null, string $lang = 'de', array $images = []): array
    {
        if (!$this->isReady()) {
            throw new InvalidInputException('KI ist nicht konfiguriert (draggo.ai.api_key fehlt).');
        }
        // Soft cap: count user turns so a runaway interview can't loop forever.
        $userTurns = \count(array_filter($history, static fn ($m): bool => ($m['role'] ?? '') === 'user'));
        $force = $userTurns >= $this->settings->maxRounds();

        $messages = $this->toMessages($history, $force, $editSpec, $images);
        $result = $this->ai->chat($this->system($lang), $messages, [$this->emitTool()]);

        $call = $result->firstToolCall('emit_blockspec');
        if ($call !== null) {
            // Auto-repair + lint + (optional) self-review, all server-side, so
            // the FIRST draft shown to the user already works.
            $spec = $this->finalizeSpec($call['input'], $lang);
            if ($spec === null) {
                return [
                    'status'  => 'error',
                    'message' => $this->lastError ?: 'Entwurf ließ sich nicht fertigstellen.',
                    'text'    => $result->text !== '' ? $result->text : ($lang === 'en' ? 'I could not finish the draft.' : 'Ich konnte den Entwurf nicht fertigstellen.'),
                ];
            }

            return [
                'status'  => 'spec',
                'text'    => $result->text !== '' ? $result->text : ($lang === 'en' ? 'Here is my draft — check the preview.' : 'Hier ist mein Entwurf — schau dir die Vorschau an.'),
                'spec'    => $spec->toArray(),
                'preview' => $this->preview($spec),
            ];
        }

        return [
            'status' => 'message',
            'text'   => $result->text !== '' ? $result->text : ($lang === 'en' ? 'Tell me more about the element you want.' : 'Erzähl mir mehr über das gewünschte Element.'),
        ];
    }

    /**
     * Plan a whole page from a free-text brief: the model picks an ordered
     * sequence of proven section templates. Returns validated template keys
     * (reliable — every key maps to a known-good container). Empty on failure.
     *
     * @return list<string>
     */
    public function planPage(string $brief, string $lang): array
    {
        if (!$this->isReady()) {
            throw new InvalidInputException('KI ist nicht konfiguriert.');
        }
        $catalog = \Vtinnovations\Draggo\Template\SectionTemplates::catalog();
        $lines = '';
        foreach ($catalog as $c) {
            $lines .= "- {$c['key']}: {$c['title']} — {$c['desc']}\n";
        }
        $keysEnum = array_map(static fn (array $c): string => $c['key'], $catalog);

        $langLine = $lang === 'en' ? 'Reply in English.' : 'Antworte auf Deutsch.';
        $system = "Du bist ein Web-Konzepter. Aus dem Brief des Nutzers planst du eine komplette, sinnvoll geordnete Landingpage NUR aus diesen Bausteinen:\n{$lines}\n"
            . "Regeln: Beginne i. d. R. mit einem Hero, ende mit Call-to-Action/Kontakt. Nutze 4–8 Sektionen, keine sinnlosen Doppelungen, passend zum Brief. Rufe NUR das Tool emit_page mit der geordneten Sektionsliste auf. {$langLine}";

        $tool = [
            'name'         => 'emit_page',
            'description'  => 'Gib die geordnete Liste der Seitensektionen zurück.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => ['sections'],
                'properties' => [
                    'sections' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['template'],
                            'properties' => [
                                'template' => ['type' => 'string', 'enum' => $keysEnum],
                                'note'     => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $r = $this->ai->chat($system, [['role' => 'user', 'content' => [['type' => 'text', 'text' => $brief]]]], [$tool]);
        } catch (\Throwable) {
            return [];
        }
        $call = $r->firstToolCall('emit_page');
        if ($call === null) {
            return [];
        }
        $keys = [];
        foreach (($call['input']['sections'] ?? []) as $s) {
            $k = \is_array($s) ? (string) ($s['template'] ?? '') : (string) $s;
            if (\Vtinnovations\Draggo\Template\SectionTemplates::exists($k)) {
                $keys[] = $k;
            }
        }

        return $keys;
    }

    /**
     * Plan AND write a page from a brief: the model picks page-type-appropriate
     * sections AND fills each one's content slots with real, on-topic copy (no
     * placeholders). Returns a list of {key, content} where content overlays the
     * section template (SectionTemplates::items). Empty list on failure.
     *
     * @return list<array{key:string,content:array<string,mixed>}>
     */
    public function composePage(string $brief, string $lang): array
    {
        if (!$this->isReady()) {
            throw new InvalidInputException('KI ist nicht konfiguriert.');
        }
        $langLine = $lang === 'en' ? 'Write ALL copy in English.' : 'Schreibe ALLE Texte auf Deutsch.';
        $system = "Du bist Web-Konzepter UND Werbetexter. Aus dem Brief planst du eine zum SEITENTYP passende Landingpage UND schreibst echte, konkrete Texte (NIEMALS Platzhalter, kein Lorem ipsum, keine generischen Floskeln). Bausteine + ihre Inhalts-Slots:\n" . $this->sectionCatalogLines() . "\n"
            . "Regeln:\n"
            . "- 4–8 Sektionen, die WIRKLICH zum Brief & Seitentyp passen — eine Produktseite, eine Über-uns-Seite und eine Dienstleistungsseite sehen UNTERSCHIEDLICH aus. Variiere die Auswahl, nimm nicht stur immer dieselbe Abfolge.\n"
            . "- Beginne i. d. R. mit hero, ende mit cta oder contact.\n"
            . "- Fülle für JEDE Sektion das content-Objekt mit den passenden Slots aus echten, themenspezifischen Texten (konkrete Überschriften, echte Sätze, echte Feature-/FAQ-/Preis-/Schritt-Einträge). `items` ist eine Liste (Objekte oder Strings je nach Slot-Beschreibung).\n"
            . "- Texte knapp, konkret, verkaufsstark.\n"
            . "- Für bildtragende Sektionen (hero, text_image, image_text, feature_img) gib zusätzlich `image_query` an: einen KURZEN ENGLISCHEN Foto-Suchbegriff, der zum Thema passt (z. B. \"modern bakery interior\", \"law office team meeting\"). Wird für automatische Bilder genutzt.\n"
            . "Rufe NUR das Tool emit_page mit der geordneten Sektionsliste inkl. content auf. {$langLine}";

        return $this->emitPageSections($system, $brief);
    }

    /**
     * Build a page from the user's OWN declared content: the model maps the
     * provided, labelled content onto fitting sections and fills the slots with
     * the USER'S words (it may lightly tidy/structure, but must NOT invent facts
     * or products). Slots without supplied content stay empty.
     *
     * @return list<array{key:string,content:array<string,mixed>}>
     */
    public function composeFromContent(string $content, string $lang): array
    {
        if (!$this->isReady()) {
            throw new InvalidInputException('KI ist nicht konfiguriert.');
        }
        $langLine = $lang === 'en' ? 'Keep the copy in the language the user wrote it in.' : 'Behalte die Sprache, in der der Nutzer geschrieben hat.';
        $system = "Du bist Web-Konzepter. Der Nutzer liefert SEINE EIGENEN Inhalte, oft mit Beschriftung (z. B. „Überschrift:“, „Über uns:“, „Leistungen:“, „Kontakt:“). Ordne GENAU DIESE Inhalte einer passenden Landingpage zu. Bausteine + ihre Inhalts-Slots:\n" . $this->sectionCatalogLines() . "\n"
            . "Regeln:\n"
            . "- Verwende die WORTE DES NUTZERS. Du darfst leicht straffen, gliedern und für Slots passend zuschneiden, aber ERFINDE KEINE Fakten, Produkte, Preise, Zitate oder Kontaktdaten, die nicht geliefert wurden.\n"
            . "- Wähle nur Sektionen, für die der Nutzer tatsächlich Inhalt geliefert hat. Bring sie in eine sinnvolle Reihenfolge (i. d. R. hero zuerst).\n"
            . "- Ordne jeden gelieferten Inhalt dem am besten passenden Slot zu (Aufzählungen → list/items, Frage+Antwort → faq, Titel+Text-Paare → features/steps usw.). Lass Slots leer, für die nichts geliefert wurde.\n"
            . "- Für bildtragende Sektionen (hero, text_image, image_text, feature_img) darfst du `image_query` setzen: einen KURZEN ENGLISCHEN Foto-Suchbegriff, der zum Thema des Nutzers passt (das ist KEIN erfundener Fakt, nur ein passendes Stockfoto).\n"
            . "Rufe NUR das Tool emit_page mit der geordneten Sektionsliste inkl. content auf. {$langLine}";

        return $this->emitPageSections($system, $content);
    }

    /** Catalogue + content-slot lines shared by both page composers. */
    private function sectionCatalogLines(): string
    {
        $catalog = \Vtinnovations\Draggo\Template\SectionTemplates::catalog();
        $schema = \Vtinnovations\Draggo\Template\SectionTemplates::contentSchema();
        $lines = '';
        foreach ($catalog as $c) {
            $slots = $schema[$c['key']] ?? '(nur Bild/Medien — kein Text)';
            $lines .= "- {$c['key']}: {$c['title']} — {$c['desc']}\n    content-Slots: {$slots}\n";
        }

        return $lines;
    }

    /**
     * Run one emit_page tool call with the given system prompt + user text and
     * return the validated [{key, content}] section list. Empty on any failure.
     *
     * @return list<array{key:string,content:array<string,mixed>}>
     */
    private function emitPageSections(string $system, string $userText): array
    {
        $keysEnum = array_map(static fn (array $c): string => $c['key'], \Vtinnovations\Draggo\Template\SectionTemplates::catalog());
        $tool = [
            'name'         => 'emit_page',
            'description'  => 'Gib die geordnete Liste der Seitensektionen MIT ausgeschriebenen Inhalten (content) zurück.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => ['sections'],
                'properties' => [
                    'sections' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['template'],
                            'properties' => [
                                'template' => ['type' => 'string', 'enum' => $keysEnum],
                                'content'  => ['type' => 'object', 'description' => 'Slot-Werte gemäß content-Slots des Templates. Wiederholende Einträge als `items`-Array.'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $r = $this->ai->chat($system, [['role' => 'user', 'content' => [['type' => 'text', 'text' => $userText]]]], [$tool]);
        } catch (\Throwable) {
            return [];
        }
        $call = $r->firstToolCall('emit_page');
        if ($call === null) {
            return [];
        }
        $out = [];
        foreach (($call['input']['sections'] ?? []) as $s) {
            if (!\is_array($s)) {
                continue;
            }
            $k = (string) ($s['template'] ?? '');
            if (!\Vtinnovations\Draggo\Template\SectionTemplates::exists($k)) {
                continue;
            }
            $out[] = ['key' => $k, 'content' => (isset($s['content']) && \is_array($s['content'])) ? $s['content'] : []];
        }

        return $out;
    }

    private ?string $lastError = null;

    /**
     * Validate → lint → auto-repair (≤3 rounds) → optional self-review. Returns
     * a working BlockSpec or null (with $this->lastError set).
     *
     * @param array<string,mixed> $raw
     */
    private function finalizeSpec(array $raw, string $lang): ?BlockSpec
    {
        $this->lastError = null;
        $spec = null;

        for ($attempt = 0; $attempt < 4; ++$attempt) {
            try {
                $candidate = $this->validator->validate($raw);
            } catch (InvalidInputException $e) {
                $this->lastError = $e->getMessage();
                $fixed = $this->askFix($raw, [$e->getMessage()], $lang);
                if ($fixed === null) {
                    return null;
                }
                $raw = $fixed;
                continue;
            }

            $issues = $this->lint($candidate);
            if ($issues === []) {
                $spec = $candidate;
                break;
            }
            $this->lastError = implode(' · ', $issues);
            $fixed = $this->askFix($candidate->toArray(), $issues, $lang);
            if ($fixed === null) {
                // Can't improve further — keep the last candidate (it at least
                // validated) rather than failing outright.
                $spec = $candidate;
                break;
            }
            $raw = $fixed;
        }

        if ($spec === null) {
            return null;
        }

        // Self-review pass (default on; toggle in Draggo settings).
        if ($this->settings->selfReview()) {
            $reviewed = $this->askReview($spec->toArray(), $lang);
            if ($reviewed !== null) {
                try {
                    $imp = $this->validator->validate($reviewed);
                    if ($this->lint($imp) === []) {
                        $spec = $imp; // only accept a clean improvement
                    }
                } catch (InvalidInputException) {
                    // keep the working spec
                }
            }
        }

        return $spec;
    }

    /**
     * Deterministic checks (no API call): every declared field used in the
     * template, no undeclared field referenced, template renders non-empty.
     *
     * @return list<string>
     */
    private function lint(BlockSpec $spec): array
    {
        $issues = [];
        preg_match_all('/fields\.([a-z][a-z0-9_]*)/i', $spec->template, $m);
        $used = array_values(array_unique($m[1] ?? []));
        $declared = $spec->fieldNames();

        foreach ($used as $u) {
            if (!\in_array($u, $declared, true)) {
                $issues[] = sprintf('Feld "%s" wird im Template benutzt, ist aber nicht deklariert.', $u);
            }
        }
        foreach ($declared as $d) {
            if (!\in_array($d, $used, true)) {
                $issues[] = sprintf('Feld "%s" ist deklariert, wird aber im Template nicht benutzt (entfernen oder per {{ fields.%s }} bzw. {%% for x in fields.%s %%} verwenden).', $d, $d, $d);
            }
        }
        try {
            $html = $this->sandbox->render($spec->template, $this->sampleFor($spec));
            if (trim($html) === '') {
                $issues[] = 'Template rendert leeren Inhalt.';
            }
        } catch (\Throwable $e) {
            $issues[] = 'Template-Render-Fehler: ' . $e->getMessage();
        }

        return $issues;
    }

    /**
     * One focused repair call: hand the model the broken spec + concrete issues,
     * get a corrected emit_blockspec back. Returns the raw input or null.
     *
     * @param array<string,mixed> $specArray
     * @param list<string>        $issues
     * @return array<string,mixed>|null
     */
    private function askFix(array $specArray, array $issues, string $lang): ?array
    {
        $json = (string) json_encode($specArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $list = "- " . implode("\n- ", $issues);
        $prompt = "Diese BlockSpec hat Probleme und muss korrigiert werden:\n{$json}\n\nProbleme:\n{$list}\n\n"
            . "Gib die KORRIGIERTE BlockSpec über emit_blockspec zurück. Behalte den type-Schlüssel. Stelle sicher: jedes deklarierte Feld wird im Template benutzt (Bilder/Listen per {% for %}), keine undeklarierten Felder, und das Template rendert sichtbaren Inhalt. Nur emit_blockspec aufrufen, keine Rückfragen.";

        return $this->emitOnce($prompt, $lang);
    }

    /**
     * One self-review call to polish a working spec. Returns raw input or null.
     *
     * @param array<string,mixed> $specArray
     * @return array<string,mixed>|null
     */
    private function askReview(array $specArray, string $lang): ?array
    {
        $json = (string) json_encode($specArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $prompt = "Prüfe diese funktionierende BlockSpec kritisch auf Render-Bugs, leere/ungenutzte Felder, fehlende Responsiveness und schwache Defaults — und verbessere sie:\n{$json}\n\n"
            . "Gib die VERBESSERTE BlockSpec über emit_blockspec zurück (type-Schlüssel behalten). Wenn nichts zu verbessern ist, gib sie unverändert zurück. Nur emit_blockspec aufrufen.";

        return $this->emitOnce($prompt, $lang);
    }

    /**
     * Single stateless model call expected to return one emit_blockspec.
     *
     * @return array<string,mixed>|null
     */
    private function emitOnce(string $userPrompt, string $lang): ?array
    {
        try {
            $r = $this->ai->chat($this->system($lang), [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => $userPrompt]]],
            ], [$this->emitTool()]);
        } catch (\Throwable) {
            return null;
        }
        $c = $r->firstToolCall('emit_blockspec');

        return $c !== null ? $c['input'] : null;
    }

    /**
     * Sample field values for sandbox rendering (preview + lint).
     *
     * @return array<string,mixed>
     */
    private function sampleFor(BlockSpec $spec): array
    {
        $sample = [];
        foreach ($spec->fields as $f) {
            $sample[$f['name']] = match ($f['type']) {
                'image'           => '/bundles/draggo/placeholder.svg',
                'images'          => ['/bundles/draggo/placeholder.svg', '/bundles/draggo/placeholder.svg', '/bundles/draggo/placeholder.svg'],
                'list'            => ['Eintrag 1', 'Eintrag 2', 'Eintrag 3'],
                'pairs'           => [['key' => 'Titel 1', 'value' => 'Beschreibung 1'], ['key' => 'Titel 2', 'value' => 'Beschreibung 2']],
                // HTML/icon sample values are wrapped as Twig\Markup so the
                // sandbox (autoescape on, no |raw) renders them as real HTML in
                // the PREVIEW — exactly like BlockController does on the frontend.
                'accordion'       => [['key' => 'Frage 1', 'value' => new Markup('<p>Antwort 1</p>', 'UTF-8')], ['key' => 'Frage 2', 'value' => new Markup('<p>Antwort 2</p>', 'UTF-8')]],
                'iconlinks'       => [['key' => new Markup('<i class="fab fa-facebook"></i>', 'UTF-8'), 'value' => '#'], ['key' => new Markup('<i class="fab fa-instagram"></i>', 'UTF-8'), 'value' => '#']],
                'richtext'        => new Markup('<p>Beispieltext</p>', 'UTF-8'),
                'icon'            => new Markup('<i class="fas fa-star"></i>', 'UTF-8'),
                'bool'            => true,
                'number', 'range' => $this->sampleNumber($f),
                'select'          => (string) ($f['options'][0][0] ?? ''),
                'url'             => '#',
                default           => $f['label'] ?: 'Beispieltext',
            };
        }

        return $sample;
    }

    /**
     * Pick a sensible sample value for a number/range field. A blanket small
     * value (the old "5") collapsed size-driven elements in the preview — e.g.
     * a logo-carousel whose height field sampled as 5px squashed the whole
     * track to 5px. Prefer the field's own default, then a name-based guess
     * (counts small, durations larger, dimensions/spacing ~48), then bounds.
     *
     * @param array<string,mixed> $f
     */
    private function sampleNumber(array $f): int|float
    {
        if (isset($f['default']) && is_numeric($f['default'])) {
            return $f['default'] + 0;
        }
        $hay = strtolower(((string) ($f['name'] ?? '')) . ' ' . ((string) ($f['label'] ?? '')));
        if (preg_match('/(col|spalt|count|anzahl|items|slides|per)/', $hay)) {
            return 3;
        }
        if (preg_match('/(speed|dauer|duration|geschwind|interval|delay|sekund|ms\b)/', $hay)) {
            return 20;
        }
        if (isset($f['max']) && is_numeric($f['max'])) {
            return min((float) $f['max'], 64);
        }

        return 48; // dimensions / spacing / radius — a visible, non-tiny default
    }

    /** Validate a (possibly user-tweaked) spec array → clean BlockSpec. */
    public function validateSpec(array $raw): BlockSpec
    {
        return $this->validator->validate($raw);
    }

    /** Sandbox-rendered preview HTML with sample data + scoped CSS. */
    public function preview(BlockSpec $spec): string
    {
        try {
            $html = $this->sandbox->render($spec->template, $this->sampleFor($spec));
        } catch (\Throwable) {
            $html = '<em>Vorschau nicht möglich.</em>';
        }
        $css = $spec->css !== '' ? '<style>' . CssScoper::scope($spec->css, '.draggo-blk-preview') . '</style>' : '';

        return $css . '<div class="draggo-blk-preview">' . $html . '</div>';
    }

    /**
     * @param list<array{role:string,content:string}> $history
     * @param array<string,mixed>|null                 $editSpec
     * @return list<array<string,mixed>>
     */
    private function toMessages(array $history, bool $force, ?array $editSpec = null, array $images = []): array
    {
        $out = [];
        // Edit mode: lead with the existing spec so the model REVISES it
        // (keeps the type key) instead of inventing a new element.
        if ($editSpec !== null) {
            $json = (string) json_encode($editSpec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $out[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' =>
                "BEARBEITUNGSMODUS. Du überarbeitest einen BESTEHENDEN Element-Typ. Behalte zwingend den type-Schlüssel \"" . ($editSpec['type'] ?? '') . "\". Baue NICHTS Neues von Grund auf — wende nur die gewünschten Änderungen auf diese BlockSpec an und gib sie via emit_blockspec mit demselben type zurück.\n\nAktuelle BlockSpec:\n" . $json,
            ]]];
            $out[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Alles klar, ich überarbeite den bestehenden Typ. Was möchtest du ändern?']]];
        }
        foreach ($history as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($m['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => [['type' => 'text', 'text' => $text]]];
        }
        // Attach uploaded screenshots (Claude vision) to the latest USER message
        // so the model can reverse-engineer them into a BlockSpec. Images ride
        // only on the turn they were sent — never stored back into history.
        if ($images !== []) {
            for ($i = \count($out) - 1; $i >= 0; --$i) {
                if (($out[$i]['role'] ?? '') !== 'user') {
                    continue;
                }
                $blocks = [];
                foreach ($images as $im) {
                    if (!\is_array($im) || empty($im['media_type']) || empty($im['data'])) {
                        continue;
                    }
                    $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => (string) $im['media_type'], 'data' => (string) $im['data']]];
                }
                if ($blocks !== []) {
                    // Image(s) first, then the user's text (Anthropic's recommended order).
                    $out[$i]['content'] = array_merge($blocks, $out[$i]['content']);
                }
                break;
            }
        }
        if ($force && $out !== []) {
            $out[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Bitte jetzt das Element mit emit_blockspec finalisieren — keine weiteren Fragen.']]];
        }

        return $out;
    }

    private function emitTool(): array
    {
        return [
            'name'        => 'emit_blockspec',
            'description' => 'Finalisiere das Content-Element als validierte BlockSpec. Erst aufrufen, wenn genug Information gesammelt wurde.',
            'input_schema' => [
                'type'       => 'object',
                'required'   => ['type', 'label', 'fields', 'template'],
                'properties' => [
                    'type'        => ['type' => 'string', 'description' => 'Eindeutiger Schlüssel, klein, a-z0-9_ (z. B. hero_split).'],
                    'label'       => ['type' => 'string', 'description' => 'Anzeigename im Editor.'],
                    'category'    => ['type' => 'string'],
                    'icon'        => ['type' => 'string', 'description' => 'Eine Font-Awesome-5-Klasse für die Palette, z. B. "fas fa-images" oder "fas fa-star". KEIN Emoji.'],
                    'description' => ['type' => 'string'],
                    'fields'      => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'required'   => ['name', 'type', 'label'],
                            'properties' => [
                                'name'    => ['type' => 'string'],
                                'type'    => ['type' => 'string', 'enum' => BlockSpecValidator::FIELD_TYPES],
                                'label'   => ['type' => 'string'],
                                'options' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]],
                            ],
                        ],
                    ],
                    'template'     => ['type' => 'string', 'description' => 'Sandboxed Twig. Felder via {{ fields.NAME }}. Kein <script>.'],
                    'css'          => ['type' => 'string', 'description' => 'CSS-Regeln inkl. @keyframes/@media erlaubt (eindeutige Keyframe-Namen!), kein @import. Wird aufs Element begrenzt.'],
                    'js'           => ['type' => 'string', 'description' => 'Optionales JavaScript für Interaktivität; Variable `el` = Wurzel-Element. Kein <script>-Tag.'],
                    'styleOptions' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => BlockSpecValidator::STYLE_OPTIONS]],
                ],
            ],
        ];
    }

    private function system(string $lang = 'de'): string
    {
        $types = implode(', ', BlockSpecValidator::FIELD_TYPES);
        $styles = implode(', ', BlockSpecValidator::STYLE_OPTIONS);
        // The model's chat replies + the labels/placeholders it generates follow
        // the backend user's language.
        $langLine = $lang === 'en'
            ? "SPRACHE: Antworte dem Nutzer AUSSCHLIESSLICH auf Englisch. Erzeuge auch alle nutzerseitigen Texte (label, description, Platzhalter) auf Englisch.\n\n"
            : "SPRACHE: Antworte dem Nutzer auf Deutsch.\n\n";

        return $langLine . <<<TXT
Du bist der Draggo-Element-Architekt — ein erfahrener Webdesigner/Entwickler, der für den Contao-Frontend-Editor „Draggo" eigene Content-Elemente baut. Du bist EXPERTE: du kennst die üblichen Bausteine und sinnvollen Optionen jedes Standard-Elements auswendig.

GRUNDHALTUNG — DU TREIBST, DER NUTZER BESTÄTIGT NUR:
- Der Nutzer soll dir NICHT alles vorkauen müssen. Aus einem Stichwort („Logo-Carousel") leitest du SELBST den kompletten, professionellen Funktionsumfang ab.
- ANTWORTE auf die erste Nachricht NICHT mit vielen Mini-Fragen. Präsentiere stattdessen SOFORT einen fertigen VORSCHLAG: die Felder + alle empfohlenen Optionen mit konkreten Default-Werten, kompakt als Liste. Dann genau EINE Frage: „Passt das so, oder möchtest du etwas ändern?"
- Schlage proaktiv konfigurierbare Optionen vor, die Profis erwarten (siehe Wissen unten). Markiere Empfehlungen klar.
- Stelle nur dann Rückfragen, wenn etwas WIRKLICH mehrdeutig ist (z. B. Pflicht-Inhalt unklar). Höchstens 1–2 kurze Fragen.
- Sobald der Nutzer zustimmt ODER nichts Wesentliches offen ist: rufe emit_blockspec auf. Kein endloses Hin und Her.
- Sprache: Deutsch, knapp, freundlich.

SCREENSHOT/BILD-MODUS (wenn der Nutzer ein Bild anhängt):
- Analysiere den Screenshot/das Foto einer Sektion oder eines Elements und BAUE ES NACH: erkenne Struktur (Spalten/Grid, Reihenfolge), Komponenten (Überschrift, Text, Buttons, Bilder, Karten, Icons, Slider …), grobe Farben, Abstände und Ausrichtung.
- Leite daraus direkt eine BlockSpec ab: passende Felder (wiederholende Dinge als pairs/accordion/images/iconlinks!), ein template das das Layout nachbildet, css das Farben/Abstände/Rundungen/Schatten annähert. Du musst nicht pixelgenau sein — triff die STRUKTUR + den Look.
- Texte aus dem Bild übernehmen, wenn lesbar; sonst sinnvolle Default-Werte. Bild-Platzhalter via image/images-Feld (nicht die Original-URL erfinden).
- Wenn das Bild eindeutig ist, BAU SOFORT (emit_blockspec) statt Rückfragen. Bei Unklarheit höchstens 1 kurze Frage.

DEIN WISSEN — typische Optionen je Archetyp (schlage passende davon AKTIV vor):
- Carousel/Slider: Bilder (images), Autoplay an/aus, Geschwindigkeit (ms), sichtbare Folien, Abstand, Pfeile an/aus, Punkte an/aus, Endlos-Schleife, Pause bei Hover, Graustufen.
- Galerie/Grid: Bilder (images), Spaltenzahl, Abstand, Seitenverhältnis, Hover (Zoom/Abdunkeln), Eckenradius, Lightbox.
- Hero/Banner: Überschrift, Subtext, Button (Text+Link), Bild/Overlay, Höhe, Ausrichtung.
- Karten/Features (pairs für Titel+Text, ggf. iconlinks/images): Titel, Text, Icon/Bild, Spalten, Schatten, Hover-Anheben.
- Testimonials: Zitat, Name, Rolle, Avatar, Sterne.
- Akkordeon/FAQ: EIN `accordion`-Feld (Titel+Rich-Text pro Eintrag), Mehrfach-offen (bool), Farben. NICHT zwei Listen.
- CTA: Titel, Text, Button, Hintergrundfarbe, Ausrichtung.
Wenn der Wunsch nicht in diese Muster passt, denk dir analog die sinnvollen Optionen aus.

DENKWEISE — OPTIMAL STATT EINFACH (zwingend):
- Baue NICHT die schnellste, sondern die BEST EDITIERBARE Lösung. Denke vor dem emit ein zweites Mal nach: „Wie würde ein Profi-Pagebuilder dieses Element strukturieren, damit der Redakteur es mühelos pflegt?"
- ZUSAMMENGEHÖRENDE wiederholende Daten gehören in EIN strukturiertes Feld, NIEMALS in zwei parallele Listen. Ein Akkordeon/FAQ ist NICHT „eine Liste Titel + eine Liste Texte" (Redakteur muss Zeilen abzählen!) — sondern EIN `accordion`- bzw. `pairs`-Feld, wo Titel und Text pro Eintrag zusammenstehen.
- Felder dürfen NICHT doppelte Pflege erzwingen. Keine HTML-Tags in Textwert-Defaults, keine hartkodierten Icons im Template.

WAS DU ERZEUGST (nur via emit_blockspec):
- fields: name (klein, a-z0-9_), type (∈ {$types}), label. select braucht options ([[wert,label],…]). Schalter = type bool. Zahl/Regler = number bzw. range.
- FELDTYP-WAHL (wichtig):
  • Einzel-Überschrift/kurzer Text → `text`. Mehrzeiliger Fließtext mit Formatierung (fett, Links, Listen) → `richtext` (rendert echtes HTML). NIE HTML-Tags in einen `text`-Wert schreiben.
  • Mehrere Bilder → `images`. Mehrere einfache Textzeilen (Aufzählung) → `list`.
  • Wiederholende Paare Titel+Text (Akkordeon, FAQ, Feature-Liste, Steps, Vorteile) → `pairs` (Titel+Kurztext) oder `accordion` (Titel+Rich-Text-Body). Iteration: {% for it in fields.NAME %}{{ it.key }} … {{ it.value }}{% endfor %}.
  • Social-/Icon-Links (Icon + URL) → `iconlinks`: {% for it in fields.NAME %}<a href="{{ it.value }}">{{ it.key }}</a>{% endfor %} ({{ it.key }} rendert das Icon automatisch).
  • Einzel-Icon → `icon`; im Template NUR {{ fields.NAME }} ausgeben — das ergibt automatisch das Icon-Markup. NIEMALS `<i class="fa…">` hartkodieren.
  • NIE feld1/feld2/… für wiederholende Dinge. Max 20 Felder.
- template: Sandbox-Twig. Werte via {{ fields.NAME }}. Erlaubt: Tags if/for/set; Filter escape/upper/lower/default/nl2br/number_format/date/join/length; Funktionen range/max/min. VERBOTEN: |raw, <script>, Objekt-Methoden. Bilder: <img src="{{ fields.NAME }}" alt=""> (Wert wird zur echten URL). `richtext`/`icon`/`accordion`-value/`iconlinks`-key rendern ihr HTML/Icon von selbst — KEIN |raw nötig.
- OPTIONALE Features sind IMMER optional: alle Felder sind ohne Ausfüllen speicherbar. Baue Schalter (bool) für Optionen wie Autoplay/Endlosschleife/Pfeile ein UND mache sie im Template/JS per {{ fields.x|default(...) }} bzw. if wirksam. Nie etwas erzwingen, das der Nutzer nicht setzen muss.
- RESPONSIVE ist eingebaut: jedes Element kann der Nutzer pro Desktop/Tablet/Mobil getrennt anpassen (Abstände, Größen, Sichtbarkeit). Gestalte template+css daher von Grund auf flüssig/responsive (Flexbox/Grid, %/clamp, flex-wrap), sodass es auf allen Geräten sauber bricht — der Nutzer muss Responsive NICHT extra erbitten.
- css: Regeln (Selektor { … }). @keyframes UND @media sind ERLAUBT — wird automatisch aufs Element begrenzt. Bei @keyframes EINDEUTIGE Namen verwenden (z. B. blk_<type>_scroll), um Kollisionen zu vermeiden. Kein @import. Endlos-Animationen (z. B. Logo-Scroll) am besten rein per CSS @keyframes + animation.
- js: OPTIONAL, für echte Interaktivität (Slider-Steuerung, Autoplay, Klick-Logik). Du bekommst die Variable `el` = das Wurzel-Element des Blocks im DOM. Arbeite NUR mit `el` (el.querySelectorAll(...), addEventListener, setInterval …). KEIN <script> im template — dafür ist NUR das js-Feld da. Nutze js nur, wenn CSS nicht reicht.
- styleOptions: Teilmenge von {$styles} — gibt dem Nutzer Editor-Regler für Hintergrund/Abstand/Ausrichtung etc.
- type: kurzer eindeutiger Schlüssel; label: Anzeigename; icon: eine Font-Awesome-5-Klasse (z. B. "fas fa-images", "fas fa-star") — KEIN Emoji; description: ein Satz.

QUALITÄTSREGELN (zwingend, sonst wird der Entwurf abgelehnt):
- JEDES deklarierte Feld MUSS im Template vorkommen ({{ fields.NAME }} bzw. {% for x in fields.NAME %}). Keine ungenutzten Felder, keine undeklarierten Felder.
- Das Template MUSS sichtbaren Inhalt rendern (nicht leer).
- Nutze robustes, simples CSS (Flexbox/Grid), sinnvolle Defaults, responsive. Keine exotischen Tricks.

BEISPIEL einer guten emit_blockspec-Eingabe (Logo-Leiste):
{
 "type":"logo_strip","label":"Logo-Leiste","category":"Logos","icon":"🏷",
 "description":"Reihe von Partner-/Kundenlogos mit optionalem Graustufen-Effekt.",
 "fields":[
   {"name":"logos","type":"images","label":"Logos"},
   {"name":"grayscale","type":"bool","label":"Graustufen (Farbe bei Hover)"},
   {"name":"height","type":"range","label":"Logo-Höhe (px)"}
 ],
 "template":"<div class=\"ls-row\">{% for l in fields.logos %}<div class=\"ls-item\"><img src=\"{{ l }}\" alt=\"\"></div>{% endfor %}</div>",
 "css":".ls-row{display:flex;flex-wrap:wrap;gap:2rem;align-items:center;justify-content:center} .ls-item img{height:60px;width:auto;object-fit:contain} .ls-item img{filter:grayscale(1);transition:filter .3s} .ls-item img:hover{filter:none}",
 "styleOptions":["padding","bg","align"]
}

BEISPIEL Akkordeon (RICHTIG — EIN strukturiertes Feld, nicht zwei Listen):
{
 "type":"faq_accordion","label":"FAQ / Akkordeon","category":"Inhalt","icon":"❓",
 "description":"Aufklappbare Frage-Antwort-Einträge.",
 "fields":[
   {"name":"items","type":"accordion","label":"Einträge (Frage + Antwort)"},
   {"name":"multi","type":"bool","label":"Mehrere gleichzeitig offen"}
 ],
 "template":"<div class=\"faq\">{% for it in fields.items %}<details class=\"faq-i\"{% if not fields.multi %} name=\"faq\"{% endif %}><summary>{{ it.key }}</summary><div class=\"faq-b\">{{ it.value }}</div></details>{% endfor %}</div>",
 "css":".faq-i{border:1px solid #e3e6ec;border-radius:8px;margin-bottom:.5rem} .faq summary{cursor:pointer;padding:1rem;font-weight:600} .faq-b{padding:0 1rem 1rem}",
 "styleOptions":["padding","bg","color","maxWidth"]
}
TXT;
    }
}
