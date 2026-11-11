<?php

namespace Sabre\VObject\ITip;

use PHPUnit\Framework\Attributes\DataProvider;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Node;
use Sabre\VObject\Reader;

class BrokerProcessReplyTest extends BrokerTester
{
    public function testReplyNoOriginal(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $this->process($itip, $old, $expected);
    }

    public function testReplyAccept(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE;PARTSTAT=ACCEPTED;SCHEDULE-STATUS=2.0:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyWithTz(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VTIMEZONE
TZID:(UTC+01:00) Brussels\, Copenhagen\, Madrid\, Paris
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
UID:foobar
DTSTART;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T120000Z
DTEND;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:(UTC+01:00) Brussels\, Copenhagen\, Madrid\, Paris
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
DTSTART;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T120000Z
DTEND;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:(UTC+01:00) Brussels\, Copenhagen\, Madrid\, Paris
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE;PARTSTAT=ACCEPTED;SCHEDULE-STATUS=2.0:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
DTSTART;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T120000Z
DTEND;TZID="(UTC+01:00) Brussels, Copenhagen, Madrid, Paris":20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyRequestStatus(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
UID:foobar
REQUEST-STATUS:2.3;foo-bar!
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ATTENDEE;PARTSTAT=ACCEPTED;SCHEDULE-STATUS=2.3:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyPartyCrasher(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:crasher@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
ATTENDEE;PARTSTAT=ACCEPTED:mailto:crasher@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $broker = new Broker();
        $broker->acceptPartyCrashers = true;
        $this->process($itip, $old, $expected, $broker);
    }

    public function testReplyExistingExceptionRecurrenceIdInUTC(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140725T040000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART;TZID=America/Toronto:20140725T000000
DTEND;TZID=America/Toronto:20140725T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART;TZID=America/Toronto:20140725T000000
DTEND;TZID=America/Toronto:20140725T010000
ATTENDEE;PARTSTAT=ACCEPTED;SCHEDULE-STATUS=2.0:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyNewException(): void
    {
        // This is a reply to 1 instance of a recurring event. This should
        // automatically create an exception.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140725T000000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART:20140725T000000Z
DTEND:20140725T010000Z
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID:20140725T000000Z
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyNewExceptionTz(): void
    {
        // This is a reply to 1 instance of a recurring event. This should
        // automatically create an exception.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART;TZID=America/Toronto:20140725T000000
DTEND;TZID=America/Toronto:20140725T010000
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyNewExceptionRecurrenceIdInDifferentTz(): void
    {
        // This is a reply to 1 instance of a recurring event. This should
        // automatically create an exception.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID;TZID=Asia/Ho_Chi_Minh:20140725T110000
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART;TZID=America/Toronto:20140725T000000
DTEND;TZID=America/Toronto:20140725T010000
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyNewExceptionRecurrenceIdInUTC(): void
    {
        // This is a reply to 1 instance of a recurring event. This should
        // automatically create an exception.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140725T040000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART;TZID=America/Toronto:20140724T000000
DTEND;TZID=America/Toronto:20140724T010000
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART;TZID=America/Toronto:20140725T000000
DTEND;TZID=America/Toronto:20140725T010000
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID;TZID=America/Toronto:20140725T000000
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyPartyCrashCreateException(): void
    {
        // IN this test there's a recurring event that has an exception. The
        // exception is missing the attendee.
        //
        // The attendee party crashes the instance, so it should show up in the
        // resulting object.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED;CN=Crasher!:mailto:crasher@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140725T000000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART:20140725T000000Z
DTEND:20140725T010000Z
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID:20140725T000000Z
ATTENDEE;PARTSTAT=ACCEPTED;CN=Crasher!:mailto:crasher@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $broker = new Broker();
        $broker->acceptPartyCrashers = true;
        $this->process($itip, $old, $expected, $broker);
    }

    public function testReplyNewExceptionNoMasterEvent(): void
    {
        /**
         * This iTip message would normally create a new exception, but the
         * server is not able to create this new instance, because there's no
         * master event to clone from.
         *
         * This test checks if the message is ignored.
         */
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED;CN=Crasher!:mailto:crasher@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140725T000000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
RECURRENCE-ID:20140724T000000Z
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = null;
        $this->process($itip, $old, $expected);
    }

    /**
     * @depends testReplyAccept
     */
    public function testReplyAcceptUpdateRSVP(): void
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE;RSVP=TRUE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
ATTENDEE;PARTSTAT=ACCEPTED;SCHEDULE-STATUS=2.0:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    public function testReplyNewExceptionFirstOccurrence(): void
    {
        // This is a reply to 1 instance of a recurring event. This should
        // automatically create an exception.
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
SEQUENCE:2
RECURRENCE-ID:20140724T000000Z
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
RRULE:FREQ=DAILY
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ATTENDEE:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
END:VEVENT
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
DTSTART:20140724T000000Z
DTEND:20140724T010000Z
ATTENDEE;PARTSTAT=ACCEPTED:mailto:foo@example.org
ORGANIZER:mailto:bar@example.org
RECURRENCE-ID:20140724T000000Z
END:VEVENT
END:VCALENDAR
ICS;

        $this->process($itip, $old, $expected);
    }

    #[DataProvider('invalidReplyValues')]
    public function testReplyDoesNotStoreInvalidValues(string $replyLines, string $expectedStatus): void
    {
        $stored = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:u1\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260106T100000Z\r\nORGANIZER:mailto:o@example.com\r\n"
            ."ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:a@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $message = new Message();
        $message->uid = 'u1';
        $message->component = 'VEVENT';
        $message->method = 'REPLY';
        $message->sequence = 1;
        $message->sender = 'mailto:a@example.com';
        $message->recipient = 'mailto:o@example.com';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REPLY\r\nBEGIN:VEVENT\r\nUID:u1\r\n".$replyLines."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $result = (new Broker())->processMessage($message, $stored);

        self::assertInstanceOf(VCalendar::class, $result);
        self::assertSame([], array_filter($result->validate(Node::PROFILE_CALDAV), fn ($m) => 3 === $m['level']));
        self::assertIsString(json_encode($result->jsonSerialize(), JSON_THROW_ON_ERROR));
        self::assertSame($expectedStatus, (string) $result->VEVENT->ATTENDEE['PARTSTAT']);
    }

    public static function invalidReplyValues(): array
    {
        return [
            'quoted-printable REQUEST-STATUS' => ["ATTENDEE;PARTSTAT=ACCEPTED:mailto:a@example.com\r\nREQUEST-STATUS;ENCODING=QUOTED-PRINTABLE:2=FF0;Success\r\n", 'ACCEPTED'],
            'raw REQUEST-STATUS' => ["ATTENDEE;PARTSTAT=ACCEPTED:mailto:a@example.com\r\nREQUEST-STATUS:2\xFF0;Success\r\n", 'ACCEPTED'],
            'raw PARTSTAT' => ["ATTENDEE;PARTSTAT=ACC\xFFEPTED:mailto:a@example.com\r\n", 'NEEDS-ACTION'],
            'PARTSTAT that is not a token' => ["ATTENDEE;PARTSTAT=\"ACCEPTED\$x\":mailto:a@example.com\r\n", 'NEEDS-ACTION'],
        ];
    }

    public function testReplyKeepsDefaultScheduleStatusForInvalidRequestStatus(): void
    {
        $stored = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:u1\r\nDTSTART:20260106T100000Z\r\n"
            ."ORGANIZER:mailto:o@example.com\r\nATTENDEE:mailto:a@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        $message = new Message();
        $message->uid = 'u1';
        $message->component = 'VEVENT';
        $message->method = 'REPLY';
        $message->sender = 'mailto:a@example.com';
        $message->recipient = 'mailto:o@example.com';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REPLY\r\nBEGIN:VEVENT\r\nUID:u1\r\n"
            ."ATTENDEE;PARTSTAT=ACCEPTED:mailto:a@example.com\r\nREQUEST-STATUS:nope;x\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $result = (new Broker())->processMessage($message, $stored);

        self::assertInstanceOf(VCalendar::class, $result);
        self::assertSame('2.0', (string) $result->VEVENT->ATTENDEE['SCHEDULE-STATUS']);
    }

    private const CRASHED = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSEQUENCE:2\r\nUID:foobar\r\n"
        ."RRULE:FREQ=DAILY;COUNT=5\r\nDTSTART:20140724T000000Z\r\nDTEND:20140724T010000Z\r\n"
        ."ORGANIZER:mailto:bar@example.org\r\nATTENDEE:mailto:foo@example.org\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nSEQUENCE:2\r\nUID:foobar\r\nRECURRENCE-ID:20140726T000000Z\r\n"
        ."DTSTART:20140726T000000Z\r\nDTEND:20140726T010000Z\r\n"
        ."ORGANIZER:mailto:bar@example.org\r\nATTENDEE:mailto:foo@example.org\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private static function reply(string $sender, string $recurrenceId): Message
    {
        $message = new Message();
        $message->method = 'REPLY';
        $message->component = 'VEVENT';
        $message->uid = 'foobar';
        $message->sender = $sender;
        $message->senderName = 'Foo';
        $message->recipient = 'mailto:bar@example.org';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REPLY\r\nBEGIN:VEVENT\r\nUID:foobar\r\nSEQUENCE:2\r\n$recurrenceId"
            ."ORGANIZER:mailto:bar@example.org\r\nATTENDEE;PARTSTAT=ACCEPTED;CN=Foo:$sender\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        return $message;
    }

    public static function crashedInstances(): array
    {
        return [
            'master' => [''],
            'existing override' => ["RECURRENCE-ID:20140726T000000Z\r\n"],
            'new override' => ["RECURRENCE-ID:20140725T000000Z\r\n"],
        ];
    }

    /**
     * By default a REPLY from someone who is not an attendee must not add
     * them to the organizer's copy.
     */
    #[DataProvider('crashedInstances')]
    public function testReplyFromAPartyCrasherIsRefusedByDefault(string $recurrenceId): void
    {
        $stored = Reader::read(self::CRASHED);
        $before = $stored->serialize();

        try {
            (new Broker())->processMessage(self::reply('mailto:crasher@example.org', $recurrenceId), $stored);
            self::fail('The REPLY of a party crasher was accepted.');
        } catch (ITipException) {
        }

        self::assertSame($before, $stored->serialize());
    }

    #[DataProvider('crashedInstances')]
    public function testReplyFromAnAttendeeInAnotherCaseIsApplied(string $recurrenceId): void
    {
        $result = (new Broker())->processMessage(self::reply('MAILTO:Foo@Example.org', $recurrenceId), Reader::read(self::CRASHED));

        self::assertInstanceOf(VCalendar::class, $result);
        $attendees = [];
        foreach ($result->select('VEVENT') as $vevent) {
            foreach ($vevent->select('ATTENDEE') as $attendee) {
                $attendees[] = $attendee->getValue().' '.$attendee['PARTSTAT'];
            }
        }
        self::assertContains('mailto:foo@example.org ACCEPTED', $attendees);
        foreach ($attendees as $attendee) {
            self::assertStringStartsWith('mailto:foo@example.org', $attendee);
        }
    }

    public function testReplyToSeriesWithoutInstances(): void
    {
        $stored = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
BEGIN:VEVENT
UID:u1
DTSTAMP:20260101T000000Z
DTSTART:20260105T090000Z
DTEND:20260105T100000Z
RRULE:FREQ=DAILY;COUNT=2
EXDATE:20260105T090000Z,20260106T090000Z
SUMMARY:Daily
ORGANIZER:mailto:o@example.com
ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:a@example.com
END:VEVENT
END:VCALENDAR
ICS);
        self::assertInstanceOf(VCalendar::class, $stored);
        $before = $stored->serialize();

        $message = new Message();
        $message->method = 'REPLY';
        $message->component = 'VEVENT';
        $message->uid = 'u1';
        $message->sender = 'mailto:a@example.com';
        $message->recipient = 'mailto:o@example.com';
        $message->message = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
METHOD:REPLY
BEGIN:VEVENT
UID:u1
DTSTAMP:20260101T000000Z
RECURRENCE-ID:20260105T090000Z
ORGANIZER:mailto:o@example.com
ATTENDEE;PARTSTAT=ACCEPTED:mailto:a@example.com
END:VEVENT
END:VCALENDAR
ICS);

        $result = (new Broker())->processMessage($message, $stored);

        // The replied instance does not exist: it is skipped, nothing changes.
        self::assertInstanceOf(VCalendar::class, $result);
        self::assertSame($before, $result->serialize());
    }
}
