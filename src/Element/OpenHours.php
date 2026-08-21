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

namespace Vtinnovations\Draggo\Element;

/**
 * Pure, dependency-free open/closed computation for the "Open-Now" badge.
 *
 * The weekly schedule is a list of 7 days (index 0 = Monday … 6 = Sunday); each
 * day is a list of {o,c} ranges in "HH:MM" wall-clock (e.g. a lunch break = two
 * ranges, a closed day = empty list). Evaluation is done in an explicit IANA
 * timezone so a Berlin shop reads "open" correctly for a visitor in New York —
 * the whole point of the element. No deps → fully unit-testable; the frontend
 * JS mirrors this exact algorithm so the live tick stays consistent.
 */
final class OpenHours
{
    /** "HH:MM" → minutes since midnight, or null when malformed. */
    public static function toMinutes(string $hm): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hm), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return $h * 60 + $min;
    }

    /** minutes-since-midnight → "HH:MM" (zero-padded). */
    public static function fromMinutes(int $min): string
    {
        $min = max(0, $min) % 1440;

        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /**
     * Normalise a raw schedule into a list of 7 days, each a list of validated
     * [openMin, closeMin, "HH:MM open", "HH:MM close"] ranges (close > open),
     * sorted by opening time. Anything malformed is dropped.
     *
     * @param mixed $raw
     * @return list<list<array{0:int,1:int,2:string,3:string}>>
     */
    public static function normalize(mixed $raw): array
    {
        $week = [];
        for ($d = 0; $d < 7; ++$d) {
            $day = [];
            $rawDay = (\is_array($raw) && isset($raw[$d]) && \is_array($raw[$d])) ? $raw[$d] : [];
            foreach ($rawDay as $range) {
                if (!\is_array($range)) {
                    continue;
                }
                $o = self::toMinutes((string) ($range['o'] ?? $range[0] ?? ''));
                $c = self::toMinutes((string) ($range['c'] ?? $range[1] ?? ''));
                if ($o === null || $c === null || $c <= $o) {
                    continue;
                }
                $day[] = [$o, $c, self::fromMinutes($o), self::fromMinutes($c)];
            }
            usort($day, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $week[] = $day;
        }

        return $week;
    }

    /** True when the schedule has at least one valid range somewhere. */
    public static function hasAny(array $week): bool
    {
        foreach ($week as $day) {
            if ($day !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate the schedule at a given instant in a timezone.
     *
     * @param list<list<array{0:int,1:int,2:string,3:string}>> $week normalised
     * @return array{open:bool,closesAt:?string,opensDow:?int,opensAt:?string,opensToday:bool,nextTs:?int}
     *   nextTs = unix timestamp of the next status change (for the JS tick).
     */
    public static function evaluate(array $week, string $tz, int $nowTs): array
    {
        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Throwable) {
            $zone = new \DateTimeZone('UTC');
        }
        $dt = (new \DateTimeImmutable('@' . $nowTs))->setTimezone($zone);
        $dow = (int) $dt->format('N') - 1;          // 0 = Monday
        $nowMin = (int) $dt->format('G') * 60 + (int) $dt->format('i');
        $midnight = $dt->setTime(0, 0);             // local midnight today

        // Currently inside a range today?
        foreach ($week[$dow] as [$o, $c, , $cStr]) {
            if ($o <= $nowMin && $nowMin < $c) {
                return [
                    'open' => true,
                    'closesAt' => $cStr,
                    'opensDow' => null,
                    'opensAt' => null,
                    'opensToday' => false,
                    'nextTs' => $midnight->modify('+' . $c . ' minutes')->getTimestamp(),
                ];
            }
        }

        // Closed → find the next opening (later today, else scan up to 7 days).
        foreach ($week[$dow] as [$o, , $oStr]) {
            if ($o > $nowMin) {
                return [
                    'open' => false,
                    'closesAt' => null,
                    'opensDow' => $dow,
                    'opensAt' => $oStr,
                    'opensToday' => true,
                    'nextTs' => $midnight->modify('+' . $o . ' minutes')->getTimestamp(),
                ];
            }
        }
        for ($i = 1; $i <= 7; ++$i) {
            $d = ($dow + $i) % 7;
            if ($week[$d] !== []) {
                [$o, , $oStr] = $week[$d][0];

                return [
                    'open' => false,
                    'closesAt' => null,
                    'opensDow' => $d,
                    'opensAt' => $oStr,
                    'opensToday' => false,
                    'nextTs' => $midnight->modify('+' . $i . ' days')->modify('+' . $o . ' minutes')->getTimestamp(),
                ];
            }
        }

        // No openings anywhere (shouldn't happen when hasAny() is true).
        return ['open' => false, 'closesAt' => null, 'opensDow' => null, 'opensAt' => null, 'opensToday' => false, 'nextTs' => null];
    }
}
