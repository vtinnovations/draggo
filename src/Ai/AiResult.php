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

namespace Vtinnovations\Draggo\Ai;

/**
 * Normalised result of one LLM turn: any free text plus the tool calls the
 * model requested. Provider-agnostic — every AiClientInterface returns this.
 */
final class AiResult
{
    /**
     * @param list<array{id:string,name:string,input:array<string,mixed>}> $toolCalls
     * @param array<string,mixed>                                          $assistantContent raw provider content blocks (for history)
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls,
        public readonly string $stopReason,
        public readonly array $assistantContent = [],
    ) {
    }

    public function firstToolCall(?string $name = null): ?array
    {
        foreach ($this->toolCalls as $call) {
            if ($name === null || $call['name'] === $name) {
                return $call;
            }
        }

        return null;
    }
}
