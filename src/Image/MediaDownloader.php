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

namespace Vtinnovations\Draggo\Image;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\StringUtil;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stores a fetched image (a remote URL from an allow-listed stock CDN, or a
 * base64 blob from a generator) into the Contao media library under
 * files/draggo/ai/ and registers it with the DBAFS. Returns the relative path
 * + binary UUID so callers can fill either a singleSRC column or a layout
 * background. SSRF-safe: 'url' fetches are restricted to a host allow-list.
 */
final class MediaDownloader
{
    private const TARGET = 'files/draggo/ai';
    private const MAX_BYTES = 8_000_000;
    private const ALLOWED_HOSTS = ['images.unsplash.com', 'plus.unsplash.com', 'images.pexels.com'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array{kind:string,data:string,ext:string} $item
     * @return array{path:string,uuid:string}|null  path = files/…, uuid = STRING uuid
     */
    public function store(array $item): ?array
    {
        $bytes = match ($item['kind'] ?? '') {
            'url' => $this->download((string) ($item['data'] ?? '')),
            'b64' => $this->decode((string) ($item['data'] ?? '')),
            default => null,
        };
        if ($bytes === null || $bytes === '' || \strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $ext = preg_match('/^(jpg|jpeg|png|gif|webp)$/', (string) ($item['ext'] ?? '')) ? (string) $item['ext'] : 'jpg';
        $absDir = $this->projectDir . '/' . self::TARGET;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return null;
        }
        $name = 'draggo-' . substr(sha1($bytes), 0, 16) . '.' . $ext;
        $rel = self::TARGET . '/' . $name;
        $abs = $absDir . '/' . $name;

        if (!is_file($abs) && @file_put_contents($abs, $bytes) === false) {
            return null;
        }
        $this->ensurePublic($absDir);

        /** @var Dbafs $dbafs */
        $dbafs = $this->framework->getAdapter(Dbafs::class);
        $model = $dbafs->addResource($rel);
        $uuid = $model !== null ? StringUtil::binToUuid($model->uuid) : '';
        if ($uuid === '') {
            return null;
        }

        return ['path' => $rel, 'uuid' => $uuid];
    }

    /** Fetch image bytes from an allow-listed stock CDN host only (SSRF guard). */
    private function download(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!\in_array($host, self::ALLOWED_HOSTS, true)) {
            return null;
        }
        try {
            $r = $this->httpClient->request('GET', $url, ['timeout' => 25, 'max_duration' => 30]);
            if ($r->getStatusCode() >= 400) {
                return null;
            }
            $type = (string) ($r->getHeaders(false)['content-type'][0] ?? '');
            if (!str_starts_with($type, 'image/')) {
                return null;
            }

            return $r->getContent(false);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decode(string $b64): ?string
    {
        $raw = base64_decode($b64, true);

        return $raw === false ? null : $raw;
    }

    /** Mirror contao:symlinks for this dir so /files/… resolves on the frontend. */
    private function ensurePublic(string $absDir): void
    {
        $rel = str_replace('\\', '/', substr($absDir, \strlen($this->projectDir) + 1));
        if ($rel === '' || !str_starts_with($rel, 'files/')) {
            return;
        }
        $link = $this->projectDir . '/public/' . $rel;
        if (is_link($link) || is_dir($link)) {
            return;
        }
        $parent = \dirname($link);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        try {
            @symlink($absDir, $link);
        } catch (\Throwable) {
        }
    }
}
