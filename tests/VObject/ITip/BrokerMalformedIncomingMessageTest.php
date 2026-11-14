<?php

namespace Sabre\VObject\ITip;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Reader;

/**
 * Incoming iTIP messages, or stored objects, missing a property the broker
 * reads (ATTENDEE, PARTSTAT, a master event) or holding another value type
 * (RECURRENCE-ID;VALUE=PERIOD, ATTENDEE;VALUE=PERIOD) must not make the broker
 * fatal.
 */
class BrokerMalformedIncomingMessageTest extends TestCase
{
    private const EXISTING = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:meeting-1\r\n"
        ."DTSTART:20260302T090000Z\r\nDTEND:20260302T100000Z\r\nORGANIZER:mailto:org@x.com\r\n"
        ."ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:att@x.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private const RECURRING = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:meeting-1\r\n"
        ."DTSTART:20260302T090000Z\r\nDTEND:20260302T100000Z\r\nRRULE:FREQ=DAILY;COUNT=5\r\n"
        ."ORGANIZER:mailto:org@x.com\r\nATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:att@x.com\r\nEND:VEVENT\r\n";

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

    private static function message(string $method, string $body): Message
    {
        $message = new Message();
        $message->component = 'VEVENT';
        $message->method = $method;
        $message->uid = 'meeting-1';
        // A REPLY comes from the attendee, a REQUEST or CANCEL from the organizer.
        $message->sender = 'REPLY' === $method ? 'mailto:att@x.com' : 'mailto:org@x.com';
        $message->recipient = 'REPLY' === $method ? 'mailto:org@x.com' : 'mailto:att@x.com';
        $message->message = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:$method\r\n".$body."END:VCALENDAR\r\n"
        );

