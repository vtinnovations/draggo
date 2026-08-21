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
 * Scopes a block-type's CSS to one element so it can never leak to the page.
 * Walks rules brace-aware so at-rules are handled correctly:
 *   - @keyframes / @font-face → passed through UNCHANGED (their inner "selectors"
 *     are keyframe stops / descriptors, not element selectors — prefixing would
 *     break them).
 *   - @media / @supports → inner rules are scoped (recursion).
 *   - normal rules → each comma-separated selector gets the scope prefix.
 */
final class CssScoper
{
    public static function scope(string $css, string $scopeSelector): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        $out = '';
        $i = 0;
        $n = \strlen($css);

        while ($i < $n) {
            $brace = strpos($css, '{', $i);
            if ($brace === false) {
                break;
            }
            $selector = trim(substr($css, $i, $brace - $i));

            // Find the matching closing brace (depth-aware for nested at-rules).
            $depth = 0;
            $j = $brace;
            for (; $j < $n; ++$j) {
                if ($css[$j] === '{') {
                    ++$depth;
                } elseif ($css[$j] === '}') {
                    if (--$depth === 0) {
                        break;
                    }
                }
            }
            $body = substr($css, $brace + 1, $j - $brace - 1);

            if (str_starts_with($selector, '@')) {
                $low = strtolower($selector);
                if (str_contains($low, 'keyframes') || str_contains($low, 'font-face')) {
                    $out .= $selector . '{' . $body . '}'; // passthrough
                } elseif (str_starts_with($low, '@media') || str_starts_with($low, '@supports')) {
                    $out .= $selector . '{' . self::scope($body, $scopeSelector) . '}';
                } else {
                    $out .= $selector . '{' . $body . '}';
                }
            } else {
                $parts = [];
                foreach (explode(',', $selector) as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $parts[] = $scopeSelector . ' ' . $p;
                    }
                }
                $out .= ($parts !== [] ? implode(',', $parts) : $scopeSelector) . '{' . $body . '}';
            }

            $i = $j + 1;
        }

        return $out;
    }
}
