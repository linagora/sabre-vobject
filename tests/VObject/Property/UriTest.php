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

    public function testStructuredNameWithUriValueIsOneXCardValue(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:a\r\nGENDER;VALUE=URI:urn:x\r\nEND:VCARD\r\n");
            $xml = \Sabre\VObject\Writer::writeXml($vcard);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString('<gender><uri>urn:x</uri></gender>', str_replace(["\n", ' '], '', $xml));
        self::assertSame('urn:x', (string) Reader::readXML($xml)->GENDER);
    }

    public function testUriWithSeveralValuesKeepsThemInJCard(): void
    {
        $vcard = new \Sabre\VObject\Component\VCard();
        $vcard->add('URL', ['http://a', 'http://b']);

        self::assertSame(['http://a', 'http://b'], $vcard->URL->getJsonValue());
    }
}
