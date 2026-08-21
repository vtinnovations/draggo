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

namespace Vtinnovations\Draggo\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Element\OpenHours;

/**
 * "Open-Now" availability badge (USP). Computes open/closed from a weekly
 * schedule in an EXPLICIT timezone — so a Berlin shop reads correctly for a
 * visitor anywhere. Renders the correct text SERVER-SIDE (no JS required, no
 * flash), emits schema.org openingHoursSpecification, and ships the schedule as
 * data-attributes so draggo-frontend.js can re-evaluate + tick at the next
 * status boundary. Mirrors CountdownController's shape.
 */
#[AsContentElement(category: 'draggo', type: 'draggo_opennow')]
class OpenNowController extends AbstractDraggoElementController
{
    use ScopedElementTrait;

    /** Short weekday names (index 0 = Monday), matched by the JS. */
    private const DAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    /** schema.org dayOfWeek IRIs (index 0 = Monday). */
    private const SCHEMA_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $week = OpenHours::normalize($this->decodeHours($model->draggo_on_hours ?? null));
        $isEditor = empty($GLOBALS['objPage']);
        $en = $this->isEnglish();

        if (!OpenHours::hasAny($week)) {
            return new Response($isEditor
                ? '<div class="draggo-opennow draggo-opennow--empty">⏰ ' . ($en ? 'Opening hours — please enter times (✎ edit)' : 'Öffnungszeiten — bitte Zeiten eintragen (✎ bearbeiten)') . '</div>'
                : '');
        }

        $tz = trim((string) ($model->draggo_on_tz ?? '')) ?: 'Europe/Berlin';
        $openLbl = trim((string) ($model->draggo_on_open ?? '')) ?: ($en ? 'Open now' : 'Jetzt geöffnet');
        $closedLbl = trim((string) ($model->draggo_on_closed ?? '')) ?: ($en ? 'Closed' : 'Geschlossen');
        // Inverted toggles: empty (default) = shown, '1' = hidden — because the
        // props store cannot persist a falsy value for a default-on checkbox.
        $showNext = empty($model->draggo_on_hidenext);
        $showSchema = empty($model->draggo_on_noschema);

        $r = OpenHours::evaluate($week, $tz, time());
        $text = $this->text($r, $openLbl, $closedLbl, $showNext, $en);

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        [$idAttr, $cls] = $this->draggoScope($model);

        // Compact schedule for the JS: 7 days × [["o","c"], …].
        $jsHours = array_map(static fn (array $d): array => array_map(static fn (array $r): array => [$r[2], $r[3]], $d), $week);

        $stateCls = $r['open'] ? 'is-open' : 'is-closed';
        $html = '<div class="' . trim('draggo-opennow ' . $stateCls . ' ' . $cls) . '"' . $idAttr
            . ' data-tz="' . $e($tz) . '"'
            . ' data-hours="' . $e((string) json_encode($jsHours)) . '"'
            . ' data-days="' . $e((string) json_encode(self::DAYS)) . '"'
            . ' data-open="' . $e($openLbl) . '"'
            . ' data-closed="' . $e($closedLbl) . '"'
            . ' data-next="' . ($showNext ? '1' : '0') . '">'
            . '<span class="draggo-on-dot" aria-hidden="true"></span>'
            . '<span class="draggo-on-text">' . $e($text) . '</span>'
            . '</div>';

        if ($showSchema) {
            $html .= $this->schemaLd($week);
        }

        return new Response($html);
    }

    /**
     * Build the badge text from an evaluation result.
     *
     * @param array{open:bool,closesAt:?string,opensDow:?int,opensAt:?string,opensToday:bool,nextTs:?int} $r
     */
    private function text(array $r, string $openLbl, string $closedLbl, bool $showNext, bool $en = false): string
    {
        $closes = $en ? ' · closes ' : ' · schließt ';
        $opens = $en ? 'opens ' : 'öffnet ';
        if ($r['open']) {
            return $openLbl . ($showNext && $r['closesAt'] !== null ? $closes . $r['closesAt'] : '');
        }
        if (!$showNext || $r['opensAt'] === null) {
            return $closedLbl;
        }
        $dayNames = $en ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] : self::DAYS;
        $when = $r['opensToday'] ? $opens : $opens . ($dayNames[$r['opensDow']] ?? '') . ' ';

        return $closedLbl . ' · ' . $when . $r['opensAt'];
    }

    /** True when the current frontend page renders in an English locale. */
    private function isEnglish(): bool
    {
        $page = $GLOBALS['objPage'] ?? null;
        $lang = \is_object($page) ? strtolower((string) ($page->language ?? '')) : '';

        return $lang === '' ? false : !str_starts_with($lang, 'de');
    }

    /** schema.org LocalBusiness fragment carrying the openingHoursSpecification. */
    private function schemaLd(array $week): string
    {
        $spec = [];
        foreach ($week as $dow => $ranges) {
            foreach ($ranges as [, , $o, $c]) {
                $spec[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/' . self::SCHEMA_DAYS[$dow],
                    'opens' => $o,
                    'closes' => $c,
                ];
            }
        }
        if ($spec === []) {
            return '';
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'openingHoursSpecification' => $spec];

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    /**
     * The schedule is stored PHP-serialized inside draggo_props (mirrors the
     * 'cards' widget). StringUtil::deserialize handles both the serialized string
     * and an already-decoded array, so reads are robust either way.
     */
    private function decodeHours(mixed $raw): array
    {
        return StringUtil::deserialize($raw, true);
    }
}
