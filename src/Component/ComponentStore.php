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

namespace Vtinnovations\Draggo\Component;

use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Editor\ContentSynchronizer;
use Vtinnovations\Draggo\Exception\ElementNotFoundException;
use Vtinnovations\Draggo\Exception\InvalidInputException;

/**
 * Reusable component library (Tier 4). Stores a portable element field set
 * (JSON) per component; inserting creates a DETACHED clone (no live link — the
 * agency "save as template + reuse" workflow). Live-linked global widgets are a
 * future step. CRUD only; the editor drives it via the API.
 */
final class ComponentStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContentSynchronizer $synchronizer,
    ) {
    }

    /**
     * @return list<array{id:int,title:string,category:string,eltype:string}>
     */
    public function all(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, title, category, eltype FROM tl_draggo_component ORDER BY category, title',
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'id'       => (int) $r['id'],
            'title'    => (string) $r['title'],
            'category' => (string) $r['category'],
            'eltype'   => (string) $r['eltype'],
        ], $rows);
    }

    /** Save an existing element (or whole grid row) as a named component. */
    public function saveFromElement(int $elementId, string $title, string $category, int $tstamp): int
    {
        $row = $this->synchronizer->findRow($elementId);
        if ($row === null) {
            throw new ElementNotFoundException($elementId);
        }

        // A grid row-start → store the WHOLE section (row + columns + content).
        if ((string) $row['type'] === 'draggo_row_start') {
            $data = ['kind' => 'tree', 'items' => $this->synchronizer->exportRowTree($elementId)];
            $eltype = 'section';
        } else {
            $data = $this->synchronizer->exportElement($elementId);
            $eltype = (string) ($data['type'] ?? '');
        }

        $title = trim($title) !== '' ? trim($title) : 'Komponente';
        $this->connection->insert('tl_draggo_component', [
            'tstamp'   => $tstamp,
            'title'    => mb_substr($title, 0, 255),
            'category' => mb_substr(trim($category), 0, 64),
            'eltype'   => mb_substr($eltype, 0, 64),
            'data'     => $this->encodeData($data),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** Insert a component as a detached clone into a container. */
    public function insert(int $componentId, int $targetPid, string $ptable, ?int $afterId, int $tstamp): int
    {
        $json = $this->connection->fetchOne(
            'SELECT data FROM tl_draggo_component WHERE id = :id',
            ['id' => $componentId],
        );
        if ($json === false) {
            throw new ElementNotFoundException($componentId);
        }
        $data = $this->decodeData((string) $json);

        // Section component → recreate the whole tree; single → one element.
        if (($data['kind'] ?? '') === 'tree' && \is_array($data['items'] ?? null)) {
            return $this->synchronizer->insertTree($data['items'], $targetPid, $ptable, $afterId, $tstamp);
        }

        return $this->synchronizer->insertElementData($data, $targetPid, $ptable, $afterId, $tstamp);
    }

    public function delete(int $componentId): void
    {
        $this->connection->delete('tl_draggo_component', ['id' => $componentId]);
    }

    /**
     * JSON-encode a tl_content row, base64-wrapping any non-UTF-8 string value
     * (binary UUID columns like singleSRC / serialized blobs) so json_encode
     * never fails and the bytes round-trip exactly.
     *
     * @param array<string,mixed> $row
     */
    private function encodeData(array $row): string
    {
        return (string) json_encode($this->enc($row), JSON_UNESCAPED_UNICODE);
    }

    /** Recursively wrap non-UTF-8 strings so json_encode never fails. */
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

    /**
     * @return array<string,mixed>
     */
    private function decodeData(string $json): array
    {
        $arr = json_decode($json, true);
        if (!\is_array($arr)) {
            throw new InvalidInputException('Komponente ist beschädigt.');
        }

        return $this->dec($arr);
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
