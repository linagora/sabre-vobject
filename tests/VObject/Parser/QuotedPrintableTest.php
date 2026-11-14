<?php

namespace Sabre\VObject\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class QuotedPrintableTest extends TestCase
{
    public function testReadQuotedPrintableSimple(): void
    {
        $data = "BEGIN:VCARD\r\nLABEL;ENCODING=QUOTED-PRINTABLE:Aach=65n\r\nEND:VCARD";

        $result = Reader::read($data);

        self::assertInstanceOf(Component::class, $result);
        self::assertEquals('VCARD', $result->name);
        self::assertCount(1, $result->children());
        self::assertEquals('Aachen', $this->getPropertyValue($result->LABEL));
    }

    public function testReadQuotedPrintableNonTextValue(): void
    {
        $data = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART;ENCODING=QUOTED-PRINTABLE:2026=301=301\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        $result = Reader::read($data);

        self::assertInstanceOf(Property\ICalendar\DateTime::class, $result->VEVENT->DTSTART);
        self::assertEquals('20260101', $result->VEVENT->DTSTART->getValue());
        self::assertSame(
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20260101\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n",
            $result->serialize()
        );
    }

    public function testReadQuotedPrintableNewlineSoft(): void
    {
        $data = "BEGIN:VCARD\r\nLABEL;ENCODING=QUOTED-PRINTABLE:Aa=\r\n ch=\r\n en\r\nEND:VCARD";
        $result = Reader::read($data);

        self::assertInstanceOf(Component::class, $result);
        self::assertEquals('VCARD', $result->name);
        self::assertCount(1, $result->children());
        self::assertEquals('Aachen', $this->getPropertyValue($result->LABEL));
    }

    public function testReadQuotedPrintableNewlineHard(): void
    {
        $data = "BEGIN:VCARD\r\nVERSION:2.1\r\nLABEL;ENCODING=QUOTED-PRINTABLE:Aachen=0D=0A=\r\n Germany\r\nEND:VCARD";
        $result = Reader::read($data);

        self::assertInstanceOf(Component::class, $result);
        self::assertEquals('VCARD', $result->name);
        self::assertCount(2, $result->children());
        self::assertEquals("Aachen\r\nGermany", $this->getPropertyValue($result->LABEL));
    }

    public function testReadQuotedPrintableCompatibilityMS(): void
    {
        $data = "BEGIN:VCARD\r\nVERSION:2.1\r\nLABEL;ENCODING=QUOTED-PRINTABLE:Aachen=0D=0A=\r\nDeutschland:okay\r\nEND:VCARD";
        $result = Reader::read($data, Reader::OPTION_FORGIVING);

        self::assertInstanceOf(Component::class, $result);
        self::assertEquals('VCARD', $result->name);
        self::assertCount(2, $result->children());
        self::assertEquals("Aachen\r\nDeutschland:okay", $this->getPropertyValue($result->LABEL));
    }

    public function testReadQuotesPrintableCompoundValues(): void
    {
        $data = <<<VCF
BEGIN:VCARD
VERSION:2.1
N:Doe;John;;;
FN:John Doe
ADR;WORK;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:;;M=C3=BCnster =
Str. 1;M=C3=BCnster;;48143;Deutschland
END:VCARD
VCF;

        $result = Reader::read($data, Reader::OPTION_FORGIVING);
        self::assertEquals([
            '', '', 'Münster Str. 1', 'Münster', '', '48143', 'Deutschland',
        ], $result->ADR->getParts());
    }

    public function testQuotedPrintableValuesSurviveARoundTrip(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."URL;ENCODING=QUOTED-PRINTABLE:https://a.example/=0D=0AEND:VEVENT=0D=0ABEGIN:VEVENT=0D=0AUID:b\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";
        $vcal = Reader::read($ics);
        self::assertCount(1, $vcal->select('VEVENT'));
        $reread = Reader::read($vcal->serialize());
        self::assertCount(1, $reread->select('VEVENT'));
        self::assertSame($vcal->VEVENT->URL->getValue(), $reread->VEVENT->URL->getValue());
        self::assertStringNotContainsString('QUOTED-PRINTABLE', $vcal->serialize());

        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nEND:VCARD\r\n");
        self::assertSame('a=41', (string) $vcard->NOTE);
        self::assertSame('a=41', (string) Reader::read($vcard->serialize())->NOTE);

        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a;b=0Ac\r\nTEL;VALUE=uri;ENCODING=QUOTED-PRINTABLE:a\\, b;c\r\nX-FOO;VALUE=uri;ENCODING=QUOTED-PRINTABLE:x=0A\r\nEND:VCARD\r\n");
        self::assertSame("a;b\nc", $vcard->NOTE->getValue());
        self::assertSame('a, b;c', $vcard->TEL->getValue());
        self::assertSame('x', $vcard->{'X-FOO'}->getValue());
        $reread = Reader::read($vcard->serialize());
        self::assertSame($vcard->serialize(), $reread->serialize());
        self::assertSame("a;b\nc", $reread->NOTE->getValue());
        self::assertSame('a, b;c', $reread->TEL->getValue());
    }

