<?php

namespace Sabre\VObject\ITip;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

/**
 * Inputs large enough to tell linear from quadratic work on the EXDATE lists
 * of an event.
 */
#[Medium]
class BrokerPerformanceTest extends TestCase
{
    private static function exdates(int $from, int $count): string
    {
        return implode(',', array_map(fn ($i) => gmdate('Ymd\THis\Z', 1767261600 + $i * 86400), range($from, $from + $count - 1)));
    }

    private static function calendar(string $events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:x\r\n".$events."END:VCALENDAR\r\n";
    }

    private static function event(string $lines): string
    {
        return "BEGIN:VEVENT\r\nUID:x\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260101T100000Z\r\n"
            ."ORGANIZER:mailto:organizer@example.org\r\nATTENDEE:mailto:attendee@example.org\r\n".$lines."END:VEVENT\r\n";
    }

    private static function override(int $i): string
    {
        $recurrenceId = gmdate('Ymd\THis\Z', 1767261600 + $i * 86400);

        return self::event("RECURRENCE-ID:$recurrenceId\r\nEXDATE:$recurrenceId\r\n");
    }

    public function testManyOverridesWithAnExdateEachInLinearTime(): void
    {
        $events = self::event("RRULE:FREQ=DAILY\r\n");
        for ($i = 1; $i <= 7000; ++$i) {
            $events .= self::override($i);
        }
        $calendar = Reader::read(self::calendar($events));

        $start = microtime(true);
        $messages = (new Broker())->parseEvent($calendar, 'mailto:organizer@example.org');
        self::assertLessThan(3.0, microtime(true) - $start);

        self::assertCount(1, $messages);
    }

    public function testLargeExdateFollowedByOverridesInLinearTime(): void
    {
        $events = self::event("RRULE:FREQ=DAILY\r\nEXDATE:".self::exdates(3000, 20000)."\r\n");
        for ($i = 1; $i <= 2500; ++$i) {
            $events .= self::override($i);
        }
        $calendar = Reader::read(self::calendar($events));

        $start = microtime(true);
        $messages = (new Broker())->parseEvent($calendar, 'mailto:organizer@example.org');
        self::assertLessThan(3.0, microtime(true) - $start);

        self::assertCount(1, $messages);
    }

    public function testAttendeeAddingAnExdateToManyInLinearTime(): void
    {
        $old = Reader::read(self::calendar(self::event("RRULE:FREQ=DAILY\r\nEXDATE:".self::exdates(1, 50000)."\r\n")));
        $new = Reader::read(self::calendar(self::event("RRULE:FREQ=DAILY\r\nEXDATE:".self::exdates(1, 50001)."\r\n")));

        $start = microtime(true);
        $messages = (new Broker())->parseEvent($new, 'mailto:attendee@example.org', $old);
        self::assertLessThan(3.0, microtime(true) - $start);

        // One REPLY declines the new instance only.
        self::assertCount(1, $messages);
        self::assertSame('REPLY', $messages[0]->method);
        $declined = $messages[0]->message->select('VEVENT');
        self::assertCount(1, $declined);
        self::assertSame(gmdate('Ymd\THis\Z', 1767261600 + 50001 * 86400), (string) reset($declined)->{'RECURRENCE-ID'});
    }
}
