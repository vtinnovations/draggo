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

namespace Vtinnovations\Draggo\Css;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Vtinnovations\Draggo\Layout\LayoutStyleCompiler;

/**
 * Phase B — central CSS generation + file cache (Roadmap / reference 0.3, 4.3).
 *
 * Instead of inlining a per-request <style> blob (the "inline-style wust"), we
 * compile every styled Draggo element on a page into ONE stylesheet, write it
 * to a web-served cache file named by a content hash, and link it. Same content
 * → same file (idempotent, no build step, Plesk/shared-hosting friendly); any
 * change produces a new hash → new file → automatic invalidation.
 *
 * `.draggo-el-{id}` is the per-instance wrapper ({{WRAPPER}} principle); this
 * class is the home for richer selector-template generation later.
 */
final class CssGenerator
{
    private const CACHE_REL = 'assets/draggo';

    public function __construct(
        private readonly Connection $connection,
        private readonly LayoutStyleCompiler $compiler,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Build the full CSS for a page plus the runtime FX work-list.
     *
     * @return array{css:string, fx:array{anim:list<string>, lightbox:list<string>}}
     */
    public function buildForPage(int $pageId): array
    {
        $articleIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_article WHERE pid = :pid',
            ['pid' => $pageId],
        );
        if ($articleIds === []) {
            return ['css' => '', 'fx' => ['anim' => [], 'lightbox' => []]];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, draggo_layout FROM tl_content
             WHERE ptable = 'tl_article' AND pid IN (:ids) AND draggo_layout IS NOT NULL AND draggo_layout != ''",
            ['ids' => array_map('intval', $articleIds)],
            ['ids' => ArrayParameterType::INTEGER],
        );

        return $this->buildFromRows($rows);
    }

    /**
     * @param list<array{id:int|string, draggo_layout:string|null}> $rows
     * @return array{css:string, fx:array{anim:list<string>, lightbox:list<string>}}
     */
    public function buildFromRows(array $rows): array
    {
        $css = '';
        $hide = ['mobile' => [], 'tablet' => [], 'desktop' => []];
        $anim = [];
        $lightbox = [];
        $sticky = [];

        foreach ($rows as $row) {
            $l = json_decode((string) $row['draggo_layout'], true);
            if (!\is_array($l) || isset($l['row']) || isset($l['col'])) {
                continue; // skip grid wrappers
            }
            $id = (int) $row['id'];
            $sel = '.draggo-el-' . $id;
            $style = $this->compiler->compile($l);
            if ($style !== '') {
                $css .= $sel . '{' . $style . '}';
                // If the wrapper has an explicit width/height, let the image
                // fill it (so style sizing actually changes the picture);
                // otherwise just keep it responsive.
                $hasSize = isset($l['width']) || isset($l['height']) || isset($l['maxWidth']);
                $css .= $sel . ' img{' . ($hasSize ? 'width:100%;' : '') . 'max-width:100%;height:auto;}';
                // A font-size on the wrapper must NOT be multiplied by the
                // heading's own em size → make headings inherit it.
                if (isset($l['fontSize'])) {
                    $css .= $sel . ' h1,' . $sel . ' h2,' . $sel . ' h3,' . $sel . ' h4,' . $sel . ' h5,' . $sel . ' h6{font-size:inherit;}';
                }
            }
            if (!empty($l['linkColor']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $l['linkColor'])) {
                $css .= $sel . ' a{color:' . $l['linkColor'] . ';}';
            }

            // Child-targeted styling (navigation links/hover/active/indicator).
            $css .= $this->compiler->scoped($sel, $l);

            // Custom CSS (Phase C): scoped to the element. `selector` token →
            // the wrapper; bare declarations get wrapped automatically.
            if (!empty($l['customCss']) && \is_string($l['customCss'])) {
                $css .= $this->scopeCustomCss($sel, $l['customCss']);
            }

            // Responsive overrides (Phase F): per breakpoint, emitted as media
            // queries (tablet ≤991, mobile ≤767). Two parts per breakpoint:
            //   • compile() → the element's own inline-style props ($sel{…})
            //   • scoped(mediaSafe) → child-targeted props (nav links/hover/
            //     indicator/submenu, logo/img sizing, …) so EVERYTHING the
            //     element can style is overridable per device, not just the
            //     inline-style props. Merged over the base so dependent rules
            //     (e.g. indicator type + colour) compute correctly.
            if (!empty($l['responsive']) && \is_array($l['responsive'])) {
                $base = $l;
                unset($base['responsive']);
                foreach ([['tablet', 991], ['mobile', 767]] as [$bp, $max]) {
                    $ov = \is_array($l['responsive'][$bp] ?? null) ? $l['responsive'][$bp] : [];
                    if ($ov === []) {
                        continue;
                    }
                    $inline = $this->compiler->compile($ov);
                    $scoped = $this->compiler->scoped($sel, array_merge($base, $ov), true);
                    $inner = ($inline !== '' ? $sel . '{' . $inline . '}' : '') . $scoped;
                    if ($inner !== '') {
                        $css .= '@media (max-width:' . $max . 'px){' . $inner . '}';
                    }
                }
            }

            if (!empty($l['hideMobile'])) {
                $hide['mobile'][] = $sel;
            }
            if (!empty($l['hideTablet'])) {
                $hide['tablet'][] = $sel;
            }
            if (!empty($l['hideDesktop'])) {
                $hide['desktop'][] = $sel;
            }

            if (!empty($l['hoverZoom'])) {
                $css .= $sel . '{overflow:hidden;}' . $sel . ' img{transition:transform .4s ease;}' . $sel . ':hover img{transform:scale(1.08);}';
            }
            if (!empty($l['lightbox'])) {
                $lightbox[] = $sel;
            }
            if (!empty($l['sticky'])) {
                $off = (int) ($l['stickyOffset'] ?? 0);
                $sticky[] = ['sel' => $sel, 'offset' => ($off >= 0 && $off <= 1000) ? $off : 0];
            }

            if (!empty($l['animation'])) {
                $starts = [
                    'fade' => 'none', 'fade-up' => 'translateY(24px)', 'fade-down' => 'translateY(-24px)',
                    'zoom' => 'scale(.9)', 'slide-left' => 'translateX(-40px)', 'slide-right' => 'translateX(40px)',
                ];
                $start = $starts[(string) $l['animation']] ?? null;
                if ($start !== null) {
                    $dur = (int) ($l['animDuration'] ?? 600);
                    $dur = ($dur > 0 && $dur <= 10000) ? $dur : 600;
                    $delay = (int) ($l['animDelay'] ?? 0);
                    $delay = ($delay >= 0 && $delay <= 10000) ? $delay : 0;
                    $css .= $sel . '{opacity:0;transform:' . $start . ';transition:opacity ' . $dur . 'ms ease ' . $delay . 'ms,transform ' . $dur . 'ms ease ' . $delay . 'ms;}';
                    $css .= $sel . '.draggo-in{opacity:1;transform:none;}';
                    $anim[] = $sel;
                }
            }
        }

        if ($hide['mobile'] !== []) {
            $css .= '@media (max-width:767px){' . implode(',', $hide['mobile']) . '{display:none!important}}';
        }
        if ($hide['tablet'] !== []) {
            $css .= '@media (min-width:768px) and (max-width:991px){' . implode(',', $hide['tablet']) . '{display:none!important}}';
        }
        if ($hide['desktop'] !== []) {
            $css .= '@media (min-width:992px){' . implode(',', $hide['desktop']) . '{display:none!important}}';
        }

        if ($lightbox !== []) {
            $css .= '.draggo-lightbox{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.85);padding:2rem;}'
                . '.draggo-lightbox.is-open{display:flex;}'
                . '.draggo-lightbox img{max-width:95%;max-height:90%;box-shadow:0 10px 40px rgba(0,0,0,.5);}'
                . '.draggo-lightbox-close{position:absolute;top:1rem;right:1.25rem;font-size:1.75rem;line-height:1;color:#fff;background:none;border:0;cursor:pointer;}';
        }

        return ['css' => $css, 'fx' => ['anim' => array_values($anim), 'lightbox' => array_values($lightbox), 'sticky' => array_values($sticky)]];
    }

