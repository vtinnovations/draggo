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

namespace Vtinnovations\Draggo\Settings;

/**
 * Effective image-source settings for the landing-page builder. Resolved at
 * runtime from the backend settings record (no cache clear). The provider can
 * be Unsplash/Pexels (real stock photos) or OpenAI (AI generation); empty = off
 * (image fields stay empty for the media picker). Each provider has its own key.
 */
final class ImageSettings
{
    private const PROVIDERS = ['unsplash', 'pexels', 'openai'];

    public function __construct(private readonly SettingsStore $store)
    {
    }

    /** Selected provider key, or '' when image sourcing is off. */
    public function provider(): string
    {
        $p = $this->store->get('img_provider');

        return \in_array($p, self::PROVIDERS, true) ? $p : '';
    }

    public function unsplashKey(): string
    {
        return trim($this->store->get('img_unsplash_key'));
    }

    public function pexelsKey(): string
    {
        return trim($this->store->get('img_pexels_key'));
    }

    public function openaiKey(): string
    {
        return trim($this->store->get('img_openai_key'));
    }

    public function openaiModel(): string
    {
        return $this->store->get('img_openai_model') ?: 'gpt-image-1';
    }

    /** Key of the currently selected provider (or '' when none/unconfigured). */
    public function activeKey(): string
    {
        return match ($this->provider()) {
            'unsplash' => $this->unsplashKey(),
            'pexels'   => $this->pexelsKey(),
            'openai'   => $this->openaiKey(),
            default    => '',
        };
    }

    /** True when a provider is selected AND its key is set. */
    public function isActive(): bool
    {
        return $this->provider() !== '' && $this->activeKey() !== '';
    }
}
