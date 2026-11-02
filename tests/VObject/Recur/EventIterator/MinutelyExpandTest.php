<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * FREQ=MINUTELY and FREQ=SECONDLY must advance, so that expand() and
 * isInTimeRange() reach a window after DTSTART.
 */
class MinutelyExpandTest extends TestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//Test//EN\r\n"
        ."BEGIN:VEVENT\r\nUID:every-minute\r\nDTSTAMP:20261101T000000Z\r\n"
        ."DTSTART:20261101T235000Z\r\nDTEND:20261101T235100Z\r\nRRULE:FREQ=MINUTELY;INTERVAL=20\r\n"
        ."END:VEVENT\r\nEND:VCALENDAR\r\n";

    public function testExpand(): void
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read(self::ICS);
        $expanded = $vcal->expand(
            new \DateTimeImmutable('2026-11-02T00:00:00Z'),
            new \DateTimeImmutable('2026-11-02T01:00:00Z')
        );

        $starts = [];
        foreach ($expanded->VEVENT as $vevent) {
            $starts[] = $vevent->DTSTART->getDateTime()->format('Y-m-d H:i:s');
        }

        self::assertSame(['2026-11-02 00:10:00', '2026-11-02 00:30:00', '2026-11-02 00:50:00'], $starts);
    }

    public function testIsInTimeRange(): void
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read(self::ICS);

        self::assertTrue($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-11-02T10:05:00Z'),
            new \DateTimeImmutable('2026-11-02T10:11:00Z')
        ));
        self::assertFalse($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-11-02T10:11:00Z'),
            new \DateTimeImmutable('2026-11-02T10:29:00Z')
        ));
    }
}
