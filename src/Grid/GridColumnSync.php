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

namespace Vtinnovations\Draggo\Grid;

use Doctrine\DBAL\Connection;

/**
 * Keeps the number of columns of a grid row in sync with its chosen preset.
 *
 * The column COUNT is physically defined by the draggo_col separators in
 * tl_content, while the preset defines the widths. If they diverge (e.g. the
 * editor saves a new preset on a row that has a different number of columns),
 * the frontend and backend would render inconsistently. After saving a
 * row-start element we therefore add or remove direct column separators so the
 * count matches the preset — making the preset the single source of truth.
 *
 * Nesting-aware: only the row's OWN (depth-1) separators are touched; nested
 * grids inside its columns are left intact. Removing a surplus column merges
 * its content into the previous column (we only drop the separator).
 */
final class GridColumnSync
{
    private const SORT_STEP = 128;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function sync(int $rowStartId): void
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, pid, ptable, type, draggo_grid_preset, draggo_grid_custom
             FROM tl_content WHERE id = :id',
            ['id' => $rowStartId],
        );

        if ($row === false || $row['type'] !== 'draggo_row_start') {
            return;
        }

        $desired = max(1, GridPresets::columnCount(
            (string) ($row['draggo_grid_preset'] ?: '1'),
            $row['draggo_grid_custom'] ? (string) $row['draggo_grid_custom'] : null,
        ));

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, type FROM tl_content WHERE pid = :pid AND ptable = :ptable ORDER BY sorting',
            ['pid' => (int) $row['pid'], 'ptable' => (string) $row['ptable']],
        );

        // Locate this row-start, its matching row-stop, and its direct columns.
        $startPos = null;
        foreach ($rows as $i => $r) {
            if ((int) $r['id'] === $rowStartId) { $startPos = $i; break; }
        }
        if ($startPos === null) {
            return;
        }

        $depth = 1;
        $directColIds = [];
        $stopId = null;
        for ($i = $startPos + 1, $n = \count($rows); $i < $n; $i++) {
            $t = $rows[$i]['type'];
            if ($t === 'draggo_row_start') {
                $depth++;
            } elseif ($t === 'draggo_row_stop') {
                $depth--;
                if ($depth === 0) { $stopId = (int) $rows[$i]['id']; break; }
            } elseif ($t === 'draggo_col' && $depth === 1) {
                $directColIds[] = (int) $rows[$i]['id'];
            }
        }
        if ($stopId === null) {
            return;
        }

        $current = 1 + \count($directColIds);
        if ($desired === $current) {
            return;
        }

        $order = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        $this->connection->transactional(function (Connection $conn) use (
            $row, $rowStartId, $desired, $current, $directColIds, $stopId, &$order
        ): void {
            if ($desired > $current) {
                // Add separators just before the row-stop.
                $newIds = [];
                for ($k = 0; $k < $desired - $current; $k++) {
                    $conn->insert('tl_content', [
                        'pid' => (int) $row['pid'], 'ptable' => (string) $row['ptable'],
                        'type' => 'draggo_col', 'sorting' => 1, 'tstamp' => 0, 'draggo_managed' => '1',
                    ]);
                    $newIds[] = (int) $conn->lastInsertId();
                }
                $stopPos = array_search($stopId, $order, true);
                array_splice($order, (int) $stopPos, 0, $newIds);
            } else {
                // Remove the last surplus separators (content merges into prev column).
                $remove = \array_slice($directColIds, $desired - 1); // keep (desired-1) separators
                foreach ($remove as $id) {
                    $conn->delete('tl_content', ['id' => $id]);
                }
                $order = array_values(array_filter($order, static fn (int $id): bool => !\in_array($id, $remove, true)));
            }

            // Renumber the whole container so order stays valid.
            $sorting = self::SORT_STEP;
            foreach ($order as $id) {
                $conn->update('tl_content', ['sorting' => $sorting], ['id' => $id]);
                $sorting += self::SORT_STEP;
            }
        });
    }
}
