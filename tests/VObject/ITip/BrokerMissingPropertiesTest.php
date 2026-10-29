<?php

namespace Sabre\VObject\ITip;

use Sabre\VObject\Reader;
use Sabre\VObject\Version;

/**
 * Events lacking properties the broker rewrites (the ATTENDEE of an EMAIL
 * alarm, the ORGANIZER of the master event) must not make it fatal.
 */
class BrokerMissingPropertiesTest extends BrokerTester
{
    protected function setUp(): void
    {
        // Notices and warnings must not be swallowed.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testInviteWithEmailAlarmWithoutAttendee(): void
    {
        $message = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White:mailto:white@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
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
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White;PARTSTAT=NEEDS-ACTION:mailto:white@example.org
DTSTAMP:**ANY**
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
DESCRIPTION:Breakfast meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;

        $this->parse(null, $message, [
            [
                'method' => 'REQUEST',
                'recipient' => 'mailto:white@example.org',
                'message' => $expectedMessage,
            ],
        ], 'mailto:strunk@example.org');
    }

    public function testAlarmWithoutAction(): void
    {
        $message = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White:mailto:white@example.org
BEGIN:VALARM
TRIGGER:-PT30M
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;

        $result = (new Broker())->parseEvent($message, 'mailto:strunk@example.org');

        self::assertCount(1, $result);
        self::assertSame('REQUEST', $result[0]->method);
    }

    public function testCancelWithEmailAlarmWithoutAttendee(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
SEQUENCE:1
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
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
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:2
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:EMAIL
DESCRIPTION:Breakfast meeting
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;

        $this->parse($oldMessage, null, [
            [
                'method' => 'CANCEL',
                'recipient' => 'mailto:one@example.org',
                'message' => $expectedMessage,
            ],
        ], 'mailto:strunk@example.org');
    }

    public function testMasterWithoutOrganizer(): void
    {
        $message = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140811T220000Z
RRULE:FREQ=DAILY;COUNT=3
ATTENDEE;CN=White:mailto:white@example.org
END:VEVENT
BEGIN:VEVENT
UID:foobar
RECURRENCE-ID:20140812T220000Z
DTSTART:20140812T230000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=White:mailto:white@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $result = (new Broker())->parseEvent(Reader::read($message), 'mailto:strunk@example.org');

        self::assertCount(1, $result);
        self::assertSame('REQUEST', $result[0]->method);
        self::assertSame('mailto:white@example.org', $result[0]->recipient);
        self::assertCount(2, $result[0]->message->VEVENT);
    }

    public function testCancelForARemovedAttendeeWithoutSequence(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS;
        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
END:VEVENT
END:VCALENDAR
ICS;
        $version = Version::VERSION;
        $expectedMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
METHOD:CANCEL
BEGIN:VEVENT
UID:foobar
DTSTAMP:**ANY**
SEQUENCE:0
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $this->parse($oldMessage, $newMessage, [
            [
                'method' => 'CANCEL',
                'recipient' => 'mailto:one@example.org',
                'message' => $expectedMessage,
            ],
        ], 'mailto:strunk@example.org');
    }

    public function testCancelledStatusWithoutSequence(): void
    {
        $oldMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140716T120000Z
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS;
        $newMessage = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foobar
DTSTART:20140716T120000Z
STATUS:CANCELLED
ORGANIZER;CN=Strunk:mailto:strunk@example.org
ATTENDEE;CN=One:mailto:one@example.org
END:VEVENT
END:VCALENDAR
ICS;

        $result = (new Broker())->parseEvent(Reader::read($newMessage), 'mailto:strunk@example.org', Reader::read($oldMessage));

        self::assertCount(1, $result);
        self::assertSame('CANCEL', $result[0]->method);
        self::assertSame('0', (string) Reader::read($result[0]->message->serialize())->VEVENT->SEQUENCE);
    }
}
