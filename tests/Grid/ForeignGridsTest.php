<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Grid;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Grid\ForeignGrids;

/**
 * Auto-derivation of foreign column-grid triples from $GLOBALS['TL_WRAPPERS']:
 * conventionally-named grids (xStart/xPart/xStop) are recognised without
 * hardcoding, curated systems always remain, non-grid wrappers are excluded.
 */
final class ForeignGridsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TL_WRAPPERS']);
    }

    public function testCuratedSystemsAlwaysPresent(): void
    {
        $GLOBALS['TL_WRAPPERS'] = ['start' => [], 'separator' => [], 'stop' => []];
        $starts = ForeignGrids::startTypes();
        self::assertContains('colsetStart', $starts);
        self::assertContains('bs_gridStart', $starts);
        self::assertContains('rsColumnStart', $starts);
    }

    public function testDerivesConventionallyNamedForeignGrid(): void
    {
        $GLOBALS['TL_WRAPPERS'] = [
            'start'     => ['pctColStart'],
            'separator' => ['pctColPart'],
            'stop'      => ['pctColStop'],
        ];
        $triple = ForeignGrids::tripleForStart('pctColStart');
        self::assertNotNull($triple);
        self::assertSame('pctColStart', $triple['start']);
        self::assertSame('pctColPart', $triple['separator']);
        self::assertSame('pctColStop', $triple['stop']);
        self::assertContains('pctColStart', ForeignGrids::startTypes());
        self::assertContains('pctColStop', ForeignGrids::stopTypes());
    }

    public function testExcludesNonGridWrappers(): void
    {
        $GLOBALS['TL_WRAPPERS'] = [
            'start'     => ['accordionStart', 'sliderStart', 'fieldsetStart'],
            'separator' => [],
            'stop'      => ['accordionStop', 'sliderStop', 'fieldsetStop'],
        ];
        $starts = ForeignGrids::startTypes();
        self::assertNotContains('accordionStart', $starts);
        self::assertNotContains('sliderStart', $starts);
        self::assertNotContains('fieldsetStart', $starts);
    }

    public function testStartWithoutMatchingStopIsNotDerived(): void
    {
        $GLOBALS['TL_WRAPPERS'] = ['start' => ['orphanStart'], 'separator' => [], 'stop' => []];
        self::assertNull(ForeignGrids::tripleForStart('orphanStart'));
        self::assertNotContains('orphanStart', ForeignGrids::startTypes());
    }

    public function testPerColumnWrapperGridNotDerived(): void
    {
        // PCT autogrid style: per-column start/stop pairs, NO separator → must NOT
        // be bridged into Draggo's separator-based column model (would lose the
        // per-column stop ids on save).
        $GLOBALS['TL_WRAPPERS'] = [
            'start'     => ['autogridColStart', 'autogridRowStart', 'autogridGridStart'],
            'separator' => [],
            'stop'      => ['autogridColStop', 'autogridRowStop', 'autogridGridStop'],
        ];
        $starts = ForeignGrids::startTypes();
        self::assertNotContains('autogridColStart', $starts);
        self::assertNotContains('autogridRowStart', $starts);
        self::assertNotContains('autogridGridStart', $starts);
        self::assertNull(ForeignGrids::tripleForStart('autogridColStart'));
    }

    public function testEditorTriplesIncludeDraggoOwnAndDerived(): void
    {
        $GLOBALS['TL_WRAPPERS'] = ['start' => ['pctColStart'], 'separator' => ['pctColPart'], 'stop' => ['pctColStop']];
        $t = ForeignGrids::editorTriples();
        self::assertContains('draggo_row_start', $t['start']);
        self::assertContains('pctColStart', $t['start']);
        self::assertContains('draggo_col', $t['separator']);
        self::assertContains('draggo_row_stop', $t['stop']);
    }
}
