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

namespace Vtinnovations\Draggo\Token;

use Doctrine\DBAL\Connection;

/**
 * Design-token store (Roadmap Phase E). Tokens are named design values
 * (colour/spacing/font/radius/shadow) exposed to the page as CSS custom
 * properties `--bld-{type}-{token}` and referenced by elements via var().
 * One central definition → change once, every reference updates.
 */
final class TokenStore
{
    public const TYPES = ['color', 'space', 'font', 'radius', 'shadow'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{id:int,type:string,token:string,label:string,value:string,darkValue:string}>
     */
    public function all(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, type, token, label, value, darkValue FROM tl_draggo_token ORDER BY type, sorting, id',
            );
        } catch (\Throwable) {
            // darkValue column not migrated yet → retry without it.
            try {
                $rows = $this->connection->fetchAllAssociative('SELECT id, type, token, label, value FROM tl_draggo_token ORDER BY type, sorting, id');
            } catch (\Throwable) {
                return []; // table not migrated yet
            }
        }

        return array_map(static fn (array $r): array => [
            'id'        => (int) $r['id'],
            'type'      => (string) $r['type'],
            'token'     => (string) $r['token'],
            'label'     => (string) $r['label'],
            'value'     => (string) $r['value'],
            'darkValue' => (string) ($r['darkValue'] ?? ''),
        ], $rows);
    }

    /**
     * Create or update a token. Returns its id.
     *
     * @param array<string,mixed> $data
     */
    public function save(array $data, int $tstamp): int
    {
        $type = \in_array($data['type'] ?? '', self::TYPES, true) ? (string) $data['type'] : 'color';
        $token = strtolower(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($data['token'] ?? '')) ?? '');
        $token = trim($token, '-');
        if ($token === '') {
            throw new \InvalidArgumentException('Token name required.');
        }
        $label = trim((string) ($data['label'] ?? ''));
        $value = $this->cleanValue((string) ($data['value'] ?? ''));
        // Dark value only meaningful for colour tokens; cleaned the same way.
        $darkValue = $type === 'color' ? $this->cleanValue((string) ($data['darkValue'] ?? '')) : '';

        $row = ['type' => $type, 'token' => $token, 'label' => $label, 'value' => $value, 'darkValue' => $darkValue, 'tstamp' => $tstamp];
        $id = (int) ($data['id'] ?? 0);

        if ($id > 0) {
            $this->connection->update('tl_draggo_token', $row, ['id' => $id]);

            return $id;
        }
        $this->connection->insert('tl_draggo_token', $row);

        return (int) $this->connection->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->connection->delete('tl_draggo_token', ['id' => $id]);
    }

    /**
     * Build the `:root{…}` CSS custom-property block (empty string if none).
     * Colour tokens with a dark value also emit a dark override that follows the
     * OS preference AND a manual [data-draggo-theme] choice on <html>.
     */
    public function cssVars(): string
    {
        $rows = $this->all();
        if ($rows === []) {
            return '';
        }
        $decls = '';
        $dark = '';
        foreach ($rows as $r) {
            $decls .= '--bld-' . $r['type'] . '-' . $r['token'] . ':' . $r['value'] . ';';
            if ($r['type'] === 'color' && ($r['darkValue'] ?? '') !== '') {
                $dark .= '--bld-color-' . $r['token'] . ':' . $r['darkValue'] . ';';
            }
        }
        $css = ':root{' . $decls . '}';
        if ($dark !== '') {
            // OS dark (only when the visitor hasn't manually chosen) + explicit dark.
            $css .= '@media (prefers-color-scheme:dark){:root:not([data-draggo-theme]){' . $dark . '}}';
            $css .= ':root[data-draggo-theme="dark"]{' . $dark . '}';
        }

        return $css;
    }

    /** Strip anything that could break out of a declaration. */
    private function cleanValue(string $v): string
    {
        $v = trim($v);
        $v = str_replace(['{', '}', '<', '>', ';'], '', $v);

        return substr($v, 0, 512);
    }
}
