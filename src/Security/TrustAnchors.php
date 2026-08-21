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

namespace Vtinnovations\Draggo\Security;

/**
 * The pinned verification keys the registry's sealed records are checked
 * against.
 *
 * Only PUBLIC key material lives here — signing keys never leave V&T
 * infrastructure, and no shared secret, API password or bearer token is used as
 * a trust root. The material arrives as fragments (see the container
 * parameters) so the deployed artefact carries no single greppable literal, and
 * every reassembled key is checked against its published SHA-256 fingerprint
 * before it is allowed into the ring.
 *
 * A key that fails reassembly, decoding, length or fingerprint validation is
 * dropped rather than trusted. An EMPTY ring is a hard failure at use time
 * (category "signing_key_store_empty"), never a reason to accept an unsigned or
 * unverified record.
 */
final class TrustAnchors
{
    /** Only Ed25519 is accepted under the current profile. */
    public const ALGORITHM = 'ed25519';

    /** Purposes a key may be approved for. */
    public const PURPOSE_RECORD = 'record';
    public const PURPOSE_ENVELOPE = 'envelope';
    public const PURPOSE_REQUEST = 'request';

    /** Diagnostic categories. Never a bypass — only a support hint. */
    public const REASON_EMPTY = 'signing_key_store_empty';
    public const REASON_UNKNOWN = 'unknown_signing_key';
    public const REASON_ALGORITHM = 'unsupported_signature_algorithm';

    /**
     * key id => ['key' => raw bytes, 'purposes' => list<string>, 'from' => int, 'until' => int|null]
     *
     * @var array<string, array{key: string, purposes: list<string>, from: int, until: int|null}>
     */
    private array $ring = [];

    /**
     * @param array<string, array{material: list<string>, fingerprint: string, algorithm: string, purposes: list<string>, from: int, until: int|null}> $anchors
     */
    public function __construct(array $anchors)
    {
        foreach ($anchors as $keyId => $anchor) {
            $keyId = (string) $keyId;

            if ('' === $keyId || self::ALGORITHM !== ($anchor['algorithm'] ?? '')) {
                continue;
            }

            $key = self::assemble($anchor['material'] ?? [], (string) ($anchor['fingerprint'] ?? ''));

            if (null === $key) {
                continue;
            }

            $purposes = array_values(array_filter(
                $anchor['purposes'] ?? [],
                static fn (mixed $p): bool => \in_array($p, [self::PURPOSE_RECORD, self::PURPOSE_ENVELOPE, self::PURPOSE_REQUEST], true),
            ));

            if ([] === $purposes) {
                continue;
            }

            $this->ring[$keyId] = [
                'key' => $key,
                'purposes' => $purposes,
                'from' => (int) ($anchor['from'] ?? 0),
                'until' => null !== ($anchor['until'] ?? null) ? (int) $anchor['until'] : null,
            ];
        }
    }

    /**
     * True when no usable key survived validation. Callers must fail closed.
     */
    public function isEmpty(): bool
    {
        return [] === $this->ring;
    }

    /** @return list<string> */
    public function keyIds(): array
    {
        return array_keys($this->ring);
    }

    /**
     * Resolve one advertised key id for a purpose. The id is only a selector:
     * it is never treated as key material, and a key that is not in the ring is
     * rejected instead of being fetched from the packet that advertises it.
     *
     * @return string|null raw public key bytes
     */
    public function resolve(string $keyId, string $purpose, ?int $now = null): ?string
    {
        $entry = $this->ring[$keyId] ?? null;

        if (null === $entry || !\in_array($purpose, $entry['purposes'], true)) {
            return null;
        }

        return $this->usable($entry, $now ?? time()) ? $entry['key'] : null;
    }

    /**
     * Every currently usable key for a purpose. The sealed record names no key
     * id, so its detached signature is tried against each record-purpose key.
     *
     * @return list<string> raw public key bytes
     */
    public function candidates(string $purpose, ?int $now = null): array
    {
        $now ??= time();
        $out = [];

        foreach ($this->ring as $entry) {
            if (\in_array($purpose, $entry['purposes'], true) && $this->usable($entry, $now)) {
                $out[] = $entry['key'];
            }
        }

        return $out;
    }

    /**
     * @param array{key: string, purposes: list<string>, from: int, until: int|null} $entry
     */
    private function usable(array $entry, int $now): bool
    {
        if ($now < $entry['from']) {
            return false;
        }

        return null === $entry['until'] || $now <= $entry['until'];
    }

    /**
     * Rebuild key bytes from their fragments and prove they are the approved
     * material before use. The fingerprint check is an integrity guard on the
     * reassembly, never a substitute for the key or for a signature.
     *
     * @param list<string> $fragments
     */
    private static function assemble(array $fragments, string $fingerprintPrefix): ?string
    {
        if ([] === $fragments || '' === $fingerprintPrefix) {
            return null;
        }

        $encoded = '';

        foreach ($fragments as $fragment) {
            if (!\is_string($fragment) || '' === $fragment) {
                return null;
            }

            $encoded .= strrev($fragment);
        }

        $raw = SealedPayload::strictBase64($encoded);

        if (null === $raw || 32 !== \strlen($raw)) {
            return null;
        }

        return str_starts_with(hash('sha256', $raw), strtolower($fingerprintPrefix)) ? $raw : null;
    }
}
