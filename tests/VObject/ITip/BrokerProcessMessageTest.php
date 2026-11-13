<?php

namespace Sabre\VObject\ITip;

use Sabre\VObject\Reader;

class BrokerProcessMessageTest extends BrokerTester
{
    public function testRequestNew(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REQUEST
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, null, $expected);
    }

    public function testRequestUpdate(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REQUEST
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ORGANIZER:mailto:org@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
ORGANIZER:mailto:org@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ORGANIZER:mailto:org@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testCancel(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:CANCEL
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ORGANIZER:mailto:org@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
ORGANIZER:mailto:org@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
UID:foobar
ORGANIZER:mailto:org@example.org
STATUS:CANCELLED
SEQUENCE:2
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    /**
     * The CANCEL that the organizer sends to a removed attendee of an event
     * without SEQUENCE has no sequence: the cancelled copy of the attendee
     * gets the default sequence 0, and can be serialized.
     */
    public function testCancelWithoutSequence(): void
    {
        $organizerOld = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20351005T090000Z
ORGANIZER:mailto:strunk@example.org
ATTENDEE:mailto:one@example.org
ATTENDEE:mailto:two@example.org
END:VEVENT
END:VCALENDAR
ICS);
        $organizerNew = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20351005T090000Z
ORGANIZER:mailto:strunk@example.org
ATTENDEE:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS);
        $attendeeCopy = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20351005T090000Z
ORGANIZER:mailto:strunk@example.org
ATTENDEE:mailto:one@example.org
ATTENDEE:mailto:two@example.org
END:VEVENT
END:VCALENDAR
ICS);

        $broker = new Broker();
        $cancels = array_values(array_filter(
            $broker->parseEvent($organizerNew, 'mailto:strunk@example.org', $organizerOld),
            fn (Message $message) => 'CANCEL' === $message->method
        ));
        self::assertCount(1, $cancels);
        self::assertSame('mailto:two@example.org', $cancels[0]->recipient);
        self::assertNull($cancels[0]->sequence);

        $result = $broker->processMessage($cancels[0], $attendeeCopy);

        self::assertSame('CANCELLED', (string) $result->VEVENT->STATUS);
        self::assertSame(0, $result->VEVENT->SEQUENCE->getValue());
        self::assertStringContainsString("SEQUENCE:0\r\n", $result->serialize());
    }

    public function testCancelNoExistingEvent(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:CANCEL
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $this->process($itip, $old, $expected);
    }

    public function testCancelInstanceOfRecurring(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
METHOD:CANCEL
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID;TZID=Romance Standard Time:20180320T160000
SUMMARY:CANCEL
DTSTART;TZID=Romance Standard Time:20180320T160000
DTEND;TZID=Romance Standard Time:20180320T163000
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART;TZID=Romance Standard Time:20180319T160000
DTEND;TZID=Romance Standard Time:20180319T163000
DTSTAMP:20180316T132615Z
SEQUENCE:0
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART;TZID=Romance Standard Time:20180319T160000
DTEND;TZID=Romance Standard Time:20180319T163000
DTSTAMP:20180316T132615Z
SEQUENCE:0
EXDATE;TZID=Romance Standard Time:20180320T160000
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testCancelExistingInstanceOfRecurring(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
METHOD:CANCEL
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:CANCEL
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:CANCEL
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART:20180319T160000Z
DTEND:20180319T163000Z
DTSTAMP:20180316T132615Z
SEQUENCE:0
END:VEVENT
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:Modified Test
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:0
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART:20180319T160000Z
DTEND:20180319T163000Z
DTSTAMP:20180316T132615Z
SEQUENCE:0
EXDATE:20180320T160000Z
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testUnsupportedComponent(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SEQUENCE:2
UID:foobar
END:VTODO
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $this->process($itip, $old, $expected);
    }

    public function testUnsupportedMethod(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:PUBLISH
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $this->process($itip, $old, $expected);
    }

    private static function cancelInstance(string $stored, string $recurrenceId): \Sabre\VObject\Component\VCalendar
    {
        $message = new Message();
        $message->method = 'CANCEL';
        $message->component = 'VEVENT';
        $message->uid = 'u1';
        $message->sequence = 2;
        $message->sender = 'mailto:o@example.com';
        $message->recipient = 'mailto:a@example.com';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:CANCEL\r\nBEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260102T000000Z\r\nSEQUENCE:2\r\n"
            ."ORGANIZER:mailto:o@example.com\r\nATTENDEE:mailto:a@example.com\r\n$recurrenceId\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        return (new Broker())->processMessage($message, Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\n$stored\r\n"
            ."ORGANIZER:mailto:o@example.com\r\nATTENDEE:mailto:a@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        ));
    }

    public function testCancelInstanceKeepsTheDatesOfEveryExdateLine(): void
    {
        $result = self::cancelInstance(
            "DTSTART:20260105T100000Z\r\nRRULE:FREQ=DAILY;COUNT=5\r\nEXDATE:20260106T100000Z\r\nEXDATE:20260107T100000Z",
            'RECURRENCE-ID:20260108T100000Z'
        );

        $excluded = [];
        foreach ($result->VEVENT->select('EXDATE') as $exDate) {
            foreach ($exDate->getDateTimes() as $dateTime) {
                $excluded[] = $dateTime->format('Ymd\THis\Z');
            }
        }
        sort($excluded);

        self::assertSame(['20260106T100000Z', '20260107T100000Z', '20260108T100000Z'], $excluded);
    }

    public function testCancelInstanceOfAnAllDaySeriesKeepsDateExdates(): void
    {
        $result = self::cancelInstance(
            "DTSTART;VALUE=DATE:20260105\r\nRRULE:FREQ=DAILY;COUNT=5\r\nEXDATE;VALUE=DATE:20260106\r\nEXDATE;VALUE=DATE:20260107",
            'RECURRENCE-ID;VALUE=DATE:20260108'
        );

        self::assertSame('DATE', (string) $result->VEVENT->EXDATE['VALUE']);
        self::assertSame(['20260106', '20260107', '20260108'], $result->VEVENT->EXDATE->getParts());
    }

    private const STORED_BY_ORGANIZER = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\n"
        ."DTSTART:20260105T100000Z\r\nSUMMARY:Board meeting\r\nORGANIZER:mailto:o@example.com\r\n"
        ."ATTENDEE:mailto:a@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private static function organizerMessage(string $method, string $sender, string $organizer = 'mailto:o@example.com'): Message
    {
        $message = new Message();
        $message->method = $method;
        $message->component = 'VEVENT';
        $message->uid = 'u1';
        $message->sequence = 2;
        $message->sender = $sender;
        $message->recipient = 'mailto:a@example.com';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:$method\r\nBEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260102T000000Z\r\nSEQUENCE:2\r\n"
            ."DTSTART:20260106T100000Z\r\nSUMMARY:Moved\r\nORGANIZER:$organizer\r\nATTENDEE:mailto:a@example.com\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        return $message;
    }

    public static function organizerMethods(): array
    {
        return ['REQUEST' => ['REQUEST'], 'CANCEL' => ['CANCEL']];
    }

    /**
     * Anyone knowing the UID of an event must not rewrite or cancel the copy
     * of it that someone else organizes.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('organizerMethods')]
    public function testOrganizerMessageFromAnotherSenderIsRefused(string $method): void
    {
        $stored = Reader::read(self::STORED_BY_ORGANIZER);
        $before = $stored->serialize();

        try {
            (new Broker())->processMessage(self::organizerMessage($method, 'mailto:m@example.com', 'mailto:m@example.com'), $stored);
            self::fail('The '.$method.' of a sender who is not the organizer was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('organizerMethods')]
    public function testOrganizerMessageOntoAStoredEventWithoutOrganizerIsRefused(string $method): void
    {
        $stored = Reader::read(str_replace("ORGANIZER:mailto:o@example.com\r\n", '', self::STORED_BY_ORGANIZER));
        $before = $stored->serialize();

        try {
            (new Broker())->processMessage(self::organizerMessage($method, 'mailto:o@example.com'), $stored);
            self::fail('The '.$method.' onto an event without organizer was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('organizerMethods')]
    public function testOrganizerMessageWhoseOrganizerIsNotItsSenderIsRefused(string $method): void
    {
        $stored = Reader::read(self::STORED_BY_ORGANIZER);
        $before = $stored->serialize();

        try {
            (new Broker())->processMessage(self::organizerMessage($method, 'mailto:o@example.com', 'mailto:m@example.com'), $stored);
            self::fail('The '.$method.' whose ORGANIZER is not its sender was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('organizerMethods')]
    public function testOrganizerMessageFromTheOrganizerInAnotherCaseIsApplied(string $method): void
    {
        $result = (new Broker())->processMessage(
            self::organizerMessage($method, 'MAILTO:O@Example.com'),
            Reader::read(self::STORED_BY_ORGANIZER)
        );

        self::assertInstanceOf(\Sabre\VObject\Component\VCalendar::class, $result);
        if ('REQUEST' === $method) {
            self::assertSame('Moved', (string) $result->VEVENT->SUMMARY);
        } else {
            self::assertSame('CANCELLED', (string) $result->VEVENT->STATUS);
        }
    }

    public static function storedCopiesOfTheOrganizer(): array
    {
        return [
            'attendee copy' => [self::STORED_BY_ORGANIZER],
            'organizer copy' => [str_replace('ATTENDEE:mailto:a@example.com', "ATTENDEE;PARTSTAT=ACCEPTED:mailto:a@example.com\r\nATTENDEE:mailto:b@example.com", self::STORED_BY_ORGANIZER)],
        ];
    }

    /**
     * Anyone may list the stored organizer as an attendee of their own
     * REQUEST: that does not let them take the event over.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('storedCopiesOfTheOrganizer')]
    public function testRequestListingTheStoredOrganizerAsAttendeeIsRefused(string $storedData): void
    {
        $stored = Reader::read($storedData);
        $before = $stored->serialize();
        $message = self::organizerMessage('REQUEST', 'mailto:m@example.com', 'mailto:m@example.com');
        $message->message->VEVENT->add('ATTENDEE', 'mailto:O@example.com');

        try {
            (new Broker())->processMessage($message, $stored);
            self::fail('The REQUEST of a sender who is not the organizer was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    public function testCancelFromANewOrganizerIsRefusedEvenWithTheFormerOneAsAttendee(): void
    {
        $stored = Reader::read(self::STORED_BY_ORGANIZER);
        $before = $stored->serialize();
        $message = self::organizerMessage('CANCEL', 'mailto:m@example.com', 'mailto:m@example.com');
        $message->message->VEVENT->add('ATTENDEE', 'mailto:o@example.com');

        try {
            (new Broker())->processMessage($message, $stored);
            self::fail('The CANCEL of a sender who is not the organizer was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    public static function storedObjectsThatAreNotEvents(): array
    {
        $cases = [];
        foreach (['REQUEST', 'CANCEL'] as $method) {
            $cases[$method.' onto a VTODO'] = [$method, "BEGIN:VTODO\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\nSUMMARY:My task\r\nEND:VTODO\r\n"];
            $cases[$method.' onto a VJOURNAL'] = [$method, "BEGIN:VJOURNAL\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\nSUMMARY:My notes\r\nEND:VJOURNAL\r\n"];
            $cases[$method.' onto a VEVENT and a VTODO'] = [$method, "BEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\n"
                ."DTSTART:20260105T100000Z\r\nORGANIZER:mailto:o@example.com\r\nATTENDEE:mailto:a@example.com\r\nEND:VEVENT\r\n"
                ."BEGIN:VTODO\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\nSUMMARY:My task\r\nEND:VTODO\r\n", ];
        }

        return $cases;
    }

    /**
     * The stored copy is looked up by UID alone: a task or journal sharing the
     * UID of a forged event must not be replaced by it (security review SEV-1
     * on 6c2091f9).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('storedObjectsThatAreNotEvents')]
    public function testOrganizerMessageOntoAStoredObjectThatIsNotOnlyEventsIsRefused(string $method, string $components): void
    {
        $stored = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$components."END:VCALENDAR\r\n");
        $before = $stored->serialize();

        try {
            (new Broker())->processMessage(self::organizerMessage($method, 'mailto:o@example.com'), $stored);
            self::fail('The '.$method.' onto a stored object that is not only events was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    public function testRequestFromASenderWithoutSchemeIsApplied(): void
    {
        $result = (new Broker())->processMessage(
            self::organizerMessage('REQUEST', 'o@example.com'),
            Reader::read(self::STORED_BY_ORGANIZER)
        );

        self::assertSame('Moved', (string) $result->VEVENT->SUMMARY);
    }
}
