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

/**
 * Provider-agnostic image source (parallels AiClientInterface). A source either
 * SEARCHES a stock library (Unsplash/Pexels → returns remote image URLs) or
 * GENERATES images (OpenAI → returns base64). The MediaDownloader turns either
 * into a file in the media library. New providers just implement this.
 */
interface ImageSourceInterface
{
    /** Stable key: 'unsplash' | 'pexels' | 'openai'. */
    public function name(): string;

    /** True when this provider's API key is set. */
    public function isConfigured(): bool;

    /**
     * Fetch up to $count images for a query (search term) or prompt (generation).
     *
     * @return list<array{kind:string,data:string,alt:string,credit:string,ext:string}>
     *   kind: 'url' (download it, host-allowlisted) | 'b64' (decode it). ext = file extension.
     */
    public function fetch(string $query, int $count): array;
}
