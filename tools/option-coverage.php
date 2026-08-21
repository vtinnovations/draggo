<?php

declare(strict_types=1);

/**
 * Per-element option coverage (Schicht 1b). For EVERY element type, runs EVERY
 * StyleSchema option through the real compiler in isolation and reports which
 * produce no CSS. Run: ddev exec php packages/draggo/tools/option-coverage.php
 * (needs the project autoloader — pure classes, no kernel).
 */

require '/var/www/html/vendor/autoload.php';

use Vtinnovations\Draggo\Control\StyleSchema;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

$c = new LayoutStyleCompiler();

$types = [
    'draggo_accordion', 'draggo_alert', 'draggo_anim', 'draggo_breadcrumb', 'draggo_button',
    'draggo_carousel', 'draggo_codeblock', 'draggo_countdown', 'draggo_counter', 'draggo_cta',
    'draggo_divider', 'draggo_flipbox', 'draggo_gallery', 'draggo_hotspot', 'draggo_icon',
    'draggo_iconbox', 'draggo_iconlist', 'draggo_imagebox', 'draggo_logo', 'draggo_loop',
    'draggo_map', 'draggo_nav', 'draggo_pagetitle', 'draggo_pricelist', 'draggo_pricetable',
    'draggo_progress', 'draggo_quote', 'draggo_search', 'draggo_sitemap', 'draggo_social',
    'draggo_spacer', 'draggo_steps', 'draggo_tabs', 'draggo_toc', 'draggo_videoplaylist',
    'draggo_block', 'draggo_row_start', 'draggo_col',
];

// Keys that legitimately produce no inline/scoped CSS on their own:
//  - content/data controls (rendered into markup, not CSS)
//  - icon pickers (markup), css class/id (attributes), enabler selects,
//  - keys only effective inside the drawer @media (need navMobileMode=drawer),
//  - structural grid keys (handled by GridPresets/listeners, not compile()).
$expectedNoCss = array_flip([
    'cssClass', 'cssId', 'customCss', 'navHamburgerIcon', 'alertIcon',
    'navMobileMode', 'navBreakpoint', 'navDrawerSide', 'navDrawerWidth', 'navDrawerBg',
    'navHamburgerColor', 'navHamburgerSize', 'navDrawerAlign', 'navDrawerGap', 'navDrawerDivider', 'navOverlayColor',
    'gridPreset', 'gridCustom', 'gridTablet', 'gridMobile', 'width', 'display', 'gridColumns',
    'flexDirection', 'flexJustify', 'flexAlign', 'flexWrap', 'gap',
    'spacerTablet', 'spacerMobile', 'hideDesktop', 'hideTablet', 'hideMobile',
    'navSeparator', 'navIndicator', 'bgGradType', 'ovType', 'ovGradType', 'bgMedia',
    'textGradient', 'sticky',
]);

// Build a test value for a control based on its type/key.
$valueFor = static function (array $ctrl) {
    $key = $ctrl['key'];
    $type = $ctrl['type'];
    $lenKeys = ['width', 'maxWidth', 'height', 'maxHeight', 'minHeight', 'posTop', 'posRight', 'posBottom', 'posLeft', 'transformTranslateX', 'transformTranslateY'];
    return match (true) {
        $type === 'color'                        => '#abcdef',
        $type === 'switcher'                      => '1',
        $type === 'length'                        => '100px',
        $type === 'dimensions'                    => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'px'],
        $type === 'select' || $type === 'choose'  => (function () use ($ctrl) {
            foreach (($ctrl['options'] ?? $ctrl['choices'] ?? []) as $o) {
                $v = is_array($o) ? ($o[0] ?? '') : $o;
                if ((string) $v !== '') {
                    return $v;
                }
            }
            return 'x';
        })(),
        $type === 'number' && str_contains(strtolower($key), 'opacity') => '0.5',
        $key === 'lineHeight'                     => '1.5',
        $key === 'transformScale'                 => '1.1',
        $type === 'number'                        => 10,
        in_array($key, $lenKeys, true)            => '100px',
        $key === 'fontFamily' || str_contains($key, 'FontFamily') => 'Arial, sans-serif',
        default                                   => 'Teststring',
    };
};

// Full render path for a single-key layout.
$render = static function (LayoutStyleCompiler $c, array $l): string {
    return $c->compile($l)
        . $c->scoped('.x', $l)
        . $c->containerLayoutCss($l)
        . $c->rowAlign($l)
        . $c->flex($l)
        . $c->visibilityCss('.x', $l)
        . $c->overlayBefore('.x', $l);
};