    /**
     * Ensure a cache file exists for the page's current CSS and return its URL
     * + the FX work-list. URL is null when the page has no Draggo styling.
     *
     * @return array{url:?string, fx:array{anim:list<string>, lightbox:list<string>}}
     */
    public function cacheForPage(int $pageId): array
    {
        $built = $this->buildForPage($pageId);
        if ($built['css'] === '') {
            return ['url' => null, 'fx' => $built['fx']];
        }

        $hash = substr(sha1($built['css']), 0, 16);
        $file = 'page-' . $pageId . '-' . $hash . '.css';
        [$webDir, $urlBase] = $this->cachePaths();
        $abs = $webDir . '/' . $file;

        if (!is_dir($webDir)) {
            @mkdir($webDir, 0775, true);
        }

        if (!is_file($abs)) {
            // Drop stale hashes for this page, then write the fresh file.
            foreach (glob($webDir . '/page-' . $pageId . '-*.css') ?: [] as $old) {
                @unlink($old);
            }
            @file_put_contents($abs, $built['css']);
        }

        return ['url' => $urlBase . '/' . $file, 'fx' => $built['fx']];
    }

    /**
     * Scope user custom CSS to the element. If it contains `{` it is treated as
     * full rules and the literal `selector` token is replaced with the wrapper;
     * otherwise the declarations are wrapped in the wrapper. Already sanitised
     * by InputSanitizer::sanitizeCss on save.
     */
    private function scopeCustomCss(string $sel, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_contains($raw, '{')) {
            return str_replace('selector', $sel, $raw);
        }

        return $sel . '{' . $raw . '}';
    }

    /**
     * Resolve [absolute cache dir, public URL base], honouring public/ (Contao 5)
     * or web/ (legacy) document roots.
     *
     * @return array{0:string,1:string}
     */
    private function cachePaths(): array
    {
        $root = is_dir($this->projectDir . '/public') ? $this->projectDir . '/public' : $this->projectDir . '/web';

        return [$root . '/' . self::CACHE_REL, '/' . self::CACHE_REL];
    }
}
