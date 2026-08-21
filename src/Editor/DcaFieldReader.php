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

namespace Vtinnovations\Draggo\Editor;

use Contao\Controller;
use Contao\System;

/**
 * Derives the editor's content-field list for a CTE type DIRECTLY from the live
 * tl_content DCA — so every backend option a core/3rd-party element exposes is
 * also editable in the Draggo frontend editor, without hand-curating each one.
 *
 * Strategy: parse the element type's REAL backend palette (+ subpalettes) into
 * an ordered field list, then map each field's Contao inputType to one of the
 * editor's existing widgets. Curated FieldRegistry entries take precedence
 * (richer widgets/labels for known fields); everything else is auto-mapped.
 *
 * draggo_* elements keep their fully hand-designed field sets (FieldRegistry)
 * and never go through here.
 */
final class DcaFieldReader
{
    /** Container/meta/system columns that must never be editable inline. */
    private const SKIP = [
        'id', 'pid', 'ptable', 'sorting', 'tstamp', 'type', 'cssID',
        'invisible', 'start', 'stop', 'protected', 'groups', 'guests',
        'customTpl', 'sectionHeadline', 'addSectionToc',
        // Draggo-internal (not core content fields).
        'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype',
        'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet',
        'draggo_grid_mobile', 'draggo_container',
    ];

    /**
     * Ordered field descriptors for a type, derived from its BE palette.
     *
     * @return list<array{name:string,widget:string,label:string,options:list<array{0:string,1:string}>,mandatory:bool,multiple:bool}>
     */
    public function specs(string $type): array
    {
        $this->ensureLoaded();

        $dca = $GLOBALS['TL_DCA']['tl_content'] ?? [];
        $fieldsDca = $dca['fields'] ?? [];
        $out = [];
        $seen = [];

        // DCA-only draggo_ elements (landing pages draggo_lp_*, scrollytelling
        // draggo_ss_*/etc.) are handled here when they have no FieldRegistry
        // entry. Their OWN draggo_* palette fields must pass the prefix guard;
        // structural draggo_* columns stay excluded (they're in self::SKIP and
        // never appear in these palettes). Foreign draggo_* fields remain skipped
        // for non-draggo (core/3rd-party) types — behaviour unchanged there.
        $ownDraggo = str_starts_with($type, 'draggo_');

        foreach ($this->paletteFields($dca, $type) as [$name, $cond]) {
            if (isset($seen[$name]) || \in_array($name, self::SKIP, true)
                || (str_starts_with($name, 'draggo_') && !$ownDraggo)) {
                continue;
            }
            $seen[$name] = true;

            // Curated field → reuse Draggo's rich widget/label/options.
            if (isset(FieldRegistry::WIDGET[$name])) {
                $spec = [
                    'name'      => $name,
                    'widget'    => FieldRegistry::WIDGET[$name],
                    'label'     => FieldRegistry::LABEL[$name] ?? $this->label($fieldsDca[$name] ?? [], $name),
                    'options'   => FieldRegistry::OPTIONS[$name] ?? $this->options($fieldsDca[$name] ?? []),
                    'mandatory' => !empty($fieldsDca[$name]['eval']['mandatory']),
                    'multiple'  => !empty($fieldsDca[$name]['eval']['multiple']),
                ];
            } else {
                $spec = $this->mapField($name, $fieldsDca[$name] ?? null);
            }
            if ($spec === null) {
                continue;
            }
            if ($cond !== null) {
                $spec['condition'] = $cond;
            }
            $out[] = $spec;
        }

        return $out;
    }

    /**
     * Full classification of a type's palette fields for the capability scanner:
     * every content field with the editor widget it maps to (or null = the
     * inputType has no Draggo widget yet → not editable inline). Structural/skip
     * columns are omitted (never content). Powers `contao:draggo:scan`.
     *
     * @return list<array{name:string,inputType:string,widget:?string,supported:bool}>
     */
    public function classifyFields(string $type): array
    {
        $this->ensureLoaded();

        $dca = $GLOBALS['TL_DCA']['tl_content'] ?? [];
        $fieldsDca = $dca['fields'] ?? [];
        $ownDraggo = str_starts_with($type, 'draggo_');
        $out = [];
        $seen = [];

        foreach ($this->paletteFields($dca, $type) as [$name, $cond]) {
            if (isset($seen[$name]) || \in_array($name, self::SKIP, true)
                || (str_starts_with($name, 'draggo_') && !$ownDraggo)) {
                continue;
            }
            $seen[$name] = true;

            $f = $fieldsDca[$name] ?? null;
            $inputType = \is_array($f) ? (string) ($f['inputType'] ?? '') : '';

            if (isset(FieldRegistry::WIDGET[$name])) {
                $widget = FieldRegistry::WIDGET[$name];
            } else {
                $spec = $this->mapField($name, \is_array($f) ? $f : null);
                $widget = $spec['widget'] ?? null;
            }

            $out[] = [
                'name'      => $name,
                'inputType' => $inputType !== '' ? $inputType : '(none)',
                'widget'    => $widget,
                'supported' => $widget !== null,
            ];
        }

        return $out;
    }

