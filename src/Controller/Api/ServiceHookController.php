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

namespace Vtinnovations\Draggo\Controller\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\History\ExchangeJournal;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Security\InboundExchange;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * Public receiver for registry-initiated record updates.
 *
 * Deliberately thin: it enforces request shape (method, media type, size) and
 * then hands off. Authentication lives in {@see InboundExchange}, verification
 * in {@see EditionResolver}, persistence in {@see ActivationStore} and replay
 * defence in {@see ExchangeJournal}. It holds no keys, no digests and no domain
 * policy of its own, and it cannot write anywhere except through the store —
 * there is no path in the request, and no source file is ever touched.
 *
 * Failures are generic on purpose. Telling an unauthenticated caller WHICH
 * check failed hands them a tuning oracle, so everything that is not a clean
 * success collapses into 401/403 with no detail.
 */
final class ServiceHookController
{
    /** Cap the body before parsing it. */
    private const MAX_BODY = 262144;

    public function __construct(
        private readonly InboundExchange $exchange,
        private readonly EditionResolver $resolver,
        private readonly ActivationStore $store,
        private readonly ExchangeJournal $journal,
        private readonly HostInventory $hosts,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // GET must be a clear 405, not a 404: the endpoint exists, and a 404
        // would send the registry hunting for a routing problem that is not
        // there.
        if (!$request->isMethod('POST')) {
            return new Response('', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }

        if (!str_contains(strtolower((string) $request->headers->get('Content-Type', '')), 'application/json')) {
            return new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $length = (int) $request->headers->get('Content-Length', '0');
        $raw = $request->getContent();

        if ($length > self::MAX_BODY || \strlen($raw) > self::MAX_BODY) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $body = $this->exchange->authenticate($request, $raw);

        if (null === $body) {
            return $this->refuse('not_authenticated');
        }

        return $this->handle($body, $raw);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, string $raw): Response
    {
        $requestId = (string) $body['request_id'];
        $nonce = (string) $body['nonce'];
        $fingerprint = $this->journal->fingerprint($raw);

        $outcome = $this->store->transaction(function () use ($body, $requestId, $nonce, $fingerprint): array {
            // Exact retry of an already-processed request is idempotent; the
            // same id with different content is someone reusing an envelope.
            $seen = $this->journal->find($requestId);

            if (null !== $seen) {
                return hash_equals($seen['fingerprint'], $fingerprint)
                    ? ['code' => Response::HTTP_OK, 'status' => 'already_processed', 'version' => $seen['version']]
                    : ['code' => Response::HTTP_CONFLICT, 'status' => 'refused', 'version' => 0];
            }

            if ($this->journal->nonceUsed($nonce)) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            $seal = $body['integrity'] ?? null;
            $payloadB64 = $body['license_payload_b64'] ?? null;

            if (!\is_array($seal) || !\is_string($payloadB64)) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            $bytes = SealedPayload::strictBase64($payloadB64);

            if (null === $bytes || '' === $bytes || \strlen($bytes) > ActivationStore::MAX_RECORD_BYTES) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            $record = $this->resolver->authenticate($bytes, $seal);
            $accepted = null !== $record ? $this->resolver->accept($record) : null;

            if (null === $accepted) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            // The push must name the same host it is updating, and that host
            // must belong to this installation.
            if (!\is_string($body['domain'] ?? null)
                || HostInventory::normalise($body['domain']) !== $accepted['domain']
                || null === $this->hosts->match($accepted['domains'])
            ) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            // An older or equal version must not displace newer state.
            $stored = $this->store->read();

            if (null !== $stored) {
                $existing = $this->resolver->authenticate($stored['bytes'], $stored['seal']);
                $existingAccepted = null !== $existing ? $this->resolver->accept($existing) : null;

                if (null !== $existingAccepted && $accepted['version'] <= $existingAccepted['version']) {
                    return ['code' => Response::HTTP_CONFLICT, 'status' => 'refused', 'version' => $existingAccepted['version']];
                }
            }

            $committed = $this->store->commit(
                $bytes,
                $seal,
                fn (string $b, array $s): bool => null !== $this->resolver->authenticate($b, $s),
            );

            if (!$committed) {
                return ['code' => Response::HTTP_FORBIDDEN, 'status' => 'refused', 'version' => 0];
            }

            $this->journal->remember($requestId, $nonce, $fingerprint, $accepted['version']);
            $this->resolver->forget();

            return ['code' => Response::HTTP_OK, 'status' => 'updated', 'version' => $accepted['version']];
        });

        if (!\is_array($outcome)) {
            return $this->refuse('store_unavailable');
        }

        $this->logger->info('Draggo registry push', [
            'operation' => 'license_update',
            'result' => $outcome['status'],
            'request_id' => $requestId,
            'http_status' => $outcome['code'],
            'applied_version' => $outcome['version'],
        ]);

        if (Response::HTTP_OK !== $outcome['code']) {
            return new Response('', $outcome['code']);
        }

        return new JsonResponse([
            'status' => $outcome['status'],
            'request_id' => $requestId,
            'license_version' => $outcome['version'],
        ]);
    }

    private function refuse(string $category): Response
    {
        // Category goes to the log, never to the caller.
        $this->logger->warning('Draggo registry push refused', ['result' => $category]);

        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
