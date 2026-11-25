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
BDAY:--0416
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
        [$input, $output] = [
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
            'full date' => ['1604-04-12', '--0412'],
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
            'omitted year' => ["item1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n$label", '--0203'],
            'omitted year on X-ANNIVERSARY' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n", '--0203'],
            'another year is not omitted' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1980-02-03\r\n", '1980-02-03'],
            'both, the date first' => ["item1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n{$label}X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n", '--0203'],
            'both, the extension first' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\nitem1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\n$label", '--0203'],
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
        self::assertSame(['in june, I think'], $v4->ANNIVERSARY->getParts(), $serialized);
        self::assertFalse(isset($v4->ANNIVERSARY['X-APPLE-OMIT-YEAR']), $serialized);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function anniversaryExtensionsOfAnotherType(): array
    {
        $label = "\r\nitem1.X-ABLABEL:_\$!<Anniversary>!\$_";

        return [
            'uri' => ['X-ANNIVERSARY;VALUE=uri:c', 'TEXT', 'c'],
            'uri with a comma' => ['X-ANNIVERSARY;VALUE=uri:http://a/b,c', 'TEXT', 'http://a/b,c'],
            'labelled uri' => ['item1.X-ABDATE;VALUE=uri:c'.$label, 'TEXT', 'c'],
            'integer' => ['X-ANNIVERSARY;VALUE=integer:12', 'TEXT', '12'],
            'boolean' => ['X-ANNIVERSARY;VALUE=boolean:TRUE', 'TEXT', 'TRUE'],
            'false' => ['X-ANNIVERSARY;VALUE=boolean:FALSE', 'TEXT', 'FALSE'],
            'float' => ['X-ANNIVERSARY;VALUE=float:1.5', 'TEXT', '1.5'],
            'integer that is a date' => ['item1.X-ABDATE;VALUE=integer:20090808'.$label, 'DATE-AND-OR-TIME', '20090808'],
        ];
    }

    #[DataProvider('anniversaryExtensionsOfAnotherType')]
    public function testAnniversaryExtensionOfAnotherTypeIsADateOrAText(string $lines, string $valueType, string $expected): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:x\r\nFN:a\r\n$lines\r\nEND:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertCount(1, $v4->select('ANNIVERSARY'), $serialized);
        self::assertSame($valueType, $v4->ANNIVERSARY->getValueType(), $serialized);
        self::assertSame([$expected], $v4->ANNIVERSARY->getParts(), $serialized);
        self::assertSame([], $v4->validate(Node::PROFILE_CARDDAV), $serialized);
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

    /**
     * The form of RFC 6350 section 4.3.1 in vCard, and of RFC 7095 section
     * 3.5.3 in jCard.
     */
    public function testOmitYearIsWrittenInTheRfc6350Form(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nBDAY;X-APPLE-OMIT-YEAR=1604:1604-02-03\r\nEND:VCARD\r\n");
        $v4 = $v3->convert(Document::VCARD40);

        self::assertSame('--0203', (string) $v4->BDAY);
        self::assertSame('--02-03', $v4->BDAY->jsonSerialize()[3]);
    }

    public function testOmitYearIsStableThroughVCard3(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nBDAY:--0203\r\nEND:VCARD\r\n");
        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());

        self::assertSame('--0203', (string) $v3->convert(Document::VCARD40)->BDAY);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textAnniversaryExtensions(): array
    {
        return [
            'X-ANNIVERSARY' => ['X-ANNIVERSARY:Wedding\\, Paris\; see C:\\\\new'],
            'labelled X-ABDATE' => ["item1.X-ABDATE:Wedding\\, Paris\; see C:\\\\new\r\nitem1.X-ABLABEL:_\$!<Anniversary>!\$_"],
        ];
    }

    #[DataProvider('textAnniversaryExtensions')]
    public function testTextAnniversaryExtensionKeepsItsEscapedCharacters(string $lines): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane Doe\r\nUID:u1\r\n$lines\r\nEND:VCARD\r\n");

        $v4 = $v3->convert(Document::VCARD40);

        self::assertSame(['Wedding, Paris; see C:\\new'], $v4->ANNIVERSARY->getParts());
        self::assertSame('TEXT', $v4->ANNIVERSARY->getValueType());
        self::assertSame(['Wedding, Paris; see C:\\new'], Reader::read($v4->serialize())->ANNIVERSARY->getParts());
        self::assertSame(['Wedding, Paris; see C:\\new'], Reader::read($v4->convert(Document::VCARD30)->convert(Document::VCARD40)->serialize())->ANNIVERSARY->getParts());
    }

    /**
     * A text anniversary is case-sensitive: only the same value is merged.
     */
    public function testAnniversaryTextDifferingInCaseIsKept(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:foo\r\nFN:Jane Doe\r\nX-ANNIVERSARY:Wedding\r\nX-ANNIVERSARY:wedding\r\nEND:VCARD\r\n");

        $v4 = $v3->convert(Document::VCARD40);

        $values = array_map(
            fn ($p) => $p->getValue(),
            array_merge($v4->select('ANNIVERSARY'), $v4->select('X-ANNIVERSARY'))
        );
        self::assertSame(['Wedding', 'wedding'], $values);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dataUrisWithParameters(): array
    {
        return [
            'base64' => ['data:image/png;name=a.png;base64,iVBORw=='],
            'base64, malformed parameter' => ['data:image/png;bO:x;base64,iVBORw=='],
            'percent-encoded' => ['data:text/plain;charset=utf-8,a%20b'],
        ];
    }

    /**
     * The parameters of the media type stay in the uri, which vCard 3 allows.
     */
    #[DataProvider('dataUrisWithParameters')]
    public function testDataUriWithParametersKeepsThem(string $uri): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:x\r\nPHOTO:$uri\r\nEND:VCARD\r\n");

        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());
        self::assertSame($uri, (string) $v3->PHOTO);
        self::assertSame($uri, (string) $v3->convert(Document::VCARD40)->PHOTO);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function dataUriMediaTypes(): array
    {
        return [
            'webp logo' => ['LOGO', 'image/webp', 'WEBP'],
            'svg photo' => ['PHOTO', 'image/svg+xml', 'SVG+XML'],
            'png photo' => ['PHOTO', 'image/png', 'PNG'],
            'another kind of media' => ['LOGO', 'application/pdf', 'application/pdf'],
        ];
    }

    #[DataProvider('dataUriMediaTypes')]
    public function testDataUriMediaTypeSurvivesVCard3(string $name, string $mediaType, string $type): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:u1\r\n$name:data:$mediaType;base64,UklGRg==\r\nEND:VCARD\r\n");

        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());
        $back = $v3->convert(Document::VCARD40);

        self::assertSame($type, (string) $v3->select($name)[0]['TYPE']);
        self::assertSame("data:$mediaType;base64,UklGRg==", (string) $back->select($name)[0]);
    }

    public function testVCard3FormatKeywordBecomesAMediaType(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:u1\r\nPHOTO;ENCODING=b;TYPE=BMP:UklGRg==\r\nLOGO;ENCODING=b;TYPE=image/x-icon:UklGRg==\r\nEND:VCARD\r\n");

        $v4 = $v3->convert(Document::VCARD40);

        self::assertSame('data:image/bmp;base64,UklGRg==', (string) $v4->PHOTO);
        self::assertSame('data:image/x-icon;base64,UklGRg==', (string) $v4->LOGO);
        self::assertFalse(isset($v4->PHOTO['TYPE']));
    }

    /**
     * A native KIND wins over an Apple extension listed before it, as it
     * does over one listed after it.
     */
    #[DataProvider('appleKindsBeforeNativeKind')]
    public function testNativeKindWinsOverAppleExtensions(string $extension): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\n$extension\r\nKIND:location\r\nEND:VCARD\r\n");

        $v4 = $v3->convert(Document::VCARD40);

        self::assertCount(1, $v4->select('KIND'));
        self::assertSame('location', (string) $v4->KIND);
        self::assertFalse(isset($v4->{'X-KIND'}));
        self::assertStringContainsString($extension."\r\n", $v4->serialize());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nativeKindsWithAnotherAppleKind(): array
    {
        return [
            'individual, company' => ['individual', 'X-ABSHOWAS:COMPANY'],
            'individual, group' => ['individual', 'X-ADDRESSBOOKSERVER-KIND:group'],
            'org, group' => ['org', 'X-ADDRESSBOOKSERVER-KIND:group'],
            'group, company' => ['group', 'X-ABSHOWAS:COMPANY'],
        ];
    }

    /**
     * The native KIND of a vCard 4.0 card survives vCard 3.0, where an Apple
     * kind extension that says otherwise is dropped.
     */
    #[DataProvider('nativeKindsWithAnotherAppleKind')]
    public function testNativeKindSurvivesVCard3(string $kind, string $extension): void
    {
        foreach (["KIND:$kind\r\n$extension", "$extension\r\nKIND:$kind"] as $lines) {
            $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:x\r\n$lines\r\nEND:VCARD\r\n");

            $v3 = $v4->convert(Document::VCARD30);
            self::assertStringNotContainsString($extension, $v3->serialize());
            $back = Reader::read($v3->serialize())->convert(Document::VCARD40);
            self::assertSame(
                'individual' === $kind ? [] : [strtoupper($kind)],
                array_map(fn ($property) => strtoupper((string) $property), $back->select('KIND')),
                $back->serialize()
            );
        }
    }

    public function testUnmappedNativeKindKeepsAppleKindInVCard3(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:x\r\nKIND:location\r\nX-ABSHOWAS:COMPANY\r\nEND:VCARD\r\n");

        $back = Reader::read($v4->convert(Document::VCARD30)->serialize())->convert(Document::VCARD40);

        self::assertSame('location', (string) $back->KIND);
        self::assertSame('COMPANY', (string) $back->{'X-ABSHOWAS'});
    }

    public static function appleKindsBeforeNativeKind(): array
    {
        return [
            'X-ABSHOWAS' => ['X-ABSHOWAS:COMPANY'],
            'X-ADDRESSBOOKSERVER-KIND' => ['X-ADDRESSBOOKSERVER-KIND:group'],
        ];
    }

    /**
     * TYPE=PREF marks the preferred value: it is not the format of a binary
     * PHOTO or LOGO, and becomes PREF=1.
     */
    public function testTypePrefIsNotAMediaType(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:u1\r\n"
            ."PHOTO;ENCODING=b;TYPE=PREF:UklGRg==\r\nLOGO;ENCODING=b;TYPE=pref,PNG:UklGRg==\r\nEND:VCARD\r\n");

        $v4 = $v3->convert(Document::VCARD40);

        self::assertSame('data:application/octet-stream;base64,UklGRg==', (string) $v4->PHOTO);
        self::assertSame('1', (string) $v4->PHOTO['PREF']);
        self::assertSame('data:image/png;base64,UklGRg==', (string) $v4->LOGO);
        self::assertSame('1', (string) $v4->LOGO['PREF']);
        self::assertFalse(isset($v4->PHOTO['TYPE']));
    }

    public function testPreferredPhotoWithoutFormatSurvivesARoundTrip(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:J\r\nUID:u1\r\nPHOTO;PREF=1:data:application/octet-stream;base64,PA==\r\nEND:VCARD\r\n");

        $back = $v4->convert(Document::VCARD30)->convert(Document::VCARD40);

        self::assertSame((string) $v4->PHOTO, (string) $back->PHOTO);
        self::assertSame('1', (string) $back->PHOTO['PREF']);
    }

    /**
     * A data: URI without a media type is text/plain (RFC 2397), not
     * application/octet-stream (#205).
     */
    #[DataProvider('dataUrisWithoutMediaType')]
    public function testDataUriWithoutMediaTypeRoundTrip(string $uri): void
    {
        $input = "BEGIN:VCARD\r\nVERSION:4.0\r\nUID:foo\r\nFN:Jane Doe\r\nPHOTO:$uri\r\nEND:VCARD\r\n";

        $vcard3 = Reader::read($input)->convert(Document::VCARD30);
        $back = Reader::read($vcard3->serialize())->convert(Document::VCARD40);

        self::assertSame($uri, (string) $vcard3->PHOTO->getValue());
        self::assertSame($uri, (string) $back->PHOTO->getValue());
    }

    public static function dataUrisWithoutMediaType(): array
    {
        return [
            'base64' => ['data:;base64,QUJD'],
            'percent-encoded' => ['data:,abc%20d'],
        ];
    }

    /**
     * A native ANNIVERSARY of another type becomes a date or a text, as the
     * extensions do (#206).
     */
    #[DataProvider('nativeAnniversariesOfAnotherType')]
    public function testNativeAnniversaryOfAnotherTypeBecomesDateOrText(string $line, string $type, string $value): void
    {
        $input = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:foo\r\nFN:Jane Doe\r\n$line\r\nEND:VCARD\r\n";
        $output = Reader::read($input)->convert(Document::VCARD40);

        self::assertSame($type, $output->ANNIVERSARY->getValueType());
        self::assertSame($value, (string) $output->ANNIVERSARY->getValue());
        self::assertSame([], array_filter($output->validate(), fn ($warning) => $warning['level'] > 1));
    }

    public static function nativeAnniversariesOfAnotherType(): array
    {
        return [
            'uri' => ['ANNIVERSARY;VALUE=uri:tel:1', 'TEXT', 'tel:1'],
            'integer date' => ['ANNIVERSARY;VALUE=integer:20200101', 'DATE-AND-OR-TIME', '20200101'],
            'date' => ['ANNIVERSARY:2020-01-01', 'DATE-AND-OR-TIME', '2020-01-01'],
        ];
    }

    public function testNativeAnniversaryWithUnknownValueTypeLosesIt(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nANNIVERSARY;VALUE=foo:2020-01-01\r\nEND:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertInstanceOf(Property\VCard\DateAndOrTime::class, $v4->ANNIVERSARY);
        self::assertFalse(isset($v4->ANNIVERSARY['VALUE']), $serialized);
        self::assertSame('2020-01-01', (string) $v4->ANNIVERSARY);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function anniversariesWithAndWithoutTheYear(): array
    {
        return [
            'two extensions' => ["X-ANNIVERSARY:1604-05-06\r\nX-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\n", '1604-05-06'],
            'the yearless one first' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\nX-ANNIVERSARY:1604-05-06\r\n", '--0506'],
            'a native ANNIVERSARY after' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\nANNIVERSARY:1604-05-06\r\n", '1604-05-06'],
            'a yearless native ANNIVERSARY after' => ["X-ANNIVERSARY:1604-05-06\r\nANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\n", '--0506'],
        ];
    }

    #[DataProvider('anniversariesWithAndWithoutTheYear')]
    public function testAnniversaryExtensionWithOmittedYearIsNotMergedWithTheRealYear(string $lines, string $anniversary): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\n{$lines}END:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertSame($anniversary, (string) $v4->ANNIVERSARY, $serialized);
        // the other date stays, as an extension
        self::assertCount(1, $v4->select('X-ANNIVERSARY'), $serialized);
        self::assertSame([], $v4->validate(Node::PROFILE_CARDDAV), $serialized);
    }

    public function testAnniversaryExtensionWithOmittedYearMergedWithTheSameDate(): void
    {
        $v3 = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:a\r\nFN:a\r\nX-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\nX-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-05-06\r\nEND:VCARD\r\n");
        $serialized = $v3->convert(Document::VCARD40)->serialize();
        $v4 = Reader::read($serialized);

        self::assertSame('--0506', (string) $v4->ANNIVERSARY, $serialized);
        self::assertCount(0, $v4->select('X-ANNIVERSARY'), $serialized);
    }

    public function testAnniversaryTwinMarksTheOmittedYearInVCard3(): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nANNIVERSARY:--0203\r\nEND:VCARD\r\n");
        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());

        self::assertSame('1604', (string) $v3->{'ITEM1.X-ABDATE'}['X-APPLE-OMIT-YEAR'], $v3->serialize());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function photosWithAVCard4Type(): array
    {
        return [
            'application/octet-stream' => ['data:application/octet-stream;base64,aUQ6ZA==', 'data:application/octet-stream;base64,aUQ6ZA=='],
            'an invalid media type' => ['data:audioaudio/ork:63;base64,aUQ6ZA==', 'data:application/octet-stream;base64,aUQ6ZA=='],
            'image/png' => ['data:image/png;base64,aUQ6ZA==', 'data:image/png;base64,aUQ6ZA=='],
        ];
    }

    #[DataProvider('photosWithAVCard4Type')]
    public function testVCard4TypeOfAPhotoIsNotItsMediaType(string $uri, string $expected): void
    {
        $v4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nUID:a\r\nFN:a\r\nPHOTO;TYPE=work:{$uri}\r\nEND:VCARD\r\n");
        $v3 = Reader::read($v4->convert(Document::VCARD30)->serialize());
        $back = Reader::read($v3->convert(Document::VCARD40)->serialize());

        self::assertSame($expected, (string) $back->PHOTO, $back->serialize());
        self::assertSame('work', (string) $back->PHOTO['TYPE'], $back->serialize());
    }

    public function testDuplicatePrefStaysOnePref(): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nEMAIL;PREF=1;TYPE=PREF:a@example.com\r\nEND:VCARD\r\n");

        $vcard3 = $input->convert(Document::VCARD30);
        self::assertSame(['PREF'], $vcard3->EMAIL['TYPE']->getParts());

        $vcard4 = $vcard3->convert(Document::VCARD40);
        self::assertSame('1', (string) $vcard4->EMAIL['PREF']);

        $again = $vcard4->convert(Document::VCARD30);
        self::assertSame('PREF', (string) $again->EMAIL['TYPE']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function duplicatePrefIn30(): array
    {
        return [
            'one TYPE' => ['EMAIL;TYPE=PREF,PREF:a@example.com', 'EMAIL;PREF=1:a@example.com'],
            'two TYPE' => ['EMAIL;TYPE=pref;TYPE=PREF:a@example.com', 'EMAIL;PREF=1:a@example.com'],
            'with another type, PREF first' => ['EMAIL;TYPE=PREF,HOME,PREF:a@example.com', 'EMAIL;PREF=1;TYPE=HOME:a@example.com'],
            'with another type, PREF last' => ['EMAIL;TYPE=HOME,PREF,PREF:a@example.com', 'EMAIL;TYPE=HOME;PREF=1:a@example.com'],
            'PREF=1 and TYPE=PREF' => ['EMAIL;PREF=1;TYPE=PREF:a@example.com', 'EMAIL;PREF=1:a@example.com'],
            'TYPE=PREF and PREF=1' => ['EMAIL;TYPE=PREF;PREF=1:a@example.com', 'EMAIL;PREF=1:a@example.com'],
        ];
    }

    #[DataProvider('duplicatePrefIn30')]
    public function testDuplicatePrefIn30IsOnePrefIn40(string $line, string $expected): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n".$line."\r\nEND:VCARD\r\n");

        self::assertSame($expected, rtrim($input->convert(Document::VCARD40)->EMAIL->serialize()));
    }

    public function testPrefWhoseRepeatedPartsAreAllOneStaysPreferredIn30(): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nEMAIL;PREF=1,1:a@example.com\r\nEND:VCARD\r\n");

        self::assertSame('EMAIL;TYPE=PREF:a@example.com', rtrim($input->convert(Document::VCARD30)->EMAIL->serialize()));
    }

    public function testPrefSetAsAnIntegerIsTypePrefIn30(): void
    {
        $card = new VCard(['VERSION' => '4.0', 'FN' => 'a', 'UID' => 'a']);
        $card->add('EMAIL', 'a@example.com', ['PREF' => 1]);

        self::assertSame('EMAIL;TYPE=PREF:a@example.com', rtrim($card->convert(Document::VCARD30)->EMAIL->serialize()));
    }

    public function testManyTypePartsConvertInLinearTime(): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\nEMAIL;TYPE=".str_repeat('a,', 200000)."PREF:a@example.com\r\nEND:VCARD\r\n");

        $start = microtime(true);
        $output = $input->convert(Document::VCARD40);
        self::assertLessThan(1.0, microtime(true) - $start);
        self::assertCount(200000, $output->EMAIL['TYPE']->getParts());
        self::assertSame('1', (string) $output->EMAIL['PREF']);
    }

    public function testLabelledAnniversaryIsComparedWithoutTheOmittedYear(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n"
            ."X-ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000305\r\n"
            ."X-ANNIVERSARY;VALUE=DATE-AND-OR-TIME:20000305\r\n"
            ."ITEM1.X-ABDATE;VALUE=DATE-AND-OR-TIME:20000305\r\n"
            ."ITEM1.X-ABLABEL:_$!<Anniversary>!\$_\r\n"
            ."END:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD40);

        self::assertCount(1, $output->select('ANNIVERSARY'));
        self::assertSame('20000305', (string) $output->ANNIVERSARY);
        self::assertSame(['20000305'], array_map(strval(...), $output->select('X-ANNIVERSARY')));
    }

    public function testVCard4WithAnOmittedYearExtensionNextToTheAnniversaryRoundTrips(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\n"
            ."X-ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000305\r\nANNIVERSARY:20000305\r\nEND:VCARD\r\n"
        );

        $back = $input->convert(Document::VCARD30)->convert(Document::VCARD40);

        self::assertSame(['20000305'], array_map(strval(...), $back->select('ANNIVERSARY')), $back->serialize());
    }

    public function testLabelledAnniversaryWithAnOmittedYearIsTheYearlessExtension(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n"
            ."X-ANNIVERSARY:20000305\r\n"
            ."X-ANNIVERSARY:--0305\r\n"
            ."ITEM1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-03-05\r\n"
            ."ITEM1.X-ABLABEL:_$!<Anniversary>!\$_\r\n"
            ."END:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD40);

        self::assertSame(['--0305'], array_map(strval(...), $output->select('ANNIVERSARY')), $output->serialize());
        self::assertSame(['20000305'], array_map(strval(...), $output->select('X-ANNIVERSARY')), $output->serialize());
    }

    /**
     * Before 47ec822c, the X-ABDATE written next to a yearless X-ANNIVERSARY
     * had no X-APPLE-OMIT-YEAR.
     *
     * @return array<string, array{string, string}>
     */
    public static function legacyTwins(): array
    {
        return [
            'year 1604' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:1604-03-05\r\nITEM1.X-ABDATE:1604-03-05\r\n", '--0305'],
            'another year' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000305\r\nITEM1.X-ABDATE;VALUE=DATE-AND-OR-TIME:20000305\r\n", '--0305'],
        ];
    }

    #[DataProvider('legacyTwins')]
    public function testLabelledTwinWithoutTheOmittedYearIsStillTheAnniversary(string $lines, string $expected): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n".$lines
            ."ITEM1.X-ABLABEL:_$!<Anniversary>!\$_\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD40);

        self::assertSame([$expected], array_map(strval(...), $output->select('ANNIVERSARY')), $output->serialize());
        self::assertSame([], $output->select('X-ANNIVERSARY'), $output->serialize());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function labelledDatesWithTheirOwnOmittedYear(): array
    {
        return [
            'year 1604' => ["X-ANNIVERSARY:1604-03-05\r\nITEM1.X-ABDATE;X-APPLE-OMIT-YEAR=1604:1604-03-05\r\n", '--0305'],
            'year 2000' => ["X-ANNIVERSARY:20000305\r\nITEM1.X-ABDATE;X-APPLE-OMIT-YEAR=2000:20000305\r\n", '--0305'],
            'another omitted year' => ["X-ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000305\r\nITEM1.X-ABDATE;X-APPLE-OMIT-YEAR=1999:20000305\r\n", '20000305'],
        ];
    }

    /**
     * A labelled X-ABDATE with its own X-APPLE-OMIT-YEAR is not the twin of
     * an X-ANNIVERSARY with the same raw value.
     */
    #[DataProvider('labelledDatesWithTheirOwnOmittedYear')]
    public function testLabelledDateWithItsOwnOmittedYearIsTheAnniversary(string $lines, string $expected): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n".$lines
            ."ITEM1.X-ABLABEL:_$!<Anniversary>!\$_\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD40);

        self::assertSame([$expected], array_map(strval(...), $output->select('ANNIVERSARY')), $output->serialize());
        self::assertCount(1, $output->select('X-ANNIVERSARY'), $output->serialize());
    }

    public function testAnniversaryWithItsOwnOmittedYearRoundTripsNextToItsFullDate(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\n"
            ."ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000305\r\nX-ANNIVERSARY:20000305\r\nEND:VCARD\r\n"
        );

        $vcard3 = $input->convert(Document::VCARD30);
        self::assertSame('2000', (string) $vcard3->select('X-ABDATE')[0]['X-APPLE-OMIT-YEAR'], $vcard3->serialize());

        $back = $vcard3->convert(Document::VCARD40);
        self::assertSame(['--0305'], array_map(strval(...), $back->select('ANNIVERSARY')), $back->serialize());
        self::assertSame(['20000305'], array_map(strval(...), $back->select('X-ANNIVERSARY')), $back->serialize());
    }

    public function testLabelInAGroupNamedZero(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\nX-ANNIVERSARY:19990101\r\n"
            ."0.X-ABDATE:20000305\r\n0.X-ABLABEL:_$!<Anniversary>!\$_\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD40);

        self::assertSame(['20000305'], array_map(strval(...), $output->select('ANNIVERSARY')), $output->serialize());
    }

    public function testAnniversaryGroupsInVCard3AreTheLowestFreeOnes(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nITEM1.EMAIL:a@example.com\r\n"
            ."ITEM2.ANNIVERSARY:20000305\r\nITEM5.ANNIVERSARY:20010305\r\nANNIVERSARY:20020305\r\nANNIVERSARY:20030305\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD30);

        // ITEM1 is used, ITEM2 only by the anniversary itself, ITEM5 by
        // another one until its own turn.
        self::assertSame(
            ['ITEM2' => '20000305', 'ITEM3' => '20010305', 'ITEM4' => '20020305', 'ITEM6' => '20030305'],
            array_combine(
                array_map(fn ($date) => $date->group, $output->select('X-ABDATE')),
                array_map(strval(...), $output->select('X-ABDATE'))
            ),
            $output->serialize()
        );
    }

    public function testKindIsReadAfterTheFirstOneIsRemoved(): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nKIND:org\r\nKIND:individual\r\nX-ABSHOWAS:COMPANY\r\nEND:VCARD\r\n");
        $input->remove($input->select('KIND')[0]);

        // The native KIND:individual wins over the extension.
        self::assertSame([], $input->convert(Document::VCARD30)->select('X-ABSHOWAS'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textAnniversaryWithAppleOmitYear(): array
    {
        return [
            'ANNIVERSARY' => ['ANNIVERSARY;X-APPLE-OMIT-YEAR=1604;VALUE=TEXT:Wedding'],
            'X-ANNIVERSARY' => ['X-ANNIVERSARY;X-APPLE-OMIT-YEAR=1604;VALUE=TEXT:Wedding'],
            'labelled X-ABDATE' => ["ITEM1.X-ABDATE;X-APPLE-OMIT-YEAR=1604;VALUE=TEXT:Wedding\r\nITEM1.X-ABLABEL:_$!<Anniversary>!\$_"],
        ];
    }

    #[DataProvider('textAnniversaryWithAppleOmitYear')]
    public function testTextAnniversaryLosesAppleOmitYear(string $line): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane Doe\r\nUID:a\r\n$line\r\nEND:VCARD\r\n");

        $output = $input->convert(Document::VCARD40);

        self::assertSame('Wedding', (string) $output->ANNIVERSARY, $output->serialize());
        self::assertSame('TEXT', $output->ANNIVERSARY->getValueType());
        self::assertFalse(isset($output->ANNIVERSARY['X-APPLE-OMIT-YEAR']), $output->serialize());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function anniversaryAndItsTwinExtension(): array
    {
        return [
            'extension first' => ['X-ANNIVERSARY;X-FOO=a:20000101', 'ANNIVERSARY;X-BAR=b:20000101', '20000101'],
            'extension last' => ['ANNIVERSARY;X-BAR=b:20000101', 'X-ANNIVERSARY;X-FOO=a:20000101', '20000101'],
            'anniversary in a group' => ['X-ANNIVERSARY;X-FOO=a:20000101', 'G.ANNIVERSARY;X-BAR=b:20000101', '20000101'],
            'both with an omitted year' => [
                'X-ANNIVERSARY;X-FOO=a;X-APPLE-OMIT-YEAR=1604:16040101',
                'ANNIVERSARY;X-BAR=b;X-APPLE-OMIT-YEAR=1604:16040101',
                '--0101',
            ],
            'without a year' => ['X-ANNIVERSARY;X-FOO=a:--0101', 'ANNIVERSARY;X-BAR=b:--0101', '--0101'],
            'extension in a group' => ['G.X-ANNIVERSARY;X-FOO=a:20000101', 'ANNIVERSARY;X-BAR=b:20000101', '20000101'],
        ];
    }

    #[DataProvider('anniversaryAndItsTwinExtension')]
    public function testNativeAnniversaryWinsOverItsTwinThroughVCard3(string $first, string $second, string $value): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:a\r\n$first\r\n$second\r\nEND:VCARD\r\n");
        $native = current($input->select('ANNIVERSARY'));

        $output = $input->convert(Document::VCARD30)->convert(Document::VCARD40);

        self::assertCount(1, $output->select('ANNIVERSARY'), $output->serialize());
        self::assertSame([], $output->select('X-ANNIVERSARY'), $output->serialize());
        $anniversary = current($output->select('ANNIVERSARY'));
        self::assertSame($value, (string) $anniversary);
        self::assertSame('b', (string) $anniversary['X-BAR'], $output->serialize());
        self::assertFalse(isset($anniversary['X-FOO']), $output->serialize());
        self::assertSame($native->group, $anniversary->group, $output->serialize());
    }

    public function testAnotherAnniversaryExtensionIsKeptInVCard3(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:a\r\n"
            ."X-ANNIVERSARY;X-APPLE-OMIT-YEAR=2000:20000101\r\nX-ANNIVERSARY:20010101\r\nANNIVERSARY:20000101\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD30);

        self::assertSame(
            ['20000101', '20010101', '20000101'],
            array_map(strval(...), $output->select('X-ANNIVERSARY')),
            $output->serialize()
        );
    }

    public function testFirstNativeAnniversaryStaysTheAnniversaryThroughVCard3(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:a\r\n"
            ."X-ANNIVERSARY;X-FOO=a:20010101\r\nANNIVERSARY:20000101\r\nANNIVERSARY:20010101\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD30)->convert(Document::VCARD40);

        self::assertSame(['20000101'], array_map(strval(...), $output->select('ANNIVERSARY')), $output->serialize());
    }

    public function testTextAnniversaryKeepsItsTwinExtensionInVCard3(): void
    {
        $input = Reader::read(
            "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Jane Doe\r\nUID:a\r\n"
            ."ANNIVERSARY;VALUE=TEXT:wedding\r\nX-ANNIVERSARY;VALUE=TEXT:wedding\r\nEND:VCARD\r\n"
        );

        $output = $input->convert(Document::VCARD30);

        // A text stays an ANNIVERSARY, which vCard 3 has not: the extension
        // is the only one a vCard 3 client reads.
        self::assertSame(['wedding'], array_map(strval(...), $output->select('X-ANNIVERSARY')), $output->serialize());
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function yearlessDateWithItsOwnAppleOmitYear(): array
    {
        return [
            'BDAY' => ['BDAY;X-APPLE-OMIT-YEAR=1604:--0203', 'BDAY', 'BDAY', '1604-02-03'],
            'BDAY with another year' => ['BDAY;X-APPLE-OMIT-YEAR=2000:--0203', 'BDAY', 'BDAY', '1604-02-03'],
            'BDAY with a time' => ['BDAY;X-APPLE-OMIT-YEAR=1604:--0203T1000', 'BDAY', 'BDAY', '16040203T1000'],
            'ANNIVERSARY' => ['ANNIVERSARY;X-APPLE-OMIT-YEAR=1604:--0203', 'X-ANNIVERSARY', 'ANNIVERSARY', '1604-02-03'],
        ];
    }

    #[DataProvider('yearlessDateWithItsOwnAppleOmitYear')]
    public function testYearlessDateKeepsOneAppleOmitYear(string $line, string $name3, string $name4, string $value3): void
    {
        $input = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:foo\r\n$line\r\nEND:VCARD\r\n");

        $vcard3 = Reader::read($input->convert(Document::VCARD30)->serialize());
        $property = current($vcard3->select($name3));
        self::assertSame($value3, (string) $property, $vcard3->serialize());
        self::assertSame(['1604'], $property['X-APPLE-OMIT-YEAR']->getParts(), $vcard3->serialize());

        $vcard4 = $vcard3->convert(Document::VCARD40);
        $property = current($vcard4->select($name4));
        self::assertSame(substr($line, strrpos($line, ':') + 1), (string) $property, $vcard4->serialize());
        self::assertFalse(isset($property['X-APPLE-OMIT-YEAR']), $vcard4->serialize());
    }
}
