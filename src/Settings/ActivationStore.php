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
 * The one authoritative home of the activation record.
 *
 * Two files that must always agree, kept under var/draggo/state (private, never
 * web-served, never committed):
 *
 *   record.json — the EXACT bytes the registry issued. Never re-encoded: the
 *                 digest and the detached signature both cover these bytes, so
 *                 a pretty-print or a key reorder on the way through would
 *                 invalidate a perfectly good record.
 *   record.seal — the authenticated envelope carrying the expected digest.
 *
 * Writes are a single logical transaction under an exclusive lock: validate,
 * stage to same-filesystem temp files, fsync, re-read and revalidate the stage,
 * back up the live pair, swap both, revalidate what is now live, and roll the
 * backup back if that last check fails. A caller can therefore never observe
 * record bytes paired with someone else's envelope.
 *
 * There is no path parameter anywhere: the location is derived from the project
 * directory, so no request can steer a write.
 */
final class ActivationStore
{
    private const DIR = '/var/draggo/state';
    private const RECORD = 'record.json';
    private const SEAL = 'record.seal';
    private const LOCK = '.lock';

    /** Refuse absurd payloads long before they reach the parser. */
    public const MAX_RECORD_BYTES = 65536;

    private readonly string $dir;

    /** @var array{bytes: string, seal: array<string, mixed>}|null */
    private ?array $cache = null;

    private bool $cached = false;

    /** Re-entrancy depth of {@see transaction()}. */
    private int $depth = 0;

    public function __construct(string $projectDir)
    {
        $this->dir = rtrim($projectDir, '/\\') . self::DIR;
    }

    /**
     * The stored pair, or null when nothing is activated.
     *
     * Returns null when either half is missing — a record without its envelope
     * is unauthenticated and must never be treated as state.
     *
     * @return array{bytes: string, seal: array<string, mixed>}|null
     */
    public function read(): ?array
    {
        if ($this->cached) {
            return $this->cache;
        }

        $this->cached = true;

        return $this->cache = $this->readPair($this->path(self::RECORD), $this->path(self::SEAL));
    }

    /**
     * Replace the stored pair atomically.
     *
     * @param string                                             $bytes    exact record bytes
     * @param array<string, mixed>                               $seal     authenticated envelope
     * @param callable(string, array<string, mixed>): bool|null  $verify   re-run over the staged and the live pair
     */
    public function commit(string $bytes, array $seal, ?callable $verify = null): bool
    {
        if ('' === $bytes || \strlen($bytes) > self::MAX_RECORD_BYTES) {
            return false;
        }

        if (null !== $verify && !$verify($bytes, $seal)) {
            return false;
        }

        $sealJson = json_encode($seal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $sealJson) {
            return false;
        }

        return $this->transaction(function () use ($bytes, $sealJson, $seal, $verify): bool {
            $record = $this->path(self::RECORD);
            $sealFile = $this->path(self::SEAL);
            $stagedRecord = $record . '.stage';
            $stagedSeal = $sealFile . '.stage';

            try {
                // 1. stage on the same filesystem so the swap can be a rename
                if (!$this->stage($stagedRecord, $bytes) || !$this->stage($stagedSeal, $sealJson)) {
                    return false;
                }

                // 2. prove the staged pair reads back exactly as intended
                $staged = $this->readPair($stagedRecord, $stagedSeal);

                if (null === $staged || $staged['bytes'] !== $bytes) {
                    return false;
                }

                if (null !== $verify && !$verify($staged['bytes'], $staged['seal'])) {
                    return false;
                }

                // 3. back up the currently live pair so step 5 can undo
                $backup = $this->backup();

                // 4. activate both halves
                if (!@rename($stagedRecord, $record)) {
                    return false;
                }

                if (!@rename($stagedSeal, $sealFile)) {
                    // The record moved but the envelope did not: restore both
                    // rather than leave a mismatched pair behind.
                    $this->restore($backup);

                    return false;
                }

                @chmod($record, 0640);
                @chmod($sealFile, 0640);

                // 5. revalidate what is actually live now; undo on any doubt
                $live = $this->readPair($record, $sealFile);

                if (null === $live || $live['bytes'] !== $bytes || (null !== $verify && !$verify($live['bytes'], $live['seal']))) {
                    $this->restore($backup);

                    return false;
                }

                $this->discard($backup);
                $this->cache = $live;
                $this->cached = true;

                return true;
            } finally {
                @unlink($stagedRecord);
                @unlink($stagedSeal);
            }
        });
    }

