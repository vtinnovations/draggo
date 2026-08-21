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

/**
 * Resolved description of one Bootstrap grid row: the container wrapper, the
 * row class (incl. gutters) and one CSS class string per column. Built by
 * GridPresets from a preset key or a custom width list; consumed by GridStack
 * during rendering.
 *
 * All class strings come from a fixed vocabulary (col-12 col-md-N with N
 * clamped 1..12), so they are always safe to emit as raw HTML.
 */
final class GridDefinition
{
    /**
     * @param list<string> $columnClasses one class string per column
     */
    public function __construct(
        public readonly string $containerClass, // '', 'container' or 'container-fluid'
        public readonly string $rowClass,
        public readonly array $columnClasses,
    ) {
    }

    public function columnCount(): int
    {
        return \count($this->columnClasses);
    }
}
