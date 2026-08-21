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
 * Request-scoped state machine that turns the flat sequence of wrapper
 * content elements (row-start → column → … → row-stop) into balanced
 * Bootstrap markup.
 *
 * Contao renders an article's content elements in sorting order, calling each
 * controller in turn, so a LIFO stack is enough — and it supports nested rows
 * (a row inside a column). Each tl_content fragment emits ONLY its own opening
 * or closing tags; the browser concatenates them into valid markup, exactly
 * like Contao's native start/separator/stop wrapper elements.
 */
final class GridStack
{
    /** @var list<array{classes:list<string>, index:int, hasContainer:bool}> */
    private array $rows = [];

    /**
     * Opens container (optional) + row + first column. $firstColStyle is the
     * inline style for the first column (col0, defined on the row-start).
     */
    public function openRow(
        GridDefinition $definition,
        string $firstColStyle = '',
        string $rowStyle = '',
        string $gapClass = '',
        string $rowAttr = '',
        string $colAttr = '',
        string $rowLayers = '',
        string $colLayers = '',
        string $rowHook = '',
        string $colHook = '',
        string $rowAlignStyle = '',
        string $rowInnerHook = '',
    ): string {
        $html = '';
        $hasContainer = $definition->containerClass !== '';
        $rowClass = 'row ' . ($gapClass !== '' ? $gapClass : 'g-3');
        $col0Class = ($definition->columnClasses[0] ?? 'col') . ($colHook !== '' ? ' ' . $colHook : '');
        // The .row carries the flex alignment (justify/align-items) + its hook —
        // NEVER the container, which must stay block/full-width so the row
        // spans the full width (otherwise it shrinks to content).
        $rowClassFull = $rowClass . ($rowInnerHook !== '' ? ' ' . $rowInnerHook : '');

        // Background layers (media/overlay) are injected as the first child of
        // the row-level element + first column.
        if ($hasContainer) {
            // Outer .container: bg/padding (rowStyle) + hook. Inner .row: alignment.
            $html .= '<div class="' . $definition->containerClass . ($rowHook !== '' ? ' ' . $rowHook : '') . '"' . self::styleAttr($rowStyle) . $rowAttr . '>';
            $html .= $rowLayers;
            $html .= '<div class="' . $rowClassFull . '"' . self::styleAttr($rowAlignStyle) . '>';
        } else {
            // No container: the .row IS the host → carries everything.
            $html .= '<div class="' . $rowClassFull . ($rowHook !== '' ? ' ' . $rowHook : '') . '"' . self::styleAttr($rowStyle . $rowAlignStyle) . $rowAttr . '>';
            $html .= $rowLayers;
        }
        $html .= '<div class="' . $col0Class . '"' . self::styleAttr($firstColStyle) . $colAttr . '>';
        $html .= $colLayers;

        $this->rows[] = [
            'classes'      => $definition->columnClasses,
            'index'        => 0,
            'hasContainer' => $hasContainer,
        ];

        return $html;
    }

    /**
     * Closes the current column, opens the next one. Falls back to a generic
     * column if a separator appears without an open row (malformed content).
     */
    public function nextColumn(string $style = '', string $attr = '', string $layers = '', string $hook = ''): string
    {
        $h = $hook !== '' ? ' ' . $hook : '';
        if ($this->rows === []) {
            return '</div><div class="col' . $h . '"' . self::styleAttr($style) . $attr . '>' . $layers;
        }

        $i = \count($this->rows) - 1;
        $this->rows[$i]['index']++;
        $class = $this->rows[$i]['classes'][$this->rows[$i]['index']] ?? 'col';

        return '</div><div class="' . $class . $h . '"' . self::styleAttr($style) . $attr . '>' . $layers;
    }

    private static function styleAttr(string $style): string
    {
        return $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
    }

    /**
     * Closes the last column + row (+ container if it was opened).
     */
    public function closeRow(): string
    {
        if ($this->rows === []) {
            return '</div></div>';
        }

        $row = array_pop($this->rows);
        $html = '</div></div>'; // column + row

        if ($row['hasContainer']) {
            $html .= '</div>'; // container
        }

        return $html;
    }
}
