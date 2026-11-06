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

        // ANNIVERSARY appears at most once in a vCard 4.0: the other values
        // stay as extensions.
        $input = $output;
        $output = <<<'OUT'
BEGIN:VCARD
VERSION:4.0
ITEM1.ANNIVERSARY:20081210
ITEM2.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20091210
ITEM3.X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20101210
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

    public function testAnniversaryStaysUngroupedWhenCardHasAnotherAbDate(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\n"
            ."ITEM1.X-ABDATE:20200101\r\nANNIVERSARY:20100601\r\nEND:VCARD\r\n");
        $back = $v4->convert(Document::VCARD30)->convert(Document::VCARD40);

        self::assertCount(1, $back->select('ANNIVERSARY'));
        self::assertEmpty($back->ANNIVERSARY->group);
        self::assertSame('20100601', (string) $back->ANNIVERSARY);
    }

    public function testExtensionsDoNotMakeRepeatedSingleProperties(): void
    {
        $v3 = Reader::read(implode("\r\n", [
            'BEGIN:VCARD', 'VERSION:3.0', 'UID:a', 'FN:a',
            'ITEM1.X-ABDATE:20100601', 'ITEM1.X-ABLABEL:_$!<Anniversary>!$_',
            'ITEM2.X-ABDATE:20200202', 'ITEM2.X-ABLABEL:_$!<Anniversary>!$_',
            'X-ADDRESSBOOKSERVER-KIND:group', 'X-ABSHOWAS:COMPANY',
            'END:VCARD', '',
        ]));
        $text = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($text);

        self::assertCount(1, $v4->select('ANNIVERSARY'));
        self::assertCount(1, $v4->select('KIND'));
        self::assertStringContainsString('ITEM2.X-ABDATE', $text);
        self::assertStringContainsString('20200202', $text);
        self::assertStringContainsString('ITEM2.X-ABLABEL:_$!<Anniversary>!$_', $text);
        self::assertStringContainsString('X-ABSHOWAS:COMPANY', $text);
        foreach ($v4->validate() as $message) {
            self::assertLessThan(3, $message['level'], $message['message']);
        }
    }

    public function testSeveralXAnniversariesAndKinds(): void
    {
        $v3 = Reader::read(implode("\r\n", [
            'BEGIN:VCARD', 'VERSION:3.0', 'UID:a', 'FN:a',
            'X-ANNIVERSARY:20100601', 'X-ANNIVERSARY:20200202', 'X-ANNIVERSARY:20100601',
            'X-ADDRESSBOOKSERVER-KIND:group', 'X-ADDRESSBOOKSERVER-KIND:group',
            'END:VCARD', '',
        ]));
        $v4 = $v3->convert(Document::VCARD40);

        self::assertSame('20100601', (string) $v4->ANNIVERSARY);
        self::assertCount(1, $v4->select('ANNIVERSARY'));
        self::assertSame('20200202', (string) $v4->{'X-ANNIVERSARY'});
        self::assertCount(1, $v4->select('KIND'));
    }

    #[DataProvider('nativeAnniversaries')]
    public function testNativeAnniversaryAfterExtensions(array $lines, ?string $extensionName): void
    {
        $v3 = Reader::read(implode("\r\n", ['BEGIN:VCARD', 'VERSION:3.0', 'UID:a', 'FN:a', ...$lines, 'END:VCARD', '']));
        $v4 = Reader::read($v3->convert(Document::VCARD40)->serialize());

        self::assertCount(1, $v4->select('ANNIVERSARY'));
        self::assertSame('20200202', (string) $v4->ANNIVERSARY);
        if ($extensionName) {
            self::assertSame('20100601', (string) current($v4->select($extensionName)));
        }
        foreach ($v4->validate() as $message) {
            self::assertLessThan(3, $message['level'], $message['message']);
        }
    }

    public static function nativeAnniversaries(): array
    {
        return [
            'X-ANNIVERSARY first' => [['X-ANNIVERSARY:20100601', 'ANNIVERSARY:20200202'], 'X-ANNIVERSARY'],
            'native first' => [['ANNIVERSARY:20200202', 'X-ANNIVERSARY:20100601'], 'X-ANNIVERSARY'],
            'same value' => [['X-ANNIVERSARY:20200202', 'ANNIVERSARY:20200202'], null],
            'labelled X-ABDATE first' => [['ITEM1.X-ABDATE:20100601', 'ITEM1.X-ABLABEL:_$!<Anniversary>!$_', 'ANNIVERSARY:20200202'], 'ITEM1.X-ABDATE'],
            'native twice' => [['ANNIVERSARY:20200202', 'ANNIVERSARY:20100601'], 'X-ANNIVERSARY'],
        ];
    }

    public function testNativeAnniversaryKeptThroughVCard3(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nX-ANNIVERSARY:20000101\r\nANNIVERSARY:20090808\r\nEND:VCARD\r\n");

        $back = Reader::read($v4->convert(Document::VCARD30)->serialize())->convert(Document::VCARD40);

        self::assertCount(1, $back->select('ANNIVERSARY'));
        self::assertSame('20090808', (string) $back->ANNIVERSARY);
        self::assertSame('20000101', (string) current($back->select('X-ANNIVERSARY')));
    }

    #[DataProvider('omitYearWithoutADay')]
    public function testOmitYearWithoutDayTo40(string $stored, string $expected): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nBDAY;X-APPLE-OMIT-YEAR=1604:$stored\r\nEND:VCARD\r\n");
        $v4 = Reader::read($v3->convert(Document::VCARD40)->serialize());

        self::assertSame($expected, (string) $v4->BDAY);
        self::assertFalse(isset($v4->BDAY['X-APPLE-OMIT-YEAR']));
        foreach ($v4->validate() as $message) {
            self::assertLessThan(3, $message['level'], $message['message']);
        }
    }

    public static function omitYearWithoutADay(): array
    {
        return [
            'month' => ['1604-04', '--04'],
            'year only' => ['1604', '1604'],
            'full date' => ['1604-04-12', '--04-12'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function anniversaryExtensionsWithoutValue(): array
    {
        $label = "item1.X-ABLABEL:_\$!<Anniversary>!\$_\r\n";

        return [
            'X-ANNIVERSARY' => ["X-ANNIVERSARY:2009-08-08\r\n", '2009-08-08'],
            'labelled X-ABDATE' => ["item1.X-ABDATE:2009-08-08\r\n$label", '2009-08-08'],
            'omitted year' => ["item1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n$label", '--02-03'],
            'omitted year on X-ANNIVERSARY' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n", '--02-03'],
            'another year is not omitted' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1980-02-03\r\n", '1980-02-03'],
            'both, the date first' => ["item1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n{$label}X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n", '--02-03'],
            'both, the extension first' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\nitem1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n$label", '--02-03'],
        ];
    }

    #[DataProvider('anniversaryExtensionsWithoutValue')]
    public function testAnniversaryExtensionWithoutValueIsADate(string $lines, string $expected): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\n{$lines}END:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertCount(1, $v4->select('ANNIVERSARY'), $serialized);
        self::assertInstanceOf(Property\VCard\DateAndOrTime::class, $v4->ANNIVERSARY);
        self::assertFalse(isset($v4->ANNIVERSARY['VALUE']), $serialized);
        self::assertFalse(isset($v4->ANNIVERSARY['X-APPLE-OMIT-YEAR']), $serialized);
        self::assertSame($expected, (string) $v4->ANNIVERSARY);
        self::assertSame([], $v4->select('X-ANNIVERSARY'), $serialized);
        self::assertSame([], $v4->validate(Node::PROFILE_CARDDAV), $serialized);
    }

    public function testAnniversaryExtensionThatIsNotADateIsAText(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nX-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:in june\\, I think\r\nEND:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertSame('TEXT', (string) $v4->ANNIVERSARY['VALUE'], $serialized);
        self::assertSame('in june, I think', (string) $v4->ANNIVERSARY, $serialized);
        self::assertFalse(isset($v4->ANNIVERSARY['X-APPLE-OMIT-YEAR']), $serialized);
    }

    public function testUnlabelledDateExtensionKeepsItsType(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nitem1.X-ABDATE:2009-08-08\r\nEND:VCARD\r\n");

        self::assertStringContainsString(
            "\r\nITEM1.X-ABDATE:2009-08-08\r\n",
            $v3->convert(Document::VCARD40)->serialize()
        );
    }

    /**
     * What the converter writes for a yearless anniversary converts back.
     */
    public function testYearlessAnniversarySurvivesVCard3(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nANNIVERSARY:--0203\r\nEND:VCARD\r\n");
        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $back = Reader::read($serialized);

        self::assertCount(1, $back->select('ANNIVERSARY'), $serialized);
        self::assertNull(DateTimeParser::parseVCardDateTime((string) $back->ANNIVERSARY)['year'], $serialized);
        self::assertSame([], $back->select('X-ANNIVERSARY'), $serialized);
        self::assertSame([], $back->select('X-ABDATE'), $serialized);
    }
}
