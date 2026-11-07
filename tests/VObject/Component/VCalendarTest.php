<?php

namespace Sabre\VObject\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject;
use Sabre\VObject\InvalidDataException;

class VCalendarTest extends TestCase
{
    use VObject\PHPUnitAssertions;

    #[DataProvider('expandData')]
    public function testExpand(string $input, string $output, string $timeZone = 'UTC', string $start = '2011-12-01', string $end = '2011-12-31'): void
    {
        $vcal = VObject\Reader::read($input);

        $timeZone = new \DateTimeZone($timeZone);

        $vcal = $vcal->expand(
            new \DateTime($start),
            new \DateTime($end),
            $timeZone
        );

        // This will normalize the output
        $output = VObject\Reader::read($output)->serialize();

        self::assertVObjectEqualsVObject($output, $vcal->serialize());
    }

    public static function expandData(): array
    {
        $tests = [];

        // No data
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
END:VCALENDAR
';

        $output = $input;
        $tests[] = [$input, $output];

        // Simple events
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla
SUMMARY:InExpand
DTSTART;VALUE=DATE:20111202
END:VEVENT
BEGIN:VEVENT
UID:bla2
SUMMARY:NotInExpand
DTSTART;VALUE=DATE:20120101
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla
SUMMARY:InExpand
DTSTART;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';

        $tests[] = [$input, $output];

        // Removing timezone info
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Paris
END:VTIMEZONE
BEGIN:VEVENT
UID:bla4
SUMMARY:RemoveTZ info
DTSTART;TZID=Europe/Paris:20111203T130102
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla4
SUMMARY:RemoveTZ info
DTSTART:20111203T120102Z
END:VEVENT
END:VCALENDAR
';

        $tests[] = [$input, $output];

        // Removing timezone info from sub-components. See Issue #278
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Paris
END:VTIMEZONE
BEGIN:VEVENT
UID:bla4
SUMMARY:RemoveTZ info
DTSTART;TZID=Europe/Paris:20111203T130102
BEGIN:VALARM
TRIGGER;VALUE=DATE-TIME;TZID=America/New_York:20151209T133200
END:VALARM
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla4
SUMMARY:RemoveTZ info
DTSTART:20111203T120102Z
BEGIN:VALARM
TRIGGER;VALUE=DATE-TIME:20151209T183200Z
END:VALARM
END:VEVENT
END:VCALENDAR
';

        $tests[] = [$input, $output];

        // Recurrence rule
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111125T120000Z
DTEND:20111125T130000Z
RRULE:FREQ=WEEKLY
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111202T120000Z
DTEND:20111202T130000Z
RECURRENCE-ID:20111202T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111209T120000Z
DTEND:20111209T130000Z
RECURRENCE-ID:20111209T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111216T120000Z
DTEND:20111216T130000Z
RECURRENCE-ID:20111216T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111223T120000Z
DTEND:20111223T130000Z
RECURRENCE-ID:20111223T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule
DTSTART:20111230T120000Z
DTEND:20111230T130000Z
RECURRENCE-ID:20111230T120000Z
END:VEVENT
END:VCALENDAR
';

        $tests[] = [$input, $output];

        // Recurrence rule + override
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule2
DTSTART:20111125T120000Z
DTEND:20111125T130000Z
RRULE:FREQ=WEEKLY
END:VEVENT
BEGIN:VEVENT
UID:bla6
RECURRENCE-ID:20111209T120000Z
DTSTART:20111209T140000Z
DTEND:20111209T150000Z
SUMMARY:Override!
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule2
DTSTART:20111202T120000Z
DTEND:20111202T130000Z
RECURRENCE-ID:20111202T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
RECURRENCE-ID:20111209T120000Z
DTSTART:20111209T140000Z
DTEND:20111209T150000Z
SUMMARY:Override!
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule2
DTSTART:20111216T120000Z
DTEND:20111216T130000Z
RECURRENCE-ID:20111216T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule2
DTSTART:20111223T120000Z
DTEND:20111223T130000Z
RECURRENCE-ID:20111223T120000Z
END:VEVENT
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule2
DTSTART:20111230T120000Z
DTEND:20111230T130000Z
RECURRENCE-ID:20111230T120000Z
END:VEVENT
END:VCALENDAR
';

        $tests[] = [$input, $output];

        // Floating dates and times.
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:bla1
DTSTART:20141112T195000
END:VEVENT
BEGIN:VEVENT
UID:bla2
DTSTART;VALUE=DATE:20141112
END:VEVENT
BEGIN:VEVENT
UID:bla3
DTSTART;VALUE=DATE:20141112
RRULE:FREQ=DAILY;COUNT=2
END:VEVENT
END:VCALENDAR
ICS;

        $output = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:bla1
DTSTART:20141112T225000Z
END:VEVENT
BEGIN:VEVENT
UID:bla2
DTSTART;VALUE=DATE:20141112
END:VEVENT
BEGIN:VEVENT
UID:bla3
DTSTART;VALUE=DATE:20141112
RECURRENCE-ID;VALUE=DATE:20141112
END:VEVENT
BEGIN:VEVENT
UID:bla3
DTSTART;VALUE=DATE:20141113
RECURRENCE-ID;VALUE=DATE:20141113
END:VEVENT
END:VCALENDAR
ICS;

        $tests[] = [$input, $output, 'America/Argentina/Buenos_Aires', '2014-01-01', '2015-01-01'];

        // Recurrence rule with no valid instances
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
UID:bla6
SUMMARY:Testing RRule3
DTSTART:20111125T120000Z
DTEND:20111125T130000Z
RRULE:FREQ=WEEKLY;COUNT=1
EXDATE:20111125T120000Z
END:VEVENT
END:VCALENDAR
';

        $output = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
END:VCALENDAR
';

        $tests[] = [$input, $output];

        return $tests;
    }

