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

namespace Vtinnovations\Draggo\Block;

use Vtinnovations\Draggo\Exception\InvalidInputException;

/**
 * Hard validation for an AI-generated BlockSpec. Rejects (never "repairs with
 * fantasy"): bad type/field names, unknown field types, selects without
 * options, and templates that fail the Twig sandbox. Returns a clean,
 * re-serialised BlockSpec ready to persist.
 */
final class BlockSpecValidator
{
    public const FIELD_TYPES = ['text', 'textarea', 'richtext', 'image', 'images', 'url', 'bool', 'select', 'number', 'range', 'color', 'icon', 'list', 'pairs', 'accordion', 'iconlinks'];

    /** Style keys the generator may request the editor to expose. */
    public const STYLE_OPTIONS = ['bg', 'color', 'padding', 'margin', 'align', 'border', 'radius', 'shadow', 'maxWidth', 'minHeight', 'animation'];

    /** Reserved type keys that block-types must not collide with. */
    private const RESERVED = ['draggo_row_start', 'draggo_col', 'draggo_row_stop', 'draggo_block'];

    public function __construct(private readonly TwigSandbox $sandbox)
    {
    }

    /**
     * @param array<string,mixed> $raw
     */
    public function validate(array $raw): BlockSpec
    {
        $spec = BlockSpec::fromArray($raw);

        if (!preg_match('/^[a-z][a-z0-9_]{1,40}$/', $spec->type) || \in_array($spec->type, self::RESERVED, true)) {
            throw new InvalidInputException('Ungültiger Typ-Schlüssel (a–z, 0–9, _, 2–41 Zeichen, nicht reserviert).');
        }
        if (trim($spec->label) === '') {
            throw new InvalidInputException('Label fehlt.');
        }
        if ($spec->fields === [] || \count($spec->fields) > 20) {
            throw new InvalidInputException('1 bis 20 Felder erforderlich.');
        }

        $seen = [];
        $clean = [];
        foreach ($spec->fields as $f) {
            $name = (string) $f['name'];
            if (!preg_match('/^[a-z][a-z0-9_]{0,30}$/', $name)) {
                throw new InvalidInputException(sprintf('Ungültiger Feldname "%s".', $name));
            }
            if (isset($seen[$name])) {
                throw new InvalidInputException(sprintf('Doppelter Feldname "%s".', $name));
            }
            $seen[$name] = true;
            if (!\in_array($f['type'], self::FIELD_TYPES, true)) {
                throw new InvalidInputException(sprintf('Unbekannter Feldtyp "%s".', $f['type']));
            }
            $cf = ['name' => $name, 'type' => $f['type'], 'label' => trim((string) $f['label']) ?: $name];
            if ($f['type'] === 'select') {
                $opts = $f['options'] ?? [];
                if (!\is_array($opts) || $opts === []) {
                    throw new InvalidInputException(sprintf('Auswahlfeld "%s" braucht Optionen.', $name));
                }
                $cf['options'] = array_values($opts);
            }
            $clean[] = $cf;
        }

        if (trim($spec->template) === '') {
            throw new InvalidInputException('Template fehlt.');
        }

        // Sandbox compile + dry render with sample data.
        $sample = [];
        foreach ($clean as $f) {
            $sample[$f['name']] = $this->sampleValue($f);
        }
        $err = $this->sandbox->validate($spec->template, $sample);
        if ($err !== null) {
            throw new InvalidInputException($err);
        }

        $styleOptions = array_values(array_intersect($spec->styleOptions, self::STYLE_OPTIONS));

        return new BlockSpec(
            $spec->type,
            mb_substr(trim($spec->label), 0, 64),
            mb_substr(trim($spec->category) ?: 'KI-Elemente', 0, 64),
            $clean,
            $spec->template,
            $this->sanitizeCss($spec->css),
            $styleOptions,
            mb_substr($spec->icon, 0, 8) ?: '🧩',
            mb_substr($spec->description, 0, 240),
            $this->sanitizeJs($spec->js),
        );
    }

    /**
     * Block JavaScript runs on the frontend scoped to the element (var `el`).
     * Only the closing-tag breakout is neutralised + length-capped; the code
     * itself is authored by trusted backend users (parity with Contao's HTML
     * element). Keep the KI generator restricted to admins you trust.
     */
    private function sanitizeJs(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Prevent breaking out of the wrapping <script> tag.
        $raw = preg_replace('#</\s*script#i', '<\\/script', $raw) ?? '';

        return mb_substr($raw, 0, 8000);
    }

    /** @param array{type:string,options?:list<array{0:string,1:string}>} $f */
    private function sampleValue(array $f): mixed
    {
        return match ($f['type']) {
            'image'  => '/files/beispiel.jpg',
            'images' => ['/files/a.jpg', '/files/b.jpg', '/files/c.jpg'],
            'list'   => ['Eintrag 1', 'Eintrag 2', 'Eintrag 3'],
            // Repeatable structured items: array of {key, value} maps. The
            // template iterates {% for it in fields.NAME %}{{ it.key }}/{{ it.value }}.
            'pairs'     => [['key' => 'Titel 1', 'value' => 'Beschreibung 1'], ['key' => 'Titel 2', 'value' => 'Beschreibung 2']],
            'accordion' => [['key' => 'Frage 1', 'value' => '<p>Antwort 1</p>'], ['key' => 'Frage 2', 'value' => '<p>Antwort 2</p>']],
            'iconlinks' => [['key' => 'fab fa-facebook', 'value' => '#'], ['key' => 'fab fa-instagram', 'value' => '#']],
            'richtext' => '<p>Beispieltext</p>',
            'bool'   => true,
            'number', 'range' => 5,
            'select' => (string) ($f['options'][0][0] ?? ''),
            'url'    => '#',
            default  => 'Beispieltext',
        };
    }

    /** CSS authored by the AI: strip breakouts, @import, JS vectors; length-cap. */
    private function sanitizeCss(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('#</?\s*style[^>]*>#i', '', $raw) ?? '';
        $raw = preg_replace('/@import[^;]*;?/i', '', $raw) ?? '';
        $raw = preg_replace('/(javascript:|expression\s*\(|behavior\s*:|@charset)/i', '', $raw) ?? '';

        return mb_substr($raw, 0, 8000);
    }
}
