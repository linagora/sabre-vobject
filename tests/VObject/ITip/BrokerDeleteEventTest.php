<?php

namespace Sabre\VObject\ITip;

class BrokerDeleteEventTest extends BrokerTester {

    function testOrganizerDeleteWithDtend() {

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

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'foobar',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:strunk@example.org',
                'senderName'    => 'Strunk',
                'recipient'     => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message'       => <<<ICS
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
ICS
            ],

            [
                'uid'           => 'foobar',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:strunk@example.org',
                'senderName'    => 'Strunk',
                'recipient'     => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'message'       => <<<ICS
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
ICS

            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');

    }

    function testOrganizerDeleteWithDuration() {

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

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'foobar',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:strunk@example.org',
                'senderName'    => 'Strunk',
                'recipient'     => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message'       => <<<ICS
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
ICS
            ],

            [
                'uid'           => 'foobar',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:strunk@example.org',
                'senderName'    => 'Strunk',
                'recipient'     => 'mailto:two@example.org',
                'recipientName' => 'Two',
                'message'       => <<<ICS
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
ICS

            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');

    }

    function testOrganizerDeleteOneRecurException() {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200108T103000
DTEND;TZID=Asia/Jakarta:20200108T110000
CLASS:PUBLIC
SUMMARY:Exception event 1
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200108T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200110T133000
DTEND;TZID=Asia/Jakarta:20200110T140000
CLASS:PUBLIC
SUMMARY:Exception event 2
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200110T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
EXDATE:20200110T050000
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200108T103000
DTEND;TZID=Asia/Jakarta:20200108T110000
CLASS:PUBLIC
SUMMARY:Exception event 1
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200108T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS;

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user0@open-paas.org',
                'recipientName' => 'John0 Doe0',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200110T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John0 Doe0:mailto:user0@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ],
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user1@open-paas.org',
                'recipientName' => 'John1 Doe1',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200110T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ]
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:admin@open-paas.org');
    }

    function testOrganizerDeleteMultipleRecurExceptions() {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200108T103000
DTEND;TZID=Asia/Jakarta:20200108T110000
CLASS:PUBLIC
SUMMARY:Exception event 1
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200108T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200110T133000
DTEND;TZID=Asia/Jakarta:20200110T140000
CLASS:PUBLIC
SUMMARY:Exception event 2
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200110T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200112T133000
DTEND;TZID=Asia/Jakarta:20200112T140000
CLASS:PUBLIC
SUMMARY:Exception event 3
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T084345Z
RECURRENCE-ID:20200112T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
EXDATE:20200110T050000
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200108T103000
DTEND;TZID=Asia/Jakarta:20200108T110000
CLASS:PUBLIC
SUMMARY:Exception event 1
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200108T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS;

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user0@open-paas.org',
                'recipientName' => 'John0 Doe0',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200110T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John0 Doe0:mailto:user0@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ],
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user0@open-paas.org',
                'recipientName' => 'John0 Doe0',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200112T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John0 Doe0:mailto:user0@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ],
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user1@open-paas.org',
                'recipientName' => 'John1 Doe1',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200110T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ],
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user1@open-paas.org',
                'recipientName' => 'John1 Doe1',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SEQUENCE:1
RECURRENCE-ID:20200112T050000Z
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ]
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:admin@open-paas.org');
    }

    function testOrganizerDeleteAllRecurExceptions() {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200108T103000
DTEND;TZID=Asia/Jakarta:20200108T110000
CLASS:PUBLIC
SUMMARY:Exception event 1
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200108T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200110T133000
DTEND;TZID=Asia/Jakarta:20200110T140000
CLASS:PUBLIC
SUMMARY:Exception event 2
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
RECURRENCE-ID:20200110T050000Z
SEQUENCE:1
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John0 Doe0:mailto:user0@open-paas.org
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS;

        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
TRANSP:OPAQUE
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
CLASS:PUBLIC
SUMMARY:Daily event
RRULE:FREQ=DAILY
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;PARTSTAT=ACCEPTED;RSVP=FALSE;ROLE=CHAIR;CUTYPE=INDIVIDUAL:mailto:admin@open-paas.org
DTSTAMP:20200108T064345Z
EXDATE:20200110T050000
END:VEVENT
END:VCALENDAR
ICS;

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user0@open-paas.org',
                'recipientName' => 'John0 Doe0',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John0 Doe0:mailto:user0@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ],
            [
                'uid'           => 'e2bc1a63-3d95-4c3e-8f24-14db2664d5c1',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:admin@open-paas.org',
                'senderName'    => 'admin admin',
                'recipient'     => 'mailto:user1@open-paas.org',
                'recipientName' => 'John1 Doe1',
                'message'       => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
METHOD:CANCEL
BEGIN:VEVENT
UID:e2bc1a63-3d95-4c3e-8f24-14db2664d5c1
DTSTAMP:**ANY**
SUMMARY:Daily event
DTSTART;TZID=Asia/Jakarta:20200107T120000
DTEND;TZID=Asia/Jakarta:20200107T123000
ORGANIZER;CN=admin admin:mailto:admin@open-paas.org
ATTENDEE;CN=John1 Doe1:mailto:user1@open-paas.org
END:VEVENT
END:VCALENDAR
ICS
            ]
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:admin@open-paas.org');
    }

    function testAttendeeDeleteWithDtend() {

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

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'foobar',
                'method'        => 'REPLY',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:one@example.org',
                'senderName'    => 'One',
                'recipient'     => 'mailto:strunk@example.org',
                'recipientName' => 'Strunk',
                'message'       => <<<ICS
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
ICS
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:one@example.org');


    }

    function testAttendeeReplyWithDuration() {

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

        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'foobar',
                'method'        => 'REPLY',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:one@example.org',
                'senderName'    => 'One',
                'recipient'     => 'mailto:strunk@example.org',
                'recipientName' => 'Strunk',
                'message'       => <<<ICS
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
ICS
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:one@example.org');


    }

    function testAttendeeDeleteCancelledEvent() {

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

    function testOrganizerDeleteEventWithAttendeeAndAlarm() {

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
        $version = \Sabre\VObject\Version::VERSION;

        $expected = [
            [
                'uid'           => 'foobar',
                'method'        => 'CANCEL',
                'component'     => 'VEVENT',
                'sender'        => 'mailto:strunk@example.org',
                'senderName'    => 'Strunk',
                'recipient'     => 'mailto:one@example.org',
                'recipientName' => 'One',
                'message'       => <<<ICS
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
ICS
            ],
        ];

        $this->parse($oldMessage, $newMessage, $expected, 'mailto:strunk@example.org');

    }

    function testNoCalendar() {

        $this->parse(null, null, [], 'mailto:one@example.org');

    }

    function testVTodo() {

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

}