        return $message;
    }

    public function testReplyWithoutPartstatDefaultsToNeedsAction(): void
    {
        $existing = Reader::read(self::EXISTING);
        $result = (new Broker())->processMessage(self::message('REPLY',
            "BEGIN:VEVENT\r\nUID:meeting-1\r\nDTSTART:20260302T090000Z\r\nORGANIZER:mailto:org@x.com\r\n"
            ."ATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
        ), $existing);

        self::assertInstanceOf(VCalendar::class, $result);
        self::assertSame('NEEDS-ACTION', (string) $result->VEVENT->ATTENDEE['PARTSTAT']);
        self::assertSame('2.0', (string) $result->VEVENT->ATTENDEE['SCHEDULE-STATUS']);
    }

    public function testReplyWithoutAttendeeIsIgnored(): void
    {
        $existing = Reader::read(self::EXISTING);
        $result = (new Broker())->processMessage(self::message('REPLY',
            "BEGIN:VEVENT\r\nUID:meeting-1\r\nDTSTART:20260302T090000Z\r\nORGANIZER:mailto:org@x.com\r\n"
            ."END:VEVENT\r\n"
        ), $existing);

        self::assertInstanceOf(VCalendar::class, $result);
        self::assertCount(1, $result->VEVENT->select('ATTENDEE'));
        self::assertSame('NEEDS-ACTION', (string) $result->VEVENT->ATTENDEE['PARTSTAT']);
    }

    public function testCancelOfAnInstanceWithoutMaster(): void
    {
        $existing = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            ."BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."ORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260304T090000Z\r\nDTSTART:20260304T090000Z\r\n"
            ."ORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n"
        );
        $message = self::message('CANCEL',
            "BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."SEQUENCE:1\r\nORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
        );
        $message->sequence = 1;
        $result = (new Broker())->processMessage($message, $existing);

        self::assertInstanceOf(VCalendar::class, $result);
        $vevents = $result->select('VEVENT');
        self::assertCount(2, $vevents);
        self::assertSame('20260303T090000Z', (string) $vevents[0]->{'RECURRENCE-ID'});
        self::assertSame('CANCELLED', (string) $vevents[0]->STATUS);
        self::assertSame('1', (string) $vevents[0]->SEQUENCE);
        self::assertSame('20260304T090000Z', (string) $vevents[1]->{'RECURRENCE-ID'});
        self::assertFalse(isset($vevents[1]->STATUS));
    }

    public function testCancelOfTheOnlyInstanceWithoutMasterKeepsAnEvent(): void
    {
        $existing = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            ."BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."ORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n"
        );
        $message = self::message('CANCEL',
            "BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."SEQUENCE:1\r\nORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n"
        );
        $message->sequence = 1;
        $result = (new Broker())->processMessage($message, $existing);

        self::assertInstanceOf(VCalendar::class, $result);
        self::assertCount(1, $result->select('VEVENT'));
        self::assertSame('CANCELLED', (string) $result->VEVENT->STATUS);
        self::assertSame('1', (string) $result->VEVENT->SEQUENCE);
    }

    #[DataProvider('periodRecurrenceIds')]
    public function testPeriodRecurrenceIdIsInvalidData(string $method, string $existing, string $message): void
    {
        $this->expectException(InvalidDataException::class);
        (new Broker())->processMessage(self::message($method, $message), Reader::read($existing));
    }

    public static function periodRecurrenceIds(): array
    {
        $periodOverride = "BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID;VALUE=PERIOD:20260303T090000Z/PT1H\r\n"
            ."DTSTART:20260303T090000Z\r\nORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n";
        $instance = "BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."SEQUENCE:1\r\nORGANIZER:mailto:org@x.com\r\nATTENDEE;PARTSTAT=ACCEPTED:mailto:att@x.com\r\nEND:VEVENT\r\n";
        $stored = self::RECURRING.$periodOverride."END:VCALENDAR\r\n";

        return [
            'REQUEST onto a period override' => ['REQUEST', $stored, $instance],
            'REPLY onto a period override' => ['REPLY', $stored, $instance],
            'CANCEL onto a period override' => ['CANCEL', $stored, $instance],
            'REPLY with a period RECURRENCE-ID' => ['REPLY', self::RECURRING."END:VCALENDAR\r\n", $periodOverride],
            'CANCEL with a period RECURRENCE-ID' => ['CANCEL', self::RECURRING."END:VCALENDAR\r\n", $periodOverride],
        ];
    }

    #[DataProvider('nonDateMasterDtStarts')]
    public function testCancelOntoAMasterWithANonDateDtstartIsInvalidData(string $dtStart): void
    {
        $stored = str_replace("DTSTART:20260302T090000Z\r\n", $dtStart."\r\n", self::RECURRING)."END:VCALENDAR\r\n";
        $instance = "BEGIN:VEVENT\r\nUID:meeting-1\r\nRECURRENCE-ID:20260303T090000Z\r\nDTSTART:20260303T090000Z\r\n"
            ."SEQUENCE:1\r\nORGANIZER:mailto:org@x.com\r\nATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\n";

        $this->expectException(InvalidDataException::class);
        (new Broker())->processMessage(self::message('CANCEL', $instance), Reader::read($stored));
    }

    public static function nonDateMasterDtStarts(): array
    {
        return [
            'TEXT' => ['DTSTART;VALUE=TEXT:tomorrow'],
            'PERIOD' => ['DTSTART;VALUE=PERIOD:20260302T090000Z/PT1H'],
        ];
    }

    public function testParseEventWithNonAddressOrganizer(): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:meeting-1\r\nDTSTART:20260302T090000Z\r\n"
            ."ORGANIZER;VALUE=PERIOD:20260302T090000Z/PT1H\r\nATTENDEE:mailto:att@x.com\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        self::assertSame([], (new Broker())->parseEvent($vcal, 'mailto:org@x.com'));
    }

    public function testParseEventWithNonAddressAttendee(): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:meeting-1\r\nDTSTART:20260302T090000Z\r\n"
            ."ORGANIZER:mailto:org@x.com\r\nATTENDEE;VALUE=PERIOD:20260302T090000Z/PT1H\r\n"
            ."ATTENDEE:mailto:att@x.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $messages = (new Broker())->parseEvent($vcal, 'mailto:org@x.com');

        $recipients = array_map(static fn (Message $message): string => $message->recipient, $messages);
        self::assertSame(['mailto:att@x.com'], $recipients);
    }
}