    #[DataProvider('lineBreaksInNonTextValues')]
    public function testLineBreakInQuotedPrintableNonTextValueIsRefused(string $document): void
    {
        $this->expectException(ParseException::class);
        Reader::read($document);
    }

    public static function lineBreaksInNonTextValues(): array
    {
        $event = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:a\r\n%s\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\n%s\r\nEND:VCARD\r\n";

        return [
            'injected component' => [sprintf($event, 'DURATION;ENCODING=QUOTED-PRINTABLE:PT1H=0AEND:VEVENT=0ABEGIN:VEVENT=0AUID:b')],
            'trailing line feed' => [sprintf($event, 'DURATION;ENCODING=QUOTED-PRINTABLE:PT1H=0A')],
            'carriage return' => [sprintf($event, 'DURATION;ENCODING=QUOTED-PRINTABLE:PT1H=0D')],
            'date-time' => [sprintf($event, 'DTSTART;ENCODING=QUOTED-PRINTABLE:20260101=0AATTENDEE:mailto:x@example.com')],
            'period' => [sprintf($event, 'FREEBUSY;ENCODING=QUOTED-PRINTABLE:20260101T000000Z/PT1H=0ANOTE:x')],
            'recur' => [sprintf($event, 'RRULE;ENCODING=QUOTED-PRINTABLE:FREQ=3DDAILY=0ANOTE:x')],
            'language tag' => [sprintf($vcard, 'LANG;ENCODING=QUOTED-PRINTABLE:fr=0ANOTE:injected')],
        ];
    }

    public function testPlainFoldInQuotedPrintableNonTextValueIsNotALineBreak(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nLANG;ENCODING=QUOTED-PRINTABLE:f\r\n r\r\nEND:VCARD\r\n");
        self::assertSame('fr', $vcard->LANG->getValue());

        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nLANG;ENCODING=QUOTED-PRINTABLE:f=\r\n r\r\nEND:VCARD\r\n");
        self::assertSame('fr', $vcard->LANG->getValue());
    }

    public function testSerializeNeverEmitsALineBreakInAValue(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:a\r\nDURATION:PT1H\rEND:VEVENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $out = $vcal->serialize();
        self::assertSame(substr_count($out, "\n"), substr_count($out, "\r\n"));
        self::assertSame(substr_count($out, "\r"), substr_count($out, "\r\n"));
        self::assertCount(1, Reader::read($out)->select('VEVENT'));
    }

    private function getPropertyValue(Property $property): string
    {
        return (string) $property;

        /*
        $param = $property['encoding'];
        if ($param !== null) {
            $encoding = strtoupper((string)$param);
            if ($encoding === 'QUOTED-PRINTABLE') {
                $value = quoted_printable_decode($value);
            } else {
                throw new Exception();
            }
        }

        $param = $property['charset'];
        if ($param !== null) {
            $charset = strtoupper((string)$param);
            if ($charset !== 'UTF-8') {
                $value = mb_convert_encoding($value, 'UTF-8', $charset);
            }
        } else {
            $value = StringUtil::convertToUTF8($value);
        }

        return $value;
         */
    }

    public function testPropertiesBeforeVersionAreReadAsOnTheNextRead(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nVERSION:3.0\r\nFN:x\r\nEND:VCARD\r\n");
        self::assertSame('a=41', (string) $card->NOTE);
        self::assertSame('a=41', (string) Reader::read($card->serialize())->NOTE);

        $card = Reader::read("BEGIN:VCARD\r\nLOGO:aGk=\r\nVERSION:4.0\r\nFN:x\r\nEND:VCARD\r\n");
        self::assertInstanceOf(Property\Uri::class, $card->LOGO);
        self::assertSame($card->LOGO::class, Reader::read($card->serialize())->LOGO::class);
        self::assertSame('x', (string) $card->FN);
    }

    /**
     * An empty line after BEGIN:VCARD leaves no buffered line: the look-ahead
     * still runs (#204).
     */
    public function testBlankLineAfterBeginDoesNotDisableTheVersionScan(): void
    {
        $first = Reader::read("BEGIN:VCARD\r\n\r\nPHOTO:http://example.com/a.jpg\r\n\r\nVERSION:4.0\r\nFN:Jane Doe\r\nEND:VCARD\r\n");
        $second = Reader::read($first->serialize());

        self::assertInstanceOf(Property\Uri::class, $first->PHOTO);
        self::assertSame($first->PHOTO::class, $second->PHOTO::class);
        self::assertSame($first->serialize(), $second->serialize());

        $card = Reader::read("BEGIN:VCARD\r\n\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nVERSION:3.0\r\nFN:x\r\nEND:VCARD\r\n");
        self::assertSame('a=41', (string) $card->NOTE);
    }

