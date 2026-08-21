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

namespace Vtinnovations\Draggo\Agent;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vtinnovations\Draggo\Block\BlockSpec;

/**
 * Opt-in, privacy-preserving telemetry: when enabled, reports only the STRUCTURE
 * of a committed block-type (type key, label, category, field TYPES) to a
 * central endpoint — so demand for element kinds can be aggregated and popular
 * ones shipped in core. Never sends templates, CSS, field values, or any page
 * content. Off by default; failures are swallowed (never block the user).
 */
final class TelemetryReporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly \Vtinnovations\Draggo\Settings\AiSettings $settings,
    ) {
    }

    public function report(BlockSpec $spec): void
    {
        $url = $this->settings->telemetryUrl();
        if (!$this->settings->telemetryEnabled() || trim($url) === '') {
            return;
        }

        $payload = [
            'type'         => $spec->type,
            'label'        => $spec->label,
            'category'     => $spec->category,
            'fieldTypes'   => array_map(static fn (array $f): string => (string) $f['type'], $spec->fields),
            'fieldCount'   => \count($spec->fields),
            'styleOptions' => $spec->styleOptions,
            'hasCss'       => $spec->css !== '',
        ];

        try {
            $this->httpClient->request('POST', $url, [
                'json'    => $payload,
                'timeout' => 8,
            ])->getStatusCode();
        } catch (\Throwable) {
            // Telemetry must never affect the editor — ignore all failures.
        }
    }
}
