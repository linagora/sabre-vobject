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

    public function testDuplicatedRequiredPropertyRepairedInLinearTime(): void
    {
        $calendar = Reader::read(self::vevent(str_repeat("UID:x\r\n", 50000).str_repeat("X-B:v\r\n", 3)));

        $start = microtime(true);
        $messages = $calendar->validate(Component::REPAIR);
        self::assertLessThan(2.0, microtime(true) - $start);

        self::assertCount(1, $calendar->VEVENT->select('UID'));
        self::assertSame(1, max(array_column($messages, 'level')));
        // The kept UID stays where it was.
        self::assertStringContainsString("BEGIN:VEVENT\r\nUID:x\r\nDTSTAMP:", $calendar->serialize());
    }

    public function testVCardSubComponentsRemovedInLinearTime(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nX:p\r\n".str_repeat("BEGIN:X\r\nEND:X\r\n", 50000)."END:VCARD\r\n");

        $start = microtime(true);
        $vcard->validate(Component::REPAIR);
        self::assertLessThan(2.0, microtime(true) - $start);

        self::assertSame([], $vcard->getComponents());
        // A property of the same name as the components is kept.
        self::assertSame('p', (string) $vcard->X);
    }

    public function testDottedRemoveInLinearTime(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nITEM2.TEL:2\r\n".str_repeat("ITEM1.TEL:1\r\n", 50000)."END:VCARD\r\n");

        $start = microtime(true);
        $vcard->remove('item1.tel');
        self::assertLessThan(1.0, microtime(true) - $start);

        self::assertSame(['2'], array_map(strval(...), array_values($vcard->select('TEL'))));
    }

    public function testOneWarningPerLineInLinearTime(): void
    {
        $calendar = Reader::read(self::vevent(str_repeat(".X:\r\n", 100000)));

        $start = microtime(true);
        $messages = $calendar->validate();
        self::assertLessThan(2.0, microtime(true) - $start);

        self::assertCount(100000, array_filter($messages, fn ($m) => str_contains($m['message'], 'empty group')));
    }

    public function testManyRdateAndExdateLinesInLinearTime(): void
    {
        $rdates = implode('', array_map(fn ($i) => 'RDATE:'.gmdate('Ymd\THis\Z', 1767261600 + $i * 3600)."\r\n", range(1, 50000)));
        $exdates = implode('', array_map(fn ($i) => 'EXDATE:'.gmdate('Ymd\THis\Z', 1767261600 + $i * 3600)."\r\n", range(1, 49990)));
        $calendar = Reader::read(self::vevent($rdates.$exdates));

        $start = microtime(true);
        $iterator = new Recur\EventIterator($calendar->VEVENT);
        $count = 0;
        foreach ($iterator as $_) {
            ++$count;
        }
        self::assertLessThan(2.0, microtime(true) - $start);

        // DTSTART and the last 10 RDATEs.
        self::assertSame(11, $count);
    }
}