    /**
     * Drop the activation. Both halves go together; a surviving envelope would
     * otherwise look like state on the next read.
     */
    public function clear(): bool
    {
        return $this->transaction(function (): bool {
            foreach ([self::RECORD, self::SEAL] as $name) {
                $file = $this->path($name);

                if (is_file($file) && !@unlink($file)) {
                    return false;
                }
            }

            $this->cache = null;
            $this->cached = true;

            return true;
        });
    }

    /**
     * Run a callback under the store's exclusive lock. Used by callers that
     * must serialise a read-decide-write cycle against parallel requests.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T|false false when the lock could not be taken
     */
    public function transaction(callable $fn): mixed
    {
        // flock() is per open-file-description, so a nested transaction opening
        // its own handle would block on the lock this process already holds.
        // Callers legitimately wrap commit()/clear() in a read-decide-write
        // cycle, so re-entry runs inline under the outermost lock.
        if ($this->depth > 0) {
            return $fn();
        }

        $this->ensureDir();

        $handle = @fopen($this->path(self::LOCK), 'c');

        if (false === $handle) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            // Someone else may have written while we waited for the lock.
            $this->cached = false;
            ++$this->depth;

            try {
                return $fn();
            } finally {
                --$this->depth;
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * The private state directory, created on demand. Neighbouring ledgers
     * (replay journal) live here too so they share its permissions and its
     * exclusion from the document root.
     */
    public function directory(): string
    {
        return $this->ensureDir();
    }

    /**
     * @return array{bytes: string, seal: array<string, mixed>}|null
     */
    private function readPair(string $recordFile, string $sealFile): ?array
    {
        if (!is_file($recordFile) || !is_file($sealFile)) {
            return null;
        }

        $size = @filesize($recordFile);

        if (false === $size || $size > self::MAX_RECORD_BYTES) {
            return null;
        }

        $bytes = @file_get_contents($recordFile);
        $sealRaw = @file_get_contents($sealFile);

        if (false === $bytes || false === $sealRaw || '' === $bytes) {
            return null;
        }

        $seal = json_decode($sealRaw, true);

        return \is_array($seal) ? ['bytes' => $bytes, 'seal' => $seal] : null;
    }

    private function stage(string $file, string $contents): bool
    {
        $handle = @fopen($file, 'wb');

        if (false === $handle) {
            return false;
        }

        try {
            if (\strlen($contents) !== @fwrite($handle, $contents)) {
                return false;
            }

            @fflush($handle);

            // Best effort: not every filesystem/stream exposes fsync.
            if (\function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        @chmod($file, 0640);

        return true;
    }

    /**
     * @return array<string, string|null> live file => backup file or null
     */
    private function backup(): array
    {
        $out = [];

        foreach ([self::RECORD, self::SEAL] as $name) {
            $file = $this->path($name);

            if (!is_file($file)) {
                $out[$file] = null;

                continue;
            }

            $bak = $file . '.bak';
            $out[$file] = @copy($file, $bak) ? $bak : null;
        }

        return $out;
    }

    /**
     * @param array<string, string|null> $backup
     */
    private function restore(array $backup): void
    {
        foreach ($backup as $file => $bak) {
            if (null === $bak) {
                // Nothing was there before: leave nothing behind.
                @unlink($file);

                continue;
            }

            @rename($bak, $file);
        }

        $this->cached = false;
        $this->cache = null;
    }

    /**
     * @param array<string, string|null> $backup
     */
    private function discard(array $backup): void
    {
        foreach ($backup as $bak) {
            if (null !== $bak) {
                @unlink($bak);
            }
        }
    }

    private function path(string $name): string
    {
        return $this->ensureDir() . '/' . $name;
    }

    private function ensureDir(): string
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Draggo state directory is not writable.');
        }

        return $this->dir;
    }
}
