<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Template;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Template\SectionTemplates;

/**
 * The AI/content overlay for landing-page section templates: real copy must be
 * injected, malicious markup stripped, and an empty overlay must reproduce the
 * original ready-made template (backward compatible).
 */
final class SectionTemplatesTest extends TestCase
{
    /** Flatten every string value in a template's item tree for easy assertions. */
    private function blob(array $items): string
    {
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function testHeroOverlayInjectsRealCopy(): void
    {
        $items = SectionTemplates::items('hero', [
            'headline' => 'Frische Brötchen täglich',
            'lead' => 'Handwerksbäckerei seit 1952.',
            'button' => 'Jetzt bestellen',
            'button_url' => 'https://baecker.example',
        ]);
        $blob = $this->blob($items);
        self::assertStringContainsString('Frische Brötchen täglich', $blob);
        self::assertStringContainsString('Handwerksbäckerei seit 1952.', $blob);
        self::assertStringContainsString('Jetzt bestellen', $blob);
        self::assertStringContainsString('https://baecker.example', $blob);
        self::assertStringNotContainsString('Deine große Überschrift', $blob, 'placeholder replaced');
    }

    public function testEmptyOverlayReproducesPlaceholderTemplate(): void
    {
        $items = SectionTemplates::items('hero');
        self::assertStringContainsString('Deine große Überschrift', $this->blob($items));
    }

    public function testRepeatingItemsFaq(): void
    {
        $items = SectionTemplates::items('faq', [
            'heading' => 'Eure Fragen',
            'items' => [
                ['q' => 'Liefert ihr?', 'a' => 'Ja, täglich frei Haus.'],
                ['q' => 'Bio?', 'a' => 'Alle Mehle sind Bio.'],
            ],
        ]);
        $blob = $this->blob($items);
        self::assertStringContainsString('Eure Fragen', $blob);
        self::assertStringContainsString('Liefert ihr?', $blob);
        self::assertStringContainsString('Ja, täglich frei Haus.', $blob);
        self::assertStringContainsString('Alle Mehle sind Bio.', $blob);
    }

    public function testPricingFeaturesList(): void
    {
        $items = SectionTemplates::items('pricing3', [
            'items' => [
                ['title' => 'Klein', 'price' => '5 €', 'period' => '/Tag', 'features' => ['1 Laib', 'Abholung'], 'button' => 'Wählen'],
            ],
        ]);
        $blob = $this->blob($items);
        self::assertStringContainsString('Klein', $blob);
        self::assertStringContainsString('1 Laib', $blob);
        self::assertStringContainsString('Abholung', $blob);
    }

    public function testMaliciousMarkupIsStripped(): void
    {
        $items = SectionTemplates::items('hero', [
            'headline' => '<script>alert(1)</script>Hallo',
            'button_url' => 'javascript:alert(1)',
        ]);
        $blob = $this->blob($items);
        self::assertStringNotContainsString('<script>', $blob, 'tags stripped from plain slot');
        self::assertStringContainsString('Hallo', $blob);
        self::assertStringNotContainsString('javascript:', $blob, 'unsafe URL scheme rejected → fallback');
    }

    public function testUnknownTemplateReturnsEmpty(): void
    {
        self::assertSame([], SectionTemplates::items('does_not_exist', ['headline' => 'x']));
    }

    public function testContentSchemaCoversNonMediaTemplates(): void
    {
        $schema = SectionTemplates::contentSchema();
        // every schema key is a real template
        foreach (array_keys($schema) as $k) {
            self::assertTrue(SectionTemplates::exists($k), "schema key {$k} is a real template");
        }
        // media-only templates correctly excluded
        self::assertArrayNotHasKey('logos', $schema);
        self::assertArrayNotHasKey('gallery_grid', $schema);
    }
}
