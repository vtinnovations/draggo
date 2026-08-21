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

namespace Vtinnovations\Draggo\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Vtinnovations\Draggo\Grid\GridColumnSync;

/**
 * After a tl_content row is saved, sync a grid row's column count to its
 * preset (see GridColumnSync). Only acts on draggo_row_start records.
 */
final class GridSyncListener
{
    public function __construct(private readonly GridColumnSync $sync)
    {
    }

    #[AsCallback(table: 'tl_content', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $type = $dc->activeRecord->type ?? null;
        if ($type !== 'draggo_row_start') {
            return;
        }

        $this->sync->sync((int) $dc->id);
    }
}