    public function testVersionOfTheNextCardIsNotUsed(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "BEGIN:VCARD\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nFN:x\r\nEND:VCARD\r\nBEGIN:VCARD\r\nVERSION:4.0\r\nFN:y\r\nEND:VCARD\r\n");
        rewind($stream);

        $card = Reader::read($stream);

        self::assertSame(VCard::UNKNOWN, $card->getDocumentType());
    }

    public function testPropertiesBeforeVersionOfAVCard21(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nVERSION:2.1\r\nFN:x\r\nEND:VCARD\r\n");
        self::assertSame('a=41', (string) $card->NOTE);
        self::assertSame('a=41', (string) Reader::read($card->serialize())->NOTE);
    }

    public function testVersionOfANestedComponentIsNotTheVersionOfTheCard(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nFN:a\r\nBEGIN:X\r\nVERSION:2.1\r\nEND:X\r\nVERSION:3.0\r\nCATEGORIES:a\\,b,c\r\nEND:VCARD\r\n");
        self::assertSame(VCard::VCARD30, $card->getDocumentType());
        self::assertSame(['a,b', 'c'], Reader::read($card->serialize())->CATEGORIES->getParts());

        // The nested component comes first.
        $card = Reader::read("BEGIN:VCARD\r\nBEGIN:X\r\nVERSION:2.1\r\nEND:X\r\nVERSION:3.0\r\nFN:a\r\nEND:VCARD\r\n");
        self::assertSame(VCard::VCARD30, $card->getDocumentType());
    }

    public function testVersionLineWithoutColonReadsTheSameAfterSerialize(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nFN:a\r\nVERSION;X\r\nVERSION:2.1\r\nURL:http://example.com/\r\nEND:VCARD\r\n");
        $serialized = $card->serialize();
        self::assertSame($serialized, Reader::read($serialized)->serialize());
    }

    public function testPlainFoldInQuotedPrintableTextValueIsNotALineBreak(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:a\r\nDESCRIPTION;ENCODING=QUOTED-PRINTABLE:first\r\n  second\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame('first second', (string) $vcal->VEVENT->DESCRIPTION);

        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:x\r\nNOTE;ENCODING=QUOTED-PRINTABLE:first\r\n  second\r\nEND:VCARD\r\n");
        self::assertSame('first second', (string) $vcard->NOTE);

        // A soft line break and an encoded line feed keep their meaning.
        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:x\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=\r\n b=0Ac\r\nEND:VCARD\r\n");
        self::assertSame("ab\nc", (string) $vcard->NOTE);
    }

    public function testQuotedPrintableValueOfAVCardWithoutVersionSurvivesSerialize(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nFN:a\r\nURL;ENCODING=QUOTED-PRINTABLE:http://example.com/a;b\r\nEND:VCARD\r\n");
        self::assertSame('http://example.com/a;b', (string) Reader::read($card->serialize())->URL);

        $card = Reader::read("BEGIN:VCARD\r\nFN:a\r\nNOTE;ENCODING=QUOTED-PRINTABLE:a=3D41\r\nEND:VCARD\r\n");
        self::assertSame('a=41', (string) $card->NOTE);
        self::assertSame('a=41', (string) Reader::read($card->serialize())->NOTE);
    }

    #[DataProvider('foldedVersionCards')]
    public function testAFoldedLineDoesNotChangeTheVersionOfTheCard(string $card): void
    {
        $vcard = Reader::read($card);
        self::assertSame(VCard::VCARD30, $vcard->getDocumentType());
        self::assertSame(['a,b', 'c'], Reader::read($vcard->serialize())->CATEGORIES->getParts());
    }

    public static function foldedVersionCards(): array
    {
        return [
            'parameter' => ["BEGIN:VCARD\r\nFN:a\r\nVERSION;X=\"y:2.1\r\n \":3.0\r\nCATEGORIES:a\\,b,c\r\nEND:VCARD\r\n"],
            'nested component' => ["BEGIN:VCARD\r\nFN:a\r\nBEG\r\n IN:X\r\nVERSION:2.1\r\nEND:X\r\nVERSION:3.0\r\nCATEGORIES:a\\,b,c\r\nEND:VCARD\r\n"],
        ];
    }

    public function testAVersionValueFoldedInTheMiddleIsFound(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nPHOTO:http://example.com/a.png\r\nVERSION:4\r\n .0\r\nFN:a\r\nEND:VCARD\r\n");
        self::assertInstanceOf(Property\Uri::class, $vcard->PHOTO);
    }

    public function testAFoldedVersionValueThatIsNotAVersionCachesNothing(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nFN:a\r\nVERSION:2.1\r\n x\r\nNOTE:b\r\nEND:VCARD\r\n");
        self::assertSame(VCard::UNKNOWN, $vcard->getDocumentType());
    }
}
