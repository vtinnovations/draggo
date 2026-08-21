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

namespace Vtinnovations\Draggo\Token;

use Doctrine\DBAL\Connection;

/**
 * Site-wide default styles (Roadmap Phase E "global defaults"): body
 * typography, headings h1–h6, links. Stored as one JSON document and emitted
 * as base CSS on every page. Per-element styles (.draggo-el-{id}) override
 * these by specificity — exactly the Elementor "global + override" model.
 */
final class DefaultsStore
{
    private const KEY = 'defaults';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string,mixed> */
    public function get(): array
    {
        try {
            $raw = $this->connection->fetchOne('SELECT svalue FROM tl_draggo_setting WHERE skey = :k', ['k' => self::KEY]);
        } catch (\Throwable) {
            return [];
        }
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, int $tstamp): void
    {
        $json = json_encode($data);
        $exists = (bool) $this->connection->fetchOne('SELECT id FROM tl_draggo_setting WHERE skey = :k', ['k' => self::KEY]);
        if ($exists) {
            $this->connection->update('tl_draggo_setting', ['svalue' => $json, 'tstamp' => $tstamp], ['skey' => self::KEY]);
        } else {
            $this->connection->insert('tl_draggo_setting', ['skey' => self::KEY, 'svalue' => $json, 'tstamp' => $tstamp]);
        }
    }

    /**
     * Compile the global defaults into base CSS. $bodySel/$prefix let the editor
     * scope everything to the canvas (so it never styles the editor chrome).
     */
    public function css(string $bodySel = 'body', string $prefix = ''): string
    {
        $d = $this->get();
        if ($d === []) {
            return '';
        }
        $css = $this->blockCss($d, $bodySel, $prefix);

        // Responsive global defaults: tablet ≤991, mobile ≤767. Each breakpoint
        // overlays the base per group (empty inherits desktop).
        $resp = \is_array($d['responsive'] ?? null) ? $d['responsive'] : [];
        foreach (['tablet' => 991, 'mobile' => 767] as $bp => $max) {
            $ov = \is_array($resp[$bp] ?? null) ? $resp[$bp] : [];
            if ($ov === []) {
                continue;
            }
            $merged = $this->mergeDefaults($d, $ov);
            $block = $this->blockCss($merged, $bodySel, $prefix);
            if ($block !== '') {
                $css .= '@media (max-width:' . $max . 'px){' . $block . '}';
            }
        }

        return $css;
    }

    /**
     * Per-group merge of a breakpoint override over the base defaults.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $ov
     * @return array<string,mixed>
     */
    private function mergeDefaults(array $base, array $ov): array
    {
        $out = [];
        foreach (['body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'link'] as $g) {
            $out[$g] = array_merge(
                \is_array($base[$g] ?? null) ? $base[$g] : [],
                \is_array($ov[$g] ?? null) ? $ov[$g] : [],
            );
        }

        return $out;
    }

    /**
     * Compile one defaults map (no @media) into CSS. $bodySel/$prefix let the
     * editor scope everything to the canvas.
     *
     * @param array<string,mixed> $d
     */
    private function blockCss(array $d, string $bodySel, string $prefix): string
    {
        $css = '';

        $hex = static fn (mixed $v): ?string => (\is_string($v) && preg_match('/^(#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})|var\(--bld-color-[a-z0-9-]+\))$/', $v)) ? $v : null;
        $len = static fn (mixed $v): ?string => (\is_string($v) && preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/', $v)) ? $v : null;
        $font = static fn (mixed $v): ?string => (\is_string($v) && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $v)) ? $v : null;
        $num = static fn (mixed $v): ?string => is_numeric($v) ? (string) (float) $v : null;
        $weight = static fn (mixed $v): ?string => \in_array((string) $v, ['300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true) ? (string) $v : null;
        $lsp = static fn (mixed $v): ?string => (is_numeric($v) && abs((float) $v) <= 100) ? ((float) $v) . 'px' : null;
        $tform = static fn (mixed $v): ?string => \in_array((string) $v, ['none', 'uppercase', 'lowercase', 'capitalize'], true) ? (string) $v : null;
        $fstyle = static fn (mixed $v): ?string => \in_array((string) $v, ['normal', 'italic'], true) ? (string) $v : null;
        $deco = static fn (mixed $v): ?string => \in_array((string) $v, ['none', 'underline', 'line-through'], true) ? (string) $v : null;

        // Full typography decl from a group, given the props it supports.
        $typo = static function (array $g) use ($font, $len, $hex, $num, $weight, $lsp, $tform, $fstyle, $deco): string {
            $decl = '';
            if ($v = $font($g['fontFamily'] ?? null)) {
                $decl .= 'font-family:' . $v . ';';
            }
            if ($v = $len($g['fontSize'] ?? null)) {
                $decl .= 'font-size:' . $v . ';';
            }
            if ($v = $weight($g['fontWeight'] ?? null)) {
                $decl .= 'font-weight:' . $v . ';';
            }
            if ($v = $hex($g['color'] ?? null)) {
                $decl .= 'color:' . $v . ';';
            }
            if ($v = $num($g['lineHeight'] ?? null)) {
                $decl .= 'line-height:' . $v . ';';
            }
            if ($v = $lsp($g['letterSpacing'] ?? null)) {
                $decl .= 'letter-spacing:' . $v . ';';
            }
            if ($v = $tform($g['textTransform'] ?? null)) {
                $decl .= 'text-transform:' . $v . ';';
            }
            if ($v = $fstyle($g['fontStyle'] ?? null)) {
                $decl .= 'font-style:' . $v . ';';
            }
            if ($v = $deco($g['textDecoration'] ?? null)) {
                $decl .= 'text-decoration:' . $v . ';';
            }

            return $decl;
        };

        $b = \is_array($d['body'] ?? null) ? $d['body'] : [];
        if (($decl = $typo($b)) !== '') {
            $css .= $bodySel . '{' . $decl . '}';
        }

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $h = \is_array($d[$tag] ?? null) ? $d[$tag] : [];
            if (($decl = $typo($h)) !== '') {
                $css .= $prefix . $tag . '{' . $decl . '}';
            }
        }

        $l = \is_array($d['link'] ?? null) ? $d['link'] : [];
        if (($decl = $typo($l)) !== '') {
            $css .= $prefix . 'a{' . $decl . '}';
        }
        if ($v = $hex($l['hoverColor'] ?? null)) {
            $css .= $prefix . 'a:hover{color:' . $v . ';}';
        }

        return $css;
    }
}
