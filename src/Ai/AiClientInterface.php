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
 * Provider-agnostic LLM contract (Claude default; OpenAI/Gemini can implement
 * the same interface later). One call = one assistant turn with optional tool
 * use. The agent loop lives in AgentService, not here.
 */
interface AiClientInterface
{
    /**
     * @param string                                       $system  system prompt
     * @param list<array<string,mixed>>                    $messages provider-format message history
     * @param list<array<string,mixed>>                    $tools   tool definitions (name/description/input_schema)
     */
    public function chat(string $system, array $messages, array $tools): AiResult;

    public function isConfigured(): bool;

    public function providerName(): string;
}
