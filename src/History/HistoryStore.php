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

use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Editor\ContentSynchronizer;
use Vtinnovations\Draggo\Exception\ElementNotFoundException;

/**
 * Restore points for a container (article/unit): a JSON snapshot of all its
 * content (+ the container's own layout) the user can roll back to. The safety
 * net — there is no per-action undo, but any saved point can be restored.
 * Keeps the most recent N snapshots per container.
 */
final class HistoryStore
{
    private const KEEP = 25;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContentSynchronizer $synchronizer,
    ) {
    }

    /** Capture the current state. Returns the new snapshot id (0 on failure). */
    public function snapshot(int $pid, string $ptable, string $label, int $tstamp): int
    {
        $items = $this->synchronizer->exportContainer($pid, $ptable);
        $layout = ($ptable === 'tl_article')
            ? (string) ($this->connection->fetchOne('SELECT draggo_layout FROM tl_article WHERE id = :id', ['id' => $pid]) ?: '')
            : '';

        $payload = (string) json_encode($this->enc(['items' => $items, 'layout' => $layout]), JSON_UNESCAPED_UNICODE);

        try {
            $this->connection->insert('tl_draggo_history', [
                'tstamp'   => $tstamp,
                'pid'      => $pid,
                'ptable'   => $ptable,
                'label'    => mb_substr(trim($label) ?: 'Wiederherstellungspunkt', 0, 128),
                'snapshot' => $payload,
            ]);
        } catch (\Throwable) {
            return 0; // table not migrated yet → silently skip
        }
        $id = (int) $this->connection->lastInsertId();
        $this->prune($pid, $ptable);

        return $id;
    }

    /**
     * The container (pid + ptable) a snapshot belongs to, or null if unknown.
     * Lets callers authorize a restore against the target page before rolling back.
     *
     * @return array{pid:int,ptable:string}|null
     */
    public function targetOf(int $snapshotId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable FROM tl_draggo_history WHERE id = :id',
            ['id' => $snapshotId],
        );

        return $row === false ? null : ['pid' => (int) $row['pid'], 'ptable' => (string) $row['ptable']];
    }

    /** @return list<array{id:int,tstamp:int,label:string}> */
    public function list(int $pid, string $ptable): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, tstamp, label FROM tl_draggo_history WHERE pid = :pid AND ptable = :pt ORDER BY tstamp DESC, id DESC',
                ['pid' => $pid, 'pt' => $ptable],
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'id'     => (int) $r['id'],
            'tstamp' => (int) $r['tstamp'],
            'label'  => (string) $r['label'],
        ], $rows);
    }

    /**
     * Restore a snapshot. Auto-saves the current state first (so a restore is
     * itself reversible). Returns the container the snapshot belongs to.
     *
     * @return array{pid:int,ptable:string}
     */
    public function restore(int $snapshotId, int $tstamp): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable, snapshot FROM tl_draggo_history WHERE id = :id',
            ['id' => $snapshotId],
        );
        if ($row === false) {
            throw new ElementNotFoundException($snapshotId);
        }
        $pid = (int) $row['pid'];
        $ptable = (string) $row['ptable'];
        $data = $this->dec(json_decode((string) $row['snapshot'], true) ?: []);
        $items = \is_array($data['items'] ?? null) ? $data['items'] : [];

        // Safety: snapshot the current state before overwriting it.
        $this->snapshot($pid, $ptable, 'Vor Wiederherstellung', $tstamp);

        $this->synchronizer->replaceContainer($pid, $ptable, $items, $tstamp);

        // Restore the container's own layout (article only).
        if ($ptable === 'tl_article' && !empty($data['layout'])) {
            $decoded = json_decode((string) $data['layout'], true);
            if (\is_array($decoded)) {
                $this->synchronizer->updateArticleLayout($pid, $decoded, $tstamp);
            }
        }

        return ['pid' => $pid, 'ptable' => $ptable];
    }

    private function prune(int $pid, string $ptable): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_draggo_history WHERE pid = :pid AND ptable = :pt ORDER BY tstamp DESC, id DESC',
            ['pid' => $pid, 'pt' => $ptable],
        );
        $old = \array_slice(array_map('intval', $ids), self::KEEP);
        foreach ($old as $id) {
            $this->connection->delete('tl_draggo_history', ['id' => $id]);
        }
    }

    /** Wrap non-UTF-8 strings (binary UUID columns) so json_encode never fails. */
    private function enc(mixed $v): mixed
    {
        if (\is_array($v)) {
            $o = [];
            foreach ($v as $k => $x) {
                $o[$k] = $this->enc($x);
            }

            return $o;
        }
        if (\is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
            return ['__b64' => base64_encode($v)];
        }

        return $v;
    }

    private function dec(mixed $v): mixed
    {
        if (\is_array($v)) {
            if (\count($v) === 1 && \array_key_exists('__b64', $v)) {
                return base64_decode((string) $v['__b64'], true);
            }
            $o = [];
            foreach ($v as $k => $x) {
                $o[$k] = $this->dec($x);
            }

            return $o;
        }

        return $v;
    }
}
