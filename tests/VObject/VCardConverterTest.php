<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCard;

class VCardConverterTest extends TestCase
{
    use PHPUnitAssertions;

    /**
     * @throws InvalidDataException
     */
    public function testConvert30to40(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:3.0
        PRODID:foo
        FN;CHARSET=UTF-8:Steve
        TEL;TYPE=PREF,HOME:+1 555 666 777
        ITEM1.TEL:+1 444 555 666
        ITEM1.X-ABLABEL:CustomLabel
        PHOTO;ENCODING=b;TYPE=JPEG,HOME:Zm9v
        PHOTO;ENCODING=b;TYPE=GIF:Zm9v
        PHOTO;X-PARAM=FOO;ENCODING=b;TYPE=PNG:Zm9v
        PHOTO;VALUE=URI:http://example.org/foo.png
        X-ABShowAs:COMPANY
        END:VCARD
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
FN:Steve
TEL;PREF=1;TYPE=HOME:+1 555 666 777
ITEM1.TEL:+1 444 555 666
ITEM1.X-ABLABEL:CustomLabel
PHOTO;TYPE=HOME:data:image/jpeg;base64,Zm9v
PHOTO:data:image/gif;base64,Zm9v
PHOTO;X-PARAM=FOO:data:image/png;base64,Zm9v
PHOTO:http://example.org/foo.png
KIND:ORG
END:VCARD
OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvert40to40(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        FN:Steve
        TEL;PREF=1;TYPE=HOME:+1 555 666 777
        PHOTO:data:image/jpeg;base64,Zm9v
        PHOTO:data:image/gif;base64,Zm9v
        PHOTO;X-PARAM=FOO:data:image/png;base64,Zm9v
        PHOTO:http://example.org/foo.png
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
FN:Steve
TEL;PREF=1;TYPE=HOME:+1 555 666 777
PHOTO:data:image/jpeg;base64,Zm9v
PHOTO:data:image/gif;base64,Zm9v
PHOTO;X-PARAM=FOO:data:image/png;base64,Zm9v
PHOTO:http://example.org/foo.png
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvert21to40(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:2.1
        N:Family;Johnson
        FN:Johnson Family
        TEL;HOME;VOICE:555-12345-345
        ADR;HOME:;;100 Street Lane;Saubel Beach;ON;H0H0H0
        LABEL;HOME;ENCODING=QUOTED-PRINTABLE:100 Street Lane=0D=0ASaubel Beach,
         ON H0H0H0
        REV:20110731T040251Z
        UID:12345678
        END:VCARD
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
N:Family;Johnson;;;
FN:Johnson Family
TEL;TYPE=HOME,VOICE:555-12345-345
ADR;TYPE=HOME:;;100 Street Lane;Saubel Beach;ON;H0H0H0;
REV:20110731T040251Z
UID:12345678
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvert30to30(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:3.0
        PRODID:foo
        FN;CHARSET=UTF-8:Steve
        TEL;TYPE=PREF,HOME:+1 555 666 777
        PHOTO;ENCODING=b;TYPE=JPEG:Zm9v
        PHOTO;ENCODING=b;TYPE=GIF:Zm9v
        PHOTO;X-PARAM=FOO;ENCODING=b;TYPE=PNG:Zm9v
        PHOTO;VALUE=URI:http://example.org/foo.png
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:3.0
PRODID:foo
FN;CHARSET=UTF-8:Steve
TEL;TYPE=PREF,HOME:+1 555 666 777
PHOTO;ENCODING=b;TYPE=JPEG:Zm9v
PHOTO;ENCODING=b;TYPE=GIF:Zm9v
PHOTO;X-PARAM=FOO;ENCODING=b;TYPE=PNG:Zm9v
PHOTO;VALUE=URI:http://example.org/foo.png
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvert40to30(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        PRODID:foo
        FN:Steve
        TEL;PREF=1;TYPE=HOME:+1 555 666 777
        PHOTO:data:image/jpeg;base64,Zm9v
        PHOTO:data:image/gif,foo
        PHOTO;X-PARAM=FOO:data:image/png;base64,Zm9v
        PHOTO:http://example.org/foo.png
        KIND:ORG
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:3.0
FN:Steve
TEL;TYPE=PREF,HOME:+1 555 666 777
PHOTO;ENCODING=b;TYPE=JPEG:Zm9v
PHOTO;ENCODING=b;TYPE=GIF:Zm9v
PHOTO;ENCODING=b;TYPE=PNG;X-PARAM=FOO:Zm9v
PHOTO;VALUE=URI:http://example.org/foo.png
X-ABSHOWAS:COMPANY
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvertGroupCard(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:3.0
        PRODID:foo
        X-ADDRESSBOOKSERVER-KIND:GROUP
        X-ADDRESSBOOKSERVER-MEMBER:mailto:someone@example.com
        X-ADDRESSBOOKSERVER-MEMBER:mailto:sometwo@example.com
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
KIND:GROUP
MEMBER:mailto:someone@example.com
MEMBER:mailto:sometwo@example.com
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );

        $input = $output;
        $output = <<<OUT
BEGIN:VCARD
VERSION:3.0
X-ADDRESSBOOKSERVER-KIND:GROUP
X-ADDRESSBOOKSERVER-MEMBER:mailto:someone@example.com
X-ADDRESSBOOKSERVER-MEMBER:mailto:sometwo@example.com
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testBDAYConversion()
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:3.0
        PRODID:foo
        BDAY;X-APPLE-OMIT-YEAR=1604:1604-04-16
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
BDAY:--04-16
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );

        $input = $output;
        $output = <<<OUT
BEGIN:VCARD
VERSION:3.0
BDAY;X-APPLE-OMIT-YEAR=1604:1604-04-16
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testUnknownSourceVCardVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.2
        PRODID:foo
        FN;CHARSET=UTF-8:Steve
        TEL;TYPE=PREF,HOME:+1 555 666 777
        ITEM1.TEL:+1 444 555 666
        ITEM1.X-ABLABEL:CustomLabel
        PHOTO;ENCODING=b;TYPE=JPEG,HOME:Zm9v
        PHOTO;ENCODING=b;TYPE=GIF:Zm9v
        PHOTO;X-PARAM=FOO;ENCODING=b;TYPE=PNG:Zm9v
        PHOTO;VALUE=URI:http://example.org/foo.png
        X-ABShowAs:COMPANY
        END:VCARD
        
        IN_WRAP;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard->convert(Document::VCARD40);
    }

    /**
     * @throws InvalidDataException
     */
    public function testUnknownTargetVCardVersion()
    {
        $this->expectException(\InvalidArgumentException::class);
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:3.0
        PRODID:foo
        END:VCARD
        
        IN_WRAP;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard->convert(Document::VCARD21);
    }

    /**
     * @throws InvalidDataException
     */
    public function testConvertIndividualCard(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        PRODID:foo
        KIND:INDIVIDUAL
        END:VCARD
        
        IN_WRAP;

        $output = <<<OUT
BEGIN:VCARD
VERSION:3.0
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );

        $input = $output;
        $output = <<<OUT
BEGIN:VCARD
VERSION:4.0
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testAnniversary(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        ITEM1.ANNIVERSARY:20081210
        END:VCARD
        
        IN_WRAP;

        $output = <<<'OUT'
BEGIN:VCARD
VERSION:3.0
ITEM1.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20081210
ITEM1.X-ABDATE;VALUE=DATE-AND-OR-TIME:20081210
ITEM1.X-ABLABEL:_$!<Anniversary>!$_
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );

        // Swapping input and output
        list(
            $input,
            $output,
        ) = [
            $output,
            $input,
        ];

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testMultipleAnniversaries(): void
    {
        $input = <<<IN_WRAP
        BEGIN:VCARD
        VERSION:4.0
        ITEM1.ANNIVERSARY:20081210
        ITEM2.ANNIVERSARY:20091210
        ITEM3.ANNIVERSARY:20101210
        END:VCARD
        
        IN_WRAP;

        $output = <<<'OUT'
BEGIN:VCARD
VERSION:3.0
ITEM1.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20081210
ITEM1.X-ABDATE;VALUE=DATE-AND-OR-TIME:20081210
ITEM1.X-ABLABEL:_$!<Anniversary>!$_
ITEM2.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20091210
ITEM2.X-ABDATE;VALUE=DATE-AND-OR-TIME:20091210
ITEM2.X-ABLABEL:_$!<Anniversary>!$_
ITEM3.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20101210
ITEM3.X-ABDATE;VALUE=DATE-AND-OR-TIME:20101210
ITEM3.X-ABLABEL:_$!<Anniversary>!$_
END:VCARD

OUT;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD30);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );

        // Swapping input and output
        list(
            $input,
            $output,
        ) = [
            $output,
            $input,
        ];

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function testNoLabel(): void
    {
        $input = <<<VCF
BEGIN:VCARD
VERSION:3.0
UID:foo
N:Doe;John;;;
FN:John Doe
item1.X-ABDATE;type=pref:2008-12-11
END:VCARD

VCF;

        $vcard = Reader::read($input);

        self::assertInstanceOf(VCard::class, $vcard);
        /** @var VCard $vcard */
        $vcard = $vcard->convert(Document::VCARD40);
        $vcard = $vcard->serialize();

        /** @var VCard $converted */
        $converted = Reader::read($vcard);
        $converted->validate();

        $version = Version::VERSION;

        $expected = <<<VCF
BEGIN:VCARD
VERSION:4.0
PRODID:-//Sabre//Sabre VObject $version//EN
UID:foo
N:Doe;John;;;
FN:John Doe
ITEM1.X-ABDATE;PREF=1:2008-12-11
END:VCARD

VCF;

        self::assertEquals($expected, str_replace("\r", '', $vcard));
    }

    /**
     * @throws InvalidDataException
     */
    public function testPhoneNumberValueTypeGetsRemoved(): void
    {
        $input = <<<VCF
BEGIN:VCARD
VERSION:3.0
UID:foo
FN:John Doe
TEL;TYPE=HOME;VALUE=PHONE-NUMBER:+1234
END:VCARD

VCF;

        $output = <<<VCF
BEGIN:VCARD
VERSION:4.0
UID:foo
FN:John Doe
TEL;TYPE=HOME:+1234
END:VCARD
VCF;

        /** @var VCard $vcard */
        $vcard = Reader::read($input);
        $vcard = $vcard->convert(Document::VCARD40);

        self::assertVObjectEqualsVObject(
            $output,
            $vcard
        );
    }

    #[DataProvider('malformedDataUris')]
    public function testMalformedDataUriIsNotEmptied(string $uri): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nLOGO:$uri\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertSame($uri, (string) $v3->LOGO->getValue());
        self::assertStringContainsString("\r\nLOGO;VALUE=URI:", $v3->serialize());

        /** @var VCard $v3 */
        $v3 = Reader::read($v3->serialize());
        $back = $v3->convert(Document::VCARD40);
        self::assertSame($uri, (string) $back->LOGO);
    }

    /**
     * @return list<array{string}>
     */
    public static function malformedDataUris(): array
    {
        return [['data:image/jpeg;base64,@@@'], ['data:image/jpeg;base64AAAA'], ['data:image?jpeUAA=']];
    }

    public function testUnknownValueTypeOnMediaProperty(): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nLOGO;VALUE=X:data:image/jpeg;base64,AAAA\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertSame("\x00\x00\x00", $v3->LOGO->getValue());
        self::assertFalse(isset($v3->LOGO['VALUE']));

        /** @var VCard $v3 */
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nLOGO;VALUE=X;ENCODING=b:AAAA\r\nEND:VCARD\r\n");
        $v4 = $v3->convert(Document::VCARD40);
        self::assertSame('data:application/octet-stream;base64,AAAA', $v4->LOGO->getValue());
        self::assertFalse(isset($v4->LOGO['VALUE']));
    }

