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

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Outbound exchange with the V&T registry: first activation and administrator
 * refresh.
 *
 * Both directions carry a COMPLETE record, never a delta, and nothing is stored
 * until the whole verification chain has passed.
 *
 * The single most important rule here: a network error, a timeout, a TLS
 * failure, a malformed body or a 5xx must NEVER erase a working licence. Losing
 * a customer's activation because the licence server had a bad afternoon is a
 * worse failure than briefly trusting stored state, so transport problems are
 * reported and the existing record is left exactly as it was.
 */
final class RegistryClient
{
    /** Refuse to buffer an oversized body. */
    private const MAX_BODY = 262144;

    /** Reject a reply whose clock is implausibly far from ours. */
    private const MAX_SKEW = 300;

    /** Outcome categories. Safe for logs; never shown verbatim to a browser. */
    public const OK = 'applied';
    public const DENIED = 'denied';
    public const UNAVAILABLE = 'unavailable';
    public const REJECTED = 'not_authentic';
    public const STALE = 'version_rollback';
    public const NO_DOMAIN = 'no_configured_domain';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ActivationStore $store,
        private readonly EditionResolver $resolver,
        private readonly HostInventory $hosts,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Activate a freshly entered key.
     *
     * @return array{status: string, version: int}
     */
    public function activate(string $key): array
    {
        return $this->exchange('activate', trim($key), null);
    }

    /**
     * Re-verify the stored key. An optional replacement key swaps the licence
     * in place without a separate removal step.
     *
     * @return array{status: string, version: int}
     */
    public function refresh(?string $replacementKey = null): array
    {
        $profile = $this->resolver->profile();
        $key = trim($replacementKey ?? '') ?: $profile->key();

        if ('' === $key) {
            return ['status' => self::DENIED, 'version' => 0];
        }

        return $this->exchange('refresh', $key, $profile->version);
    }

