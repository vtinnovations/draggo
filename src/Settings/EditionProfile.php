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
 * The evaluated, immutable answer to "what may this installation do right now".
 *
 * Draggo is a Pro-only product: there is exactly one entitled shape — an
 * authenticated record whose package is Pro, whose dates are current and whose
 * signed host set covers a configured domain. Everything else, including a
 * lapsed Pro record, is {@see inactive()} and Contao behaves as if the bundle
 * were not installed. There is no free tier, no trial, no grace mode and no
 * post-expiry fallback to fall back to.
 *
 * This object is shared INPUT, not the gate. Each protected operation asks for
 * the capability it needs at its own boundary, so removing any one call site
 * cannot open the rest.
 */
final class EditionProfile
{
    /** Capabilities a Draggo operation can require. */
    public const CAP_EDITOR = 'editor';
    public const CAP_LIBRARY = 'library';
    public const CAP_GLOBALS = 'globals';
    public const CAP_AI = 'ai';

    /** Everything a Pro record covers. */
    private const PRO_CAPABILITIES = [self::CAP_EDITOR, self::CAP_LIBRARY, self::CAP_GLOBALS, self::CAP_AI];

    /** Why the installation is not entitled. Diagnostics only, never a gate. */
    public const REASON_ACTIVE = 'active';
    public const REASON_NONE = 'not_activated';
    public const REASON_UNTRUSTED = 'record_not_authentic';
    public const REASON_MALFORMED = 'record_malformed';
    public const REASON_CRYPTO = 'verification_unavailable';
    public const REASON_DOMAIN = 'domain_not_covered';
    public const REASON_PACKAGE = 'package_not_permitted';
    public const REASON_PENDING = 'not_yet_valid';
    public const REASON_EXPIRED = 'expired';
    public const REASON_REFRESH = 'refresh_required';

    /**
     * @param list<string> $signedDomains
     */
    private function __construct(
        public readonly bool $active,
        public readonly string $reason,
        public readonly string $package,
        public readonly ?string $matchedDomain,
        public readonly array $signedDomains,
        public readonly int $maxDomains,
        public readonly int $version,
        public readonly ?int $issuedAt,
        public readonly ?int $startsAt,
        public readonly ?int $expiresAt,
        public readonly bool $lifetime,
        public readonly ?int $verifiedAt,
        private readonly string $key,
    ) {
    }

    /**
     * An entitled installation.
     *
     * @param list<string> $signedDomains
     */
    public static function entitled(
        string $package,
        string $matchedDomain,
        array $signedDomains,
        int $maxDomains,
        int $version,
        ?int $issuedAt,
        ?int $startsAt,
        ?int $expiresAt,
        bool $lifetime,
        ?int $verifiedAt,
        string $key,
    ): self {
        return new self(true, self::REASON_ACTIVE, $package, $matchedDomain, $signedDomains, $maxDomains, $version, $issuedAt, $startsAt, $expiresAt, $lifetime, $verifiedAt, $key);
    }

    /**
     * Not entitled. Optional record detail is carried for the administrator
     * screen (which key is stored, when it lapsed) without granting anything.
     *
     * @param list<string> $signedDomains
     */
    public static function inactive(
        string $reason,
        string $package = '',
        array $signedDomains = [],
        int $version = 0,
        ?int $expiresAt = null,
        string $key = '',
    ): self {
        return new self(false, $reason, $package, null, $signedDomains, 0, $version, null, null, $expiresAt, false, null, $key);
    }

    /**
     * Whether a capability may run. Under Pro-only this is all-or-nothing by
     * design, but callers still name the capability they need so the intent
     * survives any future model change.
     */
    public function allows(string $capability): bool
    {
        return $this->active && \in_array($capability, self::PRO_CAPABILITIES, true);
    }

    /**
     * Whether a content element type may be rendered or created.
     *
     * Only Draggo's own elements are ever gated. Contao core and third-party
     * elements are not this bundle's product — Draggo merely exposes them in
     * its editor, so an inactive licence must not take them away from the site.
     */
    public function allowsElement(string $type): bool
    {
        if (!str_starts_with($type, 'draggo_')) {
            return true;
        }

        return $this->active;
    }

    /** Whether a column layout may be placed. */
    public function allowsStructure(string $preset, bool $custom = false): bool
    {
        return $this->active;
    }

    /**
     * The full licence key, for server-to-server use only.
     *
     * Available solely because the record it came from was cryptographically
     * authenticated. It must never reach a template, a JSON response, a log, a
     * session marker or any client-side code — the one permitted destination is
     * the registry's own endpoint.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * The key with only its two ends legible — the one form that may be shown.
     *
     * Masking happens here rather than at the render site so the Settings
     * section never has to hold the full key to say which licence is installed.
     * A key too short to keep both ends recognisable is masked whole: half of a
     * short key is not a hint, it is the key.
     */
    public function maskedKey(): string
    {
        $key = trim($this->key);
        $mask = str_repeat('•', 8);

        if ('' === $key) {
            return '—';
        }

        if (mb_strlen($key) <= 8) {
            return $mask;
        }

        return mb_substr($key, 0, 4).$mask.mb_substr($key, -4);
    }

    /** Days remaining, or null for a lifetime/absent expiry. */
    public function daysRemaining(): ?int
    {
        if (null === $this->expiresAt || $this->lifetime) {
            return null;
        }

        return max(0, (int) ceil(($this->expiresAt - time()) / 86400));
    }
}