    public function testUnknownValueTypeIsKept(): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nTEL;VALUE=X-FOO:+1234\r\nEND:VCARD\r\n");
        /** @var VCard $v3 */
        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());
        self::assertSame('X-FOO', (string) $v3->TEL['VALUE']);
        $back = $v3->convert(Document::VCARD40);
        self::assertSame('X-FOO', (string) $back->TEL['VALUE']);
        self::assertSame('+1234', (string) $back->TEL);
    }

    public function testAnniversaryGroupDoesNotReuseCardGroup(): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nANNIVERSARY:20100601\r\n"
            ."ITEM1.X-ABDATE:20200101\r\nITEM1.X-ABLABEL:Wedding\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertCount(1, $v3->select('ITEM1.X-ABLABEL'));
        self::assertCount(1, $v3->select('ITEM2.X-ABLABEL'));

        /** @var VCard $v3 */
        $v3 = Reader::read($v3->serialize());
        $back = $v3->convert(Document::VCARD40);
        self::assertCount(1, $back->select('ANNIVERSARY'));
        self::assertSame('20100601', (string) $back->ANNIVERSARY);
        self::assertNull($back->ANNIVERSARY->group);
        self::assertSame('20200101', (string) $back->{'ITEM1.X-ABDATE'});
    }

    public function testAnniversaryGroupDoesNotReuseLabelledGroup(): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nANNIVERSARY:20100601\r\n"
            ."ITEM1.EMAIL:a@example.com\r\nITEM1.X-ABLABEL:".'_$!<Work>!$_'."\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertCount(1, $v3->select('ITEM1.X-ABLABEL'));
        self::assertSame('_$!<Work>!$_', (string) $v3->{'ITEM1.X-ABLABEL'});
        self::assertSame('_$!<Anniversary>!$_', (string) $v3->{'ITEM2.X-ABLABEL'});
    }

    #[DataProvider('datesWithoutYearAndMonth')]
    public function testBdayWithoutYearAndMonthTo30(string $value): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nBDAY:$value\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertSame($value, (string) $v3->BDAY);
        self::assertFalse(isset($v3->BDAY['X-APPLE-OMIT-YEAR']));
        $v3 = Reader::read($v3->serialize());
        foreach ($v3->validate() as $message) {
            self::assertLessThan(3, $message['level'], $message['message']);
        }
    }

    /**
     * @return list<array{string}>
     */
    public static function datesWithoutYearAndMonth(): array
    {
        return [['T1022'], ['T102200'], ['---15'], ['--02']];
    }

    public function testBdayWithoutYearKeepsTime(): void
    {
        /** @var VCard $v4 */
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nBDAY:--0203T102200Z\r\nEND:VCARD\r\n");
        $v3 = $v4->convert(Document::VCARD30);
        self::assertSame('16040203T102200Z', (string) $v3->BDAY);
        self::assertSame('1604', (string) $v3->BDAY['X-APPLE-OMIT-YEAR']);
        self::assertSame([], Reader::read($v3->serialize())->validate());

        /** @var VCard $v3 */
        $v3 = Reader::read($v3->serialize());
        self::assertSame('--0203T102200Z', (string) $v3->convert(Document::VCARD40)->BDAY);
    }

    public function testAddressBookServerMemberWithEscapedLineBreak(): void
    {
        /** @var VCard $v3 */
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nX-ADDRESSBOOKSERVER-MEMBER:urn:uuid:b\\nEMAIL:x@example.com\r\nEND:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        self::assertStringContainsString("\r\nMEMBER:urn:uuid:b\\nEMAIL:x@example.com\r\n", $serialized);
        $v4 = Reader::read($serialized);
        self::assertCount(1, $v4->select('MEMBER'));
        self::assertCount(0, $v4->select('EMAIL'));
    }

    public function testDataUriWithoutMediaTypeIsDecodedOnce(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nLOGO:data:;base64,QUJD\r\nEND:VCARD\r\n");
        self::assertSame('ABC', $v4->convert(Document::VCARD30)->LOGO->getValue());
    }

    public function testDataUriWithAParameterIsKept(): void
    {
        $uri = 'data:text/plain;charset=utf-8,test';
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nLOGO:$uri\r\nEND:VCARD\r\n");
        self::assertSame($uri, $v4->convert(Document::VCARD30)->LOGO->getValue());
    }

    public function testDataUriWithoutBase64IsPercentDecoded(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nLOGO:data:image/gif,a%20b\r\nEND:VCARD\r\n");
        self::assertSame('a b', $v4->convert(Document::VCARD30)->LOGO->getValue());
    }
}
