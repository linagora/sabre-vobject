<?php

namespace Sabre\VObject\ITip;

use Sabre\VObject\Version;

class BrokerDeleteEventTest extends BrokerTester
{
    public function testOrganizerDeleteWithDtend(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],

            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testOrganizerDeleteWithDuration(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DURATION:PT1H
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DURATION:PT1H
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],

            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DURATION:PT1H
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testAttendeeDeleteWithDtend(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REPLY',
                'component' => 'VEVENT',
                'sender' => 'mailto:one@example.org',
                'senderName' => 'One',
                'recipient' => 'mailto:strunk@example.org',
                'recipientName' => 'Strunk',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REPLY
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:1
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;PARTSTAT=DECLINED;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:one@example.org');
    }

    public function testAttendeeReplyWithDuration(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DURATION:PT1H
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REPLY',
                'component' => 'VEVENT',
                'sender' => 'mailto:one@example.org',
                'senderName' => 'One',
                'recipient' => 'mailto:strunk@example.org',
                'recipientName' => 'Strunk',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REPLY
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:1
DTSTART:20140716T120000Z
DURATION:PT1H
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;PARTSTAT=DECLINED;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:one@example.org');
    }

    public function testAttendeeDeleteCancelledEvent(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
STATUS:CANCELLED
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;

        $expected = [];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:one@example.org');
    }

    public function testAttendeeDeleteWithoutOrganizer(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $this->parse($oldMessage, null, [], 'mailto:one@example.org');
    }

    public function testOrganizerDeleteEventWithAttendeeAndAlarm(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
ATTENDEE:mailto:one@example.org
DESCRIPTION:Breakfast meeting
END:VALARM
DTSTART:20140716T120000Z
DURATION:PT1H
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = null;
        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DURATION:PT1H
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
ATTENDEE:mailto:one@example.org
DESCRIPTION:Breakfast meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testNoCalendar(): void
    {
        $this->parse(null, null, [], 'mailto:one@example.org');
    }

    public function testVTodo(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:foobar
SEQUENCE:1
END:VTODO
END:VCALENDAR
ICS;
        $this->parse($oldMessage, null, [], 'mailto:one@example.org');
    }

    private const OVERRIDE = "BEGIN:VEVENT\r\nUID:foobar\r\nDTSTAMP:20260101T000000Z\r\n"
        ."RECURRENCE-ID:20260107T100000Z\r\nDTSTART:20260107T140000Z\r\nDTEND:20260107T150000Z\r\n"
        ."ORGANIZER:mailto:strunk@example.org\r\nATTENDEE;PARTSTAT=ACCEPTED:mailto:one@example.org\r\nEND:VEVENT\r\n";

    private const SERIES_START = "BEGIN:VEVENT\r\nUID:foobar\r\nDTSTAMP:20260101T000000Z\r\n"
        ."DTSTART:20260105T100000Z\r\nDTEND:20260105T110000Z\r\nRRULE:FREQ=DAILY;COUNT=5\r\n"
        ."ORGANIZER:mailto:strunk@example.org\r\n";

    public function testAttendeeDeleteDeclinesEveryStoredInstance(): void
    {
        $old = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".self::SERIES_START
            ."ATTENDEE;PARTSTAT=ACCEPTED:mailto:one@example.org\r\nEND:VEVENT\r\n"
            .self::OVERRIDE."END:VCALENDAR\r\n";

        $messages = (new Broker())->parseEvent(null, 'mailto:one@example.org', $old);

        self::assertCount(2, $messages);
        foreach ($messages as $message) {
            self::assertSame('DECLINED', (string) $message->message->VEVENT->ATTENDEE['PARTSTAT']);
        }
    }

    public function testAttendeeDeleteOfACopyWithoutMaster(): void
    {
        $old = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".self::OVERRIDE."END:VCALENDAR\r\n";

        $messages = (new Broker())->parseEvent(null, 'mailto:one@example.org', $old);

        self::assertCount(1, $messages);
        self::assertSame('DECLINED', (string) $messages[0]->message->VEVENT->ATTENDEE['PARTSTAT']);
        self::assertSame('20260107T100000Z', (string) $messages[0]->message->VEVENT->{'RECURRENCE-ID'});
    }

    public function testAttendeeDeleteDoesNotDeclineAMasterTheyAreNotOn(): void
    {
        $old = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".self::SERIES_START
            ."ATTENDEE;PARTSTAT=ACCEPTED:mailto:two@example.org\r\nEND:VEVENT\r\n"
            .self::OVERRIDE."END:VCALENDAR\r\n";

        $messages = (new Broker())->parseEvent(null, 'mailto:one@example.org', $old);

        self::assertCount(1, $messages);
        self::assertSame('20260107T100000Z', (string) $messages[0]->message->VEVENT->{'RECURRENCE-ID'});
    }
}
