<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

/**
 * A VALUE=DATE property that holds a date-time, as an iTIP delivery stored
 * without validate() may have: the instances are all-day.
 */
class DateValueWithTimeTest extends TestCase
{
    private static function read(string $lines): VCalendar
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:u\r\n{$lines}END:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertInstanceOf(VCalendar::class, $vcal);

        return $vcal;
    }

    public function testInstanceIsReadOneWay(): void
    {
        $vcal = self::read("DTSTART;VALUE=DATE:20261027T090000\r\nRRULE:FREQ=DAILY;COUNT=3\r\n");
        $timeZone = new \DateTimeZone('Asia/Kolkata');
        $iterator = new EventIterator($vcal, 'u', $timeZone);

        $object = $iterator->getEventObject();

        self::assertEquals(new \DateTimeImmutable('2026-10-27T00:00:00', $timeZone), $iterator->getDtStart());
        self::assertEquals(new \DateTimeImmutable('2026-10-28T00:00:00', $timeZone), $iterator->getDtEnd());
        self::assertEquals($object->DTSTART->getDateTime($timeZone), $iterator->getDtStart());
        self::assertEquals($object->{'RECURRENCE-ID'}->getDateTime($timeZone), $iterator->getDtStart());
    }

    public function testDateEndAndExdateWithATime(): void
    {
        $vcal = self::read("DTSTART;VALUE=DATE:20261027T090000\r\nDTEND;VALUE=DATE:20261029T120000\r\n"
            ."RRULE:FREQ=WEEKLY;COUNT=3\r\nEXDATE;VALUE=DATE:20261103T090000\r\n");
        $iterator = new EventIterator($vcal, 'u');

        $instances = [];
        foreach ($iterator as $start) {
            $instances[] = $start->format('Y-m-d H:i').' / '.$iterator->getDtEnd()->format('Y-m-d H:i');
        }

        self::assertSame(['2026-10-27 00:00 / 2026-10-29 00:00', '2026-11-10 00:00 / 2026-11-12 00:00'], $instances);
    }
}
