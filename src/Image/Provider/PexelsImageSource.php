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
 * Pexels search (real stock photos). Returns landscape image URLs from the
 * Pexels CDN (downloaded by MediaDownloader), with photographer attribution.
 */
final class PexelsImageSource implements ImageSourceInterface
{
    private const ENDPOINT = 'https://api.pexels.com/v1/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ImageSettings $settings,
    ) {
    }

    public function name(): string
    {
        return 'pexels';
    }

    public function isConfigured(): bool
    {
        return $this->settings->pexelsKey() !== '';
    }

    public function fetch(string $query, int $count): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        try {
            $data = $this->httpClient->request('GET', self::ENDPOINT, [
                'headers' => ['Authorization' => $this->settings->pexelsKey()],
                'query'   => ['query' => $query, 'per_page' => max(1, min($count, 10)), 'orientation' => 'landscape'],
                'timeout' => 20,
            ])->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach (($data['photos'] ?? []) as $p) {
            if (!\is_array($p)) {
                continue;
            }
            $url = (string) ($p['src']['large'] ?? $p['src']['original'] ?? '');
            if ($url === '') {
                continue;
            }
            $out[] = [
                'kind'   => 'url',
                'data'   => $url,
                'alt'    => (string) ($p['alt'] ?? $query),
                'credit' => 'Foto: ' . (string) ($p['photographer'] ?? 'Pexels') . ' / Pexels',
                'ext'    => 'jpg',
            ];
            if (\count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
