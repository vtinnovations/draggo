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

use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Security\TrustAnchors;

/**
 * Turns the stored bytes into an {@see EditionProfile}.
 *
 * Verification order matters and is not negotiable:
 *
 *   1. the envelope's own signature — until that holds, the digest it carries
 *      is just an attacker-supplied string;
 *   2. the digest, against the EXACT stored bytes;
 *   3. the record's detached signature over its canonical form;
 *   4. only then, the semantic checks (schema, product, hosts, package, dates).
 *
 * Recomputing the digest over re-serialised JSON would defeat the whole
 * tripwire, so the raw bytes are carried through untouched.
 *
 * Every failure path returns an inactive profile. Nothing here can return an
 * entitled profile without a signature that verified against a pinned key.
 */
final class EditionResolver
{
    /** Reject a record whose start date is absurdly far in the future. */
    private const MAX_SKEW = 86400;

    private ?EditionProfile $cache = null;

    public function __construct(
        private readonly ActivationStore $store,
        private readonly TrustAnchors $anchors,
        private readonly HostInventory $hosts,
    ) {
    }

    /**
     * The current entitlement. Cached per request — this is consulted on nearly
     * every element render.
     */
    public function profile(): EditionProfile
    {
        return $this->cache ??= $this->evaluate();
    }

    /** Drop the memoised profile after a state change. */
    public function forget(): void
    {
        $this->cache = null;
    }

