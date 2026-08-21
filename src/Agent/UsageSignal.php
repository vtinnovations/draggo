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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\Settings\EditionResolver;

/**
 * The two server-to-server usage signals. Both go to the same fixed endpoint
 * and are deliberately NOT merged into one telemetry packet — they are separate
 * events with separate payloads and separate triggers.
 *
 *   invocation  {"project": "...", "domain": "..."}
 *               at most once per application invocation. Never carries the key.
 *
 *   moduleEntry {"domain": "...", "key": "..."}
 *               exactly once per authenticated backend session, the first time
 *               the licence section or a protected Draggo module is opened.
 *
 * The module-entry event is the ONLY place the full key leaves the server, and
 * only ever to the registry. It is read from a cryptographically authenticated
 * record — never from request input, a cookie, a template or browser storage —
 * and it must not appear in a log, a session marker, a diagnostic or any
 * response.
 *
 * The session claim is taken BEFORE delivery, so a timeout cannot turn into a
 * retry storm: one attempt per session, success or not. Parallel tabs race for
 * the same claim, and PHP's per-session lock makes that read-modify-write
 * atomic. Logging out and back in may claim once more.
 *
 * Nothing here can affect entitlement, rendering or the outcome of any request.
 */
final class UsageSignal
{
    /** Session key for the module-entry claim. Holds no key and no domain. */
    private const CLAIM = '_draggo_entry_claim';

    /** Once per PHP invocation, matching the "per invocation" contract. */
    private bool $invocationSent = false;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly EditionResolver $resolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Per-invocation signal. Project and normalised domain only.
     */
    public function invocation(): void
    {
        if ($this->invocationSent) {
            return;
        }

        $this->invocationSent = true;

        $profile = $this->resolver->profile();
        $domain = $profile->matchedDomain;

        if (null === $domain) {
            return;
        }

        $this->send(['project' => Draggo::PROJECT, 'domain' => $domain]);
    }

    /**
     * First load of the licence section or a protected module in this
     * authenticated backend session.
     *
     * Called from the backend surfaces themselves rather than from a kernel
     * listener or from entitlement evaluation, so it tracks actual module entry
     * and not every service resolution.
     */
    public function moduleEntry(): void
    {
        $profile = $this->resolver->profile();
        $key = $profile->key();
        $domain = $profile->matchedDomain;

        // No authentic key means no event — a key is never invented, and an
        // unlicensed installation stays silent.
        if ('' === $key || null === $domain || !$profile->active) {
            return;
        }

        if (!$this->claim()) {
            return;
        }

        $this->send(['domain' => $domain, 'key' => $key]);
    }

    /**
     * Atomically claim this session for this project.
     *
     * Deliberately not a process static (dies with the request), not a database
     * flag (would be permanent) and not browser storage (client-controlled).
     * The marker is a bare project slug — no key, no key digest, no domain, no
     * session identifier — so dumping the session reveals nothing.
     */
    private function claim(): bool
    {
        try {
            $request = $this->requestStack->getMainRequest();

            if (null === $request || !$request->hasSession()) {
                return false;
            }

            $session = $request->getSession();

            if (!$session->isStarted()) {
                return false;
            }

            $claimed = $session->get(self::CLAIM);

            if (\is_array($claimed) && \in_array(Draggo::SLUG, $claimed, true)) {
                return false;
            }

            $claimed = \is_array($claimed) ? $claimed : [];
            $claimed[] = Draggo::SLUG;
            $session->set(self::CLAIM, $claimed);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fire and forget. No redirects, no response processing, short timeouts,
     * TLS verification on, and every failure swallowed: a telemetry problem
     * must never surface to an editor or change what the module renders.
     *
     * @param array<string, string> $payload
     */
    private function send(array $payload): void
    {
        try {
            $this->client->request('POST', RegistryEndpoints::signal(), [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
                'max_redirects' => 0,
                'timeout' => 3,
                'max_duration' => 5,
                'verify_peer' => true,
                'verify_host' => true,
            ])->getStatusCode();
        } catch (\Throwable) {
            // Intentionally silent, and intentionally not retried.
        }
    }
}
