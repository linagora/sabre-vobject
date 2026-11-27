<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\FreeBusyGenerator;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

/**
 * A DATE-TIME DTSTART with a VALUE=DATE DTEND: each instance lasts as long as
 * the master, whatever reads it.
 */
class DateDtEndTest extends TestCase
{
    private static function read(string $dtStart, string $recurrence): VCalendar
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//y//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\n$dtStart\r\nDTEND;VALUE=DATE:20260105\r\n$recurrence\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        self::assertInstanceOf(VCalendar::class, $vcal);

        return $vcal;
    }

    public function testExpandKeepsTheEndOfTheInstance(): void
    {
        $vcal = self::read('DTSTART;TZID=Europe/Paris:20260104T100000', 'RDATE:20260112T080000Z');

        $expanded = $vcal->expand(
            new \DateTimeImmutable('2026-01-12T00:00:00Z'),
            new \DateTimeImmutable('2026-01-13T00:00:00Z'),
            new \DateTimeZone('UTC')
        );

        self::assertSame('20260112T080000Z', (string) $expanded->VEVENT->DTSTART);
        // The master lasts 15 hours in UTC.
        self::assertSame('20260112T230000Z', (string) $expanded->VEVENT->DTEND);
        self::assertFalse(isset($expanded->VEVENT->DTEND['VALUE']));
    }

    public function testFloatingInstanceHasAFloatingEnd(): void
    {
        $vcal = self::read('DTSTART:20260104T100000', 'RRULE:FREQ=WEEKLY;COUNT=2');
        $iterator = new EventIterator($vcal, 'a', new \DateTimeZone('Europe/Paris'));
        $iterator->next();

        $event = $iterator->getEventObject();

        self::assertSame('20260111T100000', (string) $event->DTSTART);
        self::assertSame('20260112T000000', (string) $event->DTEND);
        self::assertTrue($event->DTEND->isFloating());
    }

    public function testExpandAgreesWithTheFreeBusyGenerator(): void
    {
        $zone = new \DateTimeZone('America/St_Johns');
        $start = new \DateTimeImmutable('2026-03-01T00:00:00Z');
        $end = new \DateTimeImmutable('2026-03-15T00:00:00Z');
        $vcal = self::read('DTSTART;TZID=Europe/Paris:20260104T100000', 'RRULE:FREQ=WEEKLY;COUNT=12');

        $periods = [];
        foreach ($vcal->expand($start, $end, $zone)->select('VEVENT') as $event) {
            $periods[] = $event->DTSTART->getValue().'/'.$event->DTEND->getValue();
        }

        $generator = new FreeBusyGenerator($start, $end, $vcal, $zone);
        $busy = [];
        foreach ($generator->getResult()->VFREEBUSY->select('FREEBUSY') as $property) {
            $busy[] = (string) $property;
        }

        self::assertCount(2, $periods);
        self::assertSame($busy, $periods);
    }

    /**
     * The same types on both sides are not touched.
     */
    public function testAllDayInstanceKeepsADateEnd(): void
    {
        $vcal = self::read('DTSTART;VALUE=DATE:20260104', 'RRULE:FREQ=WEEKLY;COUNT=2');
        $iterator = new EventIterator($vcal, 'a', new \DateTimeZone('Europe/Paris'));
        $iterator->next();

        $event = $iterator->getEventObject();

        self::assertSame('20260112', (string) $event->DTEND);
        self::assertSame('DATE', (string) $event->DTEND['VALUE']);
    }
}
