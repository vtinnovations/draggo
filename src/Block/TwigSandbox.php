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

use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicy;

/**
 * Isolated, sandboxed Twig environment for AI-generated block templates. The
 * SecurityPolicy is an ALLOWLIST — only safe tags/filters/functions render.
 * `|raw` is NOT allowed, autoescape is on → generated templates can never emit
 * unescaped output or call dangerous code. Used both to validate (compile +
 * dry-render) and to render on the frontend.
 */
final class TwigSandbox
{
    private const TAGS = ['if', 'else', 'elseif', 'for', 'set'];

    private const FILTERS = [
        'escape', 'e', 'upper', 'lower', 'capitalize', 'title', 'trim',
        'nl2br', 'default', 'length', 'join', 'number_format', 'date',
        'striptags', 'first', 'last', 'reverse', 'abs', 'round', 'replace',
    ];

    private const FUNCTIONS = ['range', 'max', 'min', 'random'];

    private function env(): Environment
    {
        $env = new Environment(new ArrayLoader(), [
            'autoescape'  => 'html',
            'strict_variables' => false,
            'cache'       => false,
            'optimizations' => 0,
        ]);
        // Allow no object methods/properties at all (fields are plain arrays).
        $policy = new SecurityPolicy(self::TAGS, self::FILTERS, [], [], self::FUNCTIONS);
        // 2nd arg true → sandbox EVERY template, not only {% sandbox %} blocks.
        $env->addExtension(new SandboxExtension($policy, true));

        return $env;
    }

    /**
     * Compile + dry-render a template against sample field data. Returns null on
     * success, or a human error message on failure (syntax or security).
     *
     * @param array<string,mixed> $sampleFields
     */
    public function validate(string $template, array $sampleFields): ?string
    {
        try {
            $env = $this->env();
            $tpl = $env->createTemplate($template);
            $tpl->render(['fields' => $sampleFields]);

            return null;
        } catch (SecurityError $e) {
            return 'Nicht erlaubtes Konstrukt im Template: ' . $e->getMessage();
        } catch (\Throwable $e) {
            return 'Template-Fehler: ' . $e->getMessage();
        }
    }

    /**
     * Render for output. Throws on failure (caller decides fallback).
     *
     * @param array<string,mixed> $fields
     */
    public function render(string $template, array $fields): string
    {
        $env = $this->env();

        return $env->createTemplate($template)->render(['fields' => $fields]);
    }
}
