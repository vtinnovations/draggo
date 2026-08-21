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

/**
 * A data-driven, AI-generated content-element definition. NOT PHP — a validated
 * description (fields + sandboxed Twig template + scoped CSS) that one fragment
 * controller renders for every type. Persisted as JSON in tl_draggo_blocktype.
 *
 * Allowed field types (mapped to existing editor widgets):
 *   text, textarea, image, url, bool, select, number, range, color, icon
 */
final class BlockSpec
{
    /**
     * @param list<array{name:string,type:string,label:string,options?:list<array{0:string,1:string}>}> $fields
     * @param list<string> $styleOptions
     */
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $category,
        public readonly array $fields,
        public readonly string $template,
        public readonly string $css = '',
        public readonly array $styleOptions = [],
        public readonly string $icon = '🧩',
        public readonly string $description = '',
        public readonly string $js = '',
    ) {
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        $fields = [];
        foreach (($a['fields'] ?? []) as $f) {
            if (!\is_array($f)) {
                continue;
            }
            $field = [
                'name'  => (string) ($f['name'] ?? ''),
                'type'  => (string) ($f['type'] ?? 'text'),
                'label' => (string) ($f['label'] ?? ($f['name'] ?? '')),
            ];
            // Keep a scalar default + numeric bounds when the model supplies them
            // (used for sensible preview sample values + future field hints).
            if (isset($f['default']) && \is_scalar($f['default'])) {
                $field['default'] = \is_bool($f['default']) ? $f['default'] : (string) $f['default'];
            }
            foreach (['min', 'max'] as $bound) {
                if (isset($f[$bound]) && is_numeric($f[$bound])) {
                    $field[$bound] = $f[$bound] + 0;
                }
            }
            if (isset($f['options']) && \is_array($f['options'])) {
                $opts = [];
                foreach ($f['options'] as $o) {
                    if (\is_array($o)) {
                        $opts[] = [(string) ($o[0] ?? ''), (string) ($o[1] ?? ($o[0] ?? ''))];
                    } else {
                        $opts[] = [(string) $o, (string) $o];
                    }
                }
                $field['options'] = $opts;
            }
            $fields[] = $field;
        }

        return new self(
            (string) ($a['type'] ?? ''),
            (string) ($a['label'] ?? ''),
            (string) ($a['category'] ?? 'KI-Elemente'),
            $fields,
            (string) ($a['template'] ?? ''),
            (string) ($a['css'] ?? ''),
            array_values(array_filter(array_map('strval', (array) ($a['styleOptions'] ?? [])))),
            (string) ($a['icon'] ?? 'fas fa-cube'),
            (string) ($a['description'] ?? ''),
            (string) ($a['js'] ?? ''),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'label'        => $this->label,
            'category'     => $this->category,
            'fields'       => $this->fields,
            'template'     => $this->template,
            'css'          => $this->css,
            'styleOptions' => $this->styleOptions,
            'icon'         => $this->icon,
            'description'  => $this->description,
            'js'           => $this->js,
        ];
    }

    /** @return list<string> */
    public function fieldNames(): array
    {
        return array_map(static fn (array $f): string => $f['name'], $this->fields);
    }
}
