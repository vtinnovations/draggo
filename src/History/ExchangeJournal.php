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

namespace Vtinnovations\Draggo\History;

use Vtinnovations\Draggo\Settings\ActivationStore;

/**
 * Replay and idempotency ledger for inbound server-to-server exchanges.
 *
 * Answers exactly one question: has this request id been processed, and was it
 * byte-identical to what arrived then?
 *
 *   - unseen id                        → process it
 *   - same id, same authenticated body → replay the recorded outcome
 *   - same id, different body          → refuse; someone is reusing an id
 *   - nonce seen before                → refuse
 *
 * Only digests are kept. The journal never stores a nonce, a body, a key or a
 * signature in the clear, so a leaked ledger reveals nothing reusable.
 *
 * Entries outlive the retry window and are pruned by age and by count, so the
 * file cannot grow without bound. Single-node by construction: a clustered
 * deployment must point this at a shared transactional store instead (see the
 * deployment notes in the completion report).
 *
 * It lives beside the bundle's other ledger of past states rather than with the
 * verification code, so no one directory holds the whole activation flow.
 */
final class ExchangeJournal
{
    private const FILE = 'journal.json';

    /** Keep well beyond any sane retry window, then forget. */
    private const RETENTION = 7 * 86400;

    /** Hard cap so a flood cannot grow the file indefinitely. */
    private const MAX_ENTRIES = 500;

    public function __construct(private readonly ActivationStore $store)
    {
    }

    /**
     * Look up a previously processed request.
     *
     * @return array{fingerprint: string, version: int, at: int}|null
     */
    public function find(string $requestId): ?array
    {
        $entry = $this->load()['requests'][$this->digest($requestId)] ?? null;

        if (!\is_array($entry) || !isset($entry['fingerprint'], $entry['version'], $entry['at'])) {
            return null;
        }

        return [
            'fingerprint' => (string) $entry['fingerprint'],
            'version' => (int) $entry['version'],
            'at' => (int) $entry['at'],
        ];
    }

    /** True when this nonce has already been spent. */
    public function nonceUsed(string $nonce): bool
    {
        return isset($this->load()['nonces'][$this->digest($nonce)]);
    }

    /**
     * Record a processed exchange. Must be called inside the store transaction
     * that also applied the record, so a crash cannot leave the journal
     * claiming work that never landed.
     *
     * @param string $fingerprint digest of the authenticated body
     */
    public function remember(string $requestId, string $nonce, string $fingerprint, int $version): void
    {
        $data = $this->load();
        $now = time();

        $data['requests'][$this->digest($requestId)] = [
            'fingerprint' => $fingerprint,
            'version' => $version,
            'at' => $now,
        ];
        $data['nonces'][$this->digest($nonce)] = $now;

        $this->save($this->prune($data, $now));
    }

    /**
     * Digest of the authenticated body, used to tell an exact retry from an id
     * being reused with different content.
     */
    public function fingerprint(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }

    /**
     * One-way, salted with a fixed domain string so a journal entry cannot be
     * matched back against a guessed nonce list.
     */
    private function digest(string $value): string
    {
        return hash('sha256', 'vt-one/journal-v1:' . $value);
    }

    /**
     * @param array{requests: array<string, mixed>, nonces: array<string, mixed>} $data
     *
     * @return array{requests: array<string, mixed>, nonces: array<string, mixed>}
     */
    private function prune(array $data, int $now): array
    {
        $cutoff = $now - self::RETENTION;

        foreach ($data['requests'] as $key => $entry) {
            if (!\is_array($entry) || (int) ($entry['at'] ?? 0) < $cutoff) {
                unset($data['requests'][$key]);
            }
        }

        foreach ($data['nonces'] as $key => $at) {
            if ((int) $at < $cutoff) {
                unset($data['nonces'][$key]);
            }
        }

        // Oldest first, so slicing keeps the most recent window.
        if (\count($data['requests']) > self::MAX_ENTRIES) {
            uasort($data['requests'], static fn (array $a, array $b): int => ((int) $a['at']) <=> ((int) $b['at']));
            $data['requests'] = \array_slice($data['requests'], -self::MAX_ENTRIES, null, true);
        }

        if (\count($data['nonces']) > self::MAX_ENTRIES) {
            asort($data['nonces']);
            $data['nonces'] = \array_slice($data['nonces'], -self::MAX_ENTRIES, null, true);
        }

        return $data;
    }

    /**
     * @return array{requests: array<string, mixed>, nonces: array<string, mixed>}
     */
    private function load(): array
    {
        $file = $this->file();
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return [
            'requests' => \is_array($data['requests'] ?? null) ? $data['requests'] : [],
            'nonces' => \is_array($data['nonces'] ?? null) ? $data['nonces'] : [],
        ];
    }

    /**
     * @param array{requests: array<string, mixed>, nonces: array<string, mixed>} $data
     */
    private function save(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        if (false === $json) {
            return;
        }

        $file = $this->file();
        $tmp = $file . '.stage';

        if (false !== @file_put_contents($tmp, $json, LOCK_EX) && @rename($tmp, $file)) {
            @chmod($file, 0640);

            return;
        }

        @unlink($tmp);
    }

    private function file(): string
    {
        return $this->store->directory() . '/' . self::FILE;
    }
}
