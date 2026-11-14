<?php

namespace Sabre\VObject\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Node;
use Sabre\VObject\Reader;

class VAlarmTest extends TestCase
{
    #[DataProvider('timeRangeTestData')]
    public function testInTimeRange(VAlarm $valarm, \DateTime $start, \DateTime $end, bool $outcome): void
    {
        self::assertEquals($outcome, $valarm->isInTimeRange($start, $end));
    }

    public static function timeRangeTestData(): array
    {
        $tests = [];

        $calendar = new VCalendar();

        // Hard date and time
        $valarm1 = $calendar->createComponent('VALARM');
        $valarm1->add(
            $calendar->createProperty('TRIGGER', '20120312T130000Z', ['VALUE' => 'DATE-TIME'])
        );

        $tests[] = [$valarm1, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-04-01 01:00:00'), true];
        $tests[] = [$valarm1, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-03-10 01:00:00'), false];

        // Relation to start time of event
        $valarm2 = $calendar->createComponent('VALARM');
        $valarm2->add(
            $calendar->createProperty('TRIGGER', '-P1D', ['VALUE' => 'DURATION'])
        );

        $vevent2 = $calendar->createComponent('VEVENT');
        $vevent2->DTSTART = '20120313T130000Z';
        $vevent2->add($valarm2);

        $tests[] = [$valarm2, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-04-01 01:00:00'), true];
        $tests[] = [$valarm2, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-03-10 01:00:00'), false];

        // Relation to end time of event
        $valarm3 = $calendar->createComponent('VALARM');
        $valarm3->add($calendar->createProperty('TRIGGER', '-P1D', ['VALUE' => 'DURATION', 'RELATED' => 'END']));

        $vevent3 = $calendar->createComponent('VEVENT');
        $vevent3->DTSTART = '20120301T130000Z';
        $vevent3->DTEND = '20120401T130000Z';
        $vevent3->add($valarm3);

        $tests[] = [$valarm3, new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'), false];
        $tests[] = [$valarm3, new \DateTime('2012-03-25 01:00:00'), new \DateTime('2012-04-05 01:00:00'), true];

        // Relation to end time of todo
        $valarm4 = $calendar->createComponent('VALARM');
        $valarm4->TRIGGER = '-P1D';
        $valarm4->TRIGGER['VALUE'] = 'DURATION';
        $valarm4->TRIGGER['RELATED'] = 'END';

        $vtodo4 = $calendar->createComponent('VTODO');
        $vtodo4->DTSTART = '20120301T130000Z';
        $vtodo4->DUE = '20120401T130000Z';
        $vtodo4->add($valarm4);

        $tests[] = [$valarm4, new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'), false];
        $tests[] = [$valarm4, new \DateTime('2012-03-25 01:00:00'), new \DateTime('2012-04-05 01:00:00'), true];

        // Relation to start time of event + repeat
        $valarm5 = $calendar->createComponent('VALARM');
        $valarm5->TRIGGER = '-P1D';
        $valarm5->TRIGGER['VALUE'] = 'DURATION';
        $valarm5->REPEAT = 10;
        $valarm5->DURATION = 'P1D';

        $vevent5 = $calendar->createComponent('VEVENT');
        $vevent5->DTSTART = '20120301T130000Z';
        $vevent5->add($valarm5);

        $tests[] = [$valarm5, new \DateTime('2012-03-09 01:00:00'), new \DateTime('2012-03-10 01:00:00'), true];

        // Relation to start time of event + duration, but no repeat
        $valarm6 = $calendar->createComponent('VALARM');
        $valarm6->TRIGGER = '-P1D';
        $valarm6->TRIGGER['VALUE'] = 'DURATION';
        $valarm6->DURATION = 'P1D';

        $vevent6 = $calendar->createComponent('VEVENT');
        $vevent6->DTSTART = '20120313T130000Z';
        $vevent6->add($valarm6);

        $tests[] = [$valarm6, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-04-01 01:00:00'), true];
        $tests[] = [$valarm6, new \DateTime('2012-03-01 01:00:00'), new \DateTime('2012-03-10 01:00:00'), false];

        // Relation to end time of event (DURATION instead of DTEND)
        $valarm7 = $calendar->createComponent('VALARM');
        $valarm7->TRIGGER = '-P1D';
        $valarm7->TRIGGER['VALUE'] = 'DURATION';
        $valarm7->TRIGGER['RELATED'] = 'END';

        $vevent7 = $calendar->createComponent('VEVENT');
        $vevent7->DTSTART = '20120301T130000Z';
        $vevent7->DURATION = 'P30D';
        $vevent7->add($valarm7);

        $tests[] = [$valarm7, new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'), false];
        $tests[] = [$valarm7, new \DateTime('2012-03-25 01:00:00'), new \DateTime('2012-04-05 01:00:00'), true];

        // Relation to end time of event (No DTEND or DURATION)
        $valarm7 = $calendar->createComponent('VALARM');
        $valarm7->TRIGGER = '-P1D';
        $valarm7->TRIGGER['VALUE'] = 'DURATION';
        $valarm7->TRIGGER['RELATED'] = 'END';

        $vevent7 = $calendar->createComponent('VEVENT');
        $vevent7->DTSTART = '20120301T130000Z';
        $vevent7->add($valarm7);

        $tests[] = [$valarm7, new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'), true];
        $tests[] = [$valarm7, new \DateTime('2012-03-25 01:00:00'), new \DateTime('2012-04-05 01:00:00'), false];

        // Relation to start time of todo
        $valarm8 = $calendar->createComponent('VALARM');
        $valarm8->TRIGGER = '-P1D';
        $valarm8->TRIGGER['VALUE'] = 'DURATION';

        $vtodo8 = $calendar->createComponent('VTODO');
        $vtodo8->DTSTART = '20120301T130000Z';
        $vtodo8->DUE = '20120401T130000Z';
        $vtodo8->add($valarm8);

        $tests[] = [$valarm8, new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'), true];
        $tests[] = [$valarm8, new \DateTime('2012-03-25 01:00:00'), new \DateTime('2012-04-05 01:00:00'), false];

        return $tests;
    }

    public function testInTimeRangeInvalidComponent(): void
    {
        $this->expectException(InvalidDataException::class);
        $calendar = new VCalendar();
        $valarm = $calendar->createComponent('VALARM');
        $valarm->TRIGGER = '-P1D';
        $valarm->TRIGGER['RELATED'] = 'END';

        $vjournal = $calendar->createComponent('VJOURNAL');
        $vjournal->add($valarm);

        $valarm->isInTimeRange(new \DateTime('2012-02-25 01:00:00'), new \DateTime('2012-03-05 01:00:00'));
    }

    /**
     * This bug was found and reported on the mailing list.
     */
    public function testInTimeRangeBuggy(): void
    {
        $input = <<<BLA
BEGIN:VCALENDAR
BEGIN:VTODO
DTSTAMP:20121003T064931Z
UID:b848cb9a7bb16e464a06c222ca1f8102@examle.com
STATUS:NEEDS-ACTION
DTSTART:20121005T000000Z
SUMMARY:Task 1
CATEGORIES:AlarmCategory
BEGIN:VALARM
TRIGGER:-PT10M
ACTION:DISPLAY
DESCRIPTION:Task 1
END:VALARM
END:VTODO
END:VCALENDAR
BLA;

        $vobj = Reader::read($input);

        self::assertTrue($vobj->VTODO->VALARM->isInTimeRange(new \DateTime('2012-10-01 00:00:00'), new \DateTime('2012-11-01 00:00:00')));
    }

    public function testInTimeRangeRepeatOutOfRange(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:e1\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\nBEGIN:VALARM\r\nTRIGGER:-PT15M\r\n"
            ."ACTION:DISPLAY\r\nDESCRIPTION:x\r\nREPEAT:-1\r\nDURATION:PT10M\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $start = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $end = new \DateTimeImmutable('2026-01-05T18:00:00Z');
        // A negative REPEAT means no repetition: only the trigger, at 09:45Z.
        self::assertTrue($vcal->VEVENT->VALARM->isInTimeRange($start, $end));
        self::assertFalse($vcal->VEVENT->VALARM->isInTimeRange(new \DateTimeImmutable('2026-01-05T09:46:00Z'), $end));

        $vcal->VEVENT->VALARM->REPEAT = '100000000000';
        self::assertTrue($vcal->VEVENT->VALARM->isInTimeRange(new \DateTimeImmutable('2027-01-05T09:46:00Z'), new \DateTimeImmutable('2027-01-05T09:56:00Z')));
        self::assertFalse($vcal->VEVENT->VALARM->isInTimeRange(new \DateTimeImmutable('2027-01-05T09:46:00Z'), new \DateTimeImmutable('2027-01-05T09:55:00Z')));

        $vcal->VEVENT->VALARM->REPEAT = 2000000000;
        $vcal->VEVENT->VALARM->DURATION = 'PT1S';
        $vcal->VEVENT->DTSTART = '20270105T100000Z';
        $t = microtime(true);
        self::assertFalse($vcal->VEVENT->VALARM->isInTimeRange($start, $end));
        self::assertLessThan(1, microtime(true) - $t);
    }

    public function testInTimeRangeRepeatBounds(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:e1\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART;TZID=Europe/Paris:20260320T100000\r\nBEGIN:VALARM\r\nTRIGGER:PT0S\r\n"
            ."ACTION:DISPLAY\r\nDESCRIPTION:x\r\nREPEAT:20\r\nDURATION:P1D\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $alarm = $vcal->VEVENT->VALARM;
        // Repetitions are at 10:00 local time, after the DST change of 29 March too.
        self::assertTrue($alarm->isInTimeRange(new \DateTimeImmutable('2026-04-09T08:00:00Z'), new \DateTimeImmutable('2026-04-09T08:00:01Z')));
        self::assertFalse($alarm->isInTimeRange(new \DateTimeImmutable('2026-04-09T08:00:01Z'), new \DateTimeImmutable('2026-04-10T07:59:59Z')));
        // The last one (repetition 20) is on 9 April, there is none on 10 April.
        self::assertFalse($alarm->isInTimeRange(new \DateTimeImmutable('2026-04-10T00:00:00Z'), new \DateTimeImmutable('2026-04-11T00:00:00Z')));

        $alarm->REPEAT = 0;
        self::assertFalse($alarm->isInTimeRange(new \DateTimeImmutable('2026-03-21T00:00:00Z'), new \DateTimeImmutable('2026-03-22T00:00:00Z')));
        self::assertTrue($alarm->isInTimeRange(new \DateTimeImmutable('2026-03-20T00:00:00Z'), new \DateTimeImmutable('2026-03-21T00:00:00Z')));
    }

    public static function unanchoredAlarmData(): array
    {
        $alarm = "BEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:x\r\n";

        return [
            'VTODO without DTSTART' => ["BEGIN:VTODO\r\nUID:t1\r\nDTSTAMP:20260101T000000Z\r\n{$alarm}TRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VTODO\r\n"],
            'related to the end, no DUE nor DTSTART' => ["BEGIN:VTODO\r\nUID:t1\r\nDTSTAMP:20260101T000000Z\r\n{$alarm}TRIGGER;RELATED=END:PT0S\r\nEND:VALARM\r\nEND:VTODO\r\n"],
            'related to the end, DURATION without DTSTART' => ["BEGIN:VTODO\r\nUID:t1\r\nDTSTAMP:20260101T000000Z\r\nDURATION:PT1H\r\n{$alarm}TRIGGER;RELATED=END:PT0S\r\nEND:VALARM\r\nEND:VTODO\r\n"],
            'VEVENT without DTSTART' => ["BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260101T000000Z\r\n{$alarm}TRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VEVENT\r\n"],
            'DTSTART of type TEXT' => ["BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;VALUE=TEXT:x\r\n{$alarm}TRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VEVENT\r\n"],
            'TRIGGER of type TEXT' => ["BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\n{$alarm}TRIGGER;VALUE=TEXT:x\r\nEND:VALARM\r\nEND:VEVENT\r\n"],
            'no TRIGGER' => ["BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\n{$alarm}END:VALARM\r\nEND:VEVENT\r\n"],
        ];
    }

    #[DataProvider('unanchoredAlarmData')]
    public function testInTimeRangeUnanchoredAlarm(string $component): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\n{$component}END:VCALENDAR\r\n");
        $alarm = $vcal->getBaseComponent()->VALARM;

        $this->expectException(InvalidDataException::class);
        $alarm->isInTimeRange(new \DateTimeImmutable('2026-01-01T00:00:00Z'), new \DateTimeImmutable('2027-01-01T00:00:00Z'));
    }

    public function testInTimeRangeTriggerWithEmptyValueParameter(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:e1\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\nBEGIN:VALARM\r\nTRIGGER;VALUE=:-PT15M\r\n"
            ."ACTION:DISPLAY\r\nDESCRIPTION:x\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        self::assertEquals(new \DateTimeImmutable('2026-01-05T09:45:00Z'), $vcal->VEVENT->VALARM->getEffectiveTriggerTime());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function alarmAnchors(): array
    {
        $alarm = static fn (string $trigger) => "BEGIN:VALARM\r\n$trigger\r\nACTION:DISPLAY\r\nDESCRIPTION:x\r\nEND:VALARM\r\n";
        $todo = static fn (string $lines, string $trigger) => "BEGIN:VTODO\r\nUID:t1\r\nDTSTAMP:20260101T000000Z\r\n$lines".$alarm($trigger)."END:VTODO\r\n";
        $event = "BEGIN:VEVENT\r\nUID:e1\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260310T100000Z\r\nEND:VEVENT\r\n";

        return [
            'VTODO without DTSTART' => [$todo('', 'TRIGGER:-PT15M'), false],
            'VTODO without DUE nor DTSTART, related to the end' => [$todo('', 'TRIGGER;RELATED=END:-PT15M'), false],
            'VALARM under VCALENDAR' => [$alarm('TRIGGER:-PT15M').$event, false],
            'VTODO with DUE, related to the end' => [$todo("DUE:20260310T100000Z\r\n", 'TRIGGER;RELATED=END:-PT15M'), true],
            'VTODO with DTSTART' => [$todo("DTSTART:20260310T100000Z\r\n", 'TRIGGER:-PT15M'), true],
            'VTODO without DTSTART, absolute trigger' => [$todo('', 'TRIGGER;VALUE=DATE-TIME:20260310T100000Z'), true],
        ];
    }

    /**
     * validate() does not accept what isInTimeRange() refuses.
     */
    #[DataProvider('alarmAnchors')]
    public function testValidateRefusesUnanchoredAlarm(string $components, bool $valid): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\n{$components}END:VCALENDAR\r\n");

        $levels = array_column($vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR), 'level');

        self::assertSame(!$valid, in_array(3, $levels, true));
    }
}