    /** Field names that are mandatory for this type (for the editor's guard). */
    public function requiredFor(string $type): array
    {
        $req = [];
        foreach ($this->specs($type) as $s) {
            if ($s['mandatory'] && empty($s['condition'])) {
                $req[] = $s['name'];
            }
        }

        return $req;
    }

    /**
     * Map one Contao DCA field to an editor widget descriptor, or null to skip.
     *
     * @param array<string,mixed>|null $f
     * @return array{name:string,widget:string,label:string,options:list<array{0:string,1:string}>,mandatory:bool,multiple:bool}|null
     */
    private function mapField(string $name, ?array $f): ?array
    {
        if ($f === null || empty($f['inputType'])) {
            return null;
        }
        $eval = $f['eval'] ?? [];
        $input = (string) $f['inputType'];
        $multiple = !empty($eval['multiple']);
        $options = $this->options($f);

        $widget = match ($input) {
            'text', 'inputUnit' => $input === 'inputUnit' ? 'headline' : 'text',
            'textarea'          => !empty($eval['rte']) && str_starts_with((string) $eval['rte'], 'tiny') ? 'rte' : 'code',
            // radioTable = a single-choice radio grid (e.g. image "floating"
            // above/below/left/right) → a plain select with the field's options.
            'select', 'radio', 'radioTable' => $multiple ? 'mselect' : 'select',
            'checkbox'          => $multiple ? 'mselect' : 'bool',
            'fileTree'          => $multiple ? 'files' : 'file',
            'imageSize'         => 'imgsize',
            'keyValueWizard'    => 'pairs',
            'listWizard'        => 'lines',
            'tableWizard'       => 'table',
            'pageTree'          => 'select', // page picker → page select (options from tl_page)
            'picker', 'url'     => 'text',
            default             => '',
        };
        if ($widget === '') {
            return null; // unsupported widget (metaWizard, trbl, …) → skip
        }
        // A pageTree resolves to a page select; its options are filled from the
        // page table by the caller (getElementFields).
        $source = $input === 'pageTree' ? 'tl_page' : null;
        // A select with no resolvable options is useless → degrade to text,
        // unless a callback or a name-based DB lookup will fill it (handled by
        // the caller).
        if (\in_array($widget, ['select', 'mselect'], true) && $options === [] && empty($f['options_callback']) && $source === null) {
            $widget = $widget === 'mselect' ? 'lines' : 'text';
        }

        $spec = [
            'name'      => $name,
            'widget'    => $widget,
            'label'     => $this->label($f, $name),
            'options'   => $options,
            'mandatory' => !empty($eval['mandatory']),
            'multiple'  => $multiple,
        ];
        if ($source !== null) {
            $spec['optionsSource'] = $source;
        }

        return $spec;
    }

