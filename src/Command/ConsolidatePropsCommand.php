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

namespace Vtinnovations\Draggo\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot de-bloat migration: moves ALL Draggo element CONTENT fields out of
 * the ~145 individual tl_content columns into a single JSON column
 * (draggo_props), then optionally drops those columns. This removes the load on
 * the shared core table, eliminates the InnoDB row-size risk and makes the
 * schema update-safe (new element fields become JSON keys, never new columns).
 *
 * Raw column values are preserved verbatim as JSON values (serialized blobs stay
 * serialized strings), so FE hydration + the editor behave identically.
 *
 *   vendor/bin/contao-console contao:draggo:consolidate-props            # copy into JSON
 *   vendor/bin/contao-console contao:draggo:consolidate-props --drop     # copy + drop columns
 *   vendor/bin/contao-console contao:draggo:consolidate-props --dry-run  # report only
 */
#[AsCommand(name: 'contao:draggo:consolidate-props', description: 'Konsolidiert die Draggo-Inhaltsspalten in tl_content.draggo_props (JSON).')]
final class ConsolidatePropsCommand extends Command
{
    /** Structural draggo_* columns that STAY real columns (never moved to JSON). */
    private const KEEP = [
        'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container',
        'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile',
        'draggo_props',
    ];

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('drop', null, InputOption::VALUE_NONE, 'Inhaltsspalten nach dem Kopieren droppen.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur berichten, nichts schreiben.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dry = (bool) $input->getOption('dry-run');
        $drop = (bool) $input->getOption('drop');

        $cols = $this->contentColumns();
        if ($cols === []) {
            $io->success('Keine Draggo-Inhaltsspalten gefunden — bereits konsolidiert.');

            return Command::SUCCESS;
        }
        $io->writeln(\count($cols) . ' Inhaltsspalten gefunden.');

        if (!$this->columnExists('draggo_props')) {
            $io->error('Spalte draggo_props fehlt — erst `contao:migrate` ausführen (legt sie aus dem DCA an).');

            return Command::FAILURE;
        }

        // Copy each row's non-empty content columns into the JSON object,
        // preserving the existing draggo_props (merge, content keys win).
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
                // Skip empty/null/zero-ish defaults to keep the JSON lean.
                if ($v === null || $v === '' || $v === '0') {
                    continue;
                }
                $props[$c] = $v;
                $changed = true;
            }
            if ($changed && !$dry) {
                $this->db->update(
                    'tl_content',
                    ['draggo_props' => $props === [] ? null : json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ['id' => (int) $row['id']],
                );
            }
            if ($changed) {
                ++$moved;
            }
        }
        $io->writeln(($dry ? '[dry-run] ' : '') . "{$moved} Zeilen mit Inhalt in draggo_props geschrieben.");

        if ($drop && !$dry) {
            // Drop ALL content columns in ONE statement. Dropping them one by one
            // fails with InnoDB error 1118 (row size too large): each single drop
            // rebuilds the table and the intermediate row is still over the 8126
            // byte limit. One combined ALTER rebuilds straight to the small final
            // row, which is well under the limit.
            $drops = implode(', ', array_map(static fn (string $c): string => 'DROP COLUMN `' . $c . '`', $cols));
            // innodb_strict_mode rejects the rebuild on the theoretical max inline
            // row size (error 1118) even though the RESULT has far fewer columns
            // and fits fine in DYNAMIC row format. Relax it for this session so
            // the consolidation can complete; the resulting slim table is healthy.
            try {
                $this->db->executeStatement('SET SESSION innodb_strict_mode = 0');
            } catch (\Throwable) {
                // Non-MySQL / no privilege — fall through and let the ALTER try.
            }
            $this->db->executeStatement('ALTER TABLE tl_content ROW_FORMAT=DYNAMIC, ' . $drops);
            $io->writeln(\count($cols) . ' Inhaltsspalten in einem ALTER gedroppt.');
        } elseif ($drop && $dry) {
            $io->writeln('[dry-run] würde ' . \count($cols) . ' Spalten droppen: ' . implode(', ', $cols));
        }

        $io->success('Konsolidierung ' . ($dry ? '(dry-run) ' : '') . 'abgeschlossen.');

        return Command::SUCCESS;
    }

    /** All draggo_* content columns currently on tl_content (excluding KEEP). */
    private function contentColumns(): array
    {
        $all = array_map(
            static fn ($c): string => $c->getName(),
            $this->db->createSchemaManager()->listTableColumns('tl_content'),
        );
        $out = [];
        foreach ($all as $name) {
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
}
