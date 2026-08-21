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

namespace Vtinnovations\Draggo\Security;

use Vtinnovations\Draggo\Editor\ElementRegistry;
use Vtinnovations\Draggo\Exception\InvalidInputException;
use Vtinnovations\Draggo\Grid\GridPresets;

/**
 * Whitelist-based validation for editor input. Nothing reaches the database
 * that has not been checked against a REAL, registered structure:
 *   - element types against $GLOBALS['TL_CTE'] (via ElementRegistry)
 *   - updatable columns against an explicit allowlist (Phase 1 scope)
 *
 * Phase 2 extends the allowlist with layout columns; Phase 3+ validates
 * draggo_data field names against the block type's BlockSpec.
 */
final class InputSanitizer
{
    /**
     * Columns the editor may PATCH on a tl_content row in Phase 1.
     * Deliberately tiny — everything else stays in the classic backend.
     */
    private const UPDATABLE_COLUMNS = ['headline'];

    public function __construct(
        private readonly ElementRegistry $elementRegistry,
    ) {
    }

    /**
     * Validate a CTE type against the live registry.
     */
    public function assertValidType(string $type): string
    {
        if (!$this->elementRegistry->isValidType($type)) {
            throw new InvalidInputException(sprintf('Unknown content element type "%s".', $type));
        }

        // Structural wrapper types (start/separator/stop) only exist as part of a
        // pair and would corrupt the article structure if created standalone.
        // Draggo containers are built via insertStructure(), not here.
        if ($this->elementRegistry->isWrapperType($type)) {
            throw new InvalidInputException(sprintf('Wrapper element type "%s" cannot be created standalone.', $type));
        }

        return $type;
    }

    /**
     * Validate a grid preset key against GridPresets (incl. 'custom').
     */
    public function assertValidPreset(string $preset): string
    {
        // PHP turns the numeric string key '1' into int 1, so compare as strings.
        $choices = array_map('strval', GridPresets::choices());
        if (!\in_array($preset, $choices, true)) {
            throw new InvalidInputException(sprintf('Unknown grid preset "%s".', $preset));
        }

        return $preset;
    }

