<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property\ICalendar\DateTime;

class PropertyTest extends TestCase
{
    public function testToString(): void
    {
        $cal = new VCalendar();

        $property = $cal->createProperty('propname', 'propvalue');
        self::assertEquals('PROPNAME', $property->name);
        self::assertEquals('propvalue', $property->__toString());
        self::assertEquals('propvalue', (string) $property);
        self::assertEquals('propvalue', $property->getValue());
    }

    public function testCreate(): void
    {
        $cal = new VCalendar();

        $params = [
            'param1' => 'value1',
            'param2' => 'value2',
        ];

        $property = $cal->createProperty('propname', 'propvalue', $params);

        self::assertEquals('value1', $property['param1']->getValue());
        self::assertEquals('value2', $property['param2']->getValue());
    }

    public function testSetValue(): void
    {
        $cal = new VCalendar();

        $property = $cal->createProperty('propname', 'propvalue');
        $property->setValue('value2');

        self::assertEquals('PROPNAME', $property->name);
        self::assertEquals('value2', $property->__toString());
    }

    public function testParameterExists(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';

        self::assertTrue(isset($property['PARAMNAME']));
        self::assertTrue(isset($property['paramname']));
        self::assertFalse(isset($property['foo']));
    }

    public function testParameterGet(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';

        self::assertInstanceOf(Parameter::class, $property['paramname']);
    }

    public function testParameterNotExists(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';

        self::assertNull($property['foo']);
    }

    public function testParameterMultiple(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';
        $property->add('paramname', 'paramvalue');

        self::assertInstanceOf(Parameter::class, $property['paramname']);
        self::assertCount(2, $property['paramname']->getParts());
    }

    public function testSetParameterAsString(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';

        self::assertCount(1, $property->parameters());
        self::assertInstanceOf(Parameter::class, $property->parameters['PARAMNAME']);
        self::assertEquals('PARAMNAME', $property->parameters['PARAMNAME']->name);
        self::assertEquals('paramvalue', $property->parameters['PARAMNAME']->getValue());
    }

    public function testUnsetParameter(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $property['paramname'] = 'paramvalue';

        unset($property['PARAMNAME']);
        self::assertCount(0, $property->parameters());
    }

    public function testSerialize(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');

        self::assertEquals("PROPNAME:propvalue\r\n", $property->serialize());
    }

