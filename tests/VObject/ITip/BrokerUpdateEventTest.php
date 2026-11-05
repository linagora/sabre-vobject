<?php

namespace Sabre\VObject\ITip;

use Sabre\VObject\Version;

class BrokerUpdateEventTest extends BrokerTester
{
    public function testInviteChange(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
ATTENDEE;CN=Three:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

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
                'significantChange' => true,
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
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'significantChange' => false,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two;PARTSTAT=NEEDS-ACTION:mailto:two@example.org
ATTENDEE;CN=Three;PARTSTAT=NEEDS-ACTION:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:three@example.org',
                'recipientName' => 'Three',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two;PARTSTAT=NEEDS-ACTION:mailto:two@example.org
ATTENDEE;CN=Three;PARTSTAT=NEEDS-ACTION:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteChangeFromNonSchedulingToSchedulingObject(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
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
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteChangeFromSchedulingToNonSchedulingObject(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
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
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testNoAttendees(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $expected = [];
        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testRemoveInstance(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART;TZID=America/Toronto:20140716T120000
DTEND;TZID=America/Toronto:20140716T130000
RRULE:FREQ=WEEKLY
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART;TZID=America/Toronto:20140716T120000
DTEND;TZID=America/Toronto:20140716T130000
RRULE:FREQ=WEEKLY
EXDATE;TZID=America/Toronto:20140724T120000
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
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
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART;TZID=America/Toronto:20140716T120000
DTEND;TZID=America/Toronto:20140716T130000
RRULE:FREQ=WEEKLY
EXDATE;TZID=America/Toronto:20140724T120000
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    /**
     * This test is identical to the first test, except this time we change the
     * DURATION property.
     *
     * This should ensure that the message is significant for every attendee,
     */
    public function testInviteChangeSignificantChange(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DURATION:PT1H
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DURATION:PT2H
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
ATTENDEE;CN=Three:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

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
                'significantChange' => true,
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
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
DURATION:PT2H
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two;PARTSTAT=NEEDS-ACTION:mailto:two@example.org
ATTENDEE;CN=Three;PARTSTAT=NEEDS-ACTION:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:three@example.org',
                'recipientName' => 'Three',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
DURATION:PT2H
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=Two;PARTSTAT=NEEDS-ACTION:mailto:two@example.org
ATTENDEE;CN=Three;PARTSTAT=NEEDS-ACTION:mailto:three@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteNoChange(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'significantChange' => false,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteNoChangeForceSend(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;SCHEDULE-FORCE-SEND=REQUEST;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteRemoveAttendees(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
SUMMARY:foo
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

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
                'significantChange' => true,
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
                'significantChange' => true,
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

    public function testInviteChangeExdateOrder(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Apple Inc.//Mac OS X 10.10.1//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:foobar
SEQUENCE:0
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;CUTYPE=INDIVIDUAL;EMAIL=strunk@example.org;PARTSTAT=ACCE
 PTED:mailto:strunk@example.org
ATTENDEE;CN=One;CUTYPE=INDIVIDUAL;EMAIL=one@example.org;PARTSTAT=ACCEPTED;R
 OLE=REQ-PARTICIPANT;SCHEDULE-STATUS="1.2;Message delivered locally":mailto
 :one@example.org
SUMMARY:foo
DTSTART:20141211T160000Z
DTEND:20141211T170000Z
RRULE:FREQ=WEEKLY
EXDATE:20141225T160000Z,20150101T160000Z
EXDATE:20150108T160000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Apple Inc.//Mac OS X 10.10.1//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;CUTYPE=INDIVIDUAL;EMAIL=strunk@example.org;PARTSTAT=ACCE
 PTED:mailto:strunk@example.org
ATTENDEE;CN=One;CUTYPE=INDIVIDUAL;EMAIL=one@example.org;PARTSTAT=ACCEPTED;R
 OLE=REQ-PARTICIPANT;SCHEDULE-STATUS=1.2:mailto:one@example.org
DTSTART:20141211T160000Z
DTEND:20141211T170000Z
RRULE:FREQ=WEEKLY
EXDATE:20150101T160000Z
EXDATE:20150108T160000Z,20141225T160000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'significantChange' => false,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;CUTYPE=INDIVIDUAL;EMAIL=strunk@example.org;PARTSTAT=ACCE
 PTED:mailto:strunk@example.org
ATTENDEE;CN=One;CUTYPE=INDIVIDUAL;EMAIL=one@example.org;PARTSTAT=ACCEPTED;R
 OLE=REQ-PARTICIPANT:mailto:one@example.org
DTSTART:20141211T160000Z
DTEND:20141211T170000Z
RRULE:FREQ=WEEKLY
EXDATE:20150101T160000Z
EXDATE:20150108T160000Z,20141225T160000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testInviteStatusCancelled(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:2
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:3
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
STATUS:CANCELLED
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'CANCEL',
                'component' => 'VEVENT',
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:3
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    /*
     * When EXDATE is added by Broker, it needs to be in the correct
     * timezone
     */

    public function testExdateTimezone(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART;TZID=Europe/London:20140716T120000
DTEND;TZID=Europe/London:20140716T130000
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART;TZID=Europe/London:20140716T120000
DTEND;TZID=Europe/London:20140716T130000
END:VEVENT
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RECURRENCE-ID;TZID=Europe/London:20140723T120000
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
DTSTART;TZID=Europe/London:20140723T120000
DTEND;TZID=Europe/London:20140723T130000
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART;TZID=Europe/London:20140716T120000
DTEND;TZID=Europe/London:20140716T130000
EXDATE;TZID=Europe/London:20140723T120000
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    /*
     * When EXDATE is added by Broker, it needs to be in the correct
     * timezone, also in case UTC is used
     */

    public function testExdateTimezoneUTC(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RECURRENCE-ID:20140723T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
DTSTART:20140723T120000Z
DTEND:20140723T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:one@example.org',
                'recipientName' => 'One',
                'significantChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
RRULE:FREQ=WEEKLY
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=ACCEPTED:mailto:strunk@example.org
ATTENDEE;CN=One;PARTSTAT=NEEDS-ACTION:mailto:one@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
EXDATE:20140723T120000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testSimpleInviteWithAlarm()
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
DTEND:20140811T230000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White:mailto:white@example.org
BEGIN:VALARM
TRIGGER:-PT45M
ACTION:EMAIL
ATTENDEE:mailto:strunk@example.org
DESCRIPTION:Daily meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
DTEND:20140811T230000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White:mailto:white@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
ATTENDEE:mailto:strunk@example.org
DESCRIPTION:Breakfast meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;
        $version = Version::VERSION;
        $expectedMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
DTEND:20140811T230000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White;PARTSTAT=NEEDS-ACTION:mailto:white@example.org
DTSTAMP:**ANY**
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
ATTENDEE:mailto:white@example.org
DESCRIPTION:Breakfast meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;
        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:white@example.org',
                'recipientName' => 'White',
                'message' => $expectedMessage,
            ],
        ];
        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testLocationChange(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;
        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
LOCATION:Paris
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Two:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
END:VEVENT
END:VCALENDAR
ICS;

        $version = Version::VERSION;

        $expected = [
            [
                'uid' => 'foobar',
                'method' => 'REQUEST',
                'component' => 'VEVENT',
                'sender' => 'mailto:strunk@example.org',
                'senderName' => 'Strunk',
                'recipient' => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'significantChange' => false,
                'hasChange' => true,
                'message' => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
SUMMARY:foo
LOCATION:Paris
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=Strunk;PARTSTAT=NEEDS-ACTION:mailto:strunk@example.org
ATTENDEE;CN=Two;PARTSTAT=NEEDS-ACTION:mailto:two@example.org
DTSTART:20140716T120000Z
DTEND:20140716T130000Z
DTSTAMP:**ANY**
END:VEVENT
END:VCALENDAR
ICS,
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');
    }

    public function testCancelOfRemovedAttendeeNeverLowersSequence(): void
    {
        $make = fn (int $seq, string $attendees) => \Sabre\VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\n"
            ."BEGIN:VEVENT\r\nUID:u1\r\nDTSTAMP:20260101T000000Z\r\nSEQUENCE:$seq\r\nDTSTART:20260105T090000Z\r\n"
            ."DTEND:20260105T100000Z\r\nORGANIZER:mailto:o@example.com\r\n$attendees\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $old = $make(2, "ATTENDEE:mailto:a@example.com\r\nATTENDEE:mailto:d@example.com");
        $new = $make(1, 'ATTENDEE:mailto:a@example.com');

        $messages = (new Broker())->parseEvent($new, 'mailto:o@example.com', $old);
        $cancels = array_values(array_filter($messages, fn ($m) => 'CANCEL' === $m->method));

        self::assertCount(1, $cancels);
        self::assertSame('mailto:d@example.com', $cancels[0]->recipient);
        self::assertSame(2, $cancels[0]->sequence);
        self::assertSame(2, $cancels[0]->message->VEVENT->SEQUENCE->getValue());
    }

    public static function equivalentRRuleProvider(): array
    {
        return [
            ['FREQ=WEEKLY;BYDAY=MO,TU', 'FREQ=WEEKLY;BYDAY=TU,MO', false],
            ['FREQ=YEARLY;BYMONTH=5,2', 'FREQ=YEARLY;BYMONTH=2,5', false],
            ['FREQ=YEARLY;BYMONTH=6,5,6,3', 'FREQ=YEARLY;BYMONTH=3,5,6', false],
            ['FREQ=WEEKLY;BYDAY=MO,TU', 'FREQ=WEEKLY;BYDAY=MO,WE', true],
            ['FREQ=MONTHLY;BYMONTHDAY=5,05', 'FREQ=MONTHLY;BYMONTHDAY=05,5', false],
            ['FREQ=MONTHLY;BYMONTHDAY=5,+5', 'FREQ=MONTHLY;BYMONTHDAY=+5,5', false],
            ['FREQ=MONTHLY;BYMONTHDAY=5,05', 'FREQ=MONTHLY;BYMONTHDAY=5', false],
            ['FREQ=MONTHLY;BYMONTHDAY=05', 'FREQ=MONTHLY;BYMONTHDAY=5', false],
            ['FREQ=MONTHLY;BYMONTHDAY=5', 'FREQ=MONTHLY;BYMONTHDAY=-5', true],
            ['FREQ=MONTHLY;BYDAY=+1MO', 'FREQ=MONTHLY;BYDAY=1MO', false],
            ['FREQ=MONTHLY;BYDAY=1MO', 'FREQ=MONTHLY;BYDAY=-1MO', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('equivalentRRuleProvider')]
    public function testRRuleListOrderIsNotSignificant(string $old, string $new, bool $significant): void
    {
        $ics = fn (string $rrule) => \Sabre\VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:r\r\n"
            ."DTSTART:20260105T100000Z\r\nRRULE:$rrule\r\nORGANIZER:mailto:o@example.com\r\n"
            ."ATTENDEE:mailto:a@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $messages = (new Broker())->parseEvent($ics($new), 'mailto:o@example.com', $ics($old));

        self::assertCount(1, $messages);
        self::assertSame($significant, $messages[0]->significantChange);
    }

    public function testAllDayOverrideWithoutAttendeeGivesDateExdate(): void
    {
        $calendar = \Sabre\VObject\Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
BEGIN:VEVENT
UID:u1
DTSTAMP:20260102T000000Z
DTSTART;VALUE=DATE:20260105
RRULE:FREQ=DAILY;COUNT=5
ORGANIZER:mailto:o@example.com
ATTENDEE:mailto:a@example.com
ATTENDEE:mailto:b@example.com
END:VEVENT
BEGIN:VEVENT
UID:u1
DTSTAMP:20260102T000000Z
RECURRENCE-ID;VALUE=DATE:20260106
DTSTART;VALUE=DATE:20260106
ORGANIZER:mailto:o@example.com
ATTENDEE:mailto:b@example.com
END:VEVENT
END:VCALENDAR
ICS);

        $messages = (new Broker())->parseEvent($calendar, 'mailto:o@example.com');
        $toA = array_values(array_filter($messages, fn ($m) => 'mailto:a@example.com' === $m->recipient))[0];

        self::assertSame('DATE', (string) $toA->message->VEVENT->EXDATE['VALUE']);
        self::assertSame(['20260106'], $toA->message->VEVENT->EXDATE->getParts());
        foreach (\Sabre\VObject\Reader::read($toA->message->serialize())->validate() as $problem) {
            self::assertLessThan(3, $problem['level'], $problem['message']);
        }
    }

    public function testOverrideInAnotherZoneGivesTheExdateOfTheSameInstant(): void
    {
        $calendar = \Sabre\VObject\Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//test//EN
BEGIN:VEVENT
UID:u1
DTSTAMP:20260102T000000Z
DTSTART;TZID=Europe/Paris:20260105T100000
RRULE:FREQ=DAILY;COUNT=5
ORGANIZER:mailto:o@example.com
ATTENDEE:mailto:a@example.com
ATTENDEE:mailto:b@example.com
END:VEVENT
BEGIN:VEVENT
UID:u1
DTSTAMP:20260102T000000Z
RECURRENCE-ID;TZID=America/New_York:20260106T040000
DTSTART;TZID=Europe/Paris:20260106T100000
ORGANIZER:mailto:o@example.com
ATTENDEE:mailto:b@example.com
END:VEVENT
END:VCALENDAR
ICS);

        $messages = (new Broker())->parseEvent($calendar, 'mailto:o@example.com');
        $toA = array_values(array_filter($messages, fn ($m) => 'mailto:a@example.com' === $m->recipient))[0];

        self::assertSame('Europe/Paris', (string) $toA->message->VEVENT->EXDATE['TZID']);
        self::assertSame(['20260106T100000'], $toA->message->VEVENT->EXDATE->getParts());
    }
}
