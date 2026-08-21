<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Editor\ElementRegistry;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Security\InputSanitizer;

final class InputSanitizerTest extends TestCase
{
    private InputSanitizer $sanitizer;

    protected function setUp(): void
    {
        $GLOBALS['TL_CTE'] = ['texts' => ['text' => 'X']];
        $this->sanitizer = new InputSanitizer(new ElementRegistry());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_CTE']);
    }

    public function testAssertValidTypeRejectsUnknownType(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->sanitizer->assertValidType('ghost_element');
    }

    public function testAssertValidTypeAcceptsLeafType(): void
    {
        self::assertSame('text', $this->sanitizer->assertValidType('text'));
    }

    public function testAssertValidTypeRejectsWrapperType(): void
    {
        $GLOBALS['TL_CTE'] = ['texts' => ['text' => 'X'], 'legacy' => ['accordionStart' => 'Y']];
        $GLOBALS['TL_WRAPPERS'] = ['start' => ['accordionStart'], 'separator' => [], 'stop' => []];
        try {
            $this->expectException(InvalidInputException::class);
            $this->sanitizer->assertValidType('accordionStart'); // valid CTE but a wrapper → reject
        } finally {
            unset($GLOBALS['TL_WRAPPERS']);
        }
    }

    public function testSanitizeUpdateDropsNonWhitelistedColumns(): void
    {
        $clean = $this->sanitizer->sanitizeUpdate([
            'headline'  => 'Hello',
            'protected' => '1',   // not whitelisted → dropped
            'invisible' => 'x',   // not whitelisted → dropped
        ]);

        self::assertArrayNotHasKey('protected', $clean);
        self::assertArrayNotHasKey('invisible', $clean);
        self::assertArrayHasKey('headline', $clean);
        // Headline is stored in Contao's serialized {value,unit} shape.
        self::assertStringContainsString('Hello', $clean['headline']);
    }

    public function testSanitizeOrderRejectsDuplicates(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->sanitizer->sanitizeOrder([1, 2, 2]);
    }

    public function testSanitizeOrderRejectsNonNumeric(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->sanitizer->sanitizeOrder([1, 'two', 3]);
    }

    public function testSanitizeOrderReturnsIntList(): void
    {
        self::assertSame([3, 1, 2], $this->sanitizer->sanitizeOrder(['3', '1', '2']));
    }
}
