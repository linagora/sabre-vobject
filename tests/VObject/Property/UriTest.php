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
}
