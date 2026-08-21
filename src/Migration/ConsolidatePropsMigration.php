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

namespace Vtinnovations\Draggo\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * De-bloat migration: moves all Draggo element CONTENT fields out of the ~145
 * individual tl_content columns into the single tl_content.draggo_props JSON
 * column, then drops those columns. Runs automatically during `contao:migrate`
 * on existing installs — BEFORE the schema diff would try to drop the columns,
 * so no data is lost and Contao never has to drop them with --with-deletes.
 *
 * Idempotent: shouldRun() is false once no content columns remain (fresh
 * installs never create them, since the DCA strips their `sql`).
 */
final class ConsolidatePropsMigration extends AbstractMigration
{
    /** Structural draggo_* columns that stay real columns. */
    private const KEEP = [
        'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container',
        'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile', 'draggo_props',
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    public function getName(): string
    {
        return 'Draggo: consolidate element content columns into draggo_props (JSON)';
    }

    public function shouldRun(): bool
    {
        if (!$this->tableExists('tl_content')) {
            return false;
        }

        return $this->contentColumns() !== [];
    }

    public function run(): MigrationResult
    {
        $cols = $this->contentColumns();

        // Ensure the target JSON column exists (the schema diff would add it, but
        // this migration runs first — so create it here to be self-contained).
        if (!$this->columnExists('draggo_props')) {
            $this->db->executeStatement('ALTER TABLE tl_content ADD draggo_props MEDIUMBLOB DEFAULT NULL');
        }

        // Copy each row's non-empty content columns into the JSON object.
        $colList = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', array_merge(['id', 'draggo_props'], $cols)));
        $rows = $this->db->fetchAllAssociative("SELECT {$colList} FROM tl_content");
        $moved = 0;
        foreach ($rows as $row) {
            $props = [];
            if (!empty($row['draggo_props'])) {
                $decoded = json_decode((string) $row['draggo_props'], true);
                if (\is_array($decoded)) {
                    $props = $decoded;
                }
            }
            $changed = false;
            foreach ($cols as $c) {
                $v = $row[$c] ?? null;
                if ($v === null || $v === '' || $v === '0') {
                    continue;
                }
                $props[$c] = $v;
                $changed = true;
            }
            if ($changed) {
                $this->db->update(
                    'tl_content',
                    ['draggo_props' => $props === [] ? null : json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ['id' => (int) $row['id']],
                );
                ++$moved;
            }
        }

        // Drop ALL content columns in ONE statement: dropping them individually
        // fails with InnoDB error 1118 (row size too large) because each single
        // drop rebuilds the table with an intermediate row still over the limit.
        // Relaxing innodb_strict_mode lets the rebuild to the slim final table
        // succeed (it fits fine in DYNAMIC row format).
        try {
            $this->db->executeStatement('SET SESSION innodb_strict_mode = 0');
        } catch (\Throwable) {
            // Non-MySQL / insufficient privilege — let the ALTER try anyway.
        }
        $drops = implode(', ', array_map(static fn (string $c): string => 'DROP COLUMN `' . $c . '`', $cols));
        $this->db->executeStatement('ALTER TABLE tl_content ROW_FORMAT=DYNAMIC, ' . $drops);

        return $this->createResult(true, \sprintf('Konsolidiert: %d Spalten → draggo_props, %d Zeilen mit Inhalt.', \count($cols), $moved));
    }

    /** @return list<string> */
    private function contentColumns(): array
    {
        $out = [];
        foreach ($this->db->createSchemaManager()->listTableColumns('tl_content') as $c) {
            $name = $c->getName();
            if (str_starts_with($name, 'draggo_') && !\in_array($name, self::KEEP, true)) {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }

    private function columnExists(string $col): bool
    {
        foreach ($this->db->createSchemaManager()->listTableColumns('tl_content') as $c) {
            if (strtolower($c->getName()) === strtolower($col)) {
                return true;
            }
        }

        return false;
    }

    private function tableExists(string $table): bool
    {
        return $this->db->createSchemaManager()->tablesExist([$table]);
    }
}
