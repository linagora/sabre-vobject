<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Reader;

class UriTest extends TestCase
{
    public function testAlwaysEncodeUriVCalendar(): void
    {
        // Apple iCal has issues with URL properties that don't have
        // VALUE=URI specified. We added a workaround to vobject that
        // ensures VALUE=URI always appears for these.
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
URL:http://example.org/
END:VEVENT
END:VCALENDAR
ICS;
        $output = Reader::read($input)->serialize();
        self::assertStringContainsString('URL;VALUE=URI:http://example.org/', $output);
    }

    public function testUrlWithCommaRoundTrips(): void
    {
        $input = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nURL:https://example.org/?q=48.8,2.3\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $once = Reader::read($input);
        $twice = Reader::read($once->serialize());
        self::assertSame('https://example.org/?q=48.8,2.3', $once->VEVENT->URL->getValue());
        self::assertSame('https://example.org/?q=48.8,2.3', $twice->VEVENT->URL->getValue());
        self::assertSame($once->serialize(), $twice->serialize());
    }

    public function testBareCarriageReturnBeforeAFoldRoundTrips(): void
    {
        // The CR is the 75th byte of the line, so a fold would follow it.
        $input = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:c\r\n"
            .'URL:https://visio.example.com/'.str_repeat('a', 44)."\rb\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";
        $once = Reader::read($input);
        $twice = Reader::read($once->serialize());

        self::assertSame('https://visio.example.com/'.str_repeat('a', 44).'b', (string) $once->VEVENT->URL);
        self::assertSame((string) $once->VEVENT->URL, (string) $twice->VEVENT->URL);
    }

    public function testBareCarriageReturnDoesNotSurviveSerialization(): void
    {
        $vcal = new \Sabre\VObject\Component\VCalendar();
        $vcal->add('VEVENT', ['URL' => 'https://example.com/'.str_repeat('a', 48)]);
        $vcal->VEVENT->URL->setValue('https://example.com/'.str_repeat('a', 48)."\rb\nc");

        $serialized = $vcal->serialize();
        self::assertDoesNotMatchRegularExpression("/\r(?!\n)|(?<!\r)\n/", $serialized);
        self::assertSame(
            'https://example.com/'.str_repeat('a', 48).'bc',
            (string) Reader::read($serialized)->VEVENT->URL
        );
    }
}
