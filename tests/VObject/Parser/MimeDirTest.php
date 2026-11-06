<?php

namespace Sabre\VObject\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Node;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

/**
 * Note that most MimeDir related tests can actually be found in the ReaderTest
 * class one level up.
 */
class MimeDirTest extends TestCase
{
    public function testParseError(): void
    {
        $this->expectException(ParseException::class);
        $mimeDir = new MimeDir();
        $mimeDir->parse(fopen(__FILE__, 'a+'));
    }

    public function testDecodeLatin1(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:3.0
FN:umlaut u - \xFC
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $mimeDir->setCharset('ISO-8859-1');
        $vcard = $mimeDir->parse($vcard);
        self::assertEquals("umlaut u - \xC3\xBC", $vcard->FN->getValue());
    }

    public function testDecodeInlineLatin1(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:2.1
FN;CHARSET=ISO-8859-1:umlaut u - \xFC
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);
        self::assertEquals("umlaut u - \xC3\xBC", $vcard->FN->getValue());
    }

    public function testIgnoreCharsetVCard30(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:3.0
FN;CHARSET=unknown:foo-bar - \xFC
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);
        self::assertEquals("foo-bar - \xFC", $vcard->FN->getValue());
    }

    public function testDontDecodeLatin1(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:4.0
FN:umlaut u - \xFC
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);
        // This basically tests that we don't touch the input string if
        // the encoding was set to UTF-8. The result is actually invalid
        // and the validator should report this, but it tests effectively
        // that we pass through the string byte-by-byte.
        self::assertEquals("umlaut u - \xFC", $vcard->FN->getValue());
    }

    public function testDecodeUnsupportedCharset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $mimeDir = new MimeDir();
        $mimeDir->setCharset('foobar');
    }

    public function testDecodeUnsupportedInlineCharset(): void
    {
        $this->expectException(ParseException::class);
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:2.1
FN;CHARSET=foobar:nothing
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $mimeDir->parse($vcard);
    }

    public static function provideEmptyParserInput(): array
    {
        return [
            [null, 'No input provided to parse'],
            ['', 'End of document reached prematurely'],
        ];
    }

    #[DataProvider('provideEmptyParserInput')]
    public function testParseEmpty($input, $expectedExceptionMessage): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $mimeDir = new MimeDir();
        $mimeDir->parse($input);
    }

    public function testDecodeWindows1252(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:3.0
FN:Euro \x80
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $mimeDir->setCharset('Windows-1252');
        $vcard = $mimeDir->parse($vcard);
        self::assertEquals("Euro \xE2\x82\xAC", $vcard->FN->getValue());
    }

    public function testDecodeWindows1252Inline(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:2.1
FN;CHARSET=Windows-1252:Euro \x80
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);
        self::assertEquals("Euro \xE2\x82\xAC", $vcard->FN->getValue());
    }

    public function testCaseInsensitiveInlineCharset(): void
    {
        $vcard = <<<VCF
BEGIN:VCARD
VERSION:2.1
FN;CHARSET=iSo-8859-1:Euro
N;CHARSET=utf-8:Test2
END:VCARD\n
VCF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);
        // we can do a simple assertion here. As long as we don't get an exception, everything is thing
        self::assertEquals('Euro', $vcard->FN->getValue());
        self::assertEquals('Test2', $vcard->N->getValue());
    }

    public function testParsingTwiceSameContent(): void
    {
        $card = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:PRODID
BEGIN:VEVENT
DTSTAMP;TZID=Europe/Busingen:20220712T172312
UID:UID
DTSTART;VALUE=DATE;VALUE=DATE;VALUE=DATE:20220612
END:VEVENT
END:VCALENDAR
EOF;

        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($card);
        // we can do a simple assertion here. As long as we don't get an exception, everything is fine
        self::assertEquals('20220612', $vcard->VEVENT->DTSTART->getValue());
    }

    /**
     * @covers \Sabre\VObject\Parser\MimeDir::readProperty
     */
    #[DataProvider('provideBrokenVCalendar')]
    public function testBrokenMultilineContentDoesNotBreakImportWhenSetToIgnoreBrokenLines(string $vcalendar): void
    {
        $mimeDir = new MimeDir(null, MimeDir::OPTION_IGNORE_INVALID_LINES);
        $vcalendar = $mimeDir->parse($vcalendar);
        self::assertInstanceOf(VCalendar::class, $vcalendar);
    }

    /**
     * @covers \Sabre\VObject\Parser\MimeDir::readProperty
     *
     * @param string $vcalendar
     */
    #[DataProvider('provideBrokenVCalendar')]
    public function testBrokenMultilineContentDoesBreakImport($vcalendar): void
    {
        $mimeDir = new MimeDir();
        $this->expectException(ParseException::class);
        $mimeDir->parse($vcalendar);
    }

    public static function provideBrokenVCalendar(): array
    {
        return [[<<<EOF
BEGIN:VCALENDAR
BEGIN:VEVENT
CREATED:20160501T180854Z
UID:15C11082-9FC5-4159-A888-4A4B92D0DB71
DTEND;TZID=America/Los_Angeles:20160504T133000
SUMMARY:Interment
DTSTART;TZID=America/Los_Angeles:20160504T123000
DTSTAMP:20160501T180924Z
X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-MAPKIT-HANDLE=CAES8gEaEglZw
 0Xu6epCQBFdwwyNJ49ewCKOAQoNVW5pdGVkIFN0YXRlcxICVVMaCkNhbGlmb3JuaWEiAkNBK
 gdBbGFtZWRhMgdPYWtsYW5kOgU5NDYxMVIMUGllZG1vbnQgQXZlWgQ1MDAwYhE1MDAwIFBpZ
 WRtb250IEF2ZWoENDIyMHIWTW91bnRhaW4gVmlldyBDZW1ldGVyeaIBCjk0NjExLTQyMjAqE
 TUwMDAgUGllZG1vbnQgQXZlMhE1MDAwIFBpZWRtb250IEF2ZTIST2FrbGFuZCwgQ0EgIDk0N
 jExMg1Vbml0ZWQgU3RhdGVzODlAAA==;X-APPLE-RADIUS=1001.127625592278;X-TITLE
 =Mountain View Cemetery:5000 Piedmont Avenue
OAKLAND, CA 94611
SEQUENCE:0
END:VEVENT
END:VCALENDAR
EOF
        ], [
            <<<EOF
BEGIN:VCALENDAR
BEGIN:VEVENT
CREATED:20160501T180854Z
UID:15C11082-9FC5-4159-A888-4A4B92D0DB71
DTEND;TZID=America/Los_Angeles:20160504T133000
SUMMARY:Interment
DTSTART;TZID=America/Los_Angeles:20160504T123000
DTSTAMP:20160501T180924Z
X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-MAPKIT-HANDLE=CAES8gEaEglZw
 0Xu6epCQBFdwwyNJ49ewCKOAQoNVW5pdGVkIFN0YXRlcxICVVMaCkNhbGlmb3JuaWEiAkNBK
 gdBbGFtZWRhMgdPYWtsYW5kOgU5NDYxMVIMUGllZG1vbnQgQXZlWgQ1MDAwYhE1MDAwIFBpZ
 WRtb250IEF2ZWoENDIyMHIWTW91bnRhaW4gVmlldyBDZW1ldGVyeaIBCjk0NjExLTQyMjAqE
 TUwMDAgUGllZG1vbnQgQXZlMhE1MDAwIFBpZWRtb250IEF2ZTIST2FrbGFuZCwgQ0EgIDk0N
 jExMg1Vbml0ZWQgU3RhdGVzODlAAA==;X-APPLE-RADIUS=1001.127625592278;X-TITLE
 =Mountain View Cemetery:5000 Piedmont Avenue
OAKLAND, CA 94611:
SEQUENCE:0
END:VEVENT
END:VCALENDAR
EOF,
        ]];
    }

    public function testPropertyName0(): void
    {
        $iCal = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:PRODID
BEGIN:VEVENT
0:test
END:VEVENT
END:VCALENDAR
EOF;

        $mimeDir = new MimeDir();
        $vevent = $mimeDir->parse($iCal);
        // @phpstan-ignore property.dynamicName
        self::assertEquals('test', $vevent->VEVENT->{0}->getValue());
    }

    public function testInvalidParameter(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid Mimedir file. Line starting at 3: Missing parameter name for parameter value "value2"');
        $vcard = <<<EOF
BEGIN:VCARD
VERSION:4.0
FN;P1=value1; P2=value2:value
UID:1234
END:VCARD
EOF;
        $mimeDir = new MimeDir();
        $vcard = $mimeDir->parse($vcard);

        echo $vcard->serialize();
    }

    public function testRepeatedValuesInParameterListAreKept(): void
    {
        $vcal = (new MimeDir())->parse("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\n"
            ."ATTENDEE;DELEGATED-TO=\"mailto:b@x\",\"mailto:b@x\",\"mailto:c@x\":mailto:a@x\r\n"
            ."ATTENDEE;X-L=a,b,a:mailto:d@x\r\n"
            ."ATTENDEE;X-L=,:mailto:e@x\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n");
        $attendees = $vcal->VEVENT->select('ATTENDEE');
        self::assertSame(['mailto:b@x', 'mailto:b@x', 'mailto:c@x'], $attendees[0]['DELEGATED-TO']->getParts());
        self::assertSame(['a', 'b', 'a'], $attendees[1]['X-L']->getParts());
        self::assertSame(['', ''], $attendees[2]['X-L']->getParts());

        $reread = (new MimeDir())->parse($vcal->serialize());
        self::assertSame($vcal->serialize(), $reread->serialize());
    }

    public function testNamelessValueParameterSelectsTheClass(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nX-FOO;URL:x\r\nNOTE;ENCODING=QUOTED-PRINTABLE;QUOTED-PRINTABLE:a=3Db\r\nEND:VCARD\r\n");
        self::assertInstanceOf(Property\Uri::class, $card->{'X-FOO'});
        self::assertSame('a=b', $card->NOTE->getValue());
        $reread = Reader::read($card->serialize());
        self::assertSame($card->{'X-FOO'}::class, $reread->{'X-FOO'}::class);
        self::assertSame($card->serialize(), $reread->serialize());

        $this->expectException(ParseException::class);
        Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nX-FOO;VALUE=TEXT;URL:x\r\nEND:VCARD\r\n");
    }

    public function testNamelessParametersKeepTheirVCard21Form(): void
    {
        $input = "BEGIN:VCARD\r\nVERSION:2.1\r\nTEL;HOME;VOICE:1\r\nTEL;TYPE=HOME;HOME;WORK:2\r\nEND:VCARD\r\n";
        $card = Reader::read($input);
        self::assertSame(['HOME', 'VOICE'], $card->TEL['TYPE']->getParts());
        self::assertSame(['HOME', 'WORK'], $card->select('TEL')[1]['TYPE']->getParts());
        self::assertSame(
            "BEGIN:VCARD\r\nVERSION:2.1\r\nTEL;HOME;VOICE:1\r\nTEL;TYPE=HOME,WORK:2\r\nEND:VCARD\r\n",
            $card->serialize()
        );
    }

    public function testEmptyPropertyNameAfterGroupIsRejected(): void
    {
        $this->expectException(ParseException::class);
        (new MimeDir())->parse("BEGIN:VCARD\r\nVERSION:3.0\r\n.:x\r\nEND:VCARD\r\n");
    }

    public function testEmptyPropertyNameAfterGroupIsIgnoredAsInvalidLine(): void
    {
        $vcard = (new MimeDir())->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nA.;X=Y:x\r\nEND:VCARD\r\n", MimeDir::OPTION_IGNORE_INVALID_LINES);
        self::assertSame("BEGIN:VCARD\r\nVERSION:3.0\r\nEND:VCARD\r\n", $vcard->serialize());
    }

    #[DataProvider('boundaryPropertyNames')]
    public function testPropertyNamedBeginOrEndIsRejected(string $line): void
    {
        $iCal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nEND:VEVENT\r\n"
            .$line."\r\nEND:VCALENDAR\r\n";

        $this->expectException(ParseException::class);
        (new MimeDir())->parse($iCal);
    }

    #[DataProvider('boundaryPropertyNames')]
    public function testPropertyNamedBeginOrEndIsIgnoredAsInvalidLine(string $line): void
    {
        $iCal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nEND:VEVENT\r\n"
            .$line."\r\nEND:VCALENDAR\r\n";

        $vcal = (new MimeDir())->parse($iCal, MimeDir::OPTION_IGNORE_INVALID_LINES);

        self::assertSame(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            $vcal->serialize()
        );
    }

    public static function boundaryPropertyNames(): array
    {
        return [
            'END with a parameter' => ['END;VCALENDAR'],
            'END with an empty group' => ['.END:VCALENDAR'],
            'BEGIN with an empty group' => ['.BEGIN:VEVENT'],
            'BEGIN with a group' => ['foo.begin:VEVENT'],
        ];
    }

    #[DataProvider('nestedDocuments')]
    public function testNestedDocumentIsRejected(string $input): void
    {
        $this->expectException(ParseException::class);
        (new MimeDir())->parse($input);
    }

    public static function nestedDocuments(): array
    {
        return [
            'vcalendar in a vevent' => ["BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nBEGIN:vcalendar\r\nEND:vcalendar\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"],
            'vcard in a vcard' => ["BEGIN:VCARD\r\nVERSION:4.0\r\nBEGIN:vcard\r\nEND:vcard\r\nEND:VCARD\r\n"],
        ];
    }

    public function testMultiValuedValueParameterIsRejected(): void
    {
        $this->expectException(ParseException::class);
        (new MimeDir())->parse("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:x\r\n"
            ."DTEND;VALUE=DATE,DATE-TIME:20260101\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
    }

    public function testBareCarriageReturnInNonTextValueIsDropped(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nLANG:fr\rNOTE:x\r\nFN:y\r\nEND:VCARD\r\n");
        self::assertSame('frNOTE:x', $card->LANG->getValue());
        $out = $card->serialize();
        self::assertSame(substr_count($out, "\r"), substr_count($out, "\r\n"));
        self::assertSame('frNOTE:x', Reader::read($out)->LANG->getValue());

        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:a\r\nRRULE:FREQ=DAILY\r;COUNT=2\r\nDURATION:PT1H\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame('FREQ=DAILY;COUNT=2', $vcal->VEVENT->RRULE->getValue());
    }

    public function testManyEmptyParameterValuesDoNotExhaustMemory(): void
    {
        $doc = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//x//EN\r\nBEGIN:VEVENT\r\nUID:u\r\n"
            .'X-VALUES;X-P='.str_repeat(',', 100000)."b:v\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $vobj = Reader::read($doc);

        // A list of all the tokens would cost about 80 MB here.
        self::assertLessThan(16 * 1024 * 1024, memory_get_peak_usage() - $before);
        self::assertCount(100001, $vobj->VEVENT->{'X-VALUES'}['X-P']->getParts());
        self::assertSame('v', (string) $vobj->VEVENT->{'X-VALUES'});
    }

    public function testTokensAreReadInOrderAroundAColonInAValue(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nNOTE;X-A=\"b:c\",d;X-E=f:g:h;i=j\r\nEND:VCARD\r\n");

        self::assertSame(['b:c', 'd'], $card->NOTE['X-A']->getParts());
        self::assertSame('f', (string) $card->NOTE['X-E']);
        self::assertSame('g:h;i=j', (string) $card->NOTE);
    }

    public function testCarriageReturnInValueParameterDoesNotChangeTheClassOnReread(): void
    {
        $cal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\n"
            ."DTSTART;VALUE=TEXT\r:20260301T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        self::assertSame(
            Reader::read($cal->serialize())->VEVENT->DTSTART::class,
            $cal->VEVENT->DTSTART::class
        );
        // Without the CR, this value type is refused at level 3.
        self::assertSame(3, max(array_column($cal->validate(Node::PROFILE_CALDAV), 'level')));

        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nX-A;VALUE=\"DA\rTE\":20260301\r\nEND:VCARD\r\n");
        self::assertSame(Reader::read($card->serialize())->{'X-A'}::class, $card->{'X-A'}::class);
    }

    public function testLineZeroFollowedByAFoldIsAProperty(): void
    {
        $cal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n0\r\n :a\r\nEND:VCALENDAR\r\n");
        self::assertStringContainsString("\r\n0:a\r\n", $cal->serialize());
    }

    public function testPropertyNamedZeroWithContinuationBytesReadsBack(): void
    {
        $value = str_repeat("\x99", 80);
        $text = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n0:$value\r\nEND:VCALENDAR\r\n")->serialize();
        self::assertStringContainsString($value, Reader::read($text)->serialize());
    }
}
