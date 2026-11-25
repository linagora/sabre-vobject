<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Inputs large enough to tell linear from quadratic work on a component's
 * children. Parsing them alone can take most of a second with coverage on,
 * so these tests get the medium time limit.
 */
#[Medium]
class ComponentPerformanceTest extends TestCase
{
    private static function vevent(string $lines, string $rrule = ''): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:x\r\nBEGIN:VEVENT\r\nUID:x\r\nDTSTAMP:20260101T000000Z\r\n"
            ."DTSTART:20260101T100000Z\r\n".$rrule.$lines."END:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    public function testManyDistinctPropertyNamesInLinearTime(): void
    {
        $lines = implode('', array_map(fn ($i) => "X-A$i:v\r\n", range(1, 20000)));
        $calendar = Reader::read(self::vevent($lines, "RRULE:FREQ=DAILY;COUNT=10\r\n"));

        $start = microtime(true);
        $calendar->validate();
        $serialized = $calendar->serialize();
        $expanded = $calendar->expand(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-01-01'));
        self::assertLessThan(2.0, microtime(true) - $start);

        self::assertCount(20000 + 4, $calendar->VEVENT->children());
        self::assertStringContainsString("X-A20000:v\r\n", $serialized);
        self::assertCount(10, $expanded->select('VEVENT'));
    }
}
