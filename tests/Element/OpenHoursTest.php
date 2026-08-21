<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Element;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Element\OpenHours;

/**
 * Pure timezone-aware open/closed logic for the Open-Now badge. No framework.
 */
final class OpenHoursTest extends TestCase
{
    /** Mon–Fri 09:00–18:00, Sat 10:00–14:00, Sun closed. */
    private function week(): array
    {
        $wd = [['o' => '09:00', 'c' => '18:00']];
        return OpenHours::normalize([$wd, $wd, $wd, $wd, $wd, [['o' => '10:00', 'c' => '14:00']], []]);
    }

    private function ts(string $iso, string $tz): int
    {
        return (new \DateTimeImmutable($iso, new \DateTimeZone($tz)))->getTimestamp();
    }

    public function testOpenDuringBusinessHours(): void
    {
        // Wednesday 11:30 Berlin
        $r = OpenHours::evaluate($this->week(), 'Europe/Berlin', $this->ts('2026-06-17 11:30', 'Europe/Berlin'));
        self::assertTrue($r['open']);
        self::assertSame('18:00', $r['closesAt']);
    }

    public function testTimezoneIsAuthoritativeNotVisitorClock(): void
    {
        // The SAME instant: 11:30 Berlin == 05:30 New York. A naive visitor-clock
        // implementation in NY would read "closed". We evaluate in Berlin → open.
        $instant = $this->ts('2026-06-17 11:30', 'Europe/Berlin');
        $r = OpenHours::evaluate($this->week(), 'Europe/Berlin', $instant);
        self::assertTrue($r['open'], 'must read Berlin wall-clock regardless of where computed');
    }

    public function testClosedBeforeOpeningSameDay(): void
    {
        $r = OpenHours::evaluate($this->week(), 'Europe/Berlin', $this->ts('2026-06-17 07:00', 'Europe/Berlin'));
        self::assertFalse($r['open']);
        self::assertTrue($r['opensToday']);
        self::assertSame('09:00', $r['opensAt']);
        self::assertSame(2, $r['opensDow'], 'Wednesday = index 2');
    }

    public function testClosedRollsToNextOpenDay(): void
    {
        // Sunday (closed) → next opening is Monday 09:00.
        $r = OpenHours::evaluate($this->week(), 'Europe/Berlin', $this->ts('2026-06-21 12:00', 'Europe/Berlin'));
        self::assertFalse($r['open']);
        self::assertFalse($r['opensToday']);
        self::assertSame(0, $r['opensDow'], 'Monday = index 0');
        self::assertSame('09:00', $r['opensAt']);
    }

    public function testLunchBreakLeavesAMidGap(): void
    {
        $wd = [['o' => '09:00', 'c' => '12:00'], ['o' => '13:00', 'c' => '18:00']];
        $week = OpenHours::normalize([$wd, $wd, $wd, $wd, $wd, [], []]);
        // 12:30 Tuesday → closed, opens again 13:00 today
        $r = OpenHours::evaluate($week, 'Europe/Berlin', $this->ts('2026-06-16 12:30', 'Europe/Berlin'));
        self::assertFalse($r['open']);
        self::assertTrue($r['opensToday']);
        self::assertSame('13:00', $r['opensAt']);
        // 13:30 Tuesday → open, closes 18:00
        $r2 = OpenHours::evaluate($week, 'Europe/Berlin', $this->ts('2026-06-16 13:30', 'Europe/Berlin'));
        self::assertTrue($r2['open']);
        self::assertSame('18:00', $r2['closesAt']);
    }

    public function testNextTsIsTheBoundary(): void
    {
        $open = $this->ts('2026-06-17 11:30', 'Europe/Berlin');
        $r = OpenHours::evaluate($this->week(), 'Europe/Berlin', $open);
        // closes 18:00 same day
        self::assertSame($this->ts('2026-06-17 18:00', 'Europe/Berlin'), $r['nextTs']);
    }

    public function testNormalizeDropsMalformedAndSorts(): void
    {
        $week = OpenHours::normalize([[['o' => '18:00', 'c' => '09:00'], ['o' => '09:00', 'c' => '12:00'], ['o' => 'bad', 'c' => '10:00']]]);
        self::assertCount(1, $week[0], 'reversed + malformed dropped, one valid range left');
        self::assertSame('09:00', $week[0][0][2]);
    }

    public function testHasAny(): void
    {
        self::assertTrue(OpenHours::hasAny($this->week()));
        self::assertFalse(OpenHours::hasAny(OpenHours::normalize([[], [], [], [], [], [], []])));
    }
}
