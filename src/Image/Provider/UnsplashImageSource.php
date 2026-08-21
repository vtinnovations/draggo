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
 * Unsplash search (real stock photos). Returns landscape image URLs from the
 * Unsplash CDN (downloaded by MediaDownloader). Per the Unsplash API Guidelines
 * we trigger the download endpoint and keep photographer attribution.
 */
final class UnsplashImageSource implements ImageSourceInterface
{
    private const ENDPOINT = 'https://api.unsplash.com/search/photos';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ImageSettings $settings,
    ) {
    }

    public function name(): string
    {
        return 'unsplash';
    }

    public function isConfigured(): bool
    {
        return $this->settings->unsplashKey() !== '';
    }

    public function fetch(string $query, int $count): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        try {
            $data = $this->httpClient->request('GET', self::ENDPOINT, [
                'headers' => ['Authorization' => 'Client-ID ' . $this->settings->unsplashKey()],
                'query'   => ['query' => $query, 'per_page' => max(1, min($count, 10)), 'orientation' => 'landscape', 'content_filter' => 'high'],
                'timeout' => 20,
            ])->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach (($data['results'] ?? []) as $p) {
            if (!\is_array($p)) {
                continue;
            }
            $url = (string) ($p['urls']['regular'] ?? '');
            if ($url === '') {
                continue;
            }
            // Unsplash ToS: ping the download endpoint (best-effort, fire-and-forget).
            $dl = (string) ($p['links']['download_location'] ?? '');
            if ($dl !== '') {
                try {
                    $this->httpClient->request('GET', $dl, ['headers' => ['Authorization' => 'Client-ID ' . $this->settings->unsplashKey()], 'timeout' => 8])->getStatusCode();
                } catch (\Throwable) {
                }
            }
            $out[] = [
                'kind'   => 'url',
                'data'   => $url,
                'alt'    => (string) ($p['alt_description'] ?? $query),
                'credit' => 'Foto: ' . (string) ($p['user']['name'] ?? 'Unsplash') . ' / Unsplash',
                'ext'    => 'jpg',
            ];
            if (\count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
