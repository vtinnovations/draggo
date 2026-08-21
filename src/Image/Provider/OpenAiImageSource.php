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

namespace Vtinnovations\Draggo\Image\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vtinnovations\Draggo\Image\ImageSourceInterface;
use Vtinnovations\Draggo\Settings\ImageSettings;

/**
 * OpenAI image GENERATION (DALL·E / gpt-image-1). Costs per image. Requests
 * base64 output so nothing is downloaded from an external URL (no egress beyond
 * the OpenAI API itself). Used as the "KI-Bildgenerierung" provider.
 */
final class OpenAiImageSource implements ImageSourceInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/images/generations';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ImageSettings $settings,
    ) {
    }

    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return $this->settings->openaiKey() !== '';
    }

    public function fetch(string $query, int $count): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $model = $this->settings->openaiModel();
        $payload = [
            'model'  => $model,
            'prompt' => mb_substr($query, 0, 900),
            'n'      => max(1, min($count, 4)),
            'size'   => '1536x1024',
        ];
        // dall-e-* needs an explicit base64 request; gpt-image-1 returns b64 by
        // default and rejects response_format. dall-e-3 also only supports n=1.
        if (str_contains($model, 'dall-e')) {
            $payload['response_format'] = 'b64_json';
            $payload['size'] = '1792x1024';
            $payload['n'] = 1;
        }

        try {
            $data = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer ' . $this->settings->openaiKey(), 'Content-Type' => 'application/json'],
                'json'    => $payload,
                'timeout' => 90,
            ])->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach (($data['data'] ?? []) as $img) {
            $b64 = \is_array($img) ? (string) ($img['b64_json'] ?? '') : '';
            if ($b64 === '') {
                continue;
            }
            $out[] = ['kind' => 'b64', 'data' => $b64, 'alt' => mb_substr($query, 0, 120), 'credit' => '', 'ext' => 'png'];
            if (\count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
