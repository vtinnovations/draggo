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

namespace Vtinnovations\Draggo\Block;

use Doctrine\DBAL\Connection;

/**
 * Persistence for AI-generated block-type DEFINITIONS in tl_draggo_blocktype.
 * The definition (BlockSpec JSON) is stored ONCE here; placed instances are
 * normal tl_content rows (type=draggo_block, draggo_blocktype=key, values in
 * draggo_data). Saving by type key upserts so re-generating updates in place.
 */
final class BlockTypeStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Lightweight list for the palette: published types only.
     *
     * @return list<array{type:string,label:string,category:string,icon:string}>
     */
    public function all(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT type, label, category, spec FROM tl_draggo_blocktype WHERE published = '1' ORDER BY category, label",
            );
        } catch (\Throwable) {
            return []; // table not migrated yet → degrade gracefully
        }

        $out = [];
        foreach ($rows as $r) {
            $spec = json_decode((string) $r['spec'], true);
            $out[] = [
                'type'     => (string) $r['type'],
                'label'    => (string) $r['label'],
                'category' => (string) $r['category'],
                'icon'     => \is_array($spec) ? (string) ($spec['icon'] ?? '🧩') : '🧩',
            ];
        }

        return $out;
    }

    public function find(string $type): ?BlockSpec
    {
        try {
            $json = $this->connection->fetchOne(
                'SELECT spec FROM tl_draggo_blocktype WHERE type = :t',
                ['t' => $type],
            );
        } catch (\Throwable) {
            return null;
        }
        if ($json === false) {
            return null;
        }
        $arr = json_decode((string) $json, true);

        return \is_array($arr) ? BlockSpec::fromArray($arr) : null;
    }

    public function exists(string $type): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT id FROM tl_draggo_blocktype WHERE type = :t',
            ['t' => $type],
        );
    }

    /** Upsert a validated spec by its type key. */
    public function save(BlockSpec $spec, int $tstamp): void
    {
        $json = (string) json_encode($spec->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data = [
            'tstamp'    => $tstamp,
            'label'     => $spec->label,
            'category'  => $spec->category,
            'spec'      => $json,
            'published' => '1',
        ];

        if ($this->exists($spec->type)) {
            $this->connection->update('tl_draggo_blocktype', $data, ['type' => $spec->type]);

            return;
        }
        $data['type'] = $spec->type;
        $this->connection->insert('tl_draggo_blocktype', $data);
    }

    public function delete(string $type): void
    {
        $this->connection->delete('tl_draggo_blocktype', ['type' => $type]);
    }
}
