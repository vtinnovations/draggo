<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Grid;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Grid\GridDefinition;
use Vtinnovations\Draggo\Grid\GridStack;

/**
 * Golden tests (Schicht 2) for the grid markup. Locks the bug where the row
 * alignment landed on the .container (making the .row shrink to content width).
 */
final class GridStackTest extends TestCase
{
    public function testRowAlignGoesOnRowNotContainer(): void
    {
        $def = new GridDefinition('container', 'row', ['col-12']);
        $html = (new GridStack())->openRow(
            $def,
            firstColStyle: '',
            rowStyle: 'padding:1rem;',
            gapClass: '',
            rowAttr: '',
            colAttr: '',
            rowLayers: '',
            colLayers: '',
            rowHook: 'draggo-rr1',
            colHook: 'draggo-rc1',
            rowAlignStyle: 'display:flex;align-items:center;',
            rowInnerHook: 'draggo-ri1',
        );

        // Container keeps only its own style (padding), NEVER the flex align.
        self::assertMatchesRegularExpression('/<div class="container draggo-rr1" style="padding:1rem;"/', $html);
        self::assertStringNotContainsString('container draggo-rr1" style="display:flex', $html);

        // The .row carries the alignment + its hook.
        self::assertStringContainsString('class="row g-3 draggo-ri1" style="display:flex;align-items:center;"', $html);

        // First column carries the col hook.
        self::assertStringContainsString('class="col-12 draggo-rc1"', $html);
    }

    public function testNoContainerRowCarriesEverything(): void
    {
        $def = new GridDefinition('', 'row', ['col-12']);
        $html = (new GridStack())->openRow(
            $def,
            firstColStyle: '',
            rowStyle: 'padding:1rem;',
            gapClass: 'g-4',
            rowAttr: '',
            colAttr: '',
            rowLayers: '',
            colLayers: '',
            rowHook: 'draggo-rr2',
            colHook: 'draggo-rc2',
            rowAlignStyle: 'display:flex;justify-content:center;',
            rowInnerHook: 'draggo-ri2',
        );

        // The single .row host gets gap class + both hooks + combined style.
        self::assertStringContainsString('class="row g-4 draggo-ri2 draggo-rr2"', $html);
        self::assertStringContainsString('padding:1rem;display:flex;justify-content:center;', $html);
    }
}
