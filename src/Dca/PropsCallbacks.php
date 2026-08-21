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

namespace Vtinnovations\Draggo\Dca;

use Contao\DataContainer;
use Contao\System;
use Doctrine\DBAL\Connection;

/**
 * DCA load/save callbacks for the consolidated Draggo content fields. The
 * individual draggo_* element fields have no own DB column anymore — their value
 * lives in the single tl_content.draggo_props JSON object. These callbacks make
 * the STANDARD Contao backend form still read (load) and write (save) each field
 * transparently, so editing in the backend keeps working without 145 columns.
 */
final class PropsCallbacks
{
    /** Structural draggo_* columns that ARE real columns (never JSON-backed). */
    private const KEEP = [
        'draggo_managed', 'draggo_layout', 'draggo_data', 'draggo_blocktype', 'draggo_container',
        'draggo_grid_preset', 'draggo_grid_custom', 'draggo_grid_tablet', 'draggo_grid_mobile', 'draggo_props',
    ];

    /** Display value for a virtual field: read it out of draggo_props. */
    public function load(mixed $value, DataContainer $dc): mixed
    {
        $id = (int) ($dc->id ?? 0);
        $field = (string) ($dc->field ?? '');
        if ($id === 0 || $field === '') {
            return $value;
        }
        $props = $this->props($id);

        return \array_key_exists($field, $props) ? $props[$field] : $value;
    }

    /** Persist a virtual field's submitted value back into draggo_props. */
    public function save(mixed $value, DataContainer $dc): mixed
    {
        $id = (int) ($dc->id ?? 0);
        $field = (string) ($dc->field ?? '');
        if ($id === 0 || $field === '') {
            return $value;
        }
        $db = $this->db();
        $props = $this->props($id);
        if ($value === null || $value === '' || $value === '0') {
            unset($props[$field]);
        } else {
            $props[$field] = $value;
        }
        $db->update(
            'tl_content',
            ['draggo_props' => $props === [] ? null : json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id' => $id],
        );

        // NOTE: returning null does NOT by itself stop the column write — in this
        // Contao build DC_Table turns it into '' and still adds it to the UPDATE.
        // The field is kept out of the column write by eval.doNotSaveEmpty (set in
        // the DCA loop) plus the beforeSubmit() guard below. The real value is
        // already persisted to draggo_props above.
        return null;
    }

    /**
     * onbeforesubmit_callback: drop every orphan draggo_* content key (one with
     * no real column) from the values right before DC_Table builds the UPDATE.
     * This is the robust, all-paths guard against the "Unknown column" leak —
     * the values are already in draggo_props via save(), so removing them from
     * the column write is lossless. Must return the (filtered) values.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function beforeSubmit(array $values, DataContainer $dc): array
    {
        foreach (array_keys($values) as $key) {
            if (\is_string($key) && str_starts_with($key, 'draggo_') && !\in_array($key, self::KEEP, true)) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /** @return array<string,mixed> */
    private function props(int $id): array
    {
        $raw = $this->db()->fetchOne('SELECT draggo_props FROM tl_content WHERE id = :id', ['id' => $id]);
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function db(): Connection
    {
        return System::getContainer()->get('doctrine.dbal.default_connection');
    }
}
