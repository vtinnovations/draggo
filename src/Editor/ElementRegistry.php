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

use Contao\System;

/**
 * Single source of truth for which content-element types exist. Reads the
 * REAL Contao registry ($GLOBALS['TL_CTE']) — Draggo never invents element
 * types. The palette and the input whitelist both derive from here.
 */
final class ElementRegistry
{
    /**
     * Runtime-host CE types: valid + insertable by the AI generator, but hidden
     * from the manual element palette (useless when dropped blank).
     *
     * @var array<string,true>
     */
    private const HOST_TYPES = ['draggo_block' => true];


    /**
     * Flat list of every registered CTE key across all groups.
     *
     * @return list<string>
     */
    public function allTypes(): array
    {
        $types = [];

        foreach (($GLOBALS['TL_CTE'] ?? []) as $group => $elements) {
            foreach (array_keys((array) $elements) as $key) {
                $types[] = (string) $key;
            }
        }

        sort($types);

        return array_values(array_unique($types));
    }

    /**
     * Palette grouped by CTE group, with translated labels — for the editor UI.
     *
     * @return array<string, list<array{type:string,label:string}>>
     */
    public function palette(): array
    {
        $this->loadCteLabels();
        $labels = $GLOBALS['TL_LANG']['CTE'] ?? [];
        $wrappers = $this->wrapperTypes();
        $palette = [];

        foreach (($GLOBALS['TL_CTE'] ?? []) as $group => $elements) {
            foreach (array_keys((array) $elements) as $key) {
                $key = (string) $key;
                // Skip wrapper start/separator/stop CEs (Draggo's own grid
                // wrappers + Contao legacy accordionStart/Stop, sliderStart/Stop,
                // foreign colsetStart/Part/End): they can't be placed as a single
                // element in Draggo's model — grids come from the "Strukturen"
                // tab, not the element picker.
                if (isset($wrappers[$key])) {
                    continue;
                }
                // Skip runtime-host types: draggo_block is the universal renderer
                // for AI-generated block types. Dropped blank from the palette it
                // has no blocktype/data and renders nothing — it's only ever
                // inserted by the AI generator, never picked by hand. Stays valid
                // in allTypes()/isValidType() so generated instances persist.
                if (isset(self::HOST_TYPES[$key])) {
                    continue;
                }
                $label = $labels[$key][0] ?? ($labels[$key] ?? $key);
                $palette[(string) $group][] = [
                    'type'  => $key,
                    'label' => \is_array($label) ? ($label[0] ?? $key) : (string) $label,
                ];
            }
        }

        // Drop groups left empty after filtering.
        return array_filter($palette, static fn (array $els): bool => $els !== []);
    }

    /**
     * All wrapper CE types (start + separator + stop) from $GLOBALS['TL_WRAPPERS'],
     * as a lookup set. These are structural, never standalone-insertable.
     *
     * @return array<string,true>
     */
    private function wrapperTypes(): array
    {
        $set = [];
        foreach (['start', 'separator', 'stop'] as $kind) {
            foreach ((array) ($GLOBALS['TL_WRAPPERS'][$kind] ?? []) as $type) {
                $set[(string) $type] = true;
            }
        }

        return $set;
    }

    public function isValidType(string $type): bool
    {
        return \in_array($type, $this->allTypes(), true);
    }

    /**
     * True for structural wrapper CE types (start/separator/stop) that must NOT
     * be created standalone — they only exist as part of a start…stop pair and
     * would otherwise break the article's grid/wrapper structure. 'single'
     * wrappers (e.g. accordionSingle) are self-contained and stay allowed.
     */
    public function isWrapperType(string $type): bool
    {
        return isset($this->wrapperTypes()[$type]);
    }

    private function loadCteLabels(): void
    {
        // Ensure CTE language keys are loaded (cheap no-op if already present).
        // Draggo's CTE labels live in the tl_content language file, so load
        // that too — otherwise the palette shows raw type keys (draggo_icon …).
        if (class_exists(System::class)) {
            try {
                System::loadLanguageFile('default');
                System::loadLanguageFile('tl_content');
            } catch (\Throwable) {
                // No booted framework (e.g. unit test) — labels fall back to keys.
            }
        }
    }
}
