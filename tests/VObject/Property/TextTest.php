<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class TextTest extends TestCase
{
    public function assertVCard21Serialization($propValue, string $expected): void
    {
        $doc = new VCard([
            'VERSION' => '2.1',
            'PROP' => $propValue,
        ], false);

        // Adding quoted-printable, because we're testing if it gets removed
        // automatically.
        $doc->PROP['ENCODING'] = 'QUOTED-PRINTABLE';
        $doc->PROP['P1'] = 'V1';

        $output = $doc->serialize();

        self::assertEquals("BEGIN:VCARD\r\nVERSION:2.1\r\n$expected\r\nEND:VCARD\r\n", $output);
    }

    public function testSerializeVCard21(): void
    {
        self::assertVCard21Serialization(
            'f;oo',
            'PROP;P1=V1:f;oo'
        );
    }

    public function testSerializeVCard21Array(): void
    {
        self::assertVCard21Serialization(
            ['f;oo', 'bar'],
            'PROP;P1=V1:f\;oo;bar'
        );
    }

    public function testSerializeVCard21Fold(): void
    {
        self::assertVCard21Serialization(
            str_repeat('x', 80),
            'PROP;P1=V1:'.str_repeat('x', 64)."\r\n ".str_repeat('x', 16)
        );
    }

    public function testSerializeQuotedPrintable(): void
    {
        self::assertVCard21Serialization(
            "foo\r\nbar",
            'PROP;P1=V1;ENCODING=QUOTED-PRINTABLE:foo=0D=0Abar'
        );
    }

    public function testSerializeQuotedPrintableFold(): void
    {
        self::assertVCard21Serialization(
            "foo\r\nbarxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
            "PROP;P1=V1;ENCODING=QUOTED-PRINTABLE:foo=0D=0Abarxxxxxxxxxxxxxxxxxxxxxxxxxx=\r\n xxx"
        );
    }

    public function testValidateMinimumPropValue(): void
    {
        $vcard = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        UID:foo
        FN:Hi!
        N:A
        END:VCARD
        IN_WRAP;

        $vcard = Reader::read($vcard);
        self::assertCount(1, $vcard->validate());

        self::assertCount(1, $vcard->N->getParts());

        $vcard->validate(\Sabre\VObject\Node::REPAIR);

        self::assertCount(5, $vcard->N->getParts());
    }

    public function testRepairOfUriAdrSurvivesSerialization(): void
    {
        foreach (['ADR;VALUE=uri:geo:1,2', 'N;VALUE=uri:urn:x'] as $line) {
            $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:a\r\n$line\r\nEND:VCARD\r\n");
            $repair = array_column($card->validate(\Sabre\VObject\Node::PROFILE_CARDDAV | \Sabre\VObject\Node::REPAIR), 'level');
            $strict = array_column(Reader::read($card->serialize())->validate(\Sabre\VObject\Node::PROFILE_CARDDAV), 'level');
            self::assertSame([3], $repair);
            self::assertSame([3], $strict);
        }
    }

    public function testBareCarriageReturnRoundTrips(): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a\rb\r\nSUMMARY:x\ry\\nz\r\n"
            ."DTSTART:20351005T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        $again = Reader::read($vcal->serialize());

        self::assertSame('ab', (string) $vcal->VEVENT->UID);
        self::assertSame((string) $vcal->VEVENT->UID, (string) $again->VEVENT->UID);
        self::assertSame("xy\nz", (string) $vcal->VEVENT->SUMMARY);
        self::assertSame((string) $vcal->VEVENT->SUMMARY, (string) $again->VEVENT->SUMMARY);
    }

    public function testBareCarriageReturnInStructuredValue(): void
    {
        $vcard = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nN:Do\re;Jo\rhn;;;\r\nEND:VCARD\r\n");

        self::assertSame(['Doe', 'John', '', '', ''], $vcard->N->getParts());
        self::assertSame($vcard->N->getParts(), Reader::read($vcard->serialize())->N->getParts());
    }
}
