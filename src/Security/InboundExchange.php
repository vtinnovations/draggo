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

use Symfony\Component\HttpFoundation\Request;
use Vtinnovations\Draggo\Draggo;

/**
 * Authenticates a server-initiated push from the V&T registry.
 *
 * This request arrives without a backend session and without a browser CSRF
 * token, because it is server-to-server. That makes cryptographic verification
 * the ONLY thing standing between the endpoint and anyone on the internet.
 *
 * A claimed web origin proves nothing here: Origin, Referer, User-Agent, source
 * IP and reverse DNS are all attacker-controlled or spoofable, so none of them
 * are consulted. What is required is a valid Ed25519 signature over
 * vt-one/request-sig-v1 — method, exact path, request id, timestamp, nonce and
 * the SHA-256 of the RAW body bytes.
 *
 * The key-id header only selects which pinned key to use; it is not part of the
 * signed message and is never treated as a credential. The same request id,
 * timestamp and nonce also appear in the body, and both copies must agree, so a
 * signed envelope cannot be re-pointed at different content.
 */
final class InboundExchange
{
    /** Header names as sent by the registry. */
    public const H_REQUEST = 'X-VT-Request-ID';
    public const H_TIMESTAMP = 'X-VT-Timestamp';
    public const H_NONCE = 'X-VT-Nonce';
    public const H_KEY = 'X-VT-Key-ID';
    public const H_SIGNATURE = 'X-VT-Signature';

    /** Narrow acceptance window in both directions. */
    private const WINDOW = 300;

    public function __construct(private readonly TrustAnchors $anchors)
    {
    }

    /**
     * Verify the request and return its decoded body.
     *
     * @param string $rawBody the exact bytes as received; a re-encoded body
     *                        would hash differently and never verify
     *
     * @return array<string, mixed>|null null on any failure, with no detail
     *                                   leaked to the caller's response
     */
    public function authenticate(Request $request, string $rawBody): ?array
    {
        if (!SealedPayload::cryptoAvailable() || $this->anchors->isEmpty()) {
            return null;
        }

        $requestId = (string) $request->headers->get(self::H_REQUEST, '');
        $timestampRaw = (string) $request->headers->get(self::H_TIMESTAMP, '');
        $nonce = (string) $request->headers->get(self::H_NONCE, '');
        $keyId = (string) $request->headers->get(self::H_KEY, '');
        $signature = (string) $request->headers->get(self::H_SIGNATURE, '');

        if ('' === $requestId || '' === $nonce || '' === $keyId || '' === $signature) {
            return null;
        }

        // Decimal digits only: the signed line is the decimal rendering, so a
        // padded or signed variant would sign a different message.
        if (!preg_match('/^\d{1,12}$/', $timestampRaw)) {
            return null;
        }

        $timestamp = (int) $timestampRaw;

        if (abs($timestamp - time()) > self::WINDOW) {
            return null;
        }

        $key = $this->anchors->resolve($keyId, TrustAnchors::PURPOSE_REQUEST);

        if (null === $key) {
            return null;
        }

        $message = SealedPayload::requestMessage(
            $request->getMethod(),
            $request->getPathInfo(),
            $requestId,
            $timestamp,
            $nonce,
            $rawBody,
        );

        if (!SealedPayload::verify($signature, $message, $key)) {
            return null;
        }

        $body = json_decode($rawBody, true);

        if (!\is_array($body)) {
            return null;
        }

        // The duplicated metadata must match the signed headers exactly.
        if (!\is_string($body['request_id'] ?? null) || !hash_equals($requestId, $body['request_id'])) {
            return null;
        }

        if (!\is_string($body['nonce'] ?? null) || !hash_equals($nonce, $body['nonce'])) {
            return null;
        }

        if (($body['timestamp'] ?? null) !== $timestamp) {
            return null;
        }

        // ...and the packet must actually be addressed to this product.
        if ('license_update' !== ($body['action'] ?? null)
            || Draggo::PROJECT !== ($body['project'] ?? null)
            || Draggo::SLUG !== ($body['project_slug'] ?? null)
            || Draggo::PRODUCT_ID !== ($body['product_id'] ?? null)
        ) {
            return null;
        }

        return $body;
    }
}
