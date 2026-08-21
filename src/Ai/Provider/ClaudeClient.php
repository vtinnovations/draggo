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

namespace Vtinnovations\Draggo\Ai\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vtinnovations\Draggo\Ai\AiClientInterface;
use Vtinnovations\Draggo\Ai\AiResult;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Settings\AiSettings;

/**
 * Anthropic Messages API client (Claude / Opus). Implements tool use so the
 * agent can drive a structured interview and emit a validated BlockSpec.
 */
final class ClaudeClient implements AiClientInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiSettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->settings->apiKey()) !== '';
    }

    public function providerName(): string
    {
        return 'claude';
    }

    public function chat(string $system, array $messages, array $tools): AiResult
    {
        if (!$this->isConfigured()) {
            throw new InvalidInputException('Kein Claude-API-Key hinterlegt (Backend → Draggo → Einstellungen).');
        }

        $payload = [
            'model'      => $this->settings->model(),
            'max_tokens' => $this->settings->maxTokens(),
            'system'     => $system,
            'messages'   => array_values($messages),
        ];
        if ($tools !== []) {
            $payload['tools'] = array_values($tools);
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'x-api-key'         => $this->settings->apiKey(),
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 120,
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            throw new InvalidInputException('KI-Anfrage fehlgeschlagen: ' . $e->getMessage());
        }

        if ($status >= 400) {
            $msg = \is_array($data['error'] ?? null) ? (string) ($data['error']['message'] ?? 'Fehler') : 'Fehler ' . $status;
            throw new InvalidInputException('KI-Fehler: ' . $msg);
        }

        $text = '';
        $toolCalls = [];
        foreach (($data['content'] ?? []) as $block) {
            if (!\is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = [
                    'id'    => (string) ($block['id'] ?? ''),
                    'name'  => (string) ($block['name'] ?? ''),
                    'input' => \is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return new AiResult(
            trim($text),
            $toolCalls,
            (string) ($data['stop_reason'] ?? ''),
            \is_array($data['content'] ?? null) ? $data['content'] : [],
        );
    }
}