$allDead = [];
$totalOptions = 0;
$contentTypes = ['text', 'textarea', 'rte', 'code', 'file', 'files', 'imgsize', 'url', 'repeater', 'headline', 'table', 'pairs', 'lines', 'icon', 'ui_heading', 'ui_divider', 'ui_html'];

foreach ($types as $type) {
    $groups = StyleSchema::for($type);
    $deadForType = [];
    foreach ($groups as $g) {
        foreach (($g['controls'] ?? []) as $ctrl) {
            if (!isset($ctrl['key'], $ctrl['type'])) {
                continue;
            }
            $key = $ctrl['key'];
            // Skip pure content/data controls (never emit layout CSS by design).
            if (in_array($ctrl['type'], $contentTypes, true)) {
                continue;
            }
            $totalOptions++;
            try {
                $out = $render($c, [$key => $valueFor($ctrl)]);
            } catch (\Throwable $e) {
                $deadForType[] = $key . ' [THROW: ' . $e->getMessage() . ']';
                continue;
            }
            if (trim($out) === '' && !isset($expectedNoCss[$key])) {
                $deadForType[] = $key;
                $allDead[$key] = ($allDead[$key] ?? 0) + 1;
            }
        }
    }
    if ($deadForType !== []) {
        echo "⚠ $type: " . implode(', ', array_unique($deadForType)) . "\n";
    }
}

// ── Combination checks: conditional keys that only emit with an enabling
// sibling (so the allowlist above can't hide a real dead switch). ──
echo "\n--- Kombinations-Checks (bedingte Optionen) ---\n";
$combos = [
    ['Nav-Drawer (bg/divider/overlay/align/gap)', fn () => $c->scoped('.x', [
        'navMobileMode' => 'drawer', 'navDrawerBg' => '#abcdef', 'navColor' => '#123456',
        'navDrawerDivider' => '#654321', 'navOverlayColor' => '#0a0a0a', 'navHamburgerColor' => '#111111',
        'navHamburgerSize' => 30, 'navDrawerWidth' => 320, 'navDrawerAlign' => 'center', 'navDrawerGap' => 12,
    ]), ['#abcdef', '#654321', '#0a0a0a', 'justify-content', 'gap:12px', 'width:320px']],
    ['Verlauf-Hintergrund', fn () => $c->compile(['bgGradFrom' => '#111111', 'bgGradTo' => '#222222', 'bgGradType' => 'linear', 'bgGradAngle' => 90]),
        ['linear-gradient(90deg,#111111,#222222']],
    ['Overlay (Farbe/Opacity/Blend)', fn () => $c->overlayBefore('.x', ['ovType' => 'color', 'ovColor' => '#abcdef', 'ovOpacity' => 0.5, 'ovBlend' => 'multiply']),
        ['#abcdef', 'multiply']],
    ['Text-Verlauf', fn () => $c->compile(['textGradient' => true, 'gradFrom' => '#111111', 'gradTo' => '#222222']),
        ['background-clip:text', '#111111']],
    ['Nav-Indikator Box', fn () => $c->scoped('.x', ['navIndicator' => 'box', 'navIndicatorColor' => '#abcdef']),
        ['#abcdef']],
    ['Nav-Trenner Linie', fn () => $c->scoped('.x', ['navSeparator' => 'line', 'navSeparatorColor' => '#abcdef']),
        ['border-left', '#abcdef']],
    ['Container Flex+Grid', fn () => $c->containerLayoutCss(['display' => 'flex', 'flexJustify' => 'center', 'gap' => 'm']),
        ['display:flex', 'justify-content:center']],
];
$comboFail = 0;
foreach ($combos as [$label, $fn, $expects]) {
    $out = $fn();
    $missing = array_filter($expects, static fn (string $e): bool => !str_contains($out, $e));
    if ($missing === []) {
        echo "  ✅ $label\n";
    } else {
        $comboFail++;
        echo "  ⛔ $label — fehlt: " . implode(', ', $missing) . "\n";
    }
}

echo "\n--- Zusammenfassung ---\n";
echo "Element-Typen geprüft: " . count($types) . "\n";
echo "Style-Optionen geprüft (Instanzen): $totalOptions\n";
if ($allDead === []) {
    echo "✅ KEINE unerwartet toten Optionen — jede Style-Option erzeugt CSS (oder ist als no-CSS bekannt).\n";
} else {
    echo "⛔ Unerwartet OHNE CSS-Ausgabe:\n";
    foreach ($allDead as $k => $n) {
        echo "  - $k (in $n Elementen)\n";
    }
}