    /**
     * Full cryptographic authentication of a record/envelope pair, independent
     * of any stored state. Reused by the activation transport, the inbound
     * updater and the store's own post-write revalidation, so all four paths
     * apply byte-for-byte the same rules.
     *
     * @param array<string, mixed> $seal
     *
     * @return object|null the decoded record, or null when anything fails
     */
    public function authenticate(string $bytes, array $seal): ?object
    {
        if (!SealedPayload::cryptoAvailable() || $this->anchors->isEmpty()) {
            return null;
        }

        $keyId = (string) ($seal['key_id'] ?? '');
        $algorithm = (string) ($seal['signature_algorithm'] ?? '');
        $signature = (string) ($seal['signature'] ?? '');
        $digest = (string) ($seal['license_md5'] ?? '');

        if ('' === $keyId || TrustAnchors::ALGORITHM !== $algorithm || '' === $signature || '' === $digest) {
            return null;
        }

        // 1. envelope signature, using the key the envelope names
        $key = $this->anchors->resolve($keyId, TrustAnchors::PURPOSE_ENVELOPE);

        if (null === $key) {
            return null;
        }

        $envelope = SealedPayload::decodeDocument((string) json_encode($seal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $canonical = null !== $envelope ? SealedPayload::canonicalJson($envelope) : null;

        if (null === $canonical || !SealedPayload::verify($signature, $canonical, $key)) {
            return null;
        }

        // 2. exact-byte tripwire, now that the expected digest is authenticated
        if (!SealedPayload::matchesDigest($bytes, $digest)) {
            return null;
        }

        // 3. the record's own signature. The record names no key id, so it is
        //    tried against every currently usable record-purpose key.
        $record = SealedPayload::decodeDocument($bytes);

        if (null === $record) {
            return null;
        }

        $recordSignature = $record->signature ?? null;

        if (!\is_string($recordSignature) || '' === $recordSignature) {
            return null;
        }

        $recordCanonical = SealedPayload::canonicalJson($record);

        if (null === $recordCanonical) {
            return null;
        }

        foreach ($this->anchors->candidates(TrustAnchors::PURPOSE_RECORD) as $candidate) {
            if (SealedPayload::verify($recordSignature, $recordCanonical, $candidate)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Semantic acceptance of an authenticated record, independent of where it
     * came from. Returns null when the record is authentic but not acceptable
     * for this product (wrong project, wrong package, malformed host set).
     *
     * @return array{
     *     key: string, package: string, domains: list<string>, domain: string,
     *     maxDomains: int, version: int, issuedAt: int|null, startsAt: int|null,
     *     expiresAt: int|null, lifetime: bool, verifiedAt: int|null, status: string
     * }|null
     */
    public function accept(object $record): ?array
    {
        if (Draggo::SCHEMA !== ($record->schema_version ?? null)) {
            return null;
        }

        if (Draggo::PROJECT !== ($record->project ?? null) || Draggo::SLUG !== ($record->project_slug ?? null)) {
            return null;
        }

        $key = $record->license_key ?? null;

        if (!\is_string($key) || '' === trim($key)) {
            return null;
        }

        $domains = self::hostSet($record->license_domains ?? null);
        $domain = HostInventory::normalise($record->license_domain ?? null);
        $maxDomains = $record->license_max_domains ?? null;

        // A pre-upgrade record that predates the signed host set is rollback
        // material only. The fields are never invented locally.
        if (null === $domains || null === $domain || !\is_int($maxDomains)) {
            return null;
        }

        if ($maxDomains < 1) {
            return null;
        }

        // The operation host must belong to the signed set. The count is NOT
        // compared against the allowance: the registry deliberately lets
        // existing bindings survive a lowered allowance.
        if (!\in_array($domain, $domains, true)) {
            return null;
        }

        $package = $record->license_package ?? null;

        if (!\is_string($package)) {
            return null;
        }

        $lifetime = $record->license_lifetime ?? null;
        $expiresAt = $record->license_expires_at ?? null;

        if (!\is_bool($lifetime) || (null !== $expiresAt && !\is_int($expiresAt))) {
            return null;
        }

        // Lifetime means no expiry; anything time-limited must say when.
        if ($lifetime ? null !== $expiresAt : null === $expiresAt) {
            return null;
        }

        $version = $record->license_version ?? null;

        if (!\is_int($version) || $version < 0) {
            return null;
        }

        return [
            'key' => trim($key),
            'package' => strtolower(trim($package)),
            'domains' => $domains,
            'domain' => $domain,
            'maxDomains' => $maxDomains,
            'version' => $version,
            'issuedAt' => \is_int($record->license_issued_at ?? null) ? $record->license_issued_at : null,
            'startsAt' => \is_int($record->license_starts_at ?? null) ? $record->license_starts_at : null,
            'expiresAt' => $expiresAt,
            'lifetime' => $lifetime,
            'verifiedAt' => \is_int($record->license_verified_at ?? null) ? $record->license_verified_at : null,
            'status' => \is_string($record->validation_status ?? null) ? $record->validation_status : '',
        ];
    }

    /**
     * A signed host set must arrive canonical: non-empty, unique, lexically
     * sorted and free of wildcards. It is validated as received, never sorted
     * locally — reordering it would change the bytes the signature covers.
     *
     * @return list<string>|null
     */
    public static function hostSet(mixed $value): ?array
    {
        if (!\is_array($value) || [] === $value || !array_is_list($value)) {
            return null;
        }

        $out = [];

        foreach ($value as $entry) {
            if (!\is_string($entry) || str_contains($entry, '*')) {
                return null;
            }

            $host = HostInventory::normalise($entry);

            // Reject anything whose normalised form differs from what was
            // signed: the registry signs already-canonical names.
            if (null === $host || $host !== $entry) {
                return null;
            }

            $out[] = $host;
        }

        if ($out !== array_values(array_unique($out))) {
            return null;
        }

        $sorted = $out;
        sort($sorted, SORT_STRING);

        return $sorted === $out ? $out : null;
    }

    /**
     * Read the store and decide. Split from profile() so tests can drive it
     * directly and so a state change can force a fresh decision.
     */
    private function evaluate(): EditionProfile
    {
        $stored = $this->store->read();

        if (null === $stored) {
            return EditionProfile::inactive(EditionProfile::REASON_NONE);
        }

        if (!SealedPayload::cryptoAvailable()) {
            return EditionProfile::inactive(EditionProfile::REASON_CRYPTO);
        }

        if ($this->anchors->isEmpty()) {
            // Reached the signature stage with nothing to verify against. This
            // is a deployment fault, never a reason to trust the record.
            return EditionProfile::inactive(EditionProfile::REASON_UNTRUSTED);
        }

        $record = $this->authenticate($stored['bytes'], $stored['seal']);

        if (null === $record) {
            return EditionProfile::inactive(EditionProfile::REASON_UNTRUSTED);
        }

        $accepted = $this->accept($record);

        if (null === $accepted) {
            // Authentic, but not a record this product can use. The stored key
            // is deliberately not surfaced from an unusable record.
            return EditionProfile::inactive(EditionProfile::REASON_MALFORMED);
        }

        $now = time();

        // Pro-only: no trial, no free, no expired-Pro fallback. free_available
        // is not consulted, because no value of it can grant access here.
        if (!\in_array($accepted['package'], Draggo::PACKAGES, true)) {
            return EditionProfile::inactive(EditionProfile::REASON_PACKAGE, $accepted['package'], $accepted['domains'], $accepted['version'], $accepted['expiresAt'], $accepted['key']);
        }

        if ('valid' !== $accepted['status']) {
            return EditionProfile::inactive(EditionProfile::REASON_UNTRUSTED, $accepted['package'], $accepted['domains'], $accepted['version'], $accepted['expiresAt'], $accepted['key']);
        }

        if (null !== $accepted['startsAt'] && $accepted['startsAt'] - self::MAX_SKEW > $now) {
            return EditionProfile::inactive(EditionProfile::REASON_PENDING, $accepted['package'], $accepted['domains'], $accepted['version'], $accepted['expiresAt'], $accepted['key']);
        }

        if (!$accepted['lifetime'] && null !== $accepted['expiresAt'] && $accepted['expiresAt'] < $now) {
            return EditionProfile::inactive(EditionProfile::REASON_EXPIRED, $accepted['package'], $accepted['domains'], $accepted['version'], $accepted['expiresAt'], $accepted['key']);
        }

        // Finally: does this installation actually own one of the signed hosts?
        $matched = $this->hosts->match($accepted['domains']);

        if (null === $matched) {
            return EditionProfile::inactive(EditionProfile::REASON_DOMAIN, $accepted['package'], $accepted['domains'], $accepted['version'], $accepted['expiresAt'], $accepted['key']);
        }

        return EditionProfile::entitled(
            $accepted['package'],
            $matched,
            $accepted['domains'],
            $accepted['maxDomains'],
            $accepted['version'],
            $accepted['issuedAt'],
            $accepted['startsAt'],
            $accepted['expiresAt'],
            $accepted['lifetime'],
            $accepted['verifiedAt'],
            $accepted['key'],
        );
    }
}
