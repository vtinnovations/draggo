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
 * Effective AI settings: the backend settings record takes precedence, the
 * bundle config (services params) is the fallback. Resolved at runtime so a
 * key entered in the BE works immediately — no cache clear.
 */
final class AiSettings
{
    public function __construct(
        private readonly SettingsStore $store,
        private readonly string $cfgApiKey,
        private readonly string $cfgModel,
        private readonly int $cfgMaxTokens,
        private readonly int $cfgMaxRounds,
        private readonly bool $cfgTelemetry,
        private readonly string $cfgTelemetryUrl,
    ) {
    }

    public function apiKey(): string
    {
        return $this->store->get('ai_api_key') ?: $this->cfgApiKey;
    }

    public function model(): string
    {
        return $this->store->get('ai_model') ?: $this->cfgModel;
    }

    public function maxTokens(): int
    {
        return $this->cfgMaxTokens;
    }

    public function maxRounds(): int
    {
        $v = (int) $this->store->get('ai_max_rounds');

        return $v >= 1 && $v <= 30 ? $v : $this->cfgMaxRounds;
    }

    public function telemetryEnabled(): bool
    {
        return $this->store->get('ai_telemetry') !== '' ? $this->store->bool('ai_telemetry') : $this->cfgTelemetry;
    }

    public function telemetryUrl(): string
    {
        return $this->store->get('ai_telemetry_url') ?: $this->cfgTelemetryUrl;
    }

    /** Self-review polish pass on generated elements. Default ON (opt-out). */
    public function selfReview(): bool
    {
        return !$this->store->bool('ai_no_self_review');
    }
}
