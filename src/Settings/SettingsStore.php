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

use Doctrine\DBAL\Connection;

/**
 * Reads the single-row Draggo settings record (tl_draggo_settings) that the
 * backend settings module writes. Lets admins set the AI key etc. in the BE —
 * no file editing, no cache clear (values are read at runtime).
 */
final class SettingsStore
{
    /** @var array<string,mixed>|null */
    private ?array $row = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $column): string
    {
        $row = $this->row();

        return isset($row[$column]) ? (string) $row[$column] : '';
    }

    public function bool(string $column): bool
    {
        return $this->get($column) === '1';
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        if ($this->row !== null) {
            return $this->row;
        }
        try {
            $row = $this->connection->fetchAssociative('SELECT * FROM tl_draggo_settings ORDER BY id LIMIT 1');
            $this->row = \is_array($row) ? $row : [];
        } catch (\Throwable) {
            $this->row = []; // table not migrated yet
        }

        return $this->row;
    }
}
