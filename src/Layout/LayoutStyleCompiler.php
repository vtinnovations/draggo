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

namespace Vtinnovations\Draggo\Layout;

/**
 * Turns a validated layout array (stored in tl_content.draggo_layout) into a
 * safe inline-style string for a grid column/row. Only a fixed vocabulary is
 * emitted — no free CSS — so column styling can never inject arbitrary rules.
 */
final class LayoutStyleCompiler
{
    /** Padding scale keys → CSS length. */
    private const PADDING = [
        'none' => '0',
        'xs'   => '.25rem',
        's'    => '.5rem',
        'm'    => '1rem',
        'l'    => '2rem',
        'xl'   => '3rem',
    ];

    /**
     * @param array<string,mixed> $layout
     */
    public function compile(array $layout): string
    {
        $style = '';

        $bg = $this->color($layout['bg'] ?? null);
        if ($bg !== null) {
            $style .= 'background-color:' . $bg . ';';
        }

        $color = $this->color($layout['color'] ?? null);
        if ($color !== null) {
            $style .= 'color:' . $color . ';';
        }

        $pad = (string) ($layout['padding'] ?? '');
        if (isset(self::PADDING[$pad])) {
            $style .= 'padding:' . self::PADDING[$pad] . ';';
        }

        $margin = (string) ($layout['margin'] ?? '');
        if (isset(self::PADDING[$margin])) {
            $style .= 'margin-top:' . self::PADDING[$margin] . ';margin-bottom:' . self::PADDING[$margin] . ';';
        }

        // Per-side spacing (top/right/bottom/left + unit) — overrides the scale.
        foreach (['paddingBox' => 'padding', 'marginBox' => 'margin'] as $k => $css) {
            $box = $this->dimensions($layout[$k] ?? null);
            if ($box !== null) {
                $style .= $css . ':' . $box . ';';
            }
        }

        // Container min-height is a bare number (px); element min-height is a
        // length string handled by the len loop below.
        if (isset($layout['minHeight']) && is_numeric($layout['minHeight'])) {
            $minH = (int) $layout['minHeight'];
            if ($minH > 0 && $minH <= 4000) {
                $style .= 'min-height:' . $minH . 'px;';
            }
        }

        $align = (string) ($layout['align'] ?? '');
        if (\in_array($align, ['left', 'center', 'right'], true)) {
            // text-align centres text + inline children (block/text elements).
            // --bld-justify lets flex-based element roots (counter, button, …)
            // centre the BOX itself — an inline-flex root ignores its own
            // text-align, so this variable is the reliable lever.
            $jmap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
            // --bld-ml/--bld-mr drive auto-margins so block-level inline widgets
            // (e.g. a fit-content button/pill) centre themselves within the column.
            $mmap = ['left' => ['0', 'auto'], 'center' => ['auto', 'auto'], 'right' => ['auto', '0']];
            $style .= 'text-align:' . $align . ';--bld-justify:' . $jmap[$align] . ';'
                . '--bld-ml:' . $mmap[$align][0] . ';--bld-mr:' . $mmap[$align][1] . ';';
        }

        $image = $this->imageUrl($layout['image'] ?? null);
        if ($image !== null) {
            $bgSize = (string) ($layout['bgSize'] ?? 'cover');
            $size = \in_array($bgSize, ['cover', 'contain', 'auto'], true) ? $bgSize : 'cover';
            $bgPos = (string) ($layout['bgPosition'] ?? 'center');
            $pos = \in_array($bgPos, ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'], true) ? $bgPos : 'center';
            $bgRep = (string) ($layout['bgRepeat'] ?? 'no-repeat');
            $repeat = \in_array($bgRep, ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'], true) ? $bgRep : 'no-repeat';
            // "fixed" = a simple parallax effect.
            $attach = (($layout['bgAttachment'] ?? '') === 'fixed') ? 'fixed' : 'scroll';
            $style .= "background-image:url('" . $image . "');background-size:" . $size . ';background-position:' . $pos . ';background-repeat:' . $repeat . ';background-attachment:' . $attach . ';';
        }

        // Gradient background (when no image is set). Linear (with angle) or radial.
        $gFrom = $this->color($layout['bgGradFrom'] ?? null);
        $gTo = $this->color($layout['bgGradTo'] ?? null);
        if ($image === null && $gFrom !== null && $gTo !== null) {
            if (($layout['bgGradType'] ?? 'linear') === 'radial') {
                $style .= 'background-image:radial-gradient(circle,' . $gFrom . ',' . $gTo . ');';
            } else {
                $ang = (int) ($layout['bgGradAngle'] ?? 135);
                $ang = ($ang >= 0 && $ang <= 360) ? $ang : 135;
                $style .= 'background-image:linear-gradient(' . $ang . 'deg,' . $gFrom . ',' . $gTo . ');';
            }
        }

        // ── Typography ──────────────────────────────────────────────
        $ff = (string) ($layout['fontFamily'] ?? '');
        if ($ff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $ff)) {
            $style .= 'font-family:' . $ff . ';';
        }
        $fsz = (int) ($layout['fontSize'] ?? 0);
        if ($fsz > 0 && $fsz <= 400) {
            $style .= 'font-size:' . $fsz . 'px;';
        }
        $fw = (string) ($layout['fontWeight'] ?? '');
        if (\in_array($fw, ['100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $style .= 'font-weight:' . $fw . ';';
        }
        $tt = (string) ($layout['textTransform'] ?? '');
        if (\in_array($tt, ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $style .= 'text-transform:' . $tt . ';';
        }
        $fst = (string) ($layout['fontStyle'] ?? '');
        if (\in_array($fst, ['normal', 'italic'], true)) {
            $style .= 'font-style:' . $fst . ';';
        }
        $td = (string) ($layout['textDecoration'] ?? '');
        if (\in_array($td, ['none', 'underline', 'line-through'], true)) {
            $style .= 'text-decoration:' . $td . ';';
        }
        $lh = $layout['lineHeight'] ?? '';
        if (is_numeric($lh) && (float) $lh > 0 && (float) $lh <= 5) {
            $style .= 'line-height:' . (float) $lh . ';';
        }
        foreach (['letterSpacing' => 'letter-spacing', 'wordSpacing' => 'word-spacing'] as $k => $css) {
            if (isset($layout[$k]) && is_numeric($layout[$k]) && abs((float) $layout[$k]) <= 100) {
                $style .= $css . ':' . (float) $layout[$k] . 'px;';
            }
        }

        // ── Box: size, opacity, border, radius, shadow ──────────────
        $len = '/^\d+(\.\d+)?(px|%|rem|em|vh|vw)$/';
        foreach (['width' => 'width', 'maxWidth' => 'max-width', 'height' => 'height', 'minHeight' => 'min-height', 'maxHeight' => 'max-height'] as $k => $css) {
            if (isset($layout[$k]) && \is_string($layout[$k]) && preg_match($len, $layout[$k])) {
                $style .= $css . ':' . $layout[$k] . ';';
            }
        }
        if (isset($layout['opacity']) && is_numeric($layout['opacity']) && (float) $layout['opacity'] >= 0 && (float) $layout['opacity'] <= 1) {
            $style .= 'opacity:' . (float) $layout['opacity'] . ';';
        }
        $bw = (int) ($layout['borderWidth'] ?? 0);
        $bs = (string) ($layout['borderStyle'] ?? '');
        if ($bw > 0 && $bw <= 50 && \in_array($bs, ['solid', 'dashed', 'dotted', 'double'], true)) {
            $bc = $this->color($layout['borderColor'] ?? null) ?? '#000000';
            $style .= 'border:' . $bw . 'px ' . $bs . ' ' . $bc . ';';
        }
        $br = (int) ($layout['borderRadius'] ?? 0);
        if ($br > 0 && $br <= 400) {
            $style .= 'border-radius:' . $br . 'px;';
        }
        $shadows = [
            'sm' => '0 1px 3px rgba(0,0,0,.12)',
            'md' => '0 4px 12px rgba(0,0,0,.15)',
            'lg' => '0 10px 30px rgba(0,0,0,.2)',
            'xl' => '0 20px 50px rgba(0,0,0,.25)',
        ];
        if (isset($shadows[(string) ($layout['boxShadow'] ?? '')])) {
            $style .= 'box-shadow:' . $shadows[$layout['boxShadow']] . ';';
        }

        // ── Advanced: z-index, position + offsets, transform (Phase C) ──
        if (isset($layout['zIndex']) && is_numeric($layout['zIndex'])) {
            $z = (int) $layout['zIndex'];
            if ($z >= -999 && $z <= 9999) {
                $style .= 'z-index:' . $z . ';';
            }
        }
        $pos = (string) ($layout['position'] ?? '');
        if (\in_array($pos, ['relative', 'absolute', 'fixed', 'sticky'], true)) {
            $style .= 'position:' . $pos . ';';
            foreach (['posTop' => 'top', 'posRight' => 'right', 'posBottom' => 'bottom', 'posLeft' => 'left'] as $k => $css) {
                if (isset($layout[$k]) && \is_string($layout[$k]) && preg_match($len, $layout[$k])) {
                    $style .= $css . ':' . $layout[$k] . ';';
                }
            }
        }
        $tf = [];
        $rot = (int) ($layout['transformRotate'] ?? 0);
        if ($rot !== 0 && abs($rot) <= 360) {
            $tf[] = 'rotate(' . $rot . 'deg)';
        }
        if (isset($layout['transformScale']) && is_numeric($layout['transformScale'])) {
            $sc = (float) $layout['transformScale'];
            if ($sc > 0 && $sc <= 5 && $sc !== 1.0) {
                $tf[] = 'scale(' . $sc . ')';
            }
        }
        foreach (['transformTranslateX' => 'translateX', 'transformTranslateY' => 'translateY'] as $k => $fn) {
            if (isset($layout[$k]) && \is_string($layout[$k]) && preg_match($len, $layout[$k])) {
                $tf[] = $fn . '(' . $layout[$k] . ')';
            }
        }
        if ($tf !== []) {
            $style .= 'transform:' . implode(' ', $tf) . ';';
        }

        $textShadows = [
            'sm' => '0 1px 2px rgba(0,0,0,.3)',
            'md' => '0 2px 4px rgba(0,0,0,.4)',
            'lg' => '0 4px 8px rgba(0,0,0,.5)',
        ];
        if (isset($textShadows[(string) ($layout['textShadow'] ?? '')])) {
            $style .= 'text-shadow:' . $textShadows[$layout['textShadow']] . ';';
        }

        // Sticky is handled by the FE runtime (position:fixed on scroll) — pure
        // CSS position:sticky fails inside short flex/grid parents.

        // Gradient text — emit LAST so color:transparent wins over any colour.
        if (!empty($layout['textGradient'])) {
            $from = $this->color($layout['gradFrom'] ?? null) ?? '#000000';
            $to = $this->color($layout['gradTo'] ?? null) ?? '#888888';
            $style .= 'background-image:linear-gradient(90deg,' . $from . ',' . $to . ');'
                . '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;';
        }

        // Container width mode is handled in ContainerLayoutListener.
        // Responsive visibility + scroll animation + lightbox/hover-zoom are
        // emitted by ElementStyleListener (need media queries / extra selectors).

        return $style;
    }

    /**
     * Background-only declarations for an overlay layer (color | gradient |
     * image) + opacity + blend mode. Returns '' when no overlay is configured.
     * Shared by overlayStyle() (DOM div) and overlayBefore() (::before).
     *
     * @param array<string,mixed> $l
     */
    private function overlayDecl(array $l): string
    {
        $type = (string) ($l['ovType'] ?? '');
        $decl = '';

        if ($type === 'color') {
            $c = $this->color($l['ovColor'] ?? null);
            if ($c === null) {
                return '';
            }
            $decl .= 'background-color:' . $c . ';';
        } elseif ($type === 'gradient') {
            $from = $this->color($l['ovGradFrom'] ?? null);
            $to = $this->color($l['ovGradTo'] ?? null);
            if ($from === null || $to === null) {
                return '';
            }
            if (($l['ovGradType'] ?? 'linear') === 'radial') {
                $decl .= 'background-image:radial-gradient(circle,' . $from . ',' . $to . ');';
            } else {
                $ang = (int) ($l['ovGradAngle'] ?? 135);
                $ang = ($ang >= 0 && $ang <= 360) ? $ang : 135;
                $decl .= 'background-image:linear-gradient(' . $ang . 'deg,' . $from . ',' . $to . ');';
            }
        } elseif ($type === 'image') {
            $img = $this->imageUrl($l['ovImage'] ?? null);
            if ($img === null) {
                return '';
            }
            $decl .= "background-image:url('" . $img . "');background-size:cover;background-position:center;background-repeat:no-repeat;";
        } else {
            return '';
        }

        if (isset($l['ovOpacity']) && is_numeric($l['ovOpacity']) && (float) $l['ovOpacity'] >= 0 && (float) $l['ovOpacity'] <= 1) {
            $decl .= 'opacity:' . (float) $l['ovOpacity'] . ';';
        }
        $blend = (string) ($l['ovBlend'] ?? '');
        if (\in_array($blend, ['multiply', 'screen', 'overlay', 'darken', 'lighten'], true)) {
            $decl .= 'mix-blend-mode:' . $blend . ';';
        }

        return $decl;
    }

    /** True when this layout carries a usable background overlay. */
    public function hasOverlay(array $l): bool
    {
        return $this->overlayDecl($l) !== '';
    }

    /**
     * Inline style for an overlay <div> injected as a row/column's first child.
     * Sits above the parent's background, below content (z-index:-1 inside the
     * parent's isolated stacking context). Returns '' when no overlay.
     *
     * @param array<string,mixed> $l
     */
    public function overlayStyle(array $l): string
    {
        $decl = $this->overlayDecl($l);
        if ($decl === '') {
            return '';
        }

        return 'position:absolute;inset:0;pointer-events:none;z-index:-1;' . $decl;
    }

    /**
     * Inline additions for a row/column wrapper that hosts an overlay div:
     * establish a positioning context + isolated stacking context so the
     * overlay's z-index:-1 layers between bg and content. Returns '' otherwise.
     *
     * @param array<string,mixed> $l
     */
    public function overlayHostStyle(array $l): string
    {
        return $this->hasOverlay($l) ? 'position:relative;isolation:isolate;' : '';
    }

    /**
     * `$sel{position}` + `$sel::before{overlay}` rule for elements/containers
     * that style via a stylesheet (not a DOM div). Returns '' when no overlay.
     *
     * @param array<string,mixed> $l
     */
    public function overlayBefore(string $sel, array $l): string
    {
        $decl = $this->overlayDecl($l);
        if ($decl === '') {
            return '';
        }

        return $sel . '{position:relative;isolation:isolate;}'
            . $sel . '::before{content:"";position:absolute;inset:0;pointer-events:none;z-index:-1;' . $decl . '}';
    }

    // ── Media background (video / slider) — DOM layers, z-index:-2 ──────

    /** Valid image URLs from a layout's bgSlides list (max 12). @return list<string> */
    private function slideUrls(array $l): array
    {
        $slides = $l['bgSlides'] ?? null;
        if (!\is_array($slides)) {
            return [];
        }
        $out = [];
        foreach ($slides as $s) {
            $u = $this->imageUrl($s);
            if ($u !== null) {
                $out[] = $u;
            }
            if (\count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    /** True when a video or slider background is configured. */
    public function hasMediaBg(array $l): bool
    {
        $t = (string) ($l['bgMedia'] ?? '');
        if ($t === 'video') {
            return $this->imageUrl($l['bgVideo'] ?? null) !== null;
        }
        if ($t === 'slider') {
            return $this->slideUrls($l) !== [];
        }

        return false;
    }

    /**
     * HTML for the media background layer (a `<video>` or a stack of slide
     * divs). Empty string when no media background. CSS positions it (class
     * .draggo-bg-media → absolute, inset:0, z-index:-2).
     *
     * @param array<string,mixed> $l
     */
    public function mediaBgLayerHtml(array $l): string
    {
        $t = (string) ($l['bgMedia'] ?? '');

        if ($t === 'video') {
            $src = $this->imageUrl($l['bgVideo'] ?? null);
            if ($src === null) {
                return '';
            }
            $type = $this->videoMime($src);
            $poster = $this->imageUrl($l['bgVideoPoster'] ?? null);
            $posterAttr = $poster !== null ? ' poster="' . $this->attr($poster) . '"' : '';

            return '<div class="draggo-bg-media" aria-hidden="true">'
                . '<video class="draggo-bg-video" autoplay muted loop playsinline preload="metadata"' . $posterAttr . '>'
                . '<source src="' . $this->attr($src) . '" type="' . $type . '"></video></div>';
        }

        if ($t === 'slider') {
            $slides = $this->slideUrls($l);
            if ($slides === []) {
                return '';
            }
            $interval = (int) ($l['bgSlideInterval'] ?? 5000);
            $interval = ($interval >= 1000 && $interval <= 20000) ? $interval : 5000;
            $fx = \in_array($l['bgSlideFx'] ?? 'fade', ['fade', 'slide'], true) ? $l['bgSlideFx'] : 'fade';
            $divs = '';
            foreach ($slides as $i => $u) {
                $divs .= '<div class="draggo-bg-slide' . ($i === 0 ? ' is-on' : '') . '" style="background-image:url(\'' . $u . '\')"></div>';
            }
            // "slide" needs a flex track to translate; "fade" stacks + cross-fades.
            $inner = $fx === 'slide' ? '<div class="draggo-bg-track">' . $divs . '</div>' : $divs;

            return '<div class="draggo-bg-media draggo-bg-slider draggo-bg-slider--' . $fx . '" data-interval="' . $interval . '" aria-hidden="true">' . $inner . '</div>';
        }

        return '';
    }

    /** HTML for the overlay <div> (color/gradient/image tint), or ''. */
    private function overlayDivHtml(array $l): string
    {
        $s = $this->overlayStyle($l);

        return $s !== '' ? '<div class="draggo-bg-overlay" aria-hidden="true" style="' . $this->attr($s) . '"></div>' : '';
    }

    /**
     * All background layer DOM (media first, then overlay) injected as the first
     * children of a row/column wrapper. Empty string when neither is set.
     *
     * @param array<string,mixed> $l
     */
    public function bgLayersHtml(array $l): string
    {
        return $this->mediaBgLayerHtml($l) . $this->overlayDivHtml($l);
    }

    /**
     * Positioning context for a wrapper that hosts media/overlay layers. Same as
     * overlayHostStyle but also triggers for media backgrounds.
     *
     * @param array<string,mixed> $l
     */
    public function bgHostStyle(array $l): string
    {
        return ($this->hasOverlay($l) || $this->hasMediaBg($l)) ? 'position:relative;isolation:isolate;' : '';
    }

    private function videoMime(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));

        return [
            'webm' => 'video/webm',
            'ogv'  => 'video/ogg',
            'ogg'  => 'video/ogg',
        ][$ext] ?? 'video/mp4';
    }

    private function attr(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Full container style (full-width wrapper + display mode flex/grid/column)
     * plus the element's own compiled styles. Used for units (header/footer);
     * the page-article variant lives in ContainerLayoutListener.
     *
     * @param array<string,mixed> $l
     */
    public function containerStyle(array $l): string
    {
        $gaps = ['none' => '0', 'xs' => '.25rem', 's' => '.5rem', 'm' => '1rem', 'l' => '2rem', 'xl' => '3rem'];
        $gap = $gaps[(string) ($l['gap'] ?? '')] ?? '';
        $display = (string) ($l['display'] ?? '');
        $css = 'width:100%;max-width:none;';

        if ($display === 'grid') {
            $cols = (int) ($l['gridColumns'] ?? 0);
            $cols = ($cols >= 1 && $cols <= 12) ? $cols : 2;
            $css .= 'display:grid;grid-template-columns:repeat(' . $cols . ',1fr);' . ($gap !== '' ? 'gap:' . $gap . ';' : '');
        } elseif ($display === 'flex') {
            $dir = \in_array($l['flexDirection'] ?? '', ['row', 'column', 'row-reverse', 'column-reverse'], true) ? $l['flexDirection'] : 'row';
            $justify = \in_array($l['flexJustify'] ?? '', ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'], true) ? $l['flexJustify'] : 'flex-start';
            $align = \in_array($l['flexAlign'] ?? '', ['stretch', 'flex-start', 'center', 'flex-end'], true) ? $l['flexAlign'] : 'stretch';
            $wrap = (($l['flexWrap'] ?? '') === 'wrap') ? 'wrap' : 'nowrap';
            $css .= 'display:flex;flex-direction:' . $dir . ';justify-content:' . $justify . ';align-items:' . $align . ';flex-wrap:' . $wrap . ';' . ($gap !== '' ? 'gap:' . $gap . ';' : '');
        } else {
            $boxed = (($l['width'] ?? 'full') === 'boxed');
            $map = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'];
            $ai = $map[$l['alignX'] ?? ''] ?? ($boxed ? 'center' : 'stretch');
            $jc = $map[$l['alignY'] ?? ''] ?? 'flex-start';
            $css .= 'display:flex;flex-direction:column;align-items:' . $ai . ';justify-content:' . $jc . ';';
        }

        return $css . $this->compile($l);
    }

    /**
     * Inline style for the editor canvas preview. Same as compile() but without
     * sticky positioning (which would misbehave inside the scrollable canvas).
     *
     * @param array<string,mixed> $layout
     */
    public function previewCss(array $layout): string
    {
        $css = $this->compile($layout);

        // Sticky toggle (Effekte).
        $css = (string) preg_replace('/position:sticky;top:\d+px;z-index:\d+;/', '', $css);
        // Advanced positioning would break the static canvas preview → drop it.
        $css = (string) preg_replace('/position:(?:relative|absolute|fixed|sticky);/', '', $css);
        $css = (string) preg_replace('/(?:top|right|bottom|left):[^;]+;/', '', $css);

        return $css;
    }

    /**
     * Build a CSS shorthand "t r b l" from a dimensions object {top,right,
     * bottom,left,unit}. Returns null when empty/invalid.
     *
     * @param mixed $box
     */
    private function dimensions(mixed $box): ?string
    {
        if (!\is_array($box)) {
            return null;
        }
        $unit = \in_array($box['unit'] ?? 'px', ['px', '%', 'rem', 'em'], true) ? $box['unit'] : 'px';
        $parts = [];
        $any = false;
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $v = $box[$side] ?? '';
            if ($v === '' || !is_numeric($v)) {
                $parts[] = '0';
            } else {
                $parts[] = ((float) $v) . $unit;
                $any = true;
            }
        }

        return $any ? implode(' ', $parts) : null;
    }

    /**
     * Child-targeted CSS that can't live in an inline wrapper style — currently
     * the navigation element (links / hover / active / indicator). Scoped to
     * $sel (= .draggo-el-{id}). Returns '' when no nav styling is set.
     *
     * @param array<string,mixed> $l
     */
    public function scoped(string $sel, array $l, bool $mediaSafe = false): string
    {
        // $mediaSafe = true skips the blocks that emit their own @media query
        // (drawer, spacer) so the rest can be wrapped in a breakpoint media query
        // by the caller (responsive per-breakpoint scoped styling) without
        // illegal @media nesting.
        $color = fn (mixed $v): ?string => $this->color($v);
        $css = '';

        // Generic link styling for text elements (the "Links" style group):
        // hover colour, underline behaviour, weight, and a symbol before/after
        // each link. linkColor itself is emitted elsewhere (CssGenerator / chip).
        $la = $sel . ' a';
        $laDecl = '';
        $lw = (string) ($l['linkWeight'] ?? '');
        if (\in_array($lw, ['300', '400', '500', '600', '700', '800', '900'], true)) {
            $laDecl .= 'font-weight:' . $lw . ';';
        }
        $ldeco = (string) ($l['linkDeco'] ?? '');
        if ($ldeco === 'none' || $ldeco === 'underline') {
            $laDecl .= 'text-decoration:' . $ldeco . ';';
        } elseif ($ldeco === 'hover') {
            $laDecl .= 'text-decoration:none;';
        }
        if ($laDecl !== '') {
            $css .= $la . '{' . $laDecl . '}';
        }
        if ($ldeco === 'hover') {
            $css .= $la . ':hover{text-decoration:underline;}';
        }
        if ($c = $color($l['linkHoverColor'] ?? null)) {
            $css .= $la . ':hover{color:' . $c . ';}';
        }
        if (($lb = $this->cssContent($l['linkBefore'] ?? '')) !== '') {
            $css .= $la . '::before{content:"' . $lb . '";margin-right:.3em;}';
        }
        if (($lf = $this->cssContent($l['linkAfter'] ?? '')) !== '') {
            $css .= $la . '::after{content:"' . $lf . '";margin-left:.3em;}';
        }

        // ── Form element styling ($sel sits on the .draggo-form wrapper) ──
        $inputSel = $sel . ' input,' . $sel . ' textarea,' . $sel . ' select';
        $inDecl = '';
        if ($c = $color($l['formInputBg'] ?? null)) { $inDecl .= 'background-color:' . $c . ';'; }
        if ($c = $color($l['formInputColor'] ?? null)) { $inDecl .= 'color:' . $c . ';'; }
        if ($c = $color($l['formInputBorder'] ?? null)) { $inDecl .= 'border-color:' . $c . ';'; }
        if (isset($l['formInputRadius']) && is_numeric($l['formInputRadius'])) { $inDecl .= 'border-radius:' . (int) $l['formInputRadius'] . 'px;'; }
        if ($inDecl !== '') { $css .= $inputSel . '{' . $inDecl . '}'; }
        if ($c = $color($l['formAccent'] ?? null)) {
            $css .= $sel . ' input:focus,' . $sel . ' textarea:focus,' . $sel . ' select:focus{border-color:' . $c . ';box-shadow:0 0 0 3px ' . $c . '33;outline:none;}';
        }
        if ($c = $color($l['formLabelColor'] ?? null)) {
            $css .= $sel . ' .widget>label,' . $sel . ' legend{color:' . $c . ';}';
        }
        if (isset($l['formGap']) && is_numeric($l['formGap'])) {
            $css .= $sel . ' .formbody{gap:' . (int) $l['formGap'] . 'px;}';
        }
        $btnSel = $sel . ' button[type=submit],' . $sel . ' input[type=submit]';
        $btnDecl = '';
        if ($c = $color($l['formBtnBg'] ?? null)) { $btnDecl .= 'background-color:' . $c . ';'; }
        if ($c = $color($l['formBtnColor'] ?? null)) { $btnDecl .= 'color:' . $c . ';'; }
        if (isset($l['formBtnRadius']) && is_numeric($l['formBtnRadius'])) { $btnDecl .= 'border-radius:' . (int) $l['formBtnRadius'] . 'px;'; }
        if (!empty($l['formBtnFull'])) { $btnDecl .= 'width:100%;'; }
        if ($btnDecl !== '') { $css .= $btnSel . '{' . $btnDecl . '}'; }
        if ($c = $color($l['formBtnHoverBg'] ?? null)) {
            $css .= $sel . ' button[type=submit]:hover,' . $sel . ' input[type=submit]:hover{background-color:' . $c . ';}';
        }

        // $sel (.draggo-el-{id}) is placed ON the <nav>, so links are direct
        // descendants ($sel a), not $sel .draggo-nav a.
        $a = $sel . ' a';

        $aDecl = '';
        if ($c = $color($l['navColor'] ?? null)) {
            $aDecl .= 'color:' . $c . ';';
        }
        $ff = (string) ($l['navFontFamily'] ?? '');
        if ($ff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $ff)) {
            $aDecl .= 'font-family:' . $ff . ';';
        }
        if (isset($l['navLetterSpacing']) && is_numeric($l['navLetterSpacing']) && abs((float) $l['navLetterSpacing']) <= 100) {
            $aDecl .= 'letter-spacing:' . (float) $l['navLetterSpacing'] . 'px;';
        }
        $size = (int) ($l['navSize'] ?? 0);
        if ($size > 0 && $size <= 80) {
            $aDecl .= 'font-size:' . $size . 'px;';
        }
        $w = (string) ($l['navWeight'] ?? '');
        if (\in_array($w, ['300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $aDecl .= 'font-weight:' . $w . ';';
        }
        $tt = (string) ($l['navTransform'] ?? '');
        if (\in_array($tt, ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $aDecl .= 'text-transform:' . $tt . ';';
        }
        $padV = isset($l['navPadV']) ? (int) $l['navPadV'] : null;
        $padH = isset($l['navPadH']) ? (int) $l['navPadH'] : null;
        if ($padV !== null || $padH !== null) {
            $aDecl .= 'padding:' . (($padV ?? 8)) . 'px ' . (($padH ?? 12)) . 'px;';
        }
        if ($aDecl !== '') {
            $css .= $a . '{' . $aDecl . '}';
        }

        if ($c = $color($l['navHover'] ?? null)) {
            $css .= $a . ':hover{color:' . $c . ';}';
        }
        if ($c = $color($l['navHoverBg'] ?? null)) {
            $css .= $a . ':hover{background-color:' . $c . ';}';
        }
        if ($c = $color($l['navActive'] ?? null)) {
            $css .= $sel . ' .active>a,' . $sel . ' .trail>a{color:' . $c . ';}';
        }
        // Nav bar background (the <nav> itself).
        if ($c = $color($l['navBg'] ?? null)) {
            $css .= $sel . '{background-color:' . $c . ';}';
        }
        // Link corner radius (rounded items / pill nav).
        if (isset($l['navRadius']) && is_numeric($l['navRadius']) && (int) $l['navRadius'] >= 0 && (int) $l['navRadius'] <= 400) {
            $css .= $a . '{border-radius:' . (int) $l['navRadius'] . 'px;}';
        }
        // Separators between top-level items (line / dot).
        $sep = (string) ($l['navSeparator'] ?? '');
        $sepC = $color($l['navSeparatorColor'] ?? null) ?? 'currentColor';
        if ($sep === 'line') {
            $css .= $sel . '>.draggo-nav-list>.draggo-nav-item+.draggo-nav-item{border-left:1px solid ' . $sepC . ';}';
        } elseif ($sep === 'dot') {
            $css .= $sel . '>.draggo-nav-list>.draggo-nav-item+.draggo-nav-item::before{content:"\\2022";color:' . $sepC . ';opacity:.6;align-self:center;margin:0 .25rem;}';
        }

        $gap = isset($l['navGap']) ? (int) $l['navGap'] : null;
        if ($gap !== null && $gap >= 0 && $gap <= 100) {
            $css .= $sel . '>.draggo-nav-list,' . $sel . ' .draggo-nav-list{gap:' . $gap . 'px;}';
        }
        // Menu alignment (justify the top-level nav list).
        $al = (string) ($l['navAlign'] ?? '');
        if (\in_array($al, ['flex-start', 'center', 'flex-end', 'space-between'], true)) {
            $css .= $sel . '>.draggo-nav-list{justify-content:' . $al . ';width:100%;}';
        }

        // Responsive: collapse to a sliding hamburger drawer below a breakpoint.
        if (!$mediaSafe && (string) ($l['navMobileMode'] ?? '') === 'drawer') {
            // The drawer is inherently a mobile feature, so its colours/sizes
            // resolve from the mobile responsive override first, then the base
            // layout. (General responsive overrides don't reach scoped() — only
            // compile() — so reading them here is what makes "mobile" nav colours
            // actually apply to the drawer.)
            $mob = \is_array($l['responsive']['mobile'] ?? null) ? $l['responsive']['mobile'] : [];
            $eff = static fn (string $k): mixed => $mob[$k] ?? ($l[$k] ?? null);

            $bp = (int) ($eff('navBreakpoint') ?? 0);
            $bp = ($bp >= 320 && $bp <= 1600) ? $bp : 992;
            $side = (($eff('navDrawerSide') ?? 'right') === 'left') ? 'left' : 'right';
            $w = (int) ($eff('navDrawerWidth') ?? 0);
            $w = ($w >= 180 && $w <= 600) ? $w : 280;
            $dbg = $color($eff('navDrawerBg')) ?? '#ffffff';
            $hsz = (int) ($eff('navHamburgerSize') ?? 0);
            $hsz = ($hsz >= 14 && $hsz <= 64) ? $hsz : 28;
            $hidden = $side === 'left' ? '-100%' : '100%';
            $tgDecl = 'display:inline-block;font-size:' . $hsz . 'px;line-height:1;';
            if ($c = $color($eff('navHamburgerColor'))) {
                $tgDecl .= 'color:' . $c . ';';
            }
            // Header nav links usually inherit a light colour from the theme
            // header (often via a high-specificity rule like `#header a{color:#fff}`),
            // which makes them invisible in the (default white) drawer. Force a
            // readable colour with !important so theme rules can't win: the user's
            // navColor (mobile override or base) if set, else a contrasting default.
            $drawerLink = $color($eff('navColor')) ?? $this->contrastColor($dbg);
            // The "box" indicator paints the active/trail link with a coloured
            // background; forcing the drawer link colour on top would make it
            // unreadable (e.g. red text on a red box). Give those links a
            // contrasting colour instead, layered over the forced rule.
            $navInd = (string) ($eff('navIndicator') ?? '');
            $navIndC = $color($eff('navIndicatorColor'));
            $activeText = ($navInd === 'box' && $navIndC !== null) ? $this->contrastColor($navIndC) : null;
            // Drawer presentation extras.
            $dAlign = \in_array($eff('navDrawerAlign'), ['left', 'center', 'right'], true) ? (string) $eff('navDrawerAlign') : '';
            $dGap = (is_numeric($eff('navDrawerGap')) && (int) $eff('navDrawerGap') >= 0 && (int) $eff('navDrawerGap') <= 60) ? (int) $eff('navDrawerGap') : null;
            $dDiv = $color($eff('navDrawerDivider'));
            $dOverlay = $color($eff('navOverlayColor'));
            // Drawer rule bodies, prefixable so we can emit them both as a real
            // @media query (frontend + live resize) AND keyed to the editor's
            // responsive-preview state (.draggo-canvas[data-vw=…]) — the canvas
            // is only width-shrunk there, so @media never fires in the preview.
            $drawer = static function (string $p) use ($sel, $tgDecl, $side, $w, $dbg, $hidden, $drawerLink, $activeText, $dAlign, $dGap, $dDiv, $dOverlay): string {
                return $p . $sel . ' .draggo-nav-toggle{' . $tgDecl . '}'
                    // justify-content:flex-start overrides any desktop navAlign
                    // (e.g. flex-end) — in a column flex that would otherwise push
                    // the items to the BOTTOM of the drawer instead of the top.
                    . $p . $sel . '>.draggo-nav-list{position:fixed;top:0;' . $side . ':0;height:100vh;width:' . $w . 'px;max-width:85vw;background:' . $dbg . ';z-index:1000;display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:0;overflow-y:auto;padding:3.5rem 1rem 1rem;transform:translateX(' . $hidden . ');transition:transform .3s ease;box-shadow:0 0 40px rgba(0,0,0,.25);}'
                    . $p . $sel . '.is-open>.draggo-nav-list{transform:translateX(0);}'
                    . $p . $sel . ' .draggo-nav-item{position:static;display:block;}'
                    // Drawer items always stacked + full-width regardless of the
                    // desktop layout (horizontal flex / absolute dropdowns).
                    . $p . $sel . '>.draggo-nav-list>.draggo-nav-item{width:100%;}'
                    . $p . $sel . ' .draggo-nav-list .draggo-nav-list{position:static;display:flex;flex-direction:column;opacity:1;visibility:visible;box-shadow:none;background:transparent;min-width:0;left:auto;top:auto;padding-left:1rem;}'
                    // Force readable link colour over theme header rules (#header a{…}).
                    . $p . $sel . '>.draggo-nav-list a,' . $p . $sel . '>.draggo-nav-list .draggo-nav-item>a{color:' . $drawerLink . ' !important;display:block;}'
                    // Injected close (×) button inherits the drawer link colour.
                    . $p . $sel . ' .draggo-nav-close{color:' . $drawerLink . ' !important;}'
                    // Active/trail link sits on the box-indicator background → use a
                    // contrasting colour so it stays readable over the forced rule.
                    . ($activeText !== null
                        ? $p . $sel . '>.draggo-nav-list .active>a,' . $p . $sel . '>.draggo-nav-list .trail>a,' . $p . $sel . '>.draggo-nav-list a:hover{color:' . $activeText . ' !important;}'
                        : '')
                    // Text alignment of drawer entries.
                    . ($dAlign !== '' ? $p . $sel . '>.draggo-nav-list a{text-align:' . $dAlign . ';}' : '')
                    // Gap between drawer entries.
                    . ($dGap !== null ? $p . $sel . '>.draggo-nav-list{gap:' . $dGap . 'px;}' : '')
                    // Divider lines between drawer entries.
                    . ($dDiv !== null ? $p . $sel . '>.draggo-nav-list>.draggo-nav-item+.draggo-nav-item{border-top:1px solid ' . $dDiv . ';}' : '')
                    // Dim backdrop behind the open drawer (injected .draggo-nav-backdrop).
                    . ($dOverlay !== null ? $p . $sel . ' .draggo-nav-backdrop{background:' . $dOverlay . ';}' : '');
            };
            $css .= '@media (max-width:' . $bp . 'px){' . $drawer('') . '}';
            // The closed panel sits off-screen right (translateX(100%)) which adds
            // horizontal scroll width — clip it below the breakpoint so the header
            // (and hamburger) can't overflow the viewport.
            $css .= '@media (max-width:' . $bp . 'px){html{overflow-x:hidden;}}';
            // Editor preview: force the drawer for the mobile view (and tablet
            // when the breakpoint is at/above the tablet width).
            $css .= $drawer('.draggo-canvas[data-vw="mobile"] ');
            if ($bp >= 768) {
                $css .= $drawer('.draggo-canvas[data-vw="tablet"] ');
            }
            // Hamburger icon size also styles the inline svg/<i> inside the toggle.
            $css .= $sel . ' .draggo-nav-toggle .draggo-svg,' . $sel . ' .draggo-nav-toggle .draggo-fa{width:1em;height:1em;font-size:inherit;}';
        }

        // Indicator (underline / background box / dot).
        $ind = (string) ($l['navIndicator'] ?? '');
        $ic = $color($l['navIndicatorColor'] ?? null) ?? 'currentColor';
        $isz = (int) ($l['navIndicatorSize'] ?? 0);
        $isz = ($isz > 0 && $isz <= 12) ? $isz : 2;
        if ($ind === 'underline') {
            $css .= $a . '{border-bottom:' . $isz . 'px solid transparent;}';
            $css .= $a . ':hover,' . $sel . ' .active>a,' . $sel . ' .trail>a{border-bottom-color:' . $ic . ';}';
        } elseif ($ind === 'box') {
            $css .= $a . '{border-radius:4px;}';
            $css .= $a . ':hover,' . $sel . ' .active>a,' . $sel . ' .trail>a{background:' . $ic . ';}';
        } elseif ($ind === 'dot') {
            $css .= $a . '{position:relative;}';
            $css .= $a . ':hover::after,' . $sel . ' .active>a::after{content:"";position:absolute;left:50%;bottom:2px;width:' . ($isz + 2) . 'px;height:' . ($isz + 2) . 'px;border-radius:50%;background:' . $ic . ';transform:translateX(-50%);}';
        }

        // ── Submenu (level 2: nested list) — styled independently ──────
        $sub = $sel . ' .draggo-nav-list .draggo-nav-list';      // dropdown panel
        $subA = $sub . ' a';                                       // its links
        if ($c = $color($l['subBg'] ?? null)) {
            $css .= $sub . '{background:' . $c . ';}';
        }
        $subDecl = '';
        if ($c = $color($l['subColor'] ?? null)) {
            $subDecl .= 'color:' . $c . ';';
        }
        $sff = (string) ($l['subFontFamily'] ?? '');
        if ($sff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $sff)) {
            $subDecl .= 'font-family:' . $sff . ';';
        }
        $ssz = (int) ($l['subSize'] ?? 0);
        if ($ssz > 0 && $ssz <= 60) {
            $subDecl .= 'font-size:' . $ssz . 'px;';
        }
        $sw = (string) ($l['subWeight'] ?? '');
        if (\in_array($sw, ['300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $subDecl .= 'font-weight:' . $sw . ';';
        }
        $stt = (string) ($l['subTransform'] ?? '');
        if (\in_array($stt, ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $subDecl .= 'text-transform:' . $stt . ';';
        }
        $sal = (string) ($l['subAlign'] ?? '');
        if (\in_array($sal, ['left', 'center', 'right'], true)) {
            $subDecl .= 'text-align:' . $sal . ';';
        }
        $spV = isset($l['subPadV']) ? (int) $l['subPadV'] : null;
        $spH = isset($l['subPadH']) ? (int) $l['subPadH'] : null;
        if ($spV !== null || $spH !== null) {
            $subDecl .= 'padding:' . ($spV ?? 8) . 'px ' . ($spH ?? 12) . 'px;';
        }
        if ($subDecl !== '') {
            $css .= $subA . '{' . $subDecl . '}';
        }
        if ($c = $color($l['subHover'] ?? null)) {
            $css .= $subA . ':hover{color:' . $c . ';}';
        }
        if ($c = $color($l['subHoverBg'] ?? null)) {
            $css .= $subA . ':hover{background-color:' . $c . ';}';
        }
        // Dropdown panel extras: border, radius, shadow, min-width, dividers.
        $subPanel = '';
        if ($c = $color($l['subBorderColor'] ?? null)) {
            $subPanel .= 'border:1px solid ' . $c . ';';
        }
        if (isset($l['subRadius']) && is_numeric($l['subRadius']) && (int) $l['subRadius'] >= 0 && (int) $l['subRadius'] <= 80) {
            $subPanel .= 'border-radius:' . (int) $l['subRadius'] . 'px;overflow:hidden;';
        }
        $subShadow = (string) ($l['subShadow'] ?? '');
        $shadowMap = ['sm' => '0 4px 12px rgba(0,0,0,.12)', 'md' => '0 10px 30px rgba(0,0,0,.15)', 'lg' => '0 20px 50px rgba(0,0,0,.22)'];
        if (isset($shadowMap[$subShadow])) {
            $subPanel .= 'box-shadow:' . $shadowMap[$subShadow] . ';';
        }
        if (isset($l['subMinWidth']) && is_numeric($l['subMinWidth']) && (int) $l['subMinWidth'] >= 40 && (int) $l['subMinWidth'] <= 800) {
            $subPanel .= 'min-width:' . (int) $l['subMinWidth'] . 'px;';
        }
        if ($subPanel !== '') {
            $css .= $sel . ' .draggo-nav-item .draggo-nav-list{' . $subPanel . '}';
        }
        if ($c = $color($l['subDivider'] ?? null)) {
            $css .= $sel . ' .draggo-nav-item .draggo-nav-list>.draggo-nav-item+.draggo-nav-item{border-top:1px solid ' . $c . ';}';
        }

        // ── Per-widget element styling (Phase 3.5) ─────────────────────
        // $sel is placed on each element's ROOT node (ScopedElementTrait), so
        // sub-element selectors are `$sel .draggo-*`. Keys are unique per type,
        // so all blocks can run unconditionally — only set values emit CSS.
        $num = static fn (mixed $v, int $max): ?int => (isset($v) && is_numeric($v) && (int) $v >= 0 && (int) $v <= $max) ? (int) $v : null;

        // Button (root = .draggo-btn). The base .draggo-btn rule hard-codes
        // background:#0a66ff;color:#fff at (0,1,0) — the SAME specificity as the
        // per-element $sel rule compile() emits for bg/color, so by load order
        // the component default wins and the user's colour silently does nothing.
        // Re-emit bg/color on the compound $sel.draggo-btn selector (0,2,0) so the
        // chosen colours actually beat the default. Mirrors the hover handling.
        if ($c = $color($l['bg'] ?? null)) {
            $css .= $sel . '.draggo-btn{background-color:' . $c . ';background-image:none;}';
        }
        if ($c = $color($l['color'] ?? null)) {
            $css .= $sel . '.draggo-btn{color:' . $c . ';}';
        }
        if ($c = $color($l['btnHoverBg'] ?? null)) {
            $css .= $sel . '.draggo-btn:hover{background-color:' . $c . ';}';
        }
        if ($c = $color($l['btnHoverColor'] ?? null)) {
            $css .= $sel . '.draggo-btn:hover{color:' . $c . ';}';
        }
        // Button extras: radius, padding, full width, icon gap.
        $btnDecl = '';
        if (($n = $num($l['btnRadius'] ?? null, 400)) !== null) {
            $btnDecl .= 'border-radius:' . $n . 'px;';
        }
        $pv = $num($l['btnPadV'] ?? null, 100);
        $ph = $num($l['btnPadH'] ?? null, 200);
        if ($pv !== null || $ph !== null) {
            $btnDecl .= 'padding:' . ($pv ?? 12) . 'px ' . ($ph ?? 24) . 'px;';
        }
        $btnGap = $num($l['btnIconGap'] ?? null, 60);
        $btnFull = !empty($l['btnFull']);
        if ($btnGap !== null || $btnFull) {
            // Non-full button uses flex (block-level) not inline-flex so the
            // fit-content + auto-margin centring (--bld-ml/--bld-mr) still works.
            $btnDecl .= 'display:flex;align-items:center;justify-content:center;';
            if ($btnGap !== null) {
                $btnDecl .= 'gap:' . $btnGap . 'px;';
            }
        }
        if ($btnFull) {
            $btnDecl .= 'width:100%;';
        }
        if ($btnDecl !== '') {
            $css .= $sel . '{' . $btnDecl . '}';
        }

        // Icon/Image box.
        if (($n = $num($l['boxGap'] ?? null, 200)) !== null) {
            $css .= $sel . '{gap:' . $n . 'px;}';
        }
        if (($n = $num($l['boxMediaW'] ?? null, 600)) !== null) {
            $css .= $sel . ' .draggo-box-media img{width:' . $n . 'px;height:auto;}';
            $css .= $sel . ' .draggo-box-icon{font-size:' . $n . 'px;}';
        }
        // Image-box picture: fixed crop height + object-fit (emitted after the
        // width rule so height:Npx overrides the height:auto above).
        $boxImg = '';
        if (($n = $num($l['boxImgHeight'] ?? null, 1600)) !== null && $n > 0) {
            $boxImg .= 'height:' . $n . 'px;';
        }
        if (\in_array($l['boxImgFit'] ?? '', ['contain', 'cover'], true)) {
            $boxImg .= 'object-fit:' . $l['boxImgFit'] . ';';
        }
        if ($boxImg !== '') {
            $css .= $sel . ' .draggo-box-media img{' . $boxImg . '}';
        }
        $boxTitle = '';
        if ($c = $color($l['boxTitleColor'] ?? null)) {
            $boxTitle .= 'color:' . $c . ';';
        }
        if (($n = $num($l['boxTitleSize'] ?? null, 120)) !== null && $n > 0) {
            $boxTitle .= 'font-size:' . $n . 'px;';
        }
        if ($boxTitle !== '') {
            $css .= $sel . ' .draggo-box-title{' . $boxTitle . '}';
        }
        if ($c = $color($l['boxTextColor'] ?? null)) {
            $css .= $sel . ' .draggo-box-text{color:' . $c . ';}';
        }
        if ($c = $color($l['boxIconColor'] ?? null)) {
            $css .= $sel . ' .draggo-box-icon{color:' . $c . ';}';
        }
        $boxIco = '';
        if ($c = $color($l['boxIconBg'] ?? null)) {
            $boxIco .= 'background:' . $c . ';border-radius:50%;display:inline-flex;align-items:center;justify-content:center;';
        }
        if (($n = $num($l['boxIconPad'] ?? null, 100)) !== null) {
            $boxIco .= 'padding:' . $n . 'px;';
        }
        if ($boxIco !== '') {
            $css .= $sel . ' .draggo-box-icon{' . $boxIco . '}';
        }
        if (($n = $num($l['boxMediaRadius'] ?? null, 400)) !== null) {
            $css .= $sel . ' .draggo-box-media img{border-radius:' . $n . 'px;}';
        }
        if (!empty($l['boxHover'])) {
            $css .= $sel . '{transition:transform .2s,box-shadow .2s;}';
            $css .= $sel . ':hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,0,0,.15);}';
        }

        // Accordion.
        $accHead = '';
        if ($c = $color($l['accHeadBg'] ?? null)) {
            $accHead .= 'background:' . $c . ';';
        }
        if ($c = $color($l['accHeadColor'] ?? null)) {
            $accHead .= 'color:' . $c . ';';
        }
        if ($accHead !== '') {
            $css .= $sel . ' .draggo-acc-head{' . $accHead . '}';
        }
        $accAct = '';
        if ($c = $color($l['accActiveBg'] ?? null)) {
            $accAct .= 'background:' . $c . ';';
        }
        if ($c = $color($l['accActiveColor'] ?? null)) {
            $accAct .= 'color:' . $c . ';';
        }
        if ($accAct !== '') {
            $css .= $sel . ' .draggo-acc-item.is-open .draggo-acc-head{' . $accAct . '}';
        }
        $accItem = '';
        if ($c = $color($l['accBorderColor'] ?? null)) {
            $accItem .= 'border-color:' . $c . ';';
        }
        if (($n = $num($l['accRadius'] ?? null, 80)) !== null) {
            $accItem .= 'border-radius:' . $n . 'px;';
        }
        if (($n = $num($l['accGap'] ?? null, 60)) !== null) {
            $accItem .= 'margin-bottom:' . $n . 'px;';
        }
        if ($accItem !== '') {
            $css .= $sel . ' .draggo-acc-item{' . $accItem . '}';
        }
        if ($c = $color($l['accContentBg'] ?? null)) {
            $css .= $sel . ' .draggo-acc-inner{background:' . $c . ';}';
        }

        // Tabs.
        if ($c = $color($l['tabColor'] ?? null)) {
            $css .= $sel . ' .draggo-tabw-btn{color:' . $c . ';}';
        }
        if ($c = $color($l['tabActiveColor'] ?? null)) {
            $css .= $sel . ' .draggo-tabw-btn.is-active{color:' . $c . ';border-bottom-color:' . $c . ';}';
        }
        if ($c = $color($l['tabNavBorder'] ?? null)) {
            $css .= $sel . ' .draggo-tabw-nav{border-bottom-color:' . $c . ';}';
        }
        if ($c = $color($l['tabContentBg'] ?? null)) {
            $css .= $sel . ' .draggo-tabw-panel{background:' . $c . ';}';
        }
        $ta = (string) ($l['tabAlign'] ?? '');
        if (\in_array($ta, ['flex-start', 'center', 'flex-end', 'space-between'], true)) {
            $css .= $sel . ' .draggo-tabw-nav{justify-content:' . $ta . ';}';
        }
        if (($l['tabLayout'] ?? '') === 'vertical') {
            $css .= $sel . '{display:flex;align-items:flex-start;gap:1.5rem;}';
            $css .= $sel . ' .draggo-tabw-nav{flex-direction:column;border-bottom:0;flex:0 0 auto;}';
            $css .= $sel . ' .draggo-tabw-panels{flex:1;min-width:0;}';
        }

        // Carousel.
        $carArrow = '';
        if ($c = $color($l['carArrowColor'] ?? null)) {
            $carArrow .= 'color:' . $c . ';';
        }
        if ($c = $color($l['carArrowBg'] ?? null)) {
            $carArrow .= 'background:' . $c . ';';
        }
        if ($carArrow !== '') {
            $css .= $sel . ' .draggo-car-prev,' . $sel . ' .draggo-car-next{' . $carArrow . '}';
        }
        if ($c = $color($l['carDotColor'] ?? null)) {
            $css .= $sel . ' .draggo-car-dots button{background:' . $c . ';}';
        }
        if ($c = $color($l['carDotActive'] ?? null)) {
            $css .= $sel . ' .draggo-car-dots button.is-on{background:' . $c . ';}';
        }
        if (($n = $num($l['carRadius'] ?? null, 80)) !== null) {
            $css .= $sel . ' .draggo-car-slide img{border-radius:' . $n . 'px;}';
        }
        if (($n = $num($l['carGap'] ?? null, 60)) !== null) {
            $css .= $sel . ' .draggo-car-slide{padding:0 ' . $n . 'px;}';
        }
        if (($n = $num($l['carImgHeight'] ?? null, 1200)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-car-slide img{height:' . $n . 'px;width:100%;object-fit:cover;}';
        }

        // Gallery.
        if (($n = $num($l['gaImgRadius'] ?? null, 200)) !== null) {
            $css .= $sel . ' .draggo-ga-img{border-radius:' . $n . 'px;}';
        }
        if (($n = $num($l['gaImgHeight'] ?? null, 1200)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-ga-img{height:' . $n . 'px;width:100%;object-fit:cover;}';
        }
        if ($c = $color($l['gaOverlayColor'] ?? null)) {
            $css .= $sel . ' .draggo-ga-ov{background:' . $c . ';}';
        }

        // Loop grid cards.
        $card = '';
        if ($c = $color($l['cardBg'] ?? null)) {
            $card .= 'background:' . $c . ';';
        }
        if ($c = $color($l['cardBorderColor'] ?? null)) {
            $card .= 'border-color:' . $c . ';';
        }
        if (($n = $num($l['cardRadius'] ?? null, 80)) !== null) {
            $card .= 'border-radius:' . $n . 'px;';
        }
        if (($n = $num($l['cardPad'] ?? null, 120)) !== null) {
            $card .= 'padding:' . $n . 'px;';
        }
        $cardShadows = ['sm' => '0 1px 3px rgba(0,0,0,.12)', 'md' => '0 4px 12px rgba(0,0,0,.15)', 'lg' => '0 10px 30px rgba(0,0,0,.2)', 'xl' => '0 20px 50px rgba(0,0,0,.25)'];
        if (isset($cardShadows[(string) ($l['cardShadow'] ?? '')])) {
            $card .= 'box-shadow:' . $cardShadows[$l['cardShadow']] . ';';
        }
        if ($card !== '') {
            $css .= $sel . ' .draggo-loop-card{' . $card . '}';
        }
        if (($n = $num($l['loopGap'] ?? null, 120)) !== null) {
            $css .= $sel . '{gap:' . $n . 'px;}';
        }
        if ($c = $color($l['loopTitleColor'] ?? null)) {
            $css .= $sel . ' .draggo-loop-title{color:' . $c . ';}';
        }
        if (($n = $num($l['loopTitleSize'] ?? null, 120)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-loop-title{font-size:' . $n . 'px;}';
        }
        if ($c = $color($l['loopTeaserColor'] ?? null)) {
            $css .= $sel . ' .draggo-loop-teaser{color:' . $c . ';opacity:1;}';
        }
        if (($n = $num($l['loopImgHeight'] ?? null, 1200)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-loop-img img{height:' . $n . 'px;object-fit:cover;}';
        }
        if ($c = $color($l['moreColor'] ?? null)) {
            $css .= $sel . ' .draggo-loop-more{color:' . $c . ';}';
        }
        if (!empty($l['loopHover'])) {
            $css .= $sel . ' .draggo-loop-card:hover{box-shadow:0 12px 30px rgba(0,0,0,.18);transform:translateY(-4px);}';
        }

        // Progress bar.
        if ($c = $color($l['progressBarColor'] ?? null)) {
            $css .= $sel . ' .draggo-progress-bar{background:' . $c . ';}';
        }
        $track = '';
        if ($c = $color($l['progressTrackColor'] ?? null)) {
            $track .= 'background:' . $c . ';';
        }
        if (($n = $num($l['progressHeight'] ?? null, 60)) !== null && $n >= 2) {
            $track .= 'height:' . $n . 'px;';
        }
        if ($track !== '') {
            $css .= $sel . ' .draggo-progress-track{' . $track . '}';
        }

        // Social icons.
        $soc = '';
        if ($c = $color($l['socialColor'] ?? null)) {
            $soc .= 'color:' . $c . ';';
        }
        if ($c = $color($l['socialBg'] ?? null)) {
            $soc .= 'background:' . $c . ';';
        }
        if (($n = $num($l['socialSize'] ?? null, 96)) !== null && $n >= 16) {
            $soc .= 'font-size:' . $n . 'px;';
        }
        if (($n = $num($l['socialRadius'] ?? null, 400)) !== null) {
            $soc .= 'border-radius:' . $n . 'px;';
        }
        if ($soc !== '') {
            $css .= $sel . ' .draggo-social-link{' . $soc . '}';
        }
        if ($c = $color($l['socialHoverBg'] ?? null)) {
            $css .= $sel . ' .draggo-social-link:hover{background:' . $c . ';}';
        }

        // Flip box faces.
        $ff = '';
        if ($c = $color($l['flipFrontBg'] ?? null)) {
            $ff .= 'background:' . $c . ';';
        }
        if ($c = $color($l['flipFrontColor'] ?? null)) {
            $ff .= 'color:' . $c . ';';
        }
        if ($ff !== '') {
            $css .= $sel . ' .draggo-flip-front{' . $ff . '}';
        }
        $fb = '';
        if ($c = $color($l['flipBackBg'] ?? null)) {
            $fb .= 'background:' . $c . ';';
        }
        if ($c = $color($l['flipBackColor'] ?? null)) {
            $fb .= 'color:' . $c . ';';
        }
        if ($fb !== '') {
            $css .= $sel . ' .draggo-flip-back{' . $fb . '}';
        }

        // CTA button.
        $ctaBtn = '';
        if ($c = $color($l['ctaBtnBg'] ?? null)) {
            $ctaBtn .= 'background-color:' . $c . ';';
        }
        if ($c = $color($l['ctaBtnColor'] ?? null)) {
            $ctaBtn .= 'color:' . $c . ';';
        }
        if (($n = $num($l['ctaBtnRadius'] ?? null, 400)) !== null) {
            $ctaBtn .= 'border-radius:' . $n . 'px;';
        }
        if ($ctaBtn !== '') {
            $css .= $sel . ' .draggo-cta-btn{' . $ctaBtn . '}';
        }
        $ctaBtnH = '';
        if ($c = $color($l['ctaBtnHoverBg'] ?? null)) {
            $ctaBtnH .= 'background-color:' . $c . ';';
        }
        if ($c = $color($l['ctaBtnHoverColor'] ?? null)) {
            $ctaBtnH .= 'color:' . $c . ';';
        }
        if ($ctaBtnH !== '') {
            $css .= $sel . ' .draggo-cta-btn:hover{' . $ctaBtnH . '}';
        }
        if (($l['ctaLayout'] ?? '') === 'horizontal') {
            $css .= $sel . '{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;}';
            $css .= $sel . ' .draggo-cta-btn{margin-left:auto;flex-shrink:0;}';
        }

        // ── Tier 2-5 element sub-styling (Phase 3.6) ───────────────────
        // Share buttons.
        $sh = '';
        if ($c = $color($l['shareColor'] ?? null)) {
            $sh .= 'color:' . $c . ';';
        }
        if ($c = $color($l['shareBg'] ?? null)) {
            $sh .= 'background:' . $c . ';';
        }
        if (($n = $num($l['shareSize'] ?? null, 96)) !== null && $n >= 16) {
            $sh .= 'font-size:' . $n . 'px;';
        }
        if (($n = $num($l['shareRadius'] ?? null, 400)) !== null) {
            $sh .= 'border-radius:' . $n . 'px;';
        }
        if ($sh !== '') {
            $css .= $sel . ' .draggo-share-link{' . $sh . '}';
        }
        if ($c = $color($l['shareHoverBg'] ?? null)) {
            $css .= $sel . ' .draggo-share-link:hover{background:' . $c . ';}';
        }

        // Countdown.
        if ($c = $color($l['cdNumColor'] ?? null)) {
            $css .= $sel . ' .draggo-cd-num{color:' . $c . ';}';
        }
        if (($n = $num($l['cdNumSize'] ?? null, 200)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-cd-num{font-size:' . $n . 'px;}';
        }
        if ($c = $color($l['cdLblColor'] ?? null)) {
            $css .= $sel . ' .draggo-cd-lbl{color:' . $c . ';}';
        }
        if (($n = $num($l['cdGap'] ?? null, 100)) !== null) {
            $css .= $sel . '{gap:' . $n . 'px;}';
        }

        // Open-Now badge (root = .draggo-opennow). Dot inherits the state colour.
        $onBadge = '';
        if ($c = $color($l['onBg'] ?? null)) {
            $onBadge .= 'background:' . $c . ';';
        }
        if (($n = $num($l['onSize'] ?? null, 60)) !== null && $n > 0) {
            $onBadge .= 'font-size:' . $n . 'px;';
        }
        if (($n = $num($l['onRadius'] ?? null, 400)) !== null) {
            $onBadge .= 'border-radius:' . $n . 'px;';
        }
        $opv = $num($l['onPadV'] ?? null, 60);
        $oph = $num($l['onPadH'] ?? null, 80);
        if ($opv !== null || $oph !== null) {
            $onBadge .= 'padding:' . ($opv ?? 6) . 'px ' . ($oph ?? 12) . 'px;';
        }
        if ($onBadge !== '') {
            $css .= $sel . '{' . $onBadge . '}';
        }
        if (($n = $num($l['onDotSize'] ?? null, 32)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-on-dot{width:' . $n . 'px;height:' . $n . 'px;}';
        }
        if ($c = $color($l['onOpenColor'] ?? null)) {
            $css .= $sel . '.is-open{color:' . $c . ';}' . $sel . '.is-open .draggo-on-dot{background:' . $c . ';}';
        }
        if ($c = $color($l['onClosedColor'] ?? null)) {
            $css .= $sel . '.is-closed{color:' . $c . ';}' . $sel . '.is-closed .draggo-on-dot{background:' . $c . ';}';
        }

        // Icon list.
        if ($c = $color($l['ilIconColor'] ?? null)) {
            $css .= $sel . ' .draggo-ilist-ico{color:' . $c . ';}';
        }
        if (($n = $num($l['ilIconSize'] ?? null, 80)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-ilist-ico{font-size:' . $n . 'px;}';
        }
        if (($n = $num($l['ilGap'] ?? null, 60)) !== null) {
            $css .= $sel . ' .draggo-ilist-item{margin-bottom:' . $n . 'px;}';
        }

        // Steps.
        $stn = '';
        if ($c = $color($l['stepNumBg'] ?? null)) {
            $stn .= 'background:' . $c . ';';
        }
        if ($c = $color($l['stepNumColor'] ?? null)) {
            $stn .= 'color:' . $c . ';';
        }
        if ($stn !== '') {
            $css .= $sel . ' .draggo-step-num{' . $stn . '}';
        }
        if ($c = $color($l['stepLineColor'] ?? null)) {
            $css .= $sel . ' .draggo-step:not(:last-child)::before{background:' . $c . ';}';
        }

        // Price table.
        if ($c = $color($l['prtTitleColor'] ?? null)) {
            $css .= $sel . ' .draggo-prt-title{color:' . $c . ';}';
        }
        if ($c = $color($l['prtPriceColor'] ?? null)) {
            $css .= $sel . ' .draggo-prt-amount{color:' . $c . ';}';
        }
        if ($c = $color($l['prtFeatureBorderColor'] ?? null)) {
            $css .= $sel . ' .draggo-prt-feature{border-bottom-color:' . $c . ';}';
        }
        $prtBtn = '';
        if ($c = $color($l['prtBtnBg'] ?? null)) {
            $prtBtn .= 'background-color:' . $c . ';';
        }
        if ($c = $color($l['prtBtnColor'] ?? null)) {
            $prtBtn .= 'color:' . $c . ';';
        }
        if (($n = $num($l['prtBtnRadius'] ?? null, 400)) !== null) {
            $prtBtn .= 'border-radius:' . $n . 'px;';
        }
        if ($prtBtn !== '') {
            $css .= $sel . ' .draggo-prt-btn{' . $prtBtn . '}';
        }
        if ($c = $color($l['prtBtnHoverBg'] ?? null)) {
            $css .= $sel . ' .draggo-prt-btn:hover{background-color:' . $c . ';}';
        }

        // Price list.
        if ($c = $color($l['prlNameColor'] ?? null)) {
            $css .= $sel . ' .draggo-prl-name{color:' . $c . ';}';
        }
        if ($c = $color($l['prlPriceColor'] ?? null)) {
            $css .= $sel . ' .draggo-prl-price{color:' . $c . ';}';
        }
        if ($c = $color($l['prlDotColor'] ?? null)) {
            $css .= $sel . ' .draggo-prl-dots{border-bottom-color:' . $c . ';}';
        }

        // Quote (root = .draggo-quote).
        $qd = '';
        if ($c = $color($l['quoteBarColor'] ?? null)) {
            $qd .= 'border-left-color:' . $c . ';';
        }
        if (($n = $num($l['quoteBarWidth'] ?? null, 20)) !== null) {
            $qd .= 'border-left-width:' . $n . 'px;';
        }
        if ($qd !== '') {
            $css .= $sel . '{' . $qd . '}';
        }
        if (($n = $num($l['quoteTextSize'] ?? null, 120)) !== null && $n > 0) {
            $css .= $sel . ' .draggo-quote-text{font-size:' . $n . 'px;}';
        }
        if (!empty($l['quoteItalic'])) {
            $css .= $sel . ' .draggo-quote-text{font-style:italic;}';
        }
        if ($c = $color($l['quoteAuthorColor'] ?? null)) {
            $css .= $sel . ' .draggo-quote-author{color:' . $c . ';}';
        }

        // Breadcrumb.
        if ($c = $color($l['bcActiveColor'] ?? null)) {
            $css .= $sel . ' .draggo-bc-active{color:' . $c . ';}';
        }
        if ($c = $color($l['bcSepColor'] ?? null)) {
            $css .= $sel . ' .draggo-bc-sep{color:' . $c . ';}';
        }

        // Table of contents (root = .draggo-toc).
        if ($c = $color($l['tocBarColor'] ?? null)) {
            $css .= $sel . '{border-left-color:' . $c . ';}';
        }
        if ($c = $color($l['tocTitleColor'] ?? null)) {
            $css .= $sel . ' .draggo-toc-title{color:' . $c . ';}';
        }

        // Animated headline word.
        if ($c = $color($l['animWordColor'] ?? null)) {
            $css .= $sel . ' .draggo-anim-words{color:' . $c . ';}';
        }
        // Hotspot dots.
        $hsd = '';
        if ($c = $color($l['hsDotColor'] ?? null)) {
            $hsd .= 'background:' . $c . ';';
        }
        if ($c = $color($l['hsDotText'] ?? null)) {
            $hsd .= 'color:' . $c . ';';
        }
        if ($hsd !== '') {
            $css .= $sel . ' .draggo-hs-dot{' . $hsd . '}';
        }
        // Code block. codeBg must recolour the WHOLE block (container + bar +
        // pre), not just the <pre>, or a light bg leaves the rounded container
        // edges and the language/copy bar dark (two-tone). The bar gets a subtle
        // translucent overlay on top so it stays distinguishable on any bg.
        if ($c = $color($l['codeBg'] ?? null)) {
            // $sel sits ON the .draggo-code container; recolour it + the <pre>,
            // and tint the bar so it stays distinguishable on any bg.
            $css .= $sel . ',' . $sel . ' .draggo-code-pre{background:' . $c . ';}';
            $css .= $sel . ' .draggo-code-bar{background:rgba(127,127,127,.12);}';
        }
        if ($c = $color($l['codeColor'] ?? null)) {
            $css .= $sel . ' .draggo-code-pre{color:' . $c . ';}';
        }

        // Scroll-Zoom: frame corner radius (the scaled image box).
        if (($n = $num($l['zmRadius'] ?? null, 80)) !== null) {
            $css .= $sel . ' .draggo-zm-frame{border-radius:' . $n . 'px;}';
        }

        // Logo / image sizing (scoped to the rendered <img>). Logo uses
        // .draggo-logo-img; core image element uses a plain <img>.
        foreach ([['logo', $sel . ' .draggo-logo-img'], ['img', $sel . ' img']] as [$p, $imgSel]) {
            $decl = '';
            if (($n = $num($l[$p . 'Width'] ?? null, 2000)) !== null && $n > 0) {
                $decl .= 'width:' . $n . 'px;';
            }
            if (($n = $num($l[$p . 'MaxWidth'] ?? null, 2000)) !== null && $n > 0) {
                $decl .= 'max-width:' . $n . 'px;';
            }
            if (($n = $num($l[$p . 'Height'] ?? null, 1600)) !== null && $n > 0) {
                $decl .= 'height:' . $n . 'px;';
            }
            if (\in_array($l[$p . 'Fit'] ?? '', ['contain', 'cover'], true)) {
                $decl .= 'object-fit:' . $l[$p . 'Fit'] . ';';
            }
            if (($n = $num($l[$p . 'Radius'] ?? null, 400)) !== null && $n > 0) {
                $decl .= 'border-radius:' . $n . 'px;';
            }
            if ($decl !== '') {
                // Base keeps it responsive; user declarations follow → they win.
                $css .= $imgSel . '{max-width:100%;height:auto;' . $decl . '}';
            }
        }

        // Map: grayscale filter (full colour on hover).
        if (!empty($l['mapGray'])) {
            $css .= $sel . ' .draggo-map-frame{filter:grayscale(1);transition:filter .3s;}';
            $css .= $sel . ':hover .draggo-map-frame{filter:none;}';
        }

        // Alert: corner radius + border-colour override (else the semantic
        // variant border stays even when the bg is customised).
        if (($n = $num($l['alertRadius'] ?? null, 200)) !== null) {
            $css .= $sel . '{border-radius:' . $n . 'px;}';
        }
        if ($c = $color($l['alertBorder'] ?? null)) {
            $css .= $sel . '{border-color:' . $c . ';}';
        }

        // Spacer: responsive heights per breakpoint.
        if (!$mediaSafe && ($n = $num($l['spacerTablet'] ?? null, 1000)) !== null) {
            $css .= '@media (max-width:991px){' . $sel . '{height:' . $n . 'px;min-height:' . $n . 'px;}}';
        }
        if (!$mediaSafe && ($n = $num($l['spacerMobile'] ?? null, 1000)) !== null) {
            $css .= '@media (max-width:767px){' . $sel . '{height:' . $n . 'px;min-height:' . $n . 'px;}}';
        }

        // ── Per-part typography (multi-text elements) ──────────────────
        // Elements with more than one text give each part (title vs text,
        // before/words/after, front/back) an independent typography set. Keys
        // are unique per type so all calls run unconditionally — only set
        // values emit CSS. Runs inside scoped(), so each part is responsive for
        // free (CssGenerator wraps scoped(merged,true) per breakpoint @media).
        $css .= $this->partType($sel, '.draggo-box-title', 'boxTitle', $l);
        $css .= $this->partType($sel, '.draggo-box-text', 'boxText', $l);
        $css .= $this->partType($sel, '.draggo-alert-title', 'alertTitle', $l);
        $css .= $this->partType($sel, '.draggo-alert-text', 'alertText', $l);
        $css .= $this->partType($sel, '.draggo-quote-text', 'quoteText', $l);
        $css .= $this->partType($sel, '.draggo-quote-author', 'quoteAuthor', $l);
        $css .= $this->partType($sel, '.draggo-cta-title', 'ctaTitle', $l);
        $css .= $this->partType($sel, '.draggo-cta-text', 'ctaText', $l);
        $css .= $this->partType($sel, '.draggo-flip-front .draggo-flip-title', 'flipFTitle', $l);
        $css .= $this->partType($sel, '.draggo-flip-front .draggo-flip-text', 'flipFText', $l);
        $css .= $this->partType($sel, '.draggo-flip-back .draggo-flip-title', 'flipBTitle', $l);
        $css .= $this->partType($sel, '.draggo-flip-back .draggo-flip-text', 'flipBText', $l);
        $css .= $this->partType($sel, '.draggo-anim-before', 'anBefore', $l);
        $css .= $this->partType($sel, '.draggo-anim-words', 'anWord', $l, 'animWordColor');
        $css .= $this->partType($sel, '.draggo-anim-after', 'anAfter', $l);
        $css .= $this->partType($sel, '.draggo-acc-head', 'accHd', $l);
        $css .= $this->partType($sel, '.draggo-acc-inner', 'accBd', $l);
        $css .= $this->partType($sel, '.draggo-tabw-btn', 'tabLbl', $l);
        $css .= $this->partType($sel, '.draggo-tabw-panel', 'tabPanel', $l);
        $css .= $this->partType($sel, '.draggo-step-title', 'stepTtl', $l);
        $css .= $this->partType($sel, '.draggo-step-text', 'stepTxt', $l);
        $css .= $this->partType($sel, '.draggo-ss-title', 'ssTitle', $l);
        $css .= $this->partType($sel, '.draggo-ss-text', 'ssText', $l);
        $css .= $this->partType($sel, '.draggo-tm-text', 'tmText', $l);
        $css .= $this->partType($sel, '.draggo-ilist-txt', 'ilTxt', $l);
        $css .= $this->partType($sel, '.draggo-progress-label', 'prgLbl', $l);
        // Draggo Landing per-part styling (shared section classes). Only emits
        // when lp* values are set — which the schema exposes for draggo_lp_* only.
        $css .= $this->partType($sel, '.eyebrow', 'lpEye', $l);
        $css .= $this->partType($sel, ':is(h1,h2,h3)', 'lpHead', $l);
        $css .= $this->partType($sel, '.grad', 'lpGrad', $l);
        $css .= $this->partType($sel, '.lead', 'lpLead', $l);
        // Native Contao "text" element: title = the direct-child headline (h1–h6,
        // not headings inside the .rte body); body text = the .rte wrapper.
        $css .= $this->partType($sel, '> :is(h1,h2,h3,h4,h5,h6)', 'txTtl', $l);
        $css .= $this->partType($sel, '.rte', 'txTxt', $l);

        // ── Scrollytelling element CSS variables (consumed by draggo-scroll.css) ──
        // Keys are unique per scroll element; set the custom properties on the
        // element scope so the shared stylesheet picks them up.
        $scrollVars = '';
        if ($c = $color($l['ssBg'] ?? null)) {
            $scrollVars .= '--ss-bg:' . $c . ';';
        }
        if ($c = $color($l['ssFg'] ?? null)) {
            $scrollVars .= '--ss-fg:' . $c . ';';
        }
        if (($n = $num($l['ssRadius'] ?? null, 80)) !== null) {
            $scrollVars .= '--ss-radius:' . $n . 'px;';
        }
        if ($c = $color($l['hpBg'] ?? null)) {
            $scrollVars .= '--hp-bg:' . $c . ';';
        }
        if ($c = $color($l['hpFg'] ?? null)) {
            $scrollVars .= '--hp-fg:' . $c . ';';
        }
        if ($c = $color($l['thDim'] ?? null)) {
            $scrollVars .= '--th-dim:' . $c . ';';
        }
        if ($c = $color($l['thLit'] ?? null)) {
            $scrollVars .= '--th-lit:' . $c . ';';
        }
        if ($c = $color($l['tlAccent'] ?? null)) {
            $scrollVars .= '--tl-accent:' . $c . ';';
        }
        if ($c = $color($l['tlTrack'] ?? null)) {
            $scrollVars .= '--tl-track:' . $c . ';';
        }
        if ($c = $color($l['rgBg'] ?? null)) {
            $scrollVars .= '--rg-bg:' . $c . ';';
        }
        if ($c = $color($l['rgFg'] ?? null)) {
            $scrollVars .= '--rg-fg:' . $c . ';';
        }
        if ($c = $color($l['sdColor'] ?? null)) {
            $scrollVars .= '--sd-color:' . $c . ';';
        }
        if (($n = $num($l['sdWidth'] ?? null, 40)) !== null && $n > 0) {
            $scrollVars .= '--sd-w:' . $n . ';';
        }
        if ($c = $color($l['prColor'] ?? null)) {
            $scrollVars .= '--pr-color:' . $c . ';';
        }
        if ($c = $color($l['prTrack'] ?? null)) {
            $scrollVars .= '--pr-track:' . $c . ';';
        }
        if ($c = $color($l['ctBg'] ?? null)) {
            $scrollVars .= '--ct-bg:' . $c . ';';
        }
        if ($c = $color($l['ctFg'] ?? null)) {
            $scrollVars .= '--ct-fg:' . $c . ';';
        }
        if ($c = $color($l['tiBg'] ?? null)) {
            $scrollVars .= '--tilt-bg:' . $c . ';';
        }
        if ($c = $color($l['tiFg'] ?? null)) {
            $scrollVars .= '--tilt-fg:' . $c . ';';
        }
        if (isset($l['tiOverlay']) && \in_array((string) $l['tiOverlay'], ['0', '0.25', '0.45', '0.65', '0.85'], true)) {
            $scrollVars .= '--ti-ov:' . (string) $l['tiOverlay'] . ';';
        }
        if (\in_array($l['tiPos'] ?? '', ['top', 'bottom', 'left', 'right'], true)) {
            $scrollVars .= '--ti-pos:' . $l['tiPos'] . ';';
        }
        if ($scrollVars !== '') {
            $css .= $sel . '{' . $scrollVars . '}';
        }
        // Parallax content colour targets the inner content node.
        if ($c = $color($l['pxFg'] ?? null)) {
            $css .= $sel . ' .draggo-px-content{color:' . $c . ';}';
        }

        // Background overlay (color/gradient/image) above the element's bg.
        $css .= $this->overlayBefore($sel, $l);

        return $css;
    }

    /**
     * Full typography declaration for one text part of a multi-text element,
     * scoped to "$sel $partSel". Reads "{$prefix}Color|Bg|FontFamily|Size|
     * Weight|Style|Transform|Decoration|LineHeight|Spacing|Align|Mb". Returns ''
     * when nothing is set. $legacyColor optionally falls back to an older color
     * key (e.g. animWordColor) so existing data keeps working.
     *
     * @param array<string,mixed> $l
     */
    private function partType(string $sel, string $partSel, string $prefix, array $l, ?string $legacyColor = null): string
    {
        $decl = '';
        $c = $this->color($l[$prefix . 'Color'] ?? null);
        if ($c === null && $legacyColor !== null) {
            $c = $this->color($l[$legacyColor] ?? null);
        }
        if ($c !== null) {
            $decl .= 'color:' . $c . ';';
        }
        if ($bg = $this->color($l[$prefix . 'Bg'] ?? null)) {
            $decl .= 'background-color:' . $bg . ';';
        }
        $ff = (string) ($l[$prefix . 'FontFamily'] ?? '');
        if ($ff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $ff)) {
            $decl .= 'font-family:' . $ff . ';';
        }
        $fsz = (int) ($l[$prefix . 'Size'] ?? 0);
        if ($fsz > 0 && $fsz <= 400) {
            $decl .= 'font-size:' . $fsz . 'px;';
        }
        $fw = (string) ($l[$prefix . 'Weight'] ?? '');
        if (\in_array($fw, ['100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $decl .= 'font-weight:' . $fw . ';';
        }
        $fst = (string) ($l[$prefix . 'Style'] ?? '');
        if (\in_array($fst, ['normal', 'italic'], true)) {
            $decl .= 'font-style:' . $fst . ';';
        }
        $tt = (string) ($l[$prefix . 'Transform'] ?? '');
        if (\in_array($tt, ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $decl .= 'text-transform:' . $tt . ';';
        }
        $tdv = (string) ($l[$prefix . 'Decoration'] ?? '');
        if (\in_array($tdv, ['none', 'underline', 'line-through'], true)) {
            $decl .= 'text-decoration:' . $tdv . ';';
        }
        $lh = $l[$prefix . 'LineHeight'] ?? '';
        if (is_numeric($lh) && (float) $lh > 0 && (float) $lh <= 5) {
            $decl .= 'line-height:' . (float) $lh . ';';
        }
        if (isset($l[$prefix . 'Spacing']) && is_numeric($l[$prefix . 'Spacing']) && abs((float) $l[$prefix . 'Spacing']) <= 100) {
            $decl .= 'letter-spacing:' . (float) $l[$prefix . 'Spacing'] . 'px;';
        }
        $al = (string) ($l[$prefix . 'Align'] ?? '');
        if (\in_array($al, ['left', 'center', 'right', 'justify'], true)) {
            $decl .= 'text-align:' . $al . ';';
        }
        if (isset($l[$prefix . 'Mb']) && is_numeric($l[$prefix . 'Mb']) && (int) $l[$prefix . 'Mb'] >= 0 && (int) $l[$prefix . 'Mb'] <= 300) {
            $decl .= 'margin-bottom:' . (int) $l[$prefix . 'Mb'] . 'px;';
        }

        return $decl !== '' ? $sel . ' ' . $partSel . '{' . $decl . '}' : '';
    }

    /**
     * Accept only a relative files/ path or an absolute http(s) URL. No quotes,
     * parentheses or whitespace → can't break out of the url('...') context.
     */
    private function imageUrl(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || preg_match('/[\'"()\s]/', $value)) {
            return null;
        }

        // Root-absolute so the CSS background resolves identically on the
        // frontend AND in the editor canvas (served under /contao/…). An <img>
        // already uses "/" + path; mirror that here.
        if (preg_match('#^files/[^?]+$#i', $value) === 1) {
            return '/' . $value;
        }

        return preg_match('#^https?://[^\s\'"()]+$#i', $value) === 1 ? $value : null;
    }

    /**
     * Flexbox child-alignment style for a container (rows are the children).
     * alignX → align-items (cross axis), alignY → justify-content (main axis).
     *
     * @param array<string,mixed> $layout
     */
    public function flex(array $layout): string
    {
        $map = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'];
        $x = $map[$layout['alignX'] ?? ''] ?? null;
        $y = $map[$layout['alignY'] ?? ''] ?? null;
        if ($x === null && $y === null) {
            return '';
        }

        return 'display:flex;flex-direction:column;'
            . ($x !== null ? 'align-items:' . $x . ';' : '')
            . ($y !== null ? 'justify-content:' . $y . ';' : '');
    }

    /**
     * Alignment for a Bootstrap .row (horizontal = justify-content, vertical =
     * align-items), so columns inside can be centred. Returns '' when unset.
     *
     * @param array<string,mixed> $layout
     */
    public function rowAlign(array $layout): string
    {
        $map = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'];
        $x = $map[$layout['alignX'] ?? ''] ?? null;
        $y = $map[$layout['alignY'] ?? ''] ?? null;
        if ($x === null && $y === null) {
            return '';
        }

        return 'display:flex;'
            . ($x !== null ? 'justify-content:' . $x . ';' : '')
            . ($y !== null ? 'align-items:' . $y . ';' : '');
    }

    /**
     * Bootstrap gutter class for a row gap setting (none..xl → g-0..g-5).
     *
     * @param array<string,mixed> $layout
     */
    public function gapClass(array $layout): string
    {
        return [
            'none' => 'g-0', 'xs' => 'g-1', 's' => 'g-2', 'm' => 'g-3', 'l' => 'g-4', 'xl' => 'g-5',
        ][$layout['gap'] ?? ''] ?? '';
    }

    /**
     * Sanitised ` class="…" id="…"` attribute string from a layout's
     * cssClass / cssId. Values are already filtered on save; we re-escape.
     *
     * @param array<string,mixed> $layout
     */
    /**
     * Responsive @media overrides for a wrapper whose BASE styles are applied
     * INLINE (row / column / container hosts). Declarations carry !important so
     * they beat the inline base (a normal inline style otherwise always wins
     * over stylesheet rules). $sel must be a unique per-element hook class
     * (e.g. ".draggo-rr123"). Returns '' when no responsive override is set.
     *
     * $extraInline (optional) appends scope-specific inline declarations for the
     * MERGED breakpoint layout — e.g. rowAlign() for rows, flex() for columns,
     * containerLayoutCss() for containers — so alignment / flex / grid / gap
     * change per breakpoint too (compile() alone doesn't emit those). The whole
     * inline part is marked !important so it beats the inline base.
     *
     * @param array<string,mixed>                 $l
     * @param (callable(array<string,mixed>):string)|null $extraInline
     */
    public function responsiveBlock(string $sel, array $l, ?callable $extraInline = null): string
    {
        if (empty($l['responsive']) || !\is_array($l['responsive'])) {
            return '';
        }
        $base = $l;
        unset($base['responsive']);
        $css = '';
        foreach (['tablet' => 991, 'mobile' => 767] as $bp => $max) {
            $ov = \is_array($l['responsive'][$bp] ?? null) ? $l['responsive'][$bp] : [];
            if ($ov === []) {
                continue;
            }
            $merged = array_merge($base, $ov);
            $inline = $this->compile($merged) . ($extraInline !== null ? (string) $extraInline($merged) : '');
            $inlineImp = $inline !== '' ? (string) preg_replace('/;/', ' !important;', $inline) : '';
            $scoped = $this->scoped($sel, $merged, true);   // child-targeted props (no @media nesting)
            $overlay = $this->overlayBefore($sel, $merged);  // per-breakpoint overlay (::before)
            $inner = ($inlineImp !== '' ? $sel . '{' . $inlineImp . '}' : '') . $scoped . $overlay;
            if ($inner !== '') {
                $css .= '@media (max-width:' . $max . 'px){' . $inner . '}';
            }
        }

        return $css;
    }

    /**
     * Display/flex/grid layout for a CONTAINER (article) host. Extracted so the
     * base CSS and the per-breakpoint responsiveBlock() use identical logic.
     *
     * @param array<string,mixed> $l
     */
    public function containerLayoutCss(array $l): string
    {
        $boxed = (($l['width'] ?? 'full') === 'boxed');
        $display = (string) ($l['display'] ?? '');
        $gapMap = ['none' => '0', 'xs' => '.25rem', 's' => '.5rem', 'm' => '1rem', 'l' => '2rem', 'xl' => '3rem'];
        $gap = $gapMap[(string) ($l['gap'] ?? '')] ?? '';
        $css = 'width:100%;max-width:none;';
        if ($display === 'grid') {
            $cols = (int) ($l['gridColumns'] ?? 0);
            $cols = ($cols >= 1 && $cols <= 12) ? $cols : 2;
            $css .= 'display:grid;grid-template-columns:repeat(' . $cols . ',1fr);';
            if ($gap !== '') {
                $css .= 'gap:' . $gap . ';';
            }
        } elseif ($display === 'flex') {
            $dir = \in_array($l['flexDirection'] ?? '', ['row', 'column', 'row-reverse', 'column-reverse'], true) ? $l['flexDirection'] : 'row';
            $justify = \in_array($l['flexJustify'] ?? '', ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'], true) ? $l['flexJustify'] : 'flex-start';
            $align = \in_array($l['flexAlign'] ?? '', ['stretch', 'flex-start', 'center', 'flex-end'], true) ? $l['flexAlign'] : 'stretch';
            $wrap = (($l['flexWrap'] ?? '') === 'wrap') ? 'wrap' : 'nowrap';
            $css .= 'display:flex;flex-direction:' . $dir . ';justify-content:' . $justify . ';align-items:' . $align . ';flex-wrap:' . $wrap . ';';
            if ($gap !== '') {
                $css .= 'gap:' . $gap . ';';
            }
        } else {
            $alignMap = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'];
            $alignItems = $alignMap[$l['alignX'] ?? ''] ?? ($boxed ? 'center' : 'stretch');
            $justify = $alignMap[$l['alignY'] ?? ''] ?? 'flex-start';
            $css .= 'display:flex;flex-direction:column;align-items:' . $alignItems . ';justify-content:' . $justify . ';';
        }

        return $css;
    }

    /**
     * Editor-canvas responsive preview for a LEAF element: emits the element's
     * per-breakpoint overrides keyed to `.draggo-canvas[data-vw="…"]` (NOT
     * @media — the shrunk canvas isn't a real viewport, so @media never fires).
     * The ancestor selector outspecifies the base `.draggo-el-{id}` rule, so
     * no !important is needed. Mirrors the frontend so the canvas shows e.g. a
     * smaller font on tablet/mobile.
     *
     * @param array<string,mixed> $l
     */
    public function previewResponsive(string $sel, array $l): string
    {
        if (empty($l['responsive']) || !\is_array($l['responsive'])) {
            return '';
        }
        $base = $l;
        unset($base['responsive']);
        $css = '';
        foreach (['tablet', 'mobile'] as $vw) {
            $ov = \is_array($l['responsive'][$vw] ?? null) ? $l['responsive'][$vw] : [];
            if ($ov === []) {
                continue;
            }
            $merged = array_merge($base, $ov);
            $p = '.draggo-canvas[data-vw="' . $vw . '"] ';
            $inline = $this->previewCss($merged);
            $css .= ($inline !== '' ? $p . $sel . '{' . $inline . '}' : '') . $this->scoped($p . $sel, $merged, true);
        }

        return $css;
    }

    /**
     * Scope a user's custom CSS to $sel (container/row/col host). `selector`/`&`
     * tokens → the host; bare declarations get wrapped. Light sanitisation
     * (no </style>, @import, js vectors); length-capped. Authored by backend
     * users (same trust level as the element-scope customCss path).
     */
    public function customCssScoped(string $sel, mixed $raw): string
    {
        if (!\is_string($raw)) {
            return '';
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('#</?\s*style[^>]*>#i', '', $raw) ?? '';
        $raw = preg_replace('/@import[^;]*;?/i', '', $raw) ?? '';
        $raw = preg_replace('/(javascript:|expression\s*\(|behavior\s*:)/i', '', $raw) ?? '';
        $raw = mb_substr($raw, 0, 8000);

        return str_contains($raw, '{') ? str_replace(['selector', '&'], $sel, $raw) : $sel . '{' . $raw . '}';
    }

    /**
     * Per-device visibility (@media display:none) for a host. Same breakpoints
     * as the element path so container/row/col can be hidden per device too.
     *
     * @param array<string,mixed> $l
     */
    public function visibilityCss(string $sel, array $l): string
    {
        $css = '';
        if (!empty($l['hideMobile'])) {
            $css .= '@media (max-width:767px){' . $sel . '{display:none!important}}';
        }
        if (!empty($l['hideTablet'])) {
            $css .= '@media (min-width:768px) and (max-width:991px){' . $sel . '{display:none!important}}';
        }
        if (!empty($l['hideDesktop'])) {
            $css .= '@media (min-width:992px){' . $sel . '{display:none!important}}';
        }

        return $css;
    }

    /**
     * Per-breakpoint row alignment (justify-content/align-items) for the .row
     * element. !important so it beats the inline base align on the .row.
     *
     * @param array<string,mixed> $l
     */
    public function rowAlignResponsive(string $sel, array $l): string
    {
        if (empty($l['responsive']) || !\is_array($l['responsive'])) {
            return '';
        }
        $base = $l;
        unset($base['responsive']);
        $css = '';
        foreach (['tablet' => 991, 'mobile' => 767] as $bp => $max) {
            $ov = \is_array($l['responsive'][$bp] ?? null) ? $l['responsive'][$bp] : [];
            if ($ov === []) {
                continue;
            }
            $a = $this->rowAlign(array_merge($base, $ov));
            if ($a !== '') {
                $a = (string) preg_replace('/;/', ' !important;', $a);
                $css .= '@media (max-width:' . $max . 'px){' . $sel . '{' . $a . '}}';
            }
        }

        return $css;
    }

    public function attrs(array $layout): string
    {
        $out = '';
        $class = preg_replace('/[^A-Za-z0-9 _-]/', '', (string) ($layout['cssClass'] ?? ''));
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($layout['cssId'] ?? ''));
        if ($class !== '') {
            $out .= ' class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if ($id !== '') {
            $out .= ' id="' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $out;
    }

    /**
     * Accept only hex colours (#rgb, #rrggbb, #rrggbbaa). Reject everything
     * else (no rgb()/url()/expressions) → no CSS injection.
     */
    /**
     * Escape a user string for safe use inside a CSS content:"" value. The
     * InputSanitizer already strips quotes/backslashes/angles/controls; this is
     * defence-in-depth (escape backslash + double-quote) plus a length cap.
     */
    private function cssContent(mixed $v): string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return '';
        }
        $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);

        return mb_substr($s, 0, 24);
    }

    private function color(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        // Accept hex or a design-token reference var(--bld-…) (Phase E).
        return preg_match('/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/', $value) === 1 ? $value : null;
    }

    /**
     * Pick a readable text colour (dark or white) for a given background.
     * Only hex backgrounds can be measured; tokens/unknowns default to dark
     * (the drawer's default background is white).
     */
    private function contrastColor(string $bg): string
    {
        $dark = '#1b1f24';
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bg, $m)) {
            return $dark;
        }

        $hex = $m[1];
        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Perceived luminance (sRGB weights). >150 ≈ light → use dark text.
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b);

        return $lum > 150 ? $dark : '#ffffff';
    }
}