    public function testBrokenEventExpand(): void
    {
        $this->expectException(InvalidDataException::class);
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
RRULE:FREQ=WEEKLY
DTSTART;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';
        $vcal = VObject\Reader::read($input);
        $vcal->expand(
            new \DateTime('2011-12-01'),
            new \DateTime('2011-12-31')
        );
    }

    /**
     * This test used to induce an infinite loop.
     * The "medium" annotation means that phpunit will fail the
     * test if it takes longer than a default of 10 seconds.
     *
     * @medium
     */
    public function testEventExpandYearly(): void
    {
        $input = 'BEGIN:VCALENDAR
BEGIN:VEVENT
UID:1a093f1012086078fdd3d9df5ff4d7d0
DTSTART;TZID=UTC:20210203T130000
DTEND;TZID=UTC:20210203T140000
RRULE:FREQ=YEARLY;COUNT=7;WKST=MO;BYDAY=MO;BYWEEKNO=13,15,50
END:VEVENT
END:VCALENDAR
';
        $vcal = VObject\Reader::read($input);
        $events = $vcal->expand(
            new \DateTime('2021-01-01'),
            new \DateTime('2023-01-01')
        );

        self::assertCount(7, $events->VEVENT);
    }

    public function testGetDocumentType(): void
    {
        $vcard = new VCalendar();
        $vcard->VERSION = '2.0';
        self::assertEquals(VCalendar::ICALENDAR20, $vcard->getDocumentType());
    }

    public function testValidateCorrect(): void
    {
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
PRODID:foo
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
DTSTAMP:20140122T233226Z
UID:foo
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertEquals([], $vcal->validate(), 'Got an error');
    }

