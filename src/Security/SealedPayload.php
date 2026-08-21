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
 * Deterministic serialisation + detached-signature primitives for the sealed
 * records the registry issues.
 *
 * Two independent canonical forms, matching the registry byte-for-byte:
 *
 *   vt-one/canonical-json-v1  — drop the top-level "signature" member, sort
 *       object members bytewise ascending (recursively), keep list order,
 *       UTF-8 without pretty printing / escaped slashes / escaped Unicode,
 *       scalars keep their type (false is not "false", null is not 0).
 *
 *   vt-one/request-sig-v1     — six lines joined by "\n" with no trailing
 *       newline: UPPERCASED method, exact path as served, request id, decimal
 *       timestamp, nonce, lowercase hex SHA-256 of the raw body bytes. The
 *       key-id header selects the key and is deliberately NOT a signed line.
 *
 * Objects are decoded as stdClass rather than associative arrays: PHP cannot
 * tell {} from [] once both become an empty array, and that ambiguity would
 * silently change the canonical bytes.
 *
 * Everything fails closed. A missing sodium build, a malformed key, a wrong
 * signature length or a non-strict Base64 body is a verification failure, never
 * a skipped check.
 */
final class SealedPayload
{
    /** Raw Ed25519 public keys are 32 bytes, detached signatures 64. */
    private const KEY_BYTES = 32;
    private const SIG_BYTES = 64;

    /** Guard against absurd nesting in a hostile payload. */
    private const MAX_DEPTH = 32;

    public static function cryptoAvailable(): bool
    {
        return \function_exists('sodium_crypto_sign_verify_detached');
    }

    /**
     * Decode a strict Base64 string. Returns null on any deviation (whitespace,
     * alphabet, padding) so a tampered payload cannot be "repaired" into bytes
     * that differ from what the registry hashed.
     */
    public static function strictBase64(string $value): ?string
    {
        $decoded = base64_decode($value, true);

        return false === $decoded ? null : $decoded;
    }

    /**
     * Decode a sealed JSON document into stdClass/list form.
     *
     * @return object|null null when the bytes are not a JSON object
     */
    public static function decodeDocument(string $json): ?object
    {
        try {
            $decoded = json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_object($decoded) ? $decoded : null;
    }

    /**
     * vt-one/canonical-json-v1 bytes for a decoded document.
     *
     * @return string|null null when the value cannot be encoded deterministically
     */
    public static function canonicalJson(object $document): ?string
    {
        $normalised = self::normalise($document, true, 0);

        if (null === $normalised) {
            return null;
        }

        $encoded = json_encode($normalised, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false === $encoded ? null : $encoded;
    }

    /**
     * The six-line updater message. Callers pass the RAW body bytes; hashing a
     * re-encoded body would verify a document the sender never signed.
     */
    public static function requestMessage(string $method, string $path, string $requestId, int $timestamp, string $nonce, string $rawBody): string
    {
        return implode("\n", [
            strtoupper($method),
            $path,
            $requestId,
            (string) $timestamp,
            $nonce,
            hash('sha256', $rawBody),
        ]);
    }

    /**
     * Verify a detached Ed25519 signature.
     *
     * @param string $signatureB64 Base64 detached signature
     * @param string $publicKey    raw 32-byte public key
     */
    public static function verify(string $signatureB64, string $message, string $publicKey): bool
    {
        if (!self::cryptoAvailable()) {
            return false;
        }

        $signature = self::strictBase64($signatureB64);

        if (null === $signature || self::SIG_BYTES !== \strlen($signature) || self::KEY_BYTES !== \strlen($publicKey)) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        } catch (\Throwable) {
            // A sodium build that rejects the inputs is a failed verification.
            return false;
        }
    }

    /**
     * Exact-byte tripwire: MD5 of the decoded record bytes compared in constant
     * time. MD5 proves the bytes were not edited after the registry sealed them
     * — it is never proof of authenticity, so callers must verify the envelope
     * signature BEFORE trusting the digest they compare against.
     */
    public static function matchesDigest(string $exactBytes, string $expectedMd5): bool
    {
        return hash_equals(md5($exactBytes), strtolower(trim($expectedMd5)));
    }

    /**
     * Recursively sort object members and validate that every scalar survives a
     * round trip unchanged.
     *
     * @param bool $root drop the top-level "signature" member
     *
     * @return mixed|null null when the value is not canonicalisable
     */
    private static function normalise(mixed $value, bool $root, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);

            if ($root) {
                unset($members['signature']);
            }

            // Bytewise ascending, matching the registry's sort.
            ksort($members, SORT_STRING);

            $out = new \stdClass();

            foreach ($members as $name => $member) {
                $normalised = self::normalise($member, false, $depth + 1);

                if (null === $normalised && null !== $member) {
                    return null;
                }

                $out->{$name} = $normalised;
            }

            return $out;
        }

        if (\is_array($value)) {
            // Lists keep their order exactly; the registry sorts before signing.
            $out = [];

            foreach ($value as $item) {
                $normalised = self::normalise($item, false, $depth + 1);

                if (null === $normalised && null !== $item) {
                    return null;
                }

                $out[] = $normalised;
            }

            return $out;
        }

        // Scalars pass through with their type intact. Floats are rejected
        // because their JSON rendering is not portable between languages.
        if (null === $value || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        return null;
    }
}
