<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

/**
 * The EventIterator yields the recurrence set (DTSTART, RRULE and RDATE
 * instances) in chronological order, so that fastForward(), isInTimeRange()
 * and expand() see every instance.
 */
class RDateOrderTest extends TestCase
{
    private static function event(string $properties): VCalendar
    {
        return Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//EN\r\nBEGIN:VEVENT\r\nUID:r\r\n"
            .$properties
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );
    }

    /**
     * @return string[]
     */
    private static function starts(VCalendar $vcal): array
    {
        $starts = [];
        foreach (new EventIterator($vcal->VEVENT) as $start) {
            $starts[] = $start->format('Y-m-d H:i');
        }

        return $starts;
    }

    public function testRDatesOutOfOrder(): void
    {
        $vcal = self::event(
            "DTSTART:20260510T090000Z\r\nDTEND:20260510T100000Z\r\n"
            ."RDATE:20260601T090000Z,20260520T090000Z\r\n"
        );

        self::assertSame(['2026-05-10 09:00', '2026-05-20 09:00', '2026-06-01 09:00'], self::starts($vcal));
        self::assertTrue($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-05-20 08:00:00Z'),
            new \DateTimeImmutable('2026-05-20 11:00:00Z')
        ));
    }

    public function testRDateBeforeDtStart(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RDATE:20260620T090000Z,20260520T090000Z\r\n"
        );

        self::assertSame(['2026-05-20 09:00', '2026-06-01 09:00', '2026-06-20 09:00'], self::starts($vcal));
        self::assertTrue($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-05-20 08:00:00Z'),
            new \DateTimeImmutable('2026-05-20 11:00:00Z')
        ));

        $expanded = $vcal->expand(
            new \DateTimeImmutable('2026-05-01 00:00:00Z'),
            new \DateTimeImmutable('2026-05-31 00:00:00Z')
        );
        self::assertCount(1, $expanded->VEVENT);
        self::assertSame('20260520T090000Z', (string) $expanded->VEVENT->DTSTART);
        self::assertSame('20260520T100000Z', (string) $expanded->VEVENT->DTEND);
    }

    public function testRDateEqualToDtStartIsYieldedOnce(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RDATE:20260601T090000Z,20260602T090000Z\r\n"
        );

        self::assertSame(['2026-06-01 09:00', '2026-06-02 09:00'], self::starts($vcal));
    }

    public function testRRuleAndRDate(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RRULE:FREQ=WEEKLY;COUNT=3\r\n"
            ."RDATE:20260610T090000Z,20260608T090000Z,20260528T090000Z\r\n"
        );

        self::assertSame(
            [
                '2026-05-28 09:00',
                '2026-06-01 09:00',
                '2026-06-08 09:00',
                '2026-06-10 09:00',
                '2026-06-15 09:00',
            ],
            self::starts($vcal)
        );
    }

    public function testInfiniteRRuleAndRDate(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RRULE:FREQ=DAILY\r\n"
            ."RDATE:20260603T120000Z\r\n"
            ."EXDATE:20260602T090000Z\r\n"
        );

        $it = new EventIterator($vcal->VEVENT);
        self::assertTrue($it->isInfinite());

        $starts = [];
        foreach ($it as $start) {
            $starts[] = $start->format('Y-m-d H:i');
            if (count($starts) >= 5) {
                break;
            }
        }
        self::assertSame(
            ['2026-06-01 09:00', '2026-06-03 09:00', '2026-06-03 12:00', '2026-06-04 09:00', '2026-06-05 09:00'],
            $starts
        );

        $it->rewind();
        $it->fastForward(new \DateTimeImmutable('2026-06-03 11:00:00Z'));
        self::assertSame('2026-06-03 12:00', $it->current()->format('Y-m-d H:i'));
    }

    public function testRDatesInTheirOwnTimeZone(): void
    {
        $vcal = self::event(
            "DTSTART;TZID=Europe/Paris:20260601T090000\r\nDTEND;TZID=Europe/Paris:20260601T100000\r\n"
            ."RDATE:20260520T090000Z\r\n"
            ."RDATE;TZID=America/New_York:20260519T230000\r\n"
            ."RDATE:20260610T090000\r\n"
        );

        $starts = [];
        foreach (new EventIterator($vcal->VEVENT) as $start) {
            $starts[] = $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i');
        }
        // 23:00 in New York is 03:00Z, a floating RDATE is in the time zone of DTSTART.
        self::assertSame(['2026-05-20 03:00', '2026-05-20 09:00', '2026-06-01 07:00', '2026-06-10 07:00'], $starts);

        self::assertTrue($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-05-20 02:00:00Z'),
            new \DateTimeImmutable('2026-05-20 04:00:00Z')
        ));
        self::assertFalse($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-05-19 20:00:00Z'),
            new \DateTimeImmutable('2026-05-19 22:00:00Z')
        ));
    }

    public function testRDatePeriods(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RDATE;VALUE=PERIOD:20260620T090000Z/PT2H,20260520T090000Z/20260520T110000Z\r\n"
        );

        self::assertSame(['2026-05-20 09:00', '2026-06-01 09:00', '2026-06-20 09:00'], self::starts($vcal));
        self::assertTrue($vcal->VEVENT->isInTimeRange(
            new \DateTimeImmutable('2026-05-20 08:00:00Z'),
            new \DateTimeImmutable('2026-05-20 11:00:00Z')
        ));
    }

    public function testRDateNotADate(): void
    {
        $vcal = self::event(
            "DTSTART:20260601T090000Z\r\nDTEND:20260601T100000Z\r\n"
            ."RDATE;VALUE=PERIOD:PT2H/20260520T110000Z\r\n"
        );

        $this->expectException(\Sabre\VObject\InvalidDataException::class);
        new EventIterator($vcal->VEVENT);
    }
}
