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
 * Ergonomic builders that produce a control DECLARATION (a plain array the
 * editor JS renders generically). This is the heart of Phase A: a control is
 * data, not code. The same array shape will carry `selectors` (Phase B CSS
 * generation), `responsive` (Phase F) and `dynamic` (Tier 3) without changing
 * the renderer contract.
 *
 * Common $opts keys: default, condition ({field, op:'truthy'|'eq'|'neq', value}),
 * placeholder, min, max, step, units, responsive (bool), help.
 *
 * @phpstan-type ControlArray array<string,mixed>
 */
final class Control
{
    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    private static function make(string $key, string $type, string $label, array $opts): array
    {
        return array_merge(['key' => $key, 'type' => $type, 'label' => $label], $opts);
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function text(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::TEXT, $label, $opts);
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function number(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::NUMBER, $label, $opts);
    }

    /**
     * @param list<array{0:string,1:string}> $options
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public static function select(string $key, string $label, array $options, array $opts = []): array
    {
        return self::make($key, ControlType::SELECT, $label, array_merge(['options' => $options], $opts));
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function textarea(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::TEXTAREA, $label, $opts);
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function color(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::COLOR, $label, $opts);
    }

    /** Icon picker (built-in SVG or Font Awesome). @param array<string,mixed> $opts @return array<string,mixed> */
    public static function icon(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::ICON, $label, $opts);
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function length(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::LENGTH, $label, array_merge(['units' => ['px', '%', 'rem', 'em', 'vh', 'vw']], $opts));
    }

    /** @param array<string,mixed> $opts @return array<string,mixed> */
    public static function switcher(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::SWITCHER, $label, $opts);
    }

    /** Four linked values (top/right/bottom/left) + unit. @param array<string,mixed> $opts @return array<string,mixed> */
    public static function dimensions(string $key, string $label, array $opts = []): array
    {
        return self::make($key, ControlType::DIMENSIONS, $label, array_merge(['units' => ['px', '%', 'rem', 'em']], $opts));
    }

    /**
     * Button group with icon options (e.g. text-align).
     *
     * @param list<array{0:string,1:string}> $choices value => icon key
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public static function choose(string $key, string $label, array $choices, array $opts = []): array
    {
        return self::make($key, ControlType::CHOOSE, $label, array_merge(['choices' => $choices], $opts));
    }

    /**
     * Group several controls under a collapsible section.
     *
     * @param list<array<string,mixed>> $controls
     * @return array{title:string,open:bool,controls:list<array<string,mixed>>}
     */
    public static function group(string $title, array $controls, bool $open = false, bool $advanced = false): array
    {
        return ['title' => $title, 'open' => $open, 'controls' => array_values($controls), 'advanced' => $advanced];
    }
}
