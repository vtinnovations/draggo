<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Editor;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Editor\ElementRegistry;

final class ElementRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TL_CTE'] = [
            'texts'   => ['text' => 'X', 'headline' => 'X'],
            'draggo' => ['draggo_block' => 'X'],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_CTE']);
    }

    public function testAllTypesReturnsEveryRegisteredKeySortedAndUnique(): void
    {
        $registry = new ElementRegistry();

        self::assertSame(['draggo_block', 'headline', 'text'], $registry->allTypes());
    }

    public function testIsValidTypeOnlyAcceptsRegisteredKeys(): void
    {
        $registry = new ElementRegistry();

        self::assertTrue($registry->isValidType('text'));
        self::assertTrue($registry->isValidType('draggo_block'));
        self::assertFalse($registry->isValidType('made_up_element'));
        self::assertFalse($registry->isValidType(''));
    }

    public function testPaletteExcludesWrapperStartSeparatorStop(): void
    {
        // Wrapper CEs (start/separator/stop) must NOT appear in the picker —
        // they can't be placed standalone in Draggo's model.
        $GLOBALS['TL_CTE'] = [
            'draggo' => ['draggo_block' => 'X', 'draggo_row_start' => 'X', 'draggo_col' => 'X', 'draggo_row_stop' => 'X'],
            'includes' => ['accordionStart' => 'X', 'accordionStop' => 'X'],
            'texts'   => ['text' => 'X'],
        ];
        $GLOBALS['TL_WRAPPERS'] = [
            'start'     => ['draggo_row_start', 'accordionStart'],
            'separator' => ['draggo_col'],
            'stop'      => ['draggo_row_stop', 'accordionStop'],
        ];

        $palette = (new ElementRegistry())->palette();
        $types = [];
        foreach ($palette as $els) {
            foreach ($els as $e) {
                $types[] = $e['type'];
            }
        }

        self::assertContains('text', $types);
        self::assertNotContains('draggo_row_start', $types);
        self::assertNotContains('draggo_col', $types);
        self::assertNotContains('draggo_row_stop', $types);
        self::assertNotContains('accordionStart', $types);
        self::assertNotContains('accordionStop', $types);
        // The 'includes' group is now empty → dropped entirely.
        self::assertArrayNotHasKey('includes', $palette);
        // Wrappers stay VALID types (needed to save a grid).
        self::assertTrue((new ElementRegistry())->isValidType('draggo_row_start'));

        unset($GLOBALS['TL_WRAPPERS']);
    }

    public function testPaletteExcludesRuntimeHostBlockButKeepsItValid(): void
    {
        // draggo_block is the AI-block renderer host: hidden from the manual
        // picker (renders nothing when dropped blank) but must stay a valid type
        // so AI-generated instances persist.
        $GLOBALS['TL_CTE'] = [
            'draggo' => ['draggo_block' => 'X', 'draggo_icon' => 'X'],
            'texts'  => ['text' => 'X'],
        ];

        $registry = new ElementRegistry();
        $palette = $registry->palette();
        $types = [];
        foreach ($palette as $els) {
            foreach ($els as $e) {
                $types[] = $e['type'];
            }
        }

        self::assertNotContains('draggo_block', $types);
        self::assertContains('draggo_icon', $types);
        self::assertTrue($registry->isValidType('draggo_block'));
        self::assertContains('draggo_block', $registry->allTypes());
    }
}