    public function testValidateNoVersion(): void
    {
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:foo
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateWrongVersion(): void
    {
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:3.0
PRODID:foo
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateNoProdId(): void
    {
        $input = 'BEGIN:VCALENDAR
CALSCALE:GREGORIAN
VERSION:2.0
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateDoubleCalScale(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
CALSCALE:GREGORIAN
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateDoubleMethod(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
METHOD:REQUEST
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateTwoMasterEvents(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(1, $vcal->validate());
    }

    public function testValidateOneMasterEvent(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
RECURRENCE-ID;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);
        self::assertCount(0, $vcal->validate());
    }

    public function testGetBaseComponent(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VEVENT
SUMMARY:test
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
RECURRENCE-ID;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);

        $result = $vcal->getBaseComponent();
        self::assertEquals('test', $result->SUMMARY->getValue());
    }

    public function testGetBaseComponentNoResult(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VEVENT
SUMMARY:test
RECURRENCE-ID;VALUE=DATE:20111202
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
RECURRENCE-ID;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);

        $result = $vcal->getBaseComponent();
        self::assertNull($result);
    }

    public function testGetBaseComponentWithFilter(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VEVENT
SUMMARY:test
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20111202
UID:foo
DTSTAMP:20140122T234434Z
RECURRENCE-ID;VALUE=DATE:20111202
END:VEVENT
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);

        $result = $vcal->getBaseComponent('VEVENT');
        self::assertEquals('test', $result->SUMMARY->getValue());
    }

    public function testGetBaseComponentWithFilterNoResult(): void
    {
        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foo
METHOD:REQUEST
BEGIN:VTODO
SUMMARY:test
UID:foo
DTSTAMP:20140122T234434Z
END:VTODO
END:VCALENDAR
';

        $vcal = VObject\Reader::read($input);

        $result = $vcal->getBaseComponent('VEVENT');
        self::assertNull($result);
    }

    public function testNoComponents(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:vobject
END:VCALENDAR
ICS;

        self::assertValidate(
            $input,
            0,
            3,
            'An iCalendar object must have at least 1 component.'
        );
    }

    public function testCalDAVNoComponents(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:vobject
BEGIN:VTIMEZONE
TZID:America/Toronto
END:VTIMEZONE
END:VCALENDAR
ICS;

        self::assertValidate(
            $input,
            VCalendar::PROFILE_CALDAV,
            3,
            'A calendar object on a CalDAV server must have at least 1 component (VTODO, VEVENT, VJOURNAL).'
        );
    }

    public function testCalDAVMultiUID(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:vobject
BEGIN:VEVENT
UID:foo
DTSTAMP:20150109T184500Z
DTSTART:20150109T184500Z
END:VEVENT
BEGIN:VEVENT
UID:bar
DTSTAMP:20150109T184500Z
DTSTART:20150109T184500Z
END:VEVENT
END:VCALENDAR
ICS;

        self::assertValidate(
            $input,
            VCalendar::PROFILE_CALDAV,
            3,
            'A calendar object on a CalDAV server may only have components with the same UID.'
        );
    }

    public function testCalDAVMultiComponent(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:vobject
BEGIN:VEVENT
UID:foo
RECURRENCE-ID:20150109T185200Z
DTSTAMP:20150109T184500Z
DTSTART:20150109T184500Z
END:VEVENT
BEGIN:VTODO
UID:foo
DTSTAMP:20150109T184500Z
DTSTART:20150109T184500Z
END:VTODO
END:VCALENDAR
ICS;

        self::assertValidate(
            $input,
            VCalendar::PROFILE_CALDAV,
            3,
            'A calendar object on a CalDAV server may only have 1 type of component (VEVENT, VTODO or VJOURNAL).'
        );
    }

    public function testCalDAVMETHOD(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:PUBLISH
PRODID:vobject
BEGIN:VEVENT
UID:foo
RECURRENCE-ID:20150109T185200Z
DTSTAMP:20150109T184500Z
DTSTART:20150109T184500Z
END:VEVENT
END:VCALENDAR
ICS;

        self::assertValidate(
            $input,
            VCalendar::PROFILE_CALDAV,
            3,
            'A calendar object on a CalDAV server MUST NOT have a METHOD property.'
        );
    }

    public function testValidateEmptyUid(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\nRRULE:FREQ=DAILY;COUNT=3\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame([3], array_column($vcal->validate(VObject\Node::PROFILE_CALDAV), 'level'));
        self::assertSame([3], array_column($vcal->validate(VObject\Node::PROFILE_CALDAV | VObject\Node::REPAIR), 'level'));
    }

    public function testNodeInValidationErrorHasLineIndexAndLineStringProps(): void
    {
        $defectiveInput = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:PUBLISH
PRODID:vobject
BEGIN:VEVENT
UID:foo
CLASS:PUBLIC
DTSTART;VALUE=DATE:19931231
DTSTAMP:20240422T070855Z
CREATED:
LAST-MODIFIED:
DESCRIPTION:bar
END:VEVENT
ICS;

        $vcal = VObject\Reader::read($defectiveInput);
        $result = $vcal->validate();
        $warningMessages = [];
        foreach ($result as $error) {
            $warningMessages[] = $error['message'];
        }
        self::assertCount(2, $result, 'We expected exactly 2 validation messages, instead we got '.count($result).' results:'.implode(', ', $warningMessages));
        foreach ($result as $idx => $warning) {
            self::assertArrayHasKey('node', $warning);
            self::assertInstanceOf(VObject\Property\ICalendar\DateTime::class, $warning['node']);
            self::assertObjectHasProperty('lineIndex', $warning['node']);
            self::assertObjectHasProperty('lineString', $warning['node']);
            switch ($idx) {
                case 0:
                    self::assertEquals('10', $warning['node']->lineIndex);
                    self::assertEquals('CREATED:', $warning['node']->lineString);
                    break;
                case 1:
                    self::assertEquals('11', $warning['node']->lineIndex);
                    self::assertEquals('LAST-MODIFIED:', $warning['node']->lineString);
                    break;
            }
        }
    }

    public function assertValidate($ics, $options, $expectedLevel, ?string $expectedMessage = null): void
    {
        $vcal = VObject\Reader::read($ics);
        $result = $vcal->validate($options);

        self::assertValidateResult($result, $expectedLevel, $expectedMessage);
    }

    public function assertValidateResult($input, $expectedLevel, ?string $expectedMessage = null): void
    {
        $messages = [];
        foreach ($input as $warning) {
            $messages[] = $warning['message'];
        }

        if (0 === $expectedLevel) {
            self::assertCount(0, $input, 'No validation messages were expected. We got: '.implode(', ', $messages));
        } else {
            self::assertCount(1, $input, 'We expected exactly 1 validation message, We got: '.implode(', ', $messages));

            self::assertEquals($expectedMessage, $input[0]['message']);
            self::assertEquals($expectedLevel, $input[0]['level']);
        }
    }

    public function testExpandSingleOverrideWithoutMaster(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:u\r\n"
            ."RECURRENCE-ID:20261110T090000Z\r\nDTSTART:20261110T090000Z\r\nDTEND:20261110T100000Z\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n");
        $expanded = $vcal->expand(new \DateTimeImmutable('2026-11-01T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'));
        self::assertCount(1, $expanded->select('VEVENT'));
        self::assertSame('20261110T090000Z', (string) $expanded->VEVENT->{'RECURRENCE-ID'});
    }

