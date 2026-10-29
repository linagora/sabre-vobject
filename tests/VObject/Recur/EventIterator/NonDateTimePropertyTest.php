<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;

/**
 * A date property holding another value type (PERIOD, TEXT, ...) must be
 * rejected with an InvalidDataException rather than an Error.
 */
class NonDateTimePropertyTest extends TestCase
{
    #[DataProvider('invalidEvents')]
    public function testThrowsInvalidData(string $master, string $override): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:e1\r\n"
            .$master
            ."RRULE:FREQ=DAILY;COUNT=3\r\nEND:VEVENT\r\n"
            .$override
            ."END:VCALENDAR\r\n";

        $this->expectException(InvalidDataException::class);
        new EventIterator(Reader::read($ics), 'e1');
    }

    public static function invalidEvents(): array
    {
        $dtStart = "DTSTART:20260105T100000Z\r\n";

        return [
            'period RECURRENCE-ID' => [
                $dtStart,
                "BEGIN:VEVENT\r\nUID:e1\r\nRECURRENCE-ID;VALUE=PERIOD:20260106T100000Z/20260106T110000Z\r\n"
                ."DTSTART:20260106T120000Z\r\nEND:VEVENT\r\n",
            ],
            'text RECURRENCE-ID' => [
                $dtStart,
                "BEGIN:VEVENT\r\nUID:e1\r\nRECURRENCE-ID;VALUE=TEXT:foo\r\n"
                ."DTSTART:20260106T120000Z\r\nEND:VEVENT\r\n",
            ],
            'period EXDATE' => [
                $dtStart."EXDATE;VALUE=PERIOD:20260106T100000Z/20260106T110000Z\r\n",
                '',
            ],
            'text DTSTART' => [
                "DTSTART;VALUE=TEXT:foo\r\n",
                '',
            ],
            'text DTEND' => [
                $dtStart."DTEND;VALUE=TEXT:foo\r\n",
                '',
            ],
            'text DURATION' => [
                $dtStart."DURATION;VALUE=TEXT:foo\r\n",
                '',
            ],
            'text DTSTART on an override' => [
                $dtStart,
                "BEGIN:VEVENT\r\nUID:e1\r\nRECURRENCE-ID:20260106T100000Z\r\n"
                ."DTSTART;VALUE=TEXT:foo\r\nEND:VEVENT\r\n",
            ],
            'missing DTSTART' => [
                '',
                '',
            ],
        ];
    }
}