    /**
     * @return array{status: string, version: int}
     */
    private function exchange(string $action, string $key, ?int $currentVersion): array
    {
        if ('' === $key || \strlen($key) > 190) {
            return ['status' => self::DENIED, 'version' => 0];
        }

        $domain = $this->hosts->verificationHost();

        if (null === $domain) {
            return ['status' => self::NO_DOMAIN, 'version' => 0];
        }

        $requestId = self::token();
        $payload = [
            'action' => $action,
            'project' => Draggo::PROJECT,
            'project_slug' => Draggo::SLUG,
            'product_id' => Draggo::PRODUCT_ID,
            'license_key' => $key,
            'domain' => $domain,
            'request_id' => $requestId,
            'timestamp' => time(),
            'nonce' => self::token(),
        ];

        if ('refresh' === $action) {
            $payload['current_license_version'] = $currentVersion ?? 0;
        }

        $started = microtime(true);

        try {
            $response = $this->client->request('POST', RegistryEndpoints::verify(), [
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'json' => $payload,
                // Fixed destination: never follow a redirect to another host.
                'max_redirects' => 0,
                'timeout' => 8,
                'max_duration' => 15,
                // TLS peer and hostname verification stay on. There is no
                // switch to turn them off, by design.
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            $status = $response->getStatusCode();
            $contentType = (string) ($response->getHeaders(false)['content-type'][0] ?? '');
            $body = $response->getContent(false);
        } catch (HttpExceptionInterface|\Throwable) {
            // Transport failure: keep whatever is stored.
            $this->note($action, self::UNAVAILABLE, $requestId, 0, $started, 0);

            return ['status' => self::UNAVAILABLE, 'version' => 0];
        }

        if ($status >= 500) {
            $this->note($action, self::UNAVAILABLE, $requestId, $status, $started, 0);

            return ['status' => self::UNAVAILABLE, 'version' => 0];
        }

        if (\strlen($body) > self::MAX_BODY || !str_contains(strtolower($contentType), 'application/json')) {
            $this->note($action, self::REJECTED, $requestId, $status, $started, 0);

            return ['status' => self::REJECTED, 'version' => 0];
        }

        $applied = $this->apply($body, $requestId, $domain, $currentVersion);
        $this->note($action, $applied['status'], $requestId, $status, $started, $applied['version']);

        return $applied;
    }

    /**
     * Validate and store a complete package.
     *
     * @return array{status: string, version: int}
     */
    private function apply(string $body, string $requestId, string $domain, ?int $currentVersion): array
    {
        $envelope = json_decode($body, true);

        if (!\is_array($envelope)) {
            return ['status' => self::REJECTED, 'version' => 0];
        }

        // An authenticated denial is a real answer: report it, but never let it
        // delete a licence on its own — that is the removal action's job.
        if ('valid' !== ($envelope['status'] ?? null)) {
            return ['status' => self::DENIED, 'version' => 0];
        }

        // Correlate the reply with the request we actually made.
        if (!\is_string($envelope['request_id'] ?? null) || !hash_equals($requestId, $envelope['request_id'])) {
            return ['status' => self::REJECTED, 'version' => 0];
        }

        $serverTime = $envelope['server_time'] ?? null;

        if (\is_int($serverTime) && abs($serverTime - time()) > self::MAX_SKEW) {
            return ['status' => self::REJECTED, 'version' => 0];
        }

        $seal = $envelope['integrity'] ?? null;
        $payloadB64 = $envelope['license_payload_b64'] ?? null;

        if (!\is_array($seal) || !\is_string($payloadB64)) {
            return ['status' => self::REJECTED, 'version' => 0];
        }

        $bytes = SealedPayload::strictBase64($payloadB64);

        if (null === $bytes || '' === $bytes || \strlen($bytes) > ActivationStore::MAX_RECORD_BYTES) {
            return ['status' => self::REJECTED, 'version' => 0];
        }

        return $this->persist($bytes, $seal, $domain, $currentVersion);
    }

    /**
     * The verify-then-commit half, run under the store lock so a concurrent
     * refresh cannot interleave a rollback.
     *
     * @param array<string, mixed> $seal
     *
     * @return array{status: string, version: int}
     */
    private function persist(string $bytes, array $seal, string $domain, ?int $currentVersion): array
    {
        $outcome = $this->store->transaction(function () use ($bytes, $seal, $domain, $currentVersion): array {
            $record = $this->resolver->authenticate($bytes, $seal);

            if (null === $record) {
                return ['status' => self::REJECTED, 'version' => 0];
            }

            $accepted = $this->resolver->accept($record);

            if (null === $accepted) {
                return ['status' => self::REJECTED, 'version' => 0];
            }

            // The signed operation host must be the host we asked about.
            if (!hash_equals($domain, $accepted['domain'])) {
                return ['status' => self::REJECTED, 'version' => 0];
            }

            // ...and this installation must actually own one of the signed hosts.
            if (null === $this->hosts->match($accepted['domains'])) {
                return ['status' => self::NO_DOMAIN, 'version' => 0];
            }

            // Rollback prevention: never let an older package replace newer state.
            $stored = $this->store->read();

            if (null !== $stored) {
                $existing = $this->resolver->authenticate($stored['bytes'], $stored['seal']);
                $existingAccepted = null !== $existing ? $this->resolver->accept($existing) : null;

                if (null !== $existingAccepted && $accepted['version'] < $existingAccepted['version']) {
                    return ['status' => self::STALE, 'version' => $existingAccepted['version']];
                }
            }

            if (null !== $currentVersion && $accepted['version'] < $currentVersion) {
                return ['status' => self::STALE, 'version' => $accepted['version']];
            }

            // commit() re-reads and revalidates the live pair, rolling back if
            // the activated state does not verify.
            $committed = $this->store->commit(
                $bytes,
                $seal,
                fn (string $b, array $s): bool => null !== $this->resolver->authenticate($b, $s),
            );

            if (!$committed) {
                return ['status' => self::REJECTED, 'version' => 0];
            }

            $this->resolver->forget();

            return ['status' => self::OK, 'version' => $accepted['version']];
        });

        return \is_array($outcome) ? $outcome : ['status' => self::REJECTED, 'version' => 0];
    }

    /**
     * Operational breadcrumb only.
     *
     * Deliberately absent: the packet, the licence key, its length or
     * fingerprint, the raw payload, the digest, any signature, the nonce and
     * the remote error body. Those are exactly the values the integration
     * specification forbids in ordinary logs, and a "redacted" packet dump
     * would still carry most of them.
     */
    private function note(string $action, string $result, string $requestId, int $httpStatus, float $started, int $version): void
    {
        $this->logger->info('Draggo registry exchange', [
            'operation' => $action,
            'result' => $result,
            'request_id' => $requestId,
            'http_status' => $httpStatus,
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
            'applied_version' => $version,
        ]);
    }

    /** Cryptographically random, single-use. */
    private static function token(): string
    {
        return bin2hex(random_bytes(16));
    }
}
