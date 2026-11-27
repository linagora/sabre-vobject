<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

/**
 * The end of an instance is its start plus the exact duration of the master
 * event (RFC 5545 section 3.8.5.3), also when the instance spans a change of
 * UTC offset.
 */
class DtEndAcrossDstTest extends TestCase
{
    /**
     * @return string[]
     */
    private static function instances(string $dtStart, string $dtEnd, string $rrule): array
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\n"
            .$dtStart."\r\n".$dtEnd."\r\nRRULE:".$rrule."\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $result = [];
        $it = new EventIterator($vcal, 'e');
        foreach ($it as $start) {
            $result[] = $start->format('Y-m-d H:i P').' / '.$it->getDtEnd()->format('Y-m-d H:i P');
        }

        return $result;
    }

    public function testFirstInstanceEndsAtDtEnd(): void
    {
        self::assertSame(
            [
                '2026-03-28 12:00 +01:00 / 2026-03-29 12:00 +02:00',
                '2026-04-04 12:00 +02:00 / 2026-04-05 11:00 +02:00',
            ],
            self::instances(
                'DTSTART;TZID=Europe/Paris:20260328T120000',
                'DTEND;TZID=Europe/Paris:20260329T120000',
                'FREQ=WEEKLY;COUNT=2'
            )
        );
    }

    public function testInstanceSpanningTheTransitionKeepsTheElapsedDuration(): void
    {
        self::assertSame(
            [
                '2026-03-21 23:00 +01:00 / 2026-03-22 04:00 +01:00',
                '2026-03-28 23:00 +01:00 / 2026-03-29 05:00 +02:00',
                '2026-04-04 23:00 +02:00 / 2026-04-05 04:00 +02:00',
            ],
            self::instances(
                'DTSTART;TZID=Europe/Paris:20260321T230000',
                'DTEND;TZID=Europe/Paris:20260322T040000',
                'FREQ=WEEKLY;COUNT=3'
            )
        );
    }

    public function testExpandedInstanceEnd(): void
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\n"
            ."DTSTART;TZID=Europe/Paris:20260321T230000\r\nDTEND;TZID=Europe/Paris:20260322T040000\r\n"
            ."RRULE:FREQ=WEEKLY;COUNT=3\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        $expanded = $vcal->expand(
            new \DateTimeImmutable('2026-03-28 00:00:00Z'),
            new \DateTimeImmutable('2026-03-30 00:00:00Z')
        );

        self::assertSame('20260328T220000Z', (string) $expanded->VEVENT->DTSTART);
        self::assertSame('20260329T030000Z', (string) $expanded->VEVENT->DTEND);
    }

    public function testAllDayEventKeepsWholeDays(): void
    {
        /** @var VCalendar $vcal */
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e\r\n"
            ."DTSTART;VALUE=DATE:20260328\r\nDTEND;VALUE=DATE:20260330\r\n"
            ."RRULE:FREQ=WEEKLY;COUNT=2\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $ends = [];
        $it = new EventIterator($vcal, 'e', new \DateTimeZone('Europe/Paris'));
        foreach ($it as $start) {
            $ends[] = $it->getDtEnd()->format('Y-m-d H:i');
        }

        self::assertSame(['2026-03-30 00:00', '2026-04-06 00:00'], $ends);
    }
}