    /**
     * Ordered list of [fieldName, condition|null] from the type's palette. Each
     * subpalette's fields carry a condition tied to their selector so the editor
     * shows/hides them exactly like the backend (checkbox → truthy, select →
     * eq value).
     *
     * @param array<string,mixed> $dca
     * @return list<array{0:string,1:?array<string,mixed>}>
     */
    private function paletteFields(array $dca, string $type): array
    {
        $palettes = $dca['palettes'] ?? [];
        $palette = (string) ($palettes[$type] ?? $palettes['default'] ?? '');
        if ($palette === '') {
            return [];
        }
        $selectors = (array) ($palettes['__selector__'] ?? []);
        $subs = $dca['subpalettes'] ?? [];

        $names = [];
        // Strip legends "{...}" then split on ; and , → bare field names.
        foreach (preg_split('/[;,]/', preg_replace('/\{[^}]*\}/', '', $palette) ?: '') ?: [] as $tok) {
            $tok = trim($tok);
            if ($tok !== '') {
                $names[] = $tok;
            }
        }

        // Resolve the condition + field list a subpalette key contributes.
        $subFor = function (string $key) use ($selectors): ?array {
            if (\in_array($key, $selectors, true)) {
                return ['field' => $key, 'op' => 'truthy']; // checkbox selector
            }
            foreach ($selectors as $s) {
                if (str_starts_with($key, $s . '_')) {
                    return ['field' => $s, 'op' => 'eq', 'value' => substr($key, \strlen($s) + 1)];
                }
            }

            return null;
        };

        $result = [];
        foreach ($names as $n) {
            $result[] = [$n, null];
            // A checkbox selector's subpalette key is the field name itself.
            if (isset($subs[$n]) && \is_string($subs[$n])) {
                $cond = $subFor($n) ?? ['field' => $n, 'op' => 'truthy'];
                foreach (explode(',', $subs[$n]) as $sub) {
                    $sub = trim($sub);
                    if ($sub !== '') {
                        $result[] = [$sub, $cond];
                    }
                }
            }
            // Select selectors expose value-specific subpalettes ("field_value").
            foreach ($subs as $key => $fieldsStr) {
                if (!\is_string($fieldsStr) || !str_starts_with($key, $n . '_')) {
                    continue;
                }
                $cond = $subFor($key);
                if ($cond === null || $cond['field'] !== $n) {
                    continue;
                }
                foreach (explode(',', $fieldsStr) as $sub) {
                    $sub = trim($sub);
                    if ($sub !== '') {
                        $result[] = [$sub, $cond];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Normalise a DCA field's options (+reference labels) to [[value,label],…].
     *
     * @param array<string,mixed> $f
     * @return list<array{0:string,1:string}>
     */
    private function options(array $f): array
    {
        $opts = $f['options'] ?? null;
        if (!\is_array($opts) || $opts === []) {
            // No static options → resolve a DCA options_callback so 3rd-party
            // select fields (e.g. a custom-model picker) get real options in the
            // editor, exactly as the native backend does.
            $opts = $this->invokeOptionsCallback($f['options_callback'] ?? null);
        }
        if (!\is_array($opts) || $opts === []) {
            return [];
        }
        $ref = $f['reference'] ?? [];
        $out = [];
        $assoc = array_keys($opts) !== range(0, \count($opts) - 1);

        foreach ($opts as $k => $v) {
            if (\is_array($v)) {
                // Option groups → flatten.
                foreach ($v as $gk => $gv) {
                    $val = $assoc ? (string) $gk : (string) $gv;
                    $out[] = [$val, $this->refLabel($ref, $val, \is_string($gv) ? $gv : $val)];
                }
                continue;
            }
            $val = $assoc ? (string) $k : (string) $v;
            $lbl = $assoc ? (string) $v : (string) $v;
            $out[] = [$val, $this->refLabel($ref, $val, $lbl)];
        }

        return $out;
    }

    /**
     * Best-effort execution of a DCA options_callback — mirrors how Contao
     * resolves it (System::importStatic, which returns the container service for
     * public callback classes). Callbacks that require a live DataContainer fail
     * closed → empty options, and the field degrades gracefully.
     *
     * @return array<mixed>
     */
    private function invokeOptionsCallback(mixed $cb): array
    {
        if (empty($cb)) {
            return [];
        }
        try {
            if (\is_array($cb) && \count($cb) === 2 && \is_string($cb[0]) && \is_string($cb[1])) {
                $res = System::importStatic($cb[0])->{$cb[1]}(null);
            } elseif (\is_string($cb) && str_contains($cb, '::')) {
                $res = \call_user_func($cb, null);
            } elseif (\is_callable($cb)) {
                $res = $cb(null);
            } else {
                return [];
            }

            return \is_array($res) ? $res : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<mixed> $ref */
    private function refLabel(array $ref, string $value, string $fallback): string
    {
        $r = $ref[$value] ?? null;
        if (\is_array($r)) {
            return (string) ($r[0] ?? $fallback);
        }

        return $r !== null ? (string) $r : $fallback;
    }

    /** @param array<string,mixed> $f */
    private function label(array $f, string $name): string
    {
        $l = $f['label'] ?? null;
        if (\is_array($l) && isset($l[0]) && $l[0] !== '') {
            return (string) $l[0];
        }

        return \is_string($l) && $l !== '' ? $l : $name;
    }

    private function ensureLoaded(): void
    {
        if (!class_exists(Controller::class)) {
            return;
        }
        // Load core DCA labels in the BACKEND USER's language explicitly. The
        // editor chrome is already localised to that language (DRAGGO_LANG); core
        // element field/option labels come from Contao's language files via this
        // reader. Relying on ambient $GLOBALS['TL_LANGUAGE'] is unreliable in the
        // editor's JSON sub-requests — it can fall back to the system/site default
        // and surface GERMAN core labels to an English editor user (list type,
        // download order, player options, …). Forcing the BE user's language (with
        // no-cache reload) keeps core labels consistent with the editor UI.
        $lang = $this->backendUserLanguage();
        if ($lang !== null && $lang !== '') {
            $GLOBALS['TL_LANGUAGE'] = $lang;
            System::loadLanguageFile('tl_content', $lang, true);
        } else {
            System::loadLanguageFile('tl_content');
        }
        Controller::loadDataContainer('tl_content');
    }

    /** The authenticated backend user's language ('en', 'de', …), or null. */
    private function backendUserLanguage(): ?string
    {
        try {
            $user = \Contao\BackendUser::getInstance();
            $lang = \is_string($user->language ?? null) ? trim($user->language) : '';

            return $lang !== '' ? $lang : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
