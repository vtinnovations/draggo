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

use Vtinnovations\Draggo\Image\Provider\OpenAiImageSource;
use Vtinnovations\Draggo\Image\Provider\PexelsImageSource;
use Vtinnovations\Draggo\Image\Provider\UnsplashImageSource;
use Vtinnovations\Draggo\Settings\ImageSettings;

/**
 * Facade the landing builder calls: picks the configured provider
 * (Unsplash/Pexels/OpenAI), fetches images for a query/prompt and stores them
 * in the media library. Returns {path, uuid} per image, or [] when no provider
 * is active or the fetch fails (the page is still built, just without photos).
 */
final class ImageService
{
    public function __construct(
        private readonly ImageSettings $settings,
        private readonly MediaDownloader $downloader,
        private readonly UnsplashImageSource $unsplash,
        private readonly PexelsImageSource $pexels,
        private readonly OpenAiImageSource $openai,
    ) {
    }

    public function isActive(): bool
    {
        $p = $this->active();

        return $p !== null && $p->isConfigured();
    }

    private function active(): ?ImageSourceInterface
    {
        return match ($this->settings->provider()) {
            'unsplash' => $this->unsplash,
            'pexels'   => $this->pexels,
            'openai'   => $this->openai,
            default    => null,
        };
    }

    /** Fetch + store ONE image. @return array{path:string,uuid:string}|null */
    public function imageFor(string $query): ?array
    {
        return $this->imagesFor($query, 1)[0] ?? null;
    }

    /**
     * Fetch + store up to $count images for a query/prompt.
     *
     * @return list<array{path:string,uuid:string}>
     */
    public function imagesFor(string $query, int $count): array
    {
        $p = $this->active();
        $query = trim($query);
        if ($p === null || !$p->isConfigured() || $query === '' || $count < 1) {
            return [];
        }
        $out = [];
        foreach ($p->fetch($query, $count) as $item) {
            $stored = $this->downloader->store($item);
            if ($stored !== null) {
                $out[] = $stored;
            }
            if (\count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
