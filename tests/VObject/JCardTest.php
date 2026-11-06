<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JCardTest extends TestCase
{
    public function testToJCard(): void
    {
        $card = new Component\VCard([
            'VERSION' => '4.0',
            'UID' => 'foo',
            'BDAY' => '19850407',
            'REV' => '19951031T222710Z',
            'LANG' => 'nl',
            'N' => ['Last', 'First', 'Middle', '', ''],
            'item1.TEL' => '+1 555 123456',
            'item1.X-AB-LABEL' => 'Walkie Talkie',
            'ADR' => [
                '',
                '',
                ['My Street', 'Left Side', 'Second Shack'],
                'Hometown',
                'PA',
                '18252',
                'U.S.A',
            ],
        ]);

        $card->add('BDAY', '1979-12-25', ['VALUE' => 'DATE', 'X-PARAM' => [1, 2]]);
        $card->add('BDAY', '1979-12-25T02:00:00', ['VALUE' => 'DATE-TIME']);

        $card->add('X-TRUNCATED', '--1225', ['VALUE' => 'DATE']);
        $card->add('X-TIME-LOCAL', '123000', ['VALUE' => 'TIME']);
        $card->add('X-TIME-UTC', '12:30:00Z', ['VALUE' => 'TIME']);
        $card->add('X-TIME-OFFSET', '12:30:00-08:00', ['VALUE' => 'TIME']);
        $card->add('X-TIME-REDUCED', '23', ['VALUE' => 'TIME']);
        $card->add('X-TIME-TRUNCATED', '--30', ['VALUE' => 'TIME']);

        $card->add('X-KARMA-POINTS', '42', ['VALUE' => 'INTEGER']);
        $card->add('X-GRADE', '1.3', ['VALUE' => 'FLOAT']);

        $card->add('TZ', '-0500', ['VALUE' => 'UTC-OFFSET']);

        $expected = [
            'vcard',
            [
                [
                    'version',
                    new \stdClass(),
                    'text',
                    '4.0',
                ],
                [
                    'prodid',
                    new \stdClass(),
                    'text',
                    '-//Sabre//Sabre VObject '.Version::VERSION.'//EN',
                ],
                [
                    'uid',
                    new \stdClass(),
                    'text',
                    'foo',
                ],
                [
                    'bday',
                    new \stdClass(),
                    'date-and-or-time',
                    '1985-04-07',
                ],
                [
                    'bday',
                    (object) [
                        'x-param' => [1, 2],
                    ],
                    'date',
                    '1979-12-25',
                ],
                [
                    'bday',
                    new \stdClass(),
                    'date-time',
                    '1979-12-25T02:00:00',
                ],
                [
                    'rev',
                    new \stdClass(),
                    'timestamp',
                    '1995-10-31T22:27:10Z',
                ],
                [
                    'lang',
                    new \stdClass(),
                    'language-tag',
                    'nl',
                ],
                [
                    'n',
                    new \stdClass(),
                    'text',
                    ['Last', 'First', 'Middle', '', ''],
                ],
                [
                    'tel',
                    (object) [
                        'group' => 'item1',
                    ],
                    'text',
                    '+1 555 123456',
                ],
                [
                    'x-ab-label',
                    (object) [
                        'group' => 'item1',
                    ],
                    'unknown',
                    'Walkie Talkie',
                ],
                [
                    'adr',
                    new \stdClass(),
                    'text',
                    [
                        '',
                        '',
                        ['My Street', 'Left Side', 'Second Shack'],
                        'Hometown',
                        'PA',
                        '18252',
                        'U.S.A',
                    ],
                ],
                [
                    'x-truncated',
                    new \stdClass(),
                    'date',
                    '--12-25',
                ],
                [
                    'x-time-local',
                    new \stdClass(),
                    'time',
                    '12:30:00',
                ],
                [
                    'x-time-utc',
                    new \stdClass(),
                    'time',
                    '12:30:00Z',
                ],
                [
                    'x-time-offset',
                    new \stdClass(),
                    'time',
                    '12:30:00-08:00',
                ],
                [
                    'x-time-reduced',
                    new \stdClass(),
                    'time',
                    '23',
                ],
                [
                    'x-time-truncated',
                    new \stdClass(),
                    'time',
                    '--30',
                ],
                [
                    'x-karma-points',
                    new \stdClass(),
                    'integer',
                    42,
                ],
                [
                    'x-grade',
                    new \stdClass(),
                    'float',
                    1.3,
                ],
                [
                    'tz',
                    new \stdClass(),
                    'utc-offset',
                    '-05:00',
                ],
            ],
        ];

        self::assertEquals($expected, $card->jsonSerialize());
    }

    #[DataProvider('valuesJsonSerializeCannotFormat')]
    public function testValidateReportsValuesJsonSerializeCannotFormat(string $property): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\n$property\r\nEND:VCARD\r\n");

        $errors = array_filter($card->validate(Node::REPAIR), fn ($m) => 3 === $m['level']);
        self::assertCount(1, $errors);
    }

    public static function valuesJsonSerializeCannotFormat(): array
    {
        return [
            'timestamp' => ['REV:2009-08-08T14::11'],
            'time' => ['X-A;VALUE=TIME:--02-03'],
        ];
    }

    public function testValidTimestampAndTimeAreNotReported(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nREV:20090808T141100Z\r\n"
            ."X-A;VALUE=TIME:141100\r\nEND:VCARD\r\n");

        self::assertSame([], $card->validate());
        self::assertIsString(json_encode($card->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testGroupParameterIsRejected(): void
    {
        $this->expectException(ParseException::class);
        Reader::readJson('["vcard",[["version",{},"text","4.0"],["fn",{},"text","a"],'
            .'["email",{"GROUP":"item1"},"text","a@example.com"]]]');
    }

    public function testGroupParameterIsNotTheGroup(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nEMAIL;GROUP=item|1:a@example.com\r\n"
            ."item2.TEL;GROUP=item1:1\r\nEND:VCARD\r\n");
        $again = Reader::readJson(json_encode($card->jsonSerialize()));
        self::assertNull($again->EMAIL->group);
        self::assertSame('ITEM2', strtoupper((string) $again->TEL->group));
    }

    #[DataProvider('unknownValues')]
    public function testUnknownValueRoundTrip(string $value): void
    {
        $json = '["vcard",[["version",{},"text","4.0"],["fn",{},"text","a"],["x-a",{},"unknown",'.json_encode($value).']]]';
        $card = Reader::readJson($json);
        self::assertSame([$value], $card->{'X-A'}->getJsonValue());
        self::assertSame("X-A:$value\r\n", $card->{'X-A'}->serialize());

        $again = Reader::readJson(json_encode($card->jsonSerialize()));
        self::assertSame([$value], $again->{'X-A'}->getJsonValue());
    }

    public static function unknownValues(): array
    {
        return [['b,c'], ['b\\,c'], ['b\\;c'], ['a\\nb']];
    }

    public function testUnknownValueMimeDirRoundTripThroughJCal(): void
    {
        $cal = Reader::read("BEGIN:VCALENDAR\r\nX-A:a\\,b,c\r\nN:X;;;;\r\nEND:VCALENDAR\r\n");
        $again = Reader::readJson(json_encode($cal->jsonSerialize()));
        self::assertSame($cal->serialize(), $again->serialize());
        self::assertSame(['a,b', 'c'], $again->{'X-A'}->getParts());
        self::assertSame(['X', '', '', '', ''], $again->N->getParts());
    }

    public function testNestedComponentDoesNotBreakJCard(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:x\r\nUID:u\r\nBEGIN:VEVENT\r\nUID:a\r\nEND:VEVENT\r\nEND:VCARD\r\n");
        $again = Reader::readJson(json_encode($card->jsonSerialize()));
        self::assertInstanceOf(Component\VCard::class, $again);
        self::assertSame('u', (string) $again->UID);

        $messages = $card->validate(Node::PROFILE_CARDDAV);
        self::assertCount(1, $messages);
        self::assertSame(3, $messages[0]['level']);

        $messages = $card->validate(Node::PROFILE_CARDDAV | Node::REPAIR);
        self::assertCount(1, $messages);
        self::assertSame(1, $messages[0]['level']);
        self::assertSame([], $card->getComponents());
        self::assertSame([], $card->validate(Node::PROFILE_CARDDAV));
    }

    public function testEmptyGroupCanBeReadBack(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:a\r\n.NOTE:x\r\nEND:VCARD\r\n");

        $again = Reader::readJson(json_encode($card->jsonSerialize()));

        self::assertSame('x', (string) $again->NOTE);
        self::assertNull($again->NOTE->group);
    }

    public function testEmptyGroupIsReportedAndRepaired(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:a\r\n.NOTE:x\r\nEND:VCARD\r\n");
        self::assertContains('The property NOTE has an empty group', array_column($card->validate(), 'message'));

        $card->validate(Node::REPAIR);

        self::assertNull($card->NOTE->group);
        self::assertStringContainsString("\r\nNOTE:x\r\n", $card->serialize());
    }

    public function testGroupZeroIsKept(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:a\r\n0.NOTE:x\r\nEND:VCARD\r\n");
        $again = Reader::readJson(json_encode($card->jsonSerialize()));

        self::assertSame('0', $again->NOTE->group);
    }

    public function testTimeHourMinuteKeepsItsMinutes(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\nX-A;VALUE=TIME:10:22\r\nEND:VCARD\r\n");

        self::assertSame('10:22', $card->{'X-A'}->jsonSerialize()[3]);
    }
}
