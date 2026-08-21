<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Golden tests (Schicht 2) locking the recurring "childhood bug" invariants of
 * the style compiler: alignment, responsive emission, scoped nav, container
 * layout. Pure class (no deps) → fast, no framework bootstrap.
 */
final class LayoutStyleCompilerTest extends TestCase
{
    private LayoutStyleCompiler $c;

    protected function setUp(): void
    {
        $this->c = new LayoutStyleCompiler();
    }

    public function testAlignCenterEmitsTextAlignJustifyAndAutoMargins(): void
    {
        $css = $this->c->compile(['align' => 'center']);
        self::assertStringContainsString('text-align:center', $css);
        self::assertStringContainsString('--bld-justify:center', $css, 'flex widgets centre via this var');
        self::assertStringContainsString('--bld-ml:auto', $css, 'block pills centre via auto margins');
        self::assertStringContainsString('--bld-mr:auto', $css);
    }

    public function testPartTypographyScopesEachTextPartIndependently(): void
    {
        // Multi-text element: title vs text get independent typography, each
        // scoped to its own part selector under the element scope.
        $css = $this->c->scoped('.x', [
            'boxTitleColor' => '#ff0000', 'boxTitleSize' => 30, 'boxTitleWeight' => '700',
            'boxTextColor' => '#0000ff', 'boxTextAlign' => 'center', 'boxTextLineHeight' => 1.6,
        ]);
        self::assertStringContainsString('.x .draggo-box-title{', $css);
        self::assertStringContainsString('color:#ff0000', $css);
        self::assertStringContainsString('font-size:30px', $css);
        self::assertStringContainsString('font-weight:700', $css);
        self::assertStringContainsString('.x .draggo-box-text{', $css);
        self::assertStringContainsString('color:#0000ff', $css);
        self::assertStringContainsString('text-align:center', $css);
        self::assertStringContainsString('line-height:1.6', $css);
    }

    public function testFlipPartsTargetFrontAndBackFacesSeparately(): void
    {
        $css = $this->c->scoped('.x', ['flipFTitleColor' => '#111111', 'flipBTitleColor' => '#222222']);
        self::assertStringContainsString('.x .draggo-flip-front .draggo-flip-title{', $css);
        self::assertStringContainsString('.x .draggo-flip-back .draggo-flip-title{', $css);
    }

    public function testAnimWordFallsBackToLegacyColorKey(): void
    {
        // New anWordColor wins; legacy animWordColor still applies when set alone.
        $legacy = $this->c->scoped('.x', ['animWordColor' => '#abcdef']);
        self::assertStringContainsString('.x .draggo-anim-words{color:#abcdef', $legacy);
        $fresh = $this->c->scoped('.x', ['anWordColor' => '#123456']);
        self::assertStringContainsString('.x .draggo-anim-words{color:#123456', $fresh);
    }

    public function testAlignLeftKeepsLeft(): void
    {
        $css = $this->c->compile(['align' => 'left']);
        self::assertStringContainsString('--bld-justify:flex-start', $css);
        self::assertStringContainsString('--bld-ml:0', $css);
        self::assertStringContainsString('--bld-mr:auto', $css);
    }

    public function testCompileEmitsNoDisplayFlexByItself(): void
    {
        // compile() must never inject display:flex (that belongs to flex()/
        // containerLayoutCss on the right node) — guards the container-shrink bug.
        self::assertStringNotContainsString('display:flex', $this->c->compile(['align' => 'center', 'bg' => '#fff']));
    }

    public function testRowAlignMapsToJustifyAndAlignItems(): void
    {
        $css = $this->c->rowAlign(['alignX' => 'center', 'alignY' => 'end']);
        self::assertStringContainsString('display:flex;', $css);
        self::assertStringContainsString('justify-content:center', $css);
        self::assertStringContainsString('align-items:flex-end', $css);
    }

    public function testFlexColumnAlignment(): void
    {
        $css = $this->c->flex(['alignX' => 'center']);
        self::assertStringContainsString('flex-direction:column', $css);
        self::assertStringContainsString('align-items:center', $css);
    }

    public function testResponsiveBlockEmitsMediaQueries(): void
    {
        $css = $this->c->responsiveBlock('.x', ['padding' => 'm', 'responsive' => ['tablet' => ['padding' => 'l'], 'mobile' => ['padding' => 's']]]);
        self::assertStringContainsString('@media (max-width:991px)', $css);
        self::assertStringContainsString('@media (max-width:767px)', $css);
        self::assertStringContainsString('!important', $css, 'inline base needs !important to be overridden');
    }

    public function testResponsiveBlockExtraInlinePerBreakpoint(): void
    {
        // rowAlign passed as extraInline must reach the @media block.
        $css = $this->c->responsiveBlock('.x', ['responsive' => ['mobile' => ['alignY' => 'center']]], fn (array $m): string => $this->c->rowAlign($m));
        self::assertStringContainsString('@media (max-width:767px)', $css);
        self::assertStringContainsString('align-items:center', $css);
    }

    public function testRowAlignResponsiveIsImportant(): void
    {
        $css = $this->c->rowAlignResponsive('.r', ['responsive' => ['tablet' => ['alignX' => 'center']]]);
        self::assertStringContainsString('@media (max-width:991px)', $css);
        self::assertStringContainsString('justify-content:center !important', $css);
    }

    public function testContainerLayoutCssFlexAndGrid(): void
    {
        $flex = $this->c->containerLayoutCss(['display' => 'flex', 'flexJustify' => 'center']);
        self::assertStringContainsString('display:flex', $flex);
        self::assertStringContainsString('justify-content:center', $flex);

        $grid = $this->c->containerLayoutCss(['display' => 'grid', 'gridColumns' => 3]);
        self::assertStringContainsString('grid-template-columns:repeat(3,1fr)', $grid);
    }

    public function testScopedNavColours(): void
    {
        $css = $this->c->scoped('.x', ['navColor' => '#ff0000', 'navHover' => '#00ff00']);
        self::assertStringContainsString('.x a{color:#ff0000', $css);
        self::assertStringContainsString('.x a:hover{color:#00ff00', $css);
    }

    public function testVisibilityCss(): void
    {
        $css = $this->c->visibilityCss('.x', ['hideMobile' => true]);
        self::assertStringContainsString('@media (max-width:767px){.x{display:none!important}}', $css);
    }

    public function testCustomCssScopedWrapsBareDeclarations(): void
    {
        self::assertStringContainsString('.x{color:red;}', $this->c->customCssScoped('.x', 'color:red;'));
        self::assertStringContainsString('.x{', $this->c->customCssScoped('.x', 'selector{color:red}'));
        self::assertStringNotContainsString('</style', $this->c->customCssScoped('.x', '</style><script>'));
    }
}
