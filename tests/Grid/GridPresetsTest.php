<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Grid;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Grid\GridPresets;

/**
 * Golden tests for responsive grid column classes. Locks the bug where a
 * shorter mobile/tablet preset only stacked the FIRST column (padded the rest
 * from desktop widths) instead of cycling the pattern across all columns.
 */
final class GridPresetsTest extends TestCase
{
    public function testMobileOneStacksEveryColumn(): void
    {
        // Desktop 4-4-4, tablet 6-6, mobile 1 → ALL columns col-12 col-md-6 col-lg-4.
        $def = GridPresets::definition('4-4-4', 'container', null, '6-6', '1');
        self::assertSame(
            ['col-12 col-md-6 col-lg-4', 'col-12 col-md-6 col-lg-4', 'col-12 col-md-6 col-lg-4'],
            $def->columnClasses,
        );
    }

    public function testEmptyMobileDefaultsToFullWidthStack(): void
    {
        // No mobile key → every column stacks (col-12) on mobile.
        $def = GridPresets::definition('4-4-4');
        foreach ($def->columnClasses as $c) {
            self::assertStringStartsWith('col-12 ', $c);
        }
    }

    public function testTabletPatternCyclesNotPaddedFromDesktop(): void
    {
        // 6-6 on a 4-column desktop must cycle 6-6-6-6, not 6-6-<desktop>-<desktop>.
        $def = GridPresets::definition('3-3-3-3', 'container', null, '6-6', '');
        foreach ($def->columnClasses as $c) {
            self::assertStringContainsString('col-md-6', $c);
        }
    }
}
