<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

class OverrideDurationTest extends TestCase
{
    use \Sabre\VObject\PHPUnitAssertions;

    public function testOverrideDuration(): void
    {
        $ics = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
UID:1
SUMMARY:9-10Uhr
RRULE:FREQ=DAILY
DTSTART;TZID=Europe/Berlin:20210517T090000
DTEND;TZID=Europe/Berlin:20210517T100000
END:VEVENT
BEGIN:VEVENT
UID:2
SUMMARY:9-12Uhr
DTSTART;TZID=Europe/Berlin:20210519T090000
DTEND;TZID=Europe/Berlin:20210519T120000
RECURRENCE-ID;TZID=Europe/Berlin:20210519T090000
END:VEVENT
END:VCALENDAR
ICS;

        $vCalendar = Reader::read($ics);
        $eventIterator = new EventIterator($vCalendar->getComponents());

        self::assertEquals('2021-05-17 09:00:00', $eventIterator->current()->format('Y-m-d H:i:s'), 'recur event start time');
        self::assertEquals('2021-05-17 10:00:00', $eventIterator->getDtEnd()->format('Y-m-d H:i:s'), 'recur event end time');

        $eventIterator->next();
        self::assertEquals('2021-05-18 09:00:00', $eventIterator->current()->format('Y-m-d H:i:s'), 'recur event start time');
        self::assertEquals('2021-05-18 10:00:00', $eventIterator->getDtEnd()->format('Y-m-d H:i:s'), 'recur event end time');

        $eventIterator->next();
        self::assertEquals('2021-05-19 09:00:00', $eventIterator->current()->format('Y-m-d H:i:s'), 'overridden event start time');
        self::assertEquals('2021-05-19 12:00:00', $eventIterator->getDtEnd()->format('Y-m-d H:i:s'), 'overridden event end time');

        $eventIterator->next();
        self::assertEquals('2021-05-20 09:00:00', $eventIterator->current()->format('Y-m-d H:i:s'), 'recur event start time');
        self::assertEquals('2021-05-20 10:00:00', $eventIterator->getDtEnd()->format('Y-m-d H:i:s'), 'recur event end time');
    }

    public function testOverrideEndFromItsOwnDurationOrStart(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260105T090000Z\r\n"
            ."DTEND:20260105T100000Z\r\nRRULE:FREQ=DAILY;COUNT=5\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nRECURRENCE-ID:20260107T090000Z\r\n"
            ."DTSTART:20260107T140000Z\r\nDURATION:PT2H\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nRECURRENCE-ID:20260108T090000Z\r\n"
            ."DTSTART:20260108T140000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nRECURRENCE-ID:20260109T090000Z\r\n"
            ."DTSTART;VALUE=DATE:20260109\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $it = new EventIterator($vcal, 'a');
        $ends = [];
        foreach ($it as $start) {
            $ends[] = $start->format('Ymd\THis').'/'.$it->getDtEnd()->format('Ymd\THis');
        }
        self::assertSame([
            '20260105T090000/20260105T100000',
            '20260106T090000/20260106T100000',
            '20260107T140000/20260107T160000',
            '20260108T140000/20260108T140000',
            '20260109T000000/20260110T000000',
        ], $ends);
    }
}