    /**
     * Whitelist a layout payload to known keys + safe values.
     *
     * @param mixed $raw
     * @return array<string,string>
     */
    public function sanitizeLayout(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $clean = [];

        foreach (['bg', 'color'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match('/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/', trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }

        foreach (['padding', 'margin'] as $k) {
            if (isset($raw[$k]) && \in_array($raw[$k], ['none', 'xs', 's', 'm', 'l', 'xl'], true)) {
                $clean[$k] = $raw[$k];
            }
        }

        // Per-side spacing objects {top,right,bottom,left,unit}.
        foreach (['paddingBox', 'marginBox'] as $k) {
            if (isset($raw[$k]) && \is_array($raw[$k])) {
                $unit = \in_array($raw[$k]['unit'] ?? 'px', ['px', '%', 'rem', 'em'], true) ? $raw[$k]['unit'] : 'px';
                $box = ['unit' => $unit];
                $any = false;
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    $v = $raw[$k][$side] ?? '';
                    if (is_numeric($v) && abs((float) $v) <= 9999) {
                        $box[$side] = (float) $v;
                        $any = true;
                    } else {
                        $box[$side] = '';
                    }
                }
                if ($any) {
                    $clean[$k] = $box;
                }
            }
        }

        if (isset($raw['minHeight'])) {
            $mh = (int) $raw['minHeight'];
            if ($mh > 0 && $mh <= 4000) {
                $clean['minHeight'] = $mh;
            }
        }

        if (isset($raw['align']) && \in_array($raw['align'], ['left', 'center', 'right'], true)) {
            $clean['align'] = $raw['align'];
        }

        if (isset($raw['width']) && \in_array($raw['width'], ['full', 'boxed'], true)) {
            $clean['width'] = $raw['width'];
        }

        foreach (['alignX', 'alignY'] as $k) {
            if (isset($raw[$k]) && \in_array($raw[$k], ['start', 'center', 'end'], true)) {
                $clean[$k] = $raw[$k];
            }
        }

        if (isset($raw['gap']) && \in_array($raw['gap'], ['none', 'xs', 's', 'm', 'l', 'xl'], true)) {
            $clean['gap'] = $raw['gap'];
        }

        // ── Container display mode: Flexbox / Grid (Phase D, reference 2.4) ──
        if (isset($raw['display']) && \in_array($raw['display'], ['flex', 'grid'], true)) {
            $clean['display'] = $raw['display'];
        }
        if (isset($raw['flexDirection']) && \in_array($raw['flexDirection'], ['row', 'column', 'row-reverse', 'column-reverse'], true)) {
            $clean['flexDirection'] = $raw['flexDirection'];
        }
        if (isset($raw['flexJustify']) && \in_array($raw['flexJustify'], ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'], true)) {
            $clean['flexJustify'] = $raw['flexJustify'];
        }
        if (isset($raw['flexAlign']) && \in_array($raw['flexAlign'], ['stretch', 'flex-start', 'center', 'flex-end'], true)) {
            $clean['flexAlign'] = $raw['flexAlign'];
        }
        if (isset($raw['flexWrap']) && \in_array($raw['flexWrap'], ['nowrap', 'wrap'], true)) {
            $clean['flexWrap'] = $raw['flexWrap'];
        }
        if (isset($raw['gridColumns']) && is_numeric($raw['gridColumns']) && (int) $raw['gridColumns'] >= 1 && (int) $raw['gridColumns'] <= 12) {
            $clean['gridColumns'] = (int) $raw['gridColumns'];
        }

        if (isset($raw['cssClass']) && \is_string($raw['cssClass'])) {
            $c = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $raw['cssClass']) ?? '');
            if ($c !== '') {
                $clean['cssClass'] = $c;
            }
        }

        // Typography
        if (isset($raw['fontFamily']) && \is_string($raw['fontFamily'])) {
            $ff = trim($raw['fontFamily']);
            if ($ff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $ff)) {
                $clean['fontFamily'] = $ff;
            }
        }
        if (isset($raw['fontSize']) && is_numeric($raw['fontSize']) && (int) $raw['fontSize'] > 0 && (int) $raw['fontSize'] <= 400) {
            $clean['fontSize'] = (int) $raw['fontSize'];
        }
        if (isset($raw['fontWeight']) && \in_array((string) $raw['fontWeight'], ['100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $clean['fontWeight'] = (string) $raw['fontWeight'];
        }
        if (isset($raw['textTransform']) && \in_array($raw['textTransform'], ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $clean['textTransform'] = $raw['textTransform'];
        }
        if (isset($raw['fontStyle']) && \in_array($raw['fontStyle'], ['normal', 'italic'], true)) {
            $clean['fontStyle'] = $raw['fontStyle'];
        }
        if (isset($raw['textDecoration']) && \in_array($raw['textDecoration'], ['none', 'underline', 'line-through'], true)) {
            $clean['textDecoration'] = $raw['textDecoration'];
        }
        if (isset($raw['lineHeight']) && is_numeric($raw['lineHeight']) && (float) $raw['lineHeight'] > 0 && (float) $raw['lineHeight'] <= 5) {
            $clean['lineHeight'] = (float) $raw['lineHeight'];
        }
        foreach (['letterSpacing', 'wordSpacing'] as $k) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && abs((float) $raw[$k]) <= 100) {
                $clean[$k] = (float) $raw[$k];
            }
        }
        $colorRe = '/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/';
        foreach (['linkColor', 'linkHoverColor'] as $lc) {
            if (isset($raw[$lc]) && \is_string($raw[$lc]) && preg_match($colorRe, trim($raw[$lc]))) {
                $clean[$lc] = trim($raw[$lc]);
            }
        }
        if (isset($raw['linkDeco']) && \in_array($raw['linkDeco'], ['none', 'underline', 'hover'], true)) {
            $clean['linkDeco'] = $raw['linkDeco'];
        }
        if (isset($raw['linkWeight']) && \in_array((string) $raw['linkWeight'], ['300', '400', '500', '600', '700', '800', '900'], true)) {
            $clean['linkWeight'] = (string) $raw['linkWeight'];
        }
        // Link prefix/suffix go into CSS content:"" — strip anything that could
        // break out of the string (quotes, backslashes, angle brackets, control
        // chars) and cap length; emoji/arrows/letters survive.
        foreach (['linkBefore', 'linkAfter'] as $lk) {
            if (isset($raw[$lk]) && \is_string($raw[$lk])) {
                $v = preg_replace('/[\x00-\x1F<>\\\\"]/u', '', trim($raw[$lk])) ?? '';
                if ($v !== '') {
                    $clean[$lk] = mb_substr($v, 0, 24);
                }
            }
        }

        // Box / size / border / shadow
        foreach (['width', 'maxWidth', 'height', 'minHeight', 'maxHeight'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match('/^\d+(\.\d+)?(px|%|rem|em|vh|vw)$/', trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        if (isset($raw['opacity']) && is_numeric($raw['opacity']) && (float) $raw['opacity'] >= 0 && (float) $raw['opacity'] <= 1) {
            $clean['opacity'] = (float) $raw['opacity'];
        }
        if (isset($raw['borderWidth']) && is_numeric($raw['borderWidth']) && (int) $raw['borderWidth'] >= 0 && (int) $raw['borderWidth'] <= 50) {
            $clean['borderWidth'] = (int) $raw['borderWidth'];
        }
        if (isset($raw['borderStyle']) && \in_array($raw['borderStyle'], ['solid', 'dashed', 'dotted', 'double'], true)) {
            $clean['borderStyle'] = $raw['borderStyle'];
        }
        if (isset($raw['borderColor']) && \is_string($raw['borderColor']) && preg_match('/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/', trim($raw['borderColor']))) {
            $clean['borderColor'] = trim($raw['borderColor']);
        }
        if (isset($raw['borderRadius']) && is_numeric($raw['borderRadius']) && (int) $raw['borderRadius'] >= 0 && (int) $raw['borderRadius'] <= 400) {
            $clean['borderRadius'] = (int) $raw['borderRadius'];
        }
        if (isset($raw['boxShadow']) && \in_array($raw['boxShadow'], ['sm', 'md', 'lg', 'xl'], true)) {
            $clean['boxShadow'] = $raw['boxShadow'];
        }

        // ── Effekte / Sichtbarkeit / Verlaufstext ───────────────────
        if (isset($raw['textShadow']) && \in_array($raw['textShadow'], ['sm', 'md', 'lg'], true)) {
            $clean['textShadow'] = $raw['textShadow'];
        }
        if (!empty($raw['textGradient'])) {
            $clean['textGradient'] = 1;
            foreach (['gradFrom', 'gradTo'] as $k) {
                if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match('/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/', trim($raw[$k]))) {
                    $clean[$k] = trim($raw[$k]);
                }
            }
        }
        if (isset($raw['animation']) && \in_array($raw['animation'], ['fade', 'fade-up', 'fade-down', 'zoom', 'slide-left', 'slide-right'], true)) {
            $clean['animation'] = $raw['animation'];
            if (isset($raw['animDuration']) && is_numeric($raw['animDuration']) && (int) $raw['animDuration'] >= 0 && (int) $raw['animDuration'] <= 10000) {
                $clean['animDuration'] = (int) $raw['animDuration'];
            }
            if (isset($raw['animDelay']) && is_numeric($raw['animDelay']) && (int) $raw['animDelay'] >= 0 && (int) $raw['animDelay'] <= 10000) {
                $clean['animDelay'] = (int) $raw['animDelay'];
            }
        }
        if (!empty($raw['sticky'])) {
            $clean['sticky'] = 1;
            if (isset($raw['stickyOffset']) && is_numeric($raw['stickyOffset']) && (int) $raw['stickyOffset'] >= 0 && (int) $raw['stickyOffset'] <= 1000) {
                $clean['stickyOffset'] = (int) $raw['stickyOffset'];
            }
        }
        foreach (['lightbox', 'hoverZoom', 'hideDesktop', 'hideTablet', 'hideMobile'] as $k) {
            if (!empty($raw[$k])) {
                $clean[$k] = 1;
            }
        }

        // ── Advanced: z-index / position / transform / custom CSS (Phase C) ──
        if (isset($raw['zIndex']) && is_numeric($raw['zIndex']) && (int) $raw['zIndex'] >= -999 && (int) $raw['zIndex'] <= 9999) {
            $clean['zIndex'] = (int) $raw['zIndex'];
        }
        if (isset($raw['position']) && \in_array($raw['position'], ['relative', 'absolute', 'fixed', 'sticky'], true)) {
            $clean['position'] = $raw['position'];
        }
        $lenRe = '/^\d+(\.\d+)?(px|%|rem|em|vh|vw)$/';
        foreach (['posTop', 'posRight', 'posBottom', 'posLeft', 'transformTranslateX', 'transformTranslateY'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($lenRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        if (isset($raw['transformRotate']) && is_numeric($raw['transformRotate']) && abs((int) $raw['transformRotate']) <= 360) {
            $clean['transformRotate'] = (int) $raw['transformRotate'];
        }
        if (isset($raw['transformScale']) && is_numeric($raw['transformScale']) && (float) $raw['transformScale'] >= 0 && (float) $raw['transformScale'] <= 5) {
            $clean['transformScale'] = (float) $raw['transformScale'];
        }
        if (isset($raw['customCss']) && \is_string($raw['customCss'])) {
            $cc = $this->sanitizeCss($raw['customCss']);
            if ($cc !== '') {
                $clean['customCss'] = $cc;
            }
        }

        // ── Navigation element styling ──────────────────────────────
        $colorRe = '/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--bld-[a-z]+-[a-z0-9-]+\))$/';
        foreach (['navBg', 'navColor', 'navHover', 'navHoverBg', 'navActive', 'navIndicatorColor', 'navSeparatorColor', 'navDrawerBg', 'navHamburgerColor', 'navDrawerDivider', 'navOverlayColor'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($colorRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        foreach (['navSize' => 80, 'navGap' => 100, 'navPadV' => 80, 'navPadH' => 80, 'navIndicatorSize' => 12, 'navRadius' => 400, 'navDrawerGap' => 60] as $k => $max) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && (int) $raw[$k] >= 0 && (int) $raw[$k] <= $max) {
                $clean[$k] = (int) $raw[$k];
            }
        }
        if (isset($raw['navSeparator']) && \in_array($raw['navSeparator'], ['line', 'dot'], true)) {
            $clean['navSeparator'] = $raw['navSeparator'];
        }
        if (isset($raw['navDrawerAlign']) && \in_array($raw['navDrawerAlign'], ['left', 'center', 'right'], true)) {
            $clean['navDrawerAlign'] = $raw['navDrawerAlign'];
        }
        if (isset($raw['navWeight']) && \in_array((string) $raw['navWeight'], ['300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $clean['navWeight'] = (string) $raw['navWeight'];
        }
        if (isset($raw['navTransform']) && \in_array($raw['navTransform'], ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $clean['navTransform'] = $raw['navTransform'];
        }
        if (isset($raw['navIndicator']) && \in_array($raw['navIndicator'], ['underline', 'box', 'dot'], true)) {
            $clean['navIndicator'] = $raw['navIndicator'];
        }
        if (isset($raw['navAlign']) && \in_array($raw['navAlign'], ['flex-start', 'center', 'flex-end', 'space-between'], true)) {
            $clean['navAlign'] = $raw['navAlign'];
        }
        // Responsive hamburger drawer.
        if (isset($raw['navMobileMode']) && \in_array($raw['navMobileMode'], ['drawer'], true)) {
            $clean['navMobileMode'] = $raw['navMobileMode'];
        }
        if (isset($raw['navDrawerSide']) && \in_array($raw['navDrawerSide'], ['left', 'right'], true)) {
            $clean['navDrawerSide'] = $raw['navDrawerSide'];
        }
        foreach (['navBreakpoint' => [320, 1600], 'navDrawerWidth' => [180, 600], 'navHamburgerSize' => [14, 64]] as $k => $r) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && (int) $raw[$k] >= $r[0] && (int) $raw[$k] <= $r[1]) {
                $clean[$k] = (int) $raw[$k];
            }
        }
        if (isset($raw['navHamburgerIcon']) && \is_string($raw['navHamburgerIcon'])) {
            $i = strtolower(trim($raw['navHamburgerIcon']));
            $i = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 -]/', '', $i) ?? '') ?? '');
            if ($i !== '') {
                $clean['navHamburgerIcon'] = $i;
            }
        }
        if (isset($raw['navFontFamily']) && \is_string($raw['navFontFamily']) && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', trim($raw['navFontFamily']))) {
            $clean['navFontFamily'] = trim($raw['navFontFamily']);
        }
        if (isset($raw['navLetterSpacing']) && is_numeric($raw['navLetterSpacing']) && abs((float) $raw['navLetterSpacing']) <= 100) {
            $clean['navLetterSpacing'] = (float) $raw['navLetterSpacing'];
        }
        // Submenu (level 2) styling.
        foreach (['subBg', 'subColor', 'subHover', 'subHoverBg', 'subBorderColor', 'subDivider'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($colorRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        foreach (['subRadius' => [0, 80], 'subMinWidth' => [40, 800]] as $k => $r) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && (int) $raw[$k] >= $r[0] && (int) $raw[$k] <= $r[1]) {
                $clean[$k] = (int) $raw[$k];
            }
        }
        if (isset($raw['subShadow']) && \in_array($raw['subShadow'], ['sm', 'md', 'lg'], true)) {
            $clean['subShadow'] = $raw['subShadow'];
        }
        if (isset($raw['subFontFamily']) && \is_string($raw['subFontFamily']) && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', trim($raw['subFontFamily']))) {
            $clean['subFontFamily'] = trim($raw['subFontFamily']);
        }
        foreach (['subSize' => 60, 'subPadV' => 60, 'subPadH' => 60] as $k => $max) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && (int) $raw[$k] >= 0 && (int) $raw[$k] <= $max) {
                $clean[$k] = (int) $raw[$k];
            }
        }
        if (isset($raw['subWeight']) && \in_array((string) $raw['subWeight'], ['300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'], true)) {
            $clean['subWeight'] = (string) $raw['subWeight'];
        }
        if (isset($raw['subTransform']) && \in_array($raw['subTransform'], ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
            $clean['subTransform'] = $raw['subTransform'];
        }
        if (isset($raw['subAlign']) && \in_array($raw['subAlign'], ['left', 'center', 'right'], true)) {
            $clean['subAlign'] = $raw['subAlign'];
        }

        // ── Per-widget element styling (Phase 3.5) ──────────────────────
        // Button / Box / Accordion / Tabs / Carousel / Loop sub-element styles.
        foreach ([
            'btnHoverBg', 'btnHoverColor',
            'boxTitleColor', 'boxTextColor', 'boxIconColor', 'boxIconBg',
            'gaOverlayColor',
            'accHeadBg', 'accHeadColor', 'accActiveBg', 'accActiveColor', 'accBorderColor', 'accContentBg',
            'tabColor', 'tabActiveColor', 'tabNavBorder', 'tabContentBg',
            'carArrowColor', 'carArrowBg', 'carDotColor', 'carDotActive',
            'cardBg', 'cardBorderColor', 'loopTitleColor', 'loopTeaserColor', 'moreColor',
            'progressBarColor', 'progressTrackColor',
            'socialColor', 'socialBg', 'socialHoverBg',
            'flipFrontBg', 'flipFrontColor', 'flipBackBg', 'flipBackColor',
            'ctaBtnBg', 'ctaBtnColor',
            'shareColor', 'shareBg', 'shareHoverBg',
            'cdNumColor', 'cdLblColor', 'ilIconColor',
            'stepNumBg', 'stepNumColor', 'stepLineColor',
            'prtTitleColor', 'prtPriceColor', 'prtFeatureBorderColor', 'prtBtnBg',
            'prlNameColor', 'prlPriceColor', 'prlDotColor',
            'quoteBarColor', 'quoteAuthorColor', 'bcActiveColor', 'bcSepColor', 'tocBarColor', 'tocTitleColor',
            'animWordColor', 'hsDotColor', 'hsDotText', 'codeBg', 'codeColor',
            'ssBg', 'ssFg', 'hpBg', 'hpFg', 'thDim', 'thLit', 'tlAccent', 'tlTrack', 'pxFg',
            'rgBg', 'rgFg', 'sdColor', 'prColor', 'prTrack', 'ctBg', 'ctFg', 'tiBg', 'tiFg',
            'formLabelColor', 'formInputBg', 'formInputColor', 'formInputBorder', 'formAccent',
            'formBtnBg', 'formBtnColor', 'formBtnHoverBg', 'alertBorder',
            'ctaBtnHoverBg', 'ctaBtnHoverColor', 'prtBtnColor', 'prtBtnHoverBg',
            'onOpenColor', 'onClosedColor', 'onBg',
        ] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($colorRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        // Tilt-card background-photo options (enums).
        if (isset($raw['tiOverlay']) && \in_array((string) $raw['tiOverlay'], ['0', '0.25', '0.45', '0.65', '0.85'], true)) {
            $clean['tiOverlay'] = (string) $raw['tiOverlay'];
        }
        if (isset($raw['tiPos']) && \in_array($raw['tiPos'], ['top', 'bottom', 'left', 'right'], true)) {
            $clean['tiPos'] = $raw['tiPos'];
        }
        foreach ([
            'boxMediaW' => 600, 'boxGap' => 200, 'boxTitleSize' => 120, 'boxIconPad' => 100, 'boxMediaRadius' => 400, 'boxImgHeight' => 1600,
            'gaImgHeight' => 1200,
            'btnRadius' => 400, 'btnPadV' => 100, 'btnPadH' => 200, 'btnIconGap' => 60,
            'carImgHeight' => 1200, 'gaImgRadius' => 200,
            'accRadius' => 80, 'accGap' => 60,
            'carRadius' => 80, 'carGap' => 60,
            'cardRadius' => 80, 'cardPad' => 120, 'loopGap' => 120, 'loopTitleSize' => 120, 'loopImgHeight' => 1200,
            'formInputRadius' => 80, 'formGap' => 80, 'formBtnRadius' => 80, 'zmRadius' => 80,
            'progressHeight' => 60, 'socialSize' => 96,
            'shareSize' => 96, 'cdNumSize' => 200, 'cdGap' => 100, 'ilIconSize' => 80, 'ilGap' => 60, 'quoteBarWidth' => 20,
            'logoWidth' => 1200, 'logoMaxWidth' => 1200, 'logoHeight' => 800, 'logoRadius' => 400,
            'imgWidth' => 2000, 'imgMaxWidth' => 2000, 'imgHeight' => 1600, 'imgRadius' => 400,
            'quoteTextSize' => 120, 'spacerTablet' => 1000, 'spacerMobile' => 1000, 'alertRadius' => 200,
            'ssRadius' => 80, 'sdWidth' => 40,
            'ctaBtnRadius' => 400, 'prtBtnRadius' => 400, 'socialRadius' => 400, 'shareRadius' => 400,
            'onSize' => 60, 'onDotSize' => 32, 'onPadV' => 60, 'onPadH' => 80, 'onRadius' => 400,
        ] as $k => $max) {
            if (isset($raw[$k]) && is_numeric($raw[$k]) && (int) $raw[$k] >= 0 && (int) $raw[$k] <= $max) {
                $clean[$k] = (int) $raw[$k];
            }
        }
        foreach (['logoFit', 'imgFit', 'boxImgFit'] as $k) {
            if (isset($raw[$k]) && \in_array($raw[$k], ['contain', 'cover'], true)) {
                $clean[$k] = $raw[$k];
            }
        }
        if (isset($raw['ctaLayout']) && \in_array($raw['ctaLayout'], ['horizontal'], true)) {
            $clean['ctaLayout'] = $raw['ctaLayout'];
        }
        if (isset($raw['tabLayout']) && \in_array($raw['tabLayout'], ['vertical'], true)) {
            $clean['tabLayout'] = $raw['tabLayout'];
        }
        if (isset($raw['tabAlign']) && \in_array($raw['tabAlign'], ['flex-start', 'center', 'flex-end', 'space-between'], true)) {
            $clean['tabAlign'] = $raw['tabAlign'];
        }
        if (isset($raw['cardShadow']) && \in_array($raw['cardShadow'], ['sm', 'md', 'lg', 'xl'], true)) {
            $clean['cardShadow'] = $raw['cardShadow'];
        }
        foreach (['loopHover', 'btnFull', 'boxHover', 'quoteItalic', 'mapGray', 'alertIcon', 'formBtnFull'] as $k) {
            if (!empty($raw[$k])) {
                $clean[$k] = 1;
            }
        }

        // ── Per-part typography (multi-text elements) ──────────────────
        // Each prefix is one text part (title/text/before/word/after); suffixes
        // mirror LayoutStyleCompiler::partType() + StyleSchema::typoControls().
        // Responsive overrides recurse through this same method, so the part
        // keys are covered per breakpoint automatically.
        $partPrefixes = [
            'boxTitle', 'boxText', 'alertTitle', 'alertText', 'quoteText', 'quoteAuthor',
            'ctaTitle', 'ctaText', 'flipFTitle', 'flipFText', 'flipBTitle', 'flipBText',
            'anBefore', 'anWord', 'anAfter',
            'accHd', 'accBd', 'tabLbl', 'tabPanel',
            'stepTtl', 'stepTxt', 'ssTitle', 'ssText', 'tmText', 'ilTxt', 'prgLbl',
            'txTtl', 'txTxt',
            'lpEye', 'lpHead', 'lpGrad', 'lpLead',
        ];
        $weights = ['100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'];
        foreach ($partPrefixes as $p) {
            foreach (['Color', 'Bg'] as $s) {
                if (isset($raw[$p . $s]) && \is_string($raw[$p . $s]) && preg_match($colorRe, trim($raw[$p . $s]))) {
                    $clean[$p . $s] = trim($raw[$p . $s]);
                }
            }
            $ff = isset($raw[$p . 'FontFamily']) ? trim((string) $raw[$p . 'FontFamily']) : '';
            if ($ff !== '' && preg_match('/^([A-Za-z0-9 ,\'_-]+|var\(--bld-font-[a-z0-9-]+\))$/', $ff)) {
                $clean[$p . 'FontFamily'] = $ff;
            }
            if (isset($raw[$p . 'Size']) && is_numeric($raw[$p . 'Size']) && (int) $raw[$p . 'Size'] >= 0 && (int) $raw[$p . 'Size'] <= 400) {
                $clean[$p . 'Size'] = (int) $raw[$p . 'Size'];
            }
            if (isset($raw[$p . 'Mb']) && is_numeric($raw[$p . 'Mb']) && (int) $raw[$p . 'Mb'] >= 0 && (int) $raw[$p . 'Mb'] <= 300) {
                $clean[$p . 'Mb'] = (int) $raw[$p . 'Mb'];
            }
            if (isset($raw[$p . 'Spacing']) && is_numeric($raw[$p . 'Spacing']) && abs((float) $raw[$p . 'Spacing']) <= 100) {
                $clean[$p . 'Spacing'] = (float) $raw[$p . 'Spacing'];
            }
            if (isset($raw[$p . 'LineHeight']) && is_numeric($raw[$p . 'LineHeight']) && (float) $raw[$p . 'LineHeight'] > 0 && (float) $raw[$p . 'LineHeight'] <= 5) {
                $clean[$p . 'LineHeight'] = (float) $raw[$p . 'LineHeight'];
            }
            if (isset($raw[$p . 'Weight']) && \in_array((string) $raw[$p . 'Weight'], $weights, true)) {
                $clean[$p . 'Weight'] = (string) $raw[$p . 'Weight'];
            }
            if (isset($raw[$p . 'Style']) && \in_array($raw[$p . 'Style'], ['normal', 'italic'], true)) {
                $clean[$p . 'Style'] = $raw[$p . 'Style'];
            }
            if (isset($raw[$p . 'Transform']) && \in_array($raw[$p . 'Transform'], ['none', 'uppercase', 'lowercase', 'capitalize'], true)) {
                $clean[$p . 'Transform'] = $raw[$p . 'Transform'];
            }
            if (isset($raw[$p . 'Decoration']) && \in_array($raw[$p . 'Decoration'], ['none', 'underline', 'line-through'], true)) {
                $clean[$p . 'Decoration'] = $raw[$p . 'Decoration'];
            }
            if (isset($raw[$p . 'Align']) && \in_array($raw[$p . 'Align'], ['left', 'center', 'right', 'justify'], true)) {
                $clean[$p . 'Align'] = $raw[$p . 'Align'];
            }
        }

        if (isset($raw['cssId']) && \is_string($raw['cssId'])) {
            $i = trim(preg_replace('/[^A-Za-z0-9_-]/', '', $raw['cssId']) ?? '');
            if ($i !== '') {
                $clean['cssId'] = $i;
            }
        }

        // ── Responsive overrides per breakpoint (Phase F) ──────────────
        if (isset($raw['responsive']) && \is_array($raw['responsive'])) {
            $resp = [];
            foreach (['tablet', 'mobile'] as $bp) {
                if (isset($raw['responsive'][$bp]) && \is_array($raw['responsive'][$bp])) {
                    $sub = $this->sanitizeLayout($raw['responsive'][$bp]);
                    unset($sub['responsive']); // no nesting
                    if ($sub !== []) {
                        $resp[$bp] = $sub;
                    }
                }
            }
            if ($resp !== []) {
                $clean['responsive'] = $resp;
            }
        }

        // Background-image display options (Phase: bg controls).
        if (isset($raw['bgSize']) && \in_array($raw['bgSize'], ['cover', 'contain', 'auto'], true)) {
            $clean['bgSize'] = $raw['bgSize'];
        }
        if (isset($raw['bgPosition']) && \in_array($raw['bgPosition'], ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'], true)) {
            $clean['bgPosition'] = $raw['bgPosition'];
        }
        if (isset($raw['bgRepeat']) && \in_array($raw['bgRepeat'], ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'], true)) {
            $clean['bgRepeat'] = $raw['bgRepeat'];
        }
        if (isset($raw['bgAttachment']) && \in_array($raw['bgAttachment'], ['scroll', 'fixed'], true)) {
            $clean['bgAttachment'] = $raw['bgAttachment'];
        }

        if (isset($raw['image']) && \is_string($raw['image'])) {
            $img = trim($raw['image']);
            if ($img !== '' && !preg_match('/[\'"()\s]/', $img) && preg_match('#^(?:files/[^?]+|https?://[^\s\'"()]+)$#i', $img)) {
                $clean['image'] = $img;
            }
        }

        // ── Gradient background (Elementor-style: linear/radial) ───────
        foreach (['bgGradFrom', 'bgGradTo'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($colorRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        if (isset($raw['bgGradType']) && \in_array($raw['bgGradType'], ['linear', 'radial'], true)) {
            $clean['bgGradType'] = $raw['bgGradType'];
        }
        if (isset($raw['bgGradAngle']) && is_numeric($raw['bgGradAngle']) && (int) $raw['bgGradAngle'] >= 0 && (int) $raw['bgGradAngle'] <= 360) {
            $clean['bgGradAngle'] = (int) $raw['bgGradAngle'];
        }

        // ── Background overlay (color | gradient | image, above bg, below text) ──
        if (isset($raw['ovType']) && \in_array($raw['ovType'], ['color', 'gradient', 'image'], true)) {
            $clean['ovType'] = $raw['ovType'];
        }
        if (isset($raw['ovColor']) && \is_string($raw['ovColor']) && preg_match($colorRe, trim($raw['ovColor']))) {
            $clean['ovColor'] = trim($raw['ovColor']);
        }
        foreach (['ovGradFrom', 'ovGradTo'] as $k) {
            if (isset($raw[$k]) && \is_string($raw[$k]) && preg_match($colorRe, trim($raw[$k]))) {
                $clean[$k] = trim($raw[$k]);
            }
        }
        if (isset($raw['ovGradType']) && \in_array($raw['ovGradType'], ['linear', 'radial'], true)) {
            $clean['ovGradType'] = $raw['ovGradType'];
        }
        if (isset($raw['ovGradAngle']) && is_numeric($raw['ovGradAngle']) && (int) $raw['ovGradAngle'] >= 0 && (int) $raw['ovGradAngle'] <= 360) {
            $clean['ovGradAngle'] = (int) $raw['ovGradAngle'];
        }
        if (isset($raw['ovImage']) && \is_string($raw['ovImage'])) {
            $ovi = trim($raw['ovImage']);
            if ($ovi !== '' && !preg_match('/[\'"()\s]/', $ovi) && preg_match('#^(?:files/[^?]+|https?://[^\s\'"()]+)$#i', $ovi)) {
                $clean['ovImage'] = $ovi;
            }
        }
        if (isset($raw['ovOpacity']) && is_numeric($raw['ovOpacity']) && (float) $raw['ovOpacity'] >= 0 && (float) $raw['ovOpacity'] <= 1) {
            $clean['ovOpacity'] = (float) $raw['ovOpacity'];
        }
        if (isset($raw['ovBlend']) && \in_array($raw['ovBlend'], ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten'], true)) {
            $clean['ovBlend'] = $raw['ovBlend'];
        }

        // ── Media background: video | slider ───────────────────────────
        $mediaPath = static function (mixed $v): ?string {
            if (!\is_string($v)) {
                return null;
            }
            $v = trim($v);

            return ($v !== '' && !preg_match('/[\'"()\s]/', $v) && preg_match('#^(?:files/[^?]+|https?://[^\s\'"()]+)$#i', $v)) ? $v : null;
        };
        if (isset($raw['bgMedia']) && \in_array($raw['bgMedia'], ['video', 'slider'], true)) {
            $clean['bgMedia'] = $raw['bgMedia'];
        }
        if (($v = $mediaPath($raw['bgVideo'] ?? null)) !== null) {
            $clean['bgVideo'] = $v;
        }
        if (($v = $mediaPath($raw['bgVideoPoster'] ?? null)) !== null) {
            $clean['bgVideoPoster'] = $v;
        }
        if (isset($raw['bgSlides']) && \is_array($raw['bgSlides'])) {
            $slides = [];
            foreach ($raw['bgSlides'] as $s) {
                if (($u = $mediaPath($s)) !== null) {
                    $slides[] = $u;
                }
                if (\count($slides) >= 12) {
                    break;
                }
            }
            if ($slides !== []) {
                $clean['bgSlides'] = $slides;
            }
        }
        if (isset($raw['bgSlideInterval']) && is_numeric($raw['bgSlideInterval']) && (int) $raw['bgSlideInterval'] >= 1000 && (int) $raw['bgSlideInterval'] <= 20000) {
            $clean['bgSlideInterval'] = (int) $raw['bgSlideInterval'];
        }
        if (isset($raw['bgSlideFx']) && \in_array($raw['bgSlideFx'], ['fade', 'slide'], true)) {
            $clean['bgSlideFx'] = $raw['bgSlideFx'];
        }

        return $clean;
    }

    /**
     * Filter an update payload down to whitelisted, type-coerced columns.
     *
     * @param array<string,mixed> $input
     * @return array<string,scalar|null>
     */
    public function sanitizeUpdate(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            if (!\in_array($key, self::UPDATABLE_COLUMNS, true)) {
                continue; // silently drop non-whitelisted keys
            }

            if ($key === 'headline') {
                // Store in Contao's serialized {value,unit} shape.
                $clean[$key] = serialize(['unit' => 'h2', 'value' => $this->scalarString($value)]);
                continue;
            }

            $clean[$key] = $this->scalarString($value);
        }

        return $clean;
    }

    /**
     * Validate and normalise an explicit ordering list of element ids.
     *
     * @param mixed $raw
     * @return list<int>
     */
    public function sanitizeOrder(mixed $raw): array
    {
        if (!\is_array($raw) || $raw === []) {
            throw new InvalidInputException('Order payload must be a non-empty array of ids.');
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value)) {
                throw new InvalidInputException('Order list contains a non-numeric id.');
            }
            $ids[] = (int) $value;
        }

        if (\count($ids) !== \count(array_unique($ids))) {
            throw new InvalidInputException('Order list contains duplicate ids.');
        }

        return $ids;
    }

    /**
     * Custom CSS is authored by trusted backend users but still scoped to the
     * element ({{WRAPPER}}) and stripped of style-tag breakouts, @import and
     * obvious script vectors. Length-capped.
     */
    private function sanitizeCss(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('#</?\s*style[^>]*>#i', '', $raw) ?? '';
        $raw = preg_replace('/@import[^;]*;?/i', '', $raw) ?? '';
        $raw = preg_replace('/(javascript:|expression\s*\(|behavior\s*:)/i', '', $raw) ?? '';

        return substr($raw, 0, 4000);
    }

    private function scalarString(mixed $value): string
    {
        if (\is_array($value) || \is_object($value)) {
            throw new InvalidInputException('Expected a scalar value.');
        }

        return trim((string) $value);
    }
}