    public function testSerializeParam(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue', [
            'paramname' => 'paramvalue',
            'paramname2' => 'paramvalue2',
        ]);

        self::assertEquals("PROPNAME;PARAMNAME=paramvalue;PARAMNAME2=paramvalue2:propvalue\r\n", $property->serialize());
    }

    public function testSerializeNewLine(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('SUMMARY', "line1\nline2");

        self::assertEquals("SUMMARY:line1\\nline2\r\n", $property->serialize());
    }

    public function testSerializeLongLine(): void
    {
        $cal = new VCalendar();
        $value = str_repeat('!', 200);
        $property = $cal->createProperty('propname', $value);

        $expected = 'PROPNAME:'.str_repeat('!', 66)."\r\n ".str_repeat('!', 74)."\r\n ".str_repeat('!', 60)."\r\n";

        self::assertEquals($expected, $property->serialize());
    }

    public function testSerializeUTF8LineFold(): void
    {
        $cal = new VCalendar();
        $value = str_repeat('!', 65)."\xc3\xa4bla".str_repeat('!', 142)."\xc3\xa4foo"; // inserted umlaut-a
        $property = $cal->createProperty('propname', $value);

        // PROPNAME:!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ("PROPNAME:" + 65x"!" = 74 bytes)
        //  äbla!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! (" äbla"     + 69x"!" = 75 bytes)
        //  !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! (" "         + 73x"!" = 74 bytes)
        //  äfoo
        $expected = 'PROPNAME:'.str_repeat('!', 65)."\r\n \xc3\xa4bla".str_repeat('!', 69)."\r\n ".str_repeat('!', 73)."\r\n \xc3\xa4foo\r\n";
        self::assertEquals($expected, $property->serialize());
    }

    public function testGetIterator(): void
    {
        $cal = new VCalendar();
        $it = new ElementList([]);
        $property = $cal->createProperty('propname', 'propvalue');
        $property->setIterator($it);
        self::assertEquals($it, $property->getIterator());
    }

    public function testGetIteratorDefault(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('propname', 'propvalue');
        $it = $property->getIterator();
        self::assertTrue($it instanceof ElementList);
        self::assertCount(1, $it);
    }

    public function testAddScalar(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('EMAIL');

        $property->add('myparam', 'value');

        self::assertCount(1, $property->parameters());

        self::assertTrue($property->parameters['MYPARAM'] instanceof Parameter);
        self::assertEquals('MYPARAM', $property->parameters['MYPARAM']->name);
        self::assertEquals('value', $property->parameters['MYPARAM']->getValue());
    }

    public function testAddParameter(): void
    {
        $cal = new VCalendar();
        $prop = $cal->createProperty('EMAIL');

        $prop->add('MYPARAM', 'value');

        self::assertCount(1, $prop->parameters());
        self::assertEquals('MYPARAM', $prop['myparam']->name);
    }

    public function testAddParameterTwice(): void
    {
        $cal = new VCalendar();
        $prop = $cal->createProperty('EMAIL');

        $prop->add('MYPARAM', 'value1');
        $prop->add('MYPARAM', 'value2');

        self::assertCount(1, $prop->parameters);
        self::assertCount(2, $prop->parameters['MYPARAM']->getParts());

        self::assertEquals('MYPARAM', $prop['MYPARAM']->name);
    }

    public function testClone(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('EMAIL', 'value');
        $property['FOO'] = 'BAR';

        $property2 = clone $property;

        $property['FOO'] = 'BAZ';
        self::assertEquals('BAR', (string) $property2['FOO']);
    }

    public function testCreateParams(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('X-PROP', 'value', [
            'param1' => 'value1',
            'param2' => ['value2', 'value3'],
        ]);

        self::assertCount(1, $property['PARAM1']->getParts());
        self::assertCount(2, $property['PARAM2']->getParts());
    }

    public function testValidateNonUTF8(): void
    {
        $calendar = new VCalendar();
        $property = $calendar->createProperty('X-PROP', "Bla\x00");
        $result = $property->validate(Property::REPAIR);

        self::assertEquals('Property contained a control character (0x00)', $result[0]['message']);
        self::assertEquals('Bla', $property->getValue());
    }

    public function testValidateControlChars(): void
    {
        $s = 'chars[';
        foreach ([
            0x7F, 0x5E, 0x5C, 0x3B, 0x3A, 0x2C, 0x22, 0x20,
            0x1F, 0x1E, 0x1D, 0x1C, 0x1B, 0x1A, 0x19, 0x18,
            0x17, 0x16, 0x15, 0x14, 0x13, 0x12, 0x11, 0x10,
            0x0F, 0x0E, 0x0D, 0x0C, 0x0B, 0x0A, 0x09, 0x08,
            0x07, 0x06, 0x05, 0x04, 0x03, 0x02, 0x01, 0x00,
        ] as $c) {
            $s .= sprintf('%02X(%c)', $c, $c);
        }
        $s .= ']end';

        $calendar = new VCalendar();
        $property = $calendar->createProperty('X-PROP', $s);
        $result = $property->validate(Property::REPAIR);

        self::assertEquals('Property contained a control character (0x7f)', $result[0]['message']);
        self::assertEquals("chars[7F()5E(^)5C(\\\\)3B(\\;)3A(:)2C(\\,)22(\")20( )1F()1E()1D()1C()1B()1A()19()18()17()16()15()14()13()12()11()10()0F()0E()0D()0C()0B()0A(\\n)09(\t)08()07()06()05()04()03()02()01()00()]end", $property->getRawMimeDirValue());
    }

    /**
     * @throws InvalidDataException
     */
    public function testValidateBadPropertyName(): void
    {
        $calendar = new VCalendar();
        $property = $calendar->createProperty('X_*&PROP*', 'Bla');
        $result = $property->validate(Node::REPAIR);

        self::assertEquals('The property name: X_*&PROP* contains invalid characters. Only A-Z, 0-9 and - are allowed', $result[0]['message']);
        self::assertEquals('X-PROP', $property->name);
    }

    private const REPAIR_NAME_HEAD = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
        ."DTSTAMP:20261103T100000Z\r\n";

    public function testRepairEmptyNameIsNotReportedAsRepaired(): void
    {
        // The parser rejects "X.:y", a property built through the API is not.
        $vcal = Reader::read(self::REPAIR_NAME_HEAD."DTSTART:20261103T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $vcal->VEVENT->add('X.', 'y');
        $messages = $vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR);
        self::assertContains(3, array_column($messages, 'level'));
    }

    public function testRepairedNameGetsTheClassOfTheNewName(): void
    {
        $vcal = Reader::read(self::REPAIR_NAME_HEAD."X..DTSTART:x\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $messages = $vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR);
        self::assertContains(3, array_column($messages, 'level'));
        self::assertInstanceOf(DateTime::class, $vcal->VEVENT->DTSTART);
    }

    public function testRepairedNameIsValidAndStable(): void
    {
        $vcal = Reader::read(self::REPAIR_NAME_HEAD."X..DTSTART:20261103T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame([1], array_column($vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR), 'level'));
        self::assertInstanceOf(DateTime::class, $vcal->VEVENT->DTSTART);
        self::assertSame('X', $vcal->VEVENT->DTSTART->group);
        self::assertSame([], $vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR));
        self::assertSame([], Reader::read($vcal->serialize())->validate(Node::PROFILE_CALDAV));
    }

    #[DataProvider('boundaryNames')]
    public function testRepairNeverProducesAComponentBoundary(string $line): void
    {
        $vcal = Reader::read(self::REPAIR_NAME_HEAD."DTSTART:20260105T100000Z\r\n$line\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $messages = $vcal->validate(Node::PROFILE_CALDAV | Node::REPAIR);
        self::assertContains(3, array_column($messages, 'level'));
    }

    public static function boundaryNames(): array
    {
        return [['X..END:VEVENT'], ['X..BEGIN:VEVENT'], ['..END:VEVENT'], ['..BEGIN:VTODO']];
    }

    public function testRepairedNameIsFiledUnderTheNewName(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:x\r\nX..UID:x\r\nEND:VCARD\r\n");
        // Two UIDs (one of them grouped) cannot be merged: the cardinality
        // rule sees both of them.
        self::assertSame([1, 3], array_column($card->validate(Node::PROFILE_CARDDAV | Node::REPAIR), 'level'));
        self::assertCount(2, $card->select('UID'));
        self::assertSame([3], array_column(Reader::read($card->serialize())->validate(Node::PROFILE_CARDDAV), 'level'));

        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:x\r\nX..EMAIL:x@example.com\r\nEND:VCARD\r\n");
        self::assertSame([1], array_column($card->validate(Node::PROFILE_CARDDAV | Node::REPAIR), 'level'));
        self::assertCount(1, $card->select('EMAIL'));
        self::assertSame([], $card->validate(Node::PROFILE_CARDDAV | Node::REPAIR));
    }

    /**
     * @throws InvalidDataException
     */
    public function testGetValue(): void
    {
        $calendar = new VCalendar();
        $property = $calendar->createProperty('SUMMARY', null);
        self::assertEquals([], $property->getParts());
        self::assertNull($property->getValue());

        $property->setValue([]);
        self::assertEquals([], $property->getParts());
        self::assertNull($property->getValue());

        $property->setValue([1]);
        self::assertEquals([1], $property->getParts());
        self::assertEquals(1, $property->getValue());

        $property->setValue([1, 2]);
        self::assertEquals([1, 2], $property->getParts());
        self::assertEquals('1,2', $property->getValue());

        $property->setValue('str');
        self::assertEquals(['str'], $property->getParts());
        self::assertEquals('str', $property->getValue());
    }

    /**
     * ElementList should reject this.
     */
    public function testArrayAccessSetInt(): void
    {
        $this->expectException(\LogicException::class);
        $calendar = new VCalendar();
        $property = $calendar->createProperty('X-PROP', null);

        $calendar->add($property);
        $calendar->{'X-PROP'}[0] = 'Something!';
    }

    /**
     * ElementList should reject this.
     */
    public function testArrayAccessUnsetInt(): void
    {
        $this->expectException(\LogicException::class);
        $calendar = new VCalendar();
        $property = $calendar->createProperty('X-PROP', null);

        $calendar->add($property);
        unset($calendar->{'X-PROP'}[0]);
    }

    public function testValidateBadEncoding(): void
    {
        $document = new VCalendar();
        $property = $document->add('X-FOO', 'value');
        $property['ENCODING'] = 'invalid';

        $result = $property->validate();

        self::assertEquals('ENCODING=INVALID is not valid for this document type.', $result[0]['message']);
        self::assertEquals(3, $result[0]['level']);
    }

    public function testValidateBadEncodingVCard4(): void
    {
        $document = new VCard(['VERSION' => '4.0']);
        $property = $document->add('X-FOO', 'value');
        $property['ENCODING'] = 'BASE64';

        $result = $property->validate();

        self::assertEquals('ENCODING parameter is not valid in vCard 4.', $result[0]['message']);
        self::assertEquals(3, $result[0]['level']);
    }

    public function testValidateBadEncodingVCard3(): void
    {
        $document = new VCard(['VERSION' => '3.0']);
        $property = $document->add('X-FOO', 'value');
        $property['ENCODING'] = 'BASE64';

        $result = $property->validate();

        self::assertEquals('ENCODING=BASE64 is not valid for this document type.', $result[0]['message']);
        self::assertEquals(3, $result[0]['level']);

        // Validate the reparation of BASE64 formatted vCard v3
        $result = $property->validate(Property::REPAIR);

        self::assertEquals('ENCODING=BASE64 has been transformed to ENCODING=B.', $result[0]['message']);
        self::assertEquals(1, $result[0]['level']);
    }

    public function testValidateBadEncodingVCard21(): void
    {
        $document = new VCard(['VERSION' => '2.1']);
        $property = $document->add('X-FOO', 'value');
        $property['ENCODING'] = 'B';

        $result = $property->validate();

        self::assertEquals('ENCODING=B is not valid for this document type.', $result[0]['message']);
        self::assertEquals(3, $result[0]['level']);
    }

    public function testUnknownValuesWillBeIgnored(): void
    {
        $cal = new VCalendar();
        $property = $cal->createProperty('DTSTAMP', '20240101T000000Z', ['VALUE' => 'DATETIME']);

        self::assertEquals("DTSTAMP;VALUE=DATETIME:20240101T000000Z\r\n", $property->serialize());

        self::assertInstanceOf(DateTime::class, $property);
        self::assertCount(1, $property->parameters());
        self::assertInstanceOf(Parameter::class, $property->parameters['VALUE']);
        self::assertEquals('VALUE', $property->parameters['VALUE']->name);
        self::assertEquals('DATETIME', $property->parameters['VALUE']->getValue());
    }

    public function testSerializeKeepsFalsyGroups(): void
    {
        foreach (['3.0', '2.1'] as $version) {
            $card = Reader::read("BEGIN:VCARD\r\nVERSION:$version\r\n0.EMAIL:a@example.com\r\n..X-FOO:b\r\n.X-BAR:c\r\nEND:VCARD\r\n");
            $reread = Reader::read($card->serialize());
            self::assertSame('0', $reread->EMAIL->group);
            self::assertSame(
                array_map(fn ($p) => [$p->group, $p->name], $card->children()),
                array_map(fn ($p) => [$p->group, $p->name], $reread->children())
            );
            self::assertSame($card->serialize(), $reread->serialize());
            self::assertSame('0', $card->EMAIL->jsonSerialize()[1]->group);
        }
    }

    public function testRepairedNameWithValueOfNewClassInvalid(): void
    {
        $calendar = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260101T000000Z\r\nX..RRULE:foo\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $messages = $calendar->validate(Node::PROFILE_CALDAV | Node::REPAIR);

        self::assertContains(3, array_column($messages, 'level'));
        self::assertFalse(isset($calendar->VEVENT->RRULE));
    }

    public function testRepairedNameIntoARuleWithSeveralParts(): void
    {
        $calendar = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\nDTSTART:20260101T000000Z\r\nX..RRULE:FREQ=DAILY;COUNT=2\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $messages = $calendar->validate(Node::PROFILE_CALDAV | Node::REPAIR);

        self::assertNotContains(3, array_column($messages, 'level'));
        self::assertSame(['FREQ' => 'DAILY', 'COUNT' => '2'], $calendar->VEVENT->RRULE->getParts());
    }

    #[DataProvider('repairedNameProvider')]
    public function testRepairedNameDoesNotValidateTheReplacedProperty(string $line): void
    {
        $calendar = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20260101T000000Z\r\n$line\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $messages = $calendar->validate(Node::PROFILE_CALDAV | Node::REPAIR);

        self::assertNotContains(3, array_column($messages, 'level'));
        foreach ($messages as $message) {
            if ($message['node'] instanceof Property) {
                self::assertNotSame('.', ((string) $message['node']->name)[0] ?? '');
            }
        }
    }

    public static function repairedNameProvider(): array
    {
        return [
            ["DTSTART:20260101T000000Z\r\nX..Y;VALUE=RECUR:BYDAY=MO"],
            ['X..DTSTART;VALUE=DATE:20260101T090000Z'],
        ];
    }
}