    public function testExpandNonRecurringMasterWithOverride(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fuzz//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:moved\r\nDTSTAMP:20261103T100000Z\r\nRECURRENCE-ID:20260325T100000Z\r\n"
            ."DTSTART:20260326T100000Z\r\nDTEND:20260326T110000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:moved\r\nDTSTAMP:20261103T100000Z\r\n"
            ."DTSTART:20260325T100000Z\r\nDTEND:20260325T110000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:other\r\nDTSTAMP:20261103T100000Z\r\n"
            ."DTSTART:20260327T100000Z\r\nDTEND:20260327T110000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $expanded = $vcal->expand(new \DateTimeImmutable('2026-03-01T00:00:00Z'), new \DateTimeImmutable('2026-04-01T00:00:00Z'));
        $events = [];
        foreach ($expanded->select('VEVENT') as $event) {
            $events[] = $event->UID.' '.$event->DTSTART.' '.$event->{'RECURRENCE-ID'};
        }
        sort($events);
        self::assertSame([
            'moved 20260326T100000Z 20260325T100000Z',
            'other 20260327T100000Z ',
        ], $events);
    }

    public function testExpandKeepsEveryMasterOfAUid(): void
    {
        $vcal = VObject\Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
BEGIN:VEVENT
UID:shared
DTSTAMP:20260101T000000Z
DTSTART:20260105T100000Z
DURATION:PT1H
RRULE:FREQ=DAILY;COUNT=3
END:VEVENT
BEGIN:VEVENT
UID:shared
DTSTAMP:20260101T000000Z
RECURRENCE-ID:20260106T100000Z
DTSTART:20260106T120000Z
DURATION:PT1H
END:VEVENT
BEGIN:VEVENT
UID:shared
DTSTAMP:20260101T000000Z
DTSTART:20260120T100000Z
DURATION:PT1H
RRULE:FREQ=DAILY;COUNT=2
END:VEVENT
END:VCALENDAR
ICS);
        $expanded = $vcal->expand(new \DateTime('2026-01-01'), new \DateTime('2026-02-11'));
        $starts = [];
        foreach ($expanded->select('VEVENT') as $event) {
            $starts[] = (string) $event->DTSTART;
        }
        self::assertSame([
            '20260105T100000Z',
            '20260106T120000Z',
            '20260107T100000Z',
            '20260120T100000Z',
            '20260121T100000Z',
        ], $starts);
    }

    public function testExpandStripsRecurrenceFromOverrides(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fuzz//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:weekly\r\nDTSTAMP:20261103T100000Z\r\nDTSTART:20260325T100000Z\r\n"
            ."DURATION:PT1H\r\nRRULE:FREQ=WEEKLY;COUNT=4\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:weekly\r\nDTSTAMP:20261103T100000Z\r\nRECURRENCE-ID:20260401T100000Z\r\n"
            ."DTSTART:20260402T100000Z\r\nDURATION:PT1H\r\nRRULE:FREQ=DAILY;COUNT=3\r\nEXDATE:20260403T100000Z\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n");
        $expanded = $vcal->expand(new \DateTimeImmutable('2026-03-01T00:00:00Z'), new \DateTimeImmutable('2026-05-01T00:00:00Z'));
        self::assertCount(4, $expanded->select('VEVENT'));
        foreach ($expanded->select('VEVENT') as $vevent) {
            self::assertFalse(isset($vevent->RRULE) || isset($vevent->RDATE) || isset($vevent->EXDATE) || isset($vevent->EXRULE));
        }
        self::assertSame('20260402T100000Z', (string) $expanded->VEVENT[1]->DTSTART);
        self::assertSame('20260401T100000Z', (string) $expanded->VEVENT[1]->{'RECURRENCE-ID'});
        // The stored override is unchanged.
        self::assertTrue(isset($vcal->VEVENT[1]->RRULE));
    }

    public function testDtEndBeforeDtStartIsFoundAtItsStart(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fuzz//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:neg\r\nDTSTAMP:20261103T100000Z\r\n"
            ."DTSTART:20260330T010000Z\r\nDTEND:20260130T020000Z\r\nRRULE:FREQ=DAILY;COUNT=1\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:single\r\nDTSTAMP:20261103T100000Z\r\n"
            ."DTSTART:20260330T010000Z\r\nDTEND:20260130T020000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:override\r\nDTSTAMP:20261103T100000Z\r\n"
            ."DTSTART:20260301T010000Z\r\nDURATION:PT1H\r\nRRULE:FREQ=DAILY;COUNT=3\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:override\r\nDTSTAMP:20261103T100000Z\r\nRECURRENCE-ID:20260302T010000Z\r\n"
            ."DTSTART:20260330T010000Z\r\nDTEND:20260130T020000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $week = [new \DateTimeImmutable('2026-03-29T00:00:00Z'), new \DateTimeImmutable('2026-04-05T00:00:00Z')];
        $february = [new \DateTimeImmutable('2026-01-11T17:00:00Z'), new \DateTimeImmutable('2026-02-13T14:04:56Z')];

        foreach ($vcal->VEVENT as $vevent) {
            if (!isset($vevent->{'RECURRENCE-ID'})) {
                self::assertSame('override' !== (string) $vevent->UID, $vevent->isInTimeRange(...$week), (string) $vevent->UID);
                self::assertFalse($vevent->isInTimeRange(...$february), (string) $vevent->UID);
            }
        }

        $uids = [];
        foreach ($vcal->expand(...$week)->VEVENT as $vevent) {
            $uids[] = (string) $vevent->UID;
        }
        self::assertSame(['single', 'neg', 'override'], $uids);
        self::assertCount(0, $vcal->expand(...$february)->select('VEVENT'));
    }

    public function testExpandDoesNotReturnAnEndBeforeTheStart(): void
    {
        $head = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fuzz//EN\r\n";
        $stamp = "DTSTAMP:20261103T100000Z\r\n";
        $vcal = VObject\Reader::read($head
            ."BEGIN:VEVENT\r\nUID:single\r\n$stamp"
            ."DTSTART:20260410T090000Z\r\nDTEND:20260105T100000Z\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:negative\r\n$stamp"
            ."DTSTART:20260410T090000Z\r\nDURATION:-P100D\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:series\r\n$stamp"
            ."DTSTART:20260410T090000Z\r\nDTEND:20260410T100000Z\r\nRRULE:FREQ=DAILY;COUNT=2\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:series\r\n$stamp"
            ."RECURRENCE-ID:20260411T090000Z\r\nDTSTART:20260411T090000Z\r\nDTEND:20260105T100000Z\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n");

        $expanded = $vcal->expand(new \DateTimeImmutable('2026-01-11T17:00:00Z'), new \DateTimeImmutable('2026-05-22T17:00:00Z'));

        self::assertCount(4, $expanded->select('VEVENT'));
        foreach ($expanded->select('VEVENT') as $vevent) {
            if ('negative' === (string) $vevent->UID) {
                self::assertSame('PT0S', (string) $vevent->DURATION);
                continue;
            }
            self::assertGreaterThanOrEqual(
                $vevent->DTSTART->getDateTime(),
                $vevent->DTEND->getDateTime(),
                (string) $vevent->UID
            );
        }
    }

    #[DataProvider('eventsEndingInARepeatedHour')]
    public function testExpandKeepsEndInRepeatedHour(string $dtStart, string $dtEnd, string $expectedStart, string $expectedEnd): void
    {
        $vcal = VObject\Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\n"
            ."DTSTART;TZID=America/New_York:$dtStart\r\nDTEND;TZID=America/New_York:$dtEnd\r\n"
            ."RRULE:FREQ=DAILY;COUNT=3\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $expanded = $vcal->expand(
            new \DateTimeImmutable('2026-11-01T06:00:00Z'),
            new \DateTimeImmutable('2026-11-01T12:00:00Z')
        );

        self::assertSame($expectedStart, (string) $expanded->VEVENT->DTSTART);
        self::assertSame($expectedEnd, (string) $expanded->VEVENT->DTEND);
    }

    public static function eventsEndingInARepeatedHour(): array
    {
        return [
            'one hour' => ['20261030T011500', '20261030T021500', '20261101T051500Z', '20261101T061500Z'],
            'two hours' => ['20261030T003000', '20261030T023000', '20261101T043000Z', '20261101T063000Z'],
            'thirty minutes, end read before its start' => ['20261030T014500', '20261030T021500', '20261101T054500Z', '20261101T061500Z'],
        ];
    }

    /**
     * @return array<string, array{string, string, string, string, int}>
     */
    public static function windowsInTheReferenceZone(): array
    {
        return [
            'an all-day instance that has ended in the zone' => ['DTSTART;VALUE=DATE:20260411', 'Europe/Paris', '2026-04-11T23:00:00', '2026-09-01T23:00:00', 0],
            'a floating instance at the end of the window' => ["DTSTART:20261126T183000\r\nDURATION:PT10M", 'Europe/Paris', '2026-04-11T23:00:00', '2026-11-26T18:00:00', 1],
        ];
    }

    /**
     * What a CalDAV server has to do to answer a calendar-query with expand:
     * with the window in the zone of the calendar, the filter and the
     * expansion agree (RFC 4791 sections 7.3 and 9.9).
     */
    #[DataProvider('windowsInTheReferenceZone')]
    public function testTimeRangeFilterAgreesWithExpandInTheReferenceZone(string $lines, string $zone, string $start, string $end, int $instances): void
    {
        $utc = new \DateTimeZone('UTC');
        $timeZone = new \DateTimeZone($zone);
        $start = new \DateTimeImmutable($start, $utc);
        $end = new \DateTimeImmutable($end, $utc);
        $vcal = VObject\Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//y//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\n$lines\r\nRRULE:FREQ=DAILY;COUNT=1\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        self::assertSame($instances > 0, $vcal->VEVENT->isInTimeRange($start->setTimezone($timeZone), $end->setTimezone($timeZone)));
        self::assertCount($instances, $vcal->expand($start, $end, $timeZone)->select('VEVENT'));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function nonRecurringEventsAtTheEdgeOfTheWindow(): array
    {
        return [
            // 17:30 in New York is 22:30Z, after the end of the window.
            'floating, after the window in the zone' => ["DTSTART:20261126T173000\r\nDURATION:PT10M", 'America/New_York', 0],
            // 18:30 in Paris is 17:30Z, inside the window.
            'floating, in the window in the zone' => ["DTSTART:20261126T183000\r\nDURATION:PT10M", 'Europe/Paris', 1],
            // This day starts on 11-26 at 10:00Z in Kiritimati, inside the window.
            'all-day, in the window in the zone' => ['DTSTART;VALUE=DATE:20261127', 'Pacific/Kiritimati', 1],
            // This day ends on 04-11 at 10:00Z in Kiritimati, before the window.
            'all-day, before the window in the zone' => ['DTSTART;VALUE=DATE:20260411', 'Pacific/Kiritimati', 0],
            'with a time zone, whatever the zone' => ["DTSTART:20261126T173000Z\r\nDURATION:PT10M", 'America/New_York', 1],
        ];
    }

    #[DataProvider('nonRecurringEventsAtTheEdgeOfTheWindow')]
    public function testExpandSelectsNonRecurringEventInTheReferenceZone(string $lines, string $zone, int $instances): void
    {
        $utc = new \DateTimeZone('UTC');
        $vcal = VObject\Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//y//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\n$lines\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $result = $vcal->expand(
            new \DateTimeImmutable('2026-04-11T18:00:00', $utc),
            new \DateTimeImmutable('2026-11-26T18:00:00', $utc),
            new \DateTimeZone($zone)
        );

        self::assertCount($instances, $result->select('VEVENT'));
    }

    public function testExpandRecurringEventWithUidZero(): void
    {
        $vcal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//fuzz//EN\r\nBEGIN:VEVENT\r\nUID:0\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260106T080000Z\r\nRRULE:FREQ=DAILY;COUNT=2\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:0\r\nDTSTAMP:20260101T000000Z\r\nRECURRENCE-ID:20260107T080000Z\r\nDTSTART:20260107T090000Z\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n");

        $expanded = $vcal->expand(new \DateTimeImmutable('2026-01-01T00:00:00Z'), new \DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertSame(['20260106T080000Z', '20260107T090000Z'], array_map(fn ($event) => (string) $event->DTSTART, $expanded->select('VEVENT')));
    }
}
