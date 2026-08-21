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
use Doctrine\DBAL\Connection;

/**
 * Fills the draggo_unit select on the frontend module with id => "title [type]".
 */
final class UnitOptionsListener
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<int,string>
     */
    #[AsCallback(table: 'tl_module', target: 'fields.draggo_unit.options')]
    public function __invoke(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title, type FROM tl_draggo_unit ORDER BY type, title',
        );

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = sprintf('%s [%s]', $row['title'], $row['type']);
        }

        return $options;
    }
}
