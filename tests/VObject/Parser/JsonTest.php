<?php

namespace Sabre\VObject\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject;
use Sabre\VObject\ParseException;

class JsonTest extends TestCase
{
    public function testRoundTripJCard(): void
    {
        $input = [
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
                    '-//Sabre//Sabre VObject '.VObject\Version::VERSION.'//EN',
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

        $parser = new Json(json_encode($input));
        $vobj = $parser->parse();

        $version = VObject\Version::VERSION;

        $result = $vobj->serialize();
        $expected = <<<VCF
BEGIN:VCARD
VERSION:4.0
PRODID:-//Sabre//Sabre VObject $version//EN
UID:foo
BDAY:1985-04-07
BDAY;X-PARAM=1,2;VALUE=DATE:1979-12-25
BDAY;VALUE=DATE-TIME:1979-12-25T02:00:00
REV:1995-10-31T22:27:10Z
LANG:nl
N:Last;First;Middle;;
item1.TEL:+1 555 123456
item1.X-AB-LABEL:Walkie Talkie
ADR:;;My Street,Left Side,Second Shack;Hometown;PA;18252;U.S.A
X-TRUNCATED;VALUE=DATE:--12-25
X-TIME-LOCAL;VALUE=TIME:123000
X-TIME-UTC;VALUE=TIME:123000Z
X-TIME-OFFSET;VALUE=TIME:123000-0800
X-TIME-REDUCED;VALUE=TIME:23
X-TIME-TRUNCATED;VALUE=TIME:--30
X-KARMA-POINTS;VALUE=INTEGER:42
X-GRADE;VALUE=FLOAT:1.3
TZ;VALUE=UTC-OFFSET:-0500
END:VCARD

VCF;
        self::assertEquals($expected, str_replace("\r", '', $result));

        self::assertEquals(
            $input,
            $vobj->jsonSerialize()
        );
    }

    public function testRoundTripJCal(): void
    {
        $input = [
            'vcalendar',
            [
                [
                    'version',
                    new \stdClass(),
                    'text',
                    '2.0',
                ],
                [
                    'prodid',
                    new \stdClass(),
                    'text',
                    '-//Sabre//Sabre VObject '.VObject\Version::VERSION.'//EN',
                ],
                [
                    'calscale',
                    new \stdClass(),
                    'text',
                    'GREGORIAN',
                ],
            ],
            [
                ['vevent',
                    [
                        [
                            'uid', new \stdClass(), 'text', 'foo',
                        ],
                        [
                            'dtstart', new \stdClass(), 'date', '2013-05-26',
                        ],
                        [
                            'duration', new \stdClass(), 'duration', 'P1D',
                        ],
                        [
                            'categories', new \stdClass(), 'text', 'home', 'testing',
                        ],
                        [
                            'created', new \stdClass(), 'date-time', '2013-05-26T18:10:00Z',
                        ],
                        [
                            'attach', new \stdClass(), 'binary', base64_encode('attachment'),
                        ],
                        [
                            'attendee', new \stdClass(), 'cal-address', 'mailto:armin@example.org',
                        ],
                        [
                            'attendee',
                            (object) [
                                'cn' => 'Dominik',
                                'partstat' => 'DECLINED',
                            ],
                            'cal-address',
                            'mailto:dominik@example.org',
                        ],
                        [
                            'geo', new \stdClass(), 'float', [51.96668, 7.61876],
                        ],
                        [
                            'sequence', new \stdClass(), 'integer', 5,
                        ],
                        [
                            'freebusy', new \stdClass(), 'period',  ['2013-05-26T21:02:13Z', 'PT1H'], ['2013-06-26T12:00:00Z', '2013-06-26T13:00:00Z'],
                        ],
                        [
                            'url', new \stdClass(), 'uri', 'http://example.org/',
                        ],
                        [
                            'tzoffsetfrom', new \stdClass(), 'utc-offset', '+05:00',
                        ],
                        [
                            'rrule', new \stdClass(), 'recur', [
                                'freq' => 'WEEKLY',
                                'byday' => ['MO', 'TU'],
                            ],
                        ],
                        [
                            'x-bool', new \stdClass(), 'boolean', true,
                        ],
                        [
                            'x-time', new \stdClass(), 'time', '08:00:00',
                        ],
                        [
                            'request-status',
                            new \stdClass(),
                            'text',
                            ['2.0', 'Success'],
                        ],
                        [
                            'request-status',
                            new \stdClass(),
                            'text',
                            ['3.7', 'Invalid Calendar User', 'ATTENDEE:mailto:jsmith@example.org'],
                        ],
                    ],
                    [
                        ['valarm',
                            [
                                [
                                    'action', new \stdClass(), 'text', 'DISPLAY',
                                ],
                            ],
                            [],
                        ],
                    ],
                ],
            ],
        ];

        $parser = new Json(json_encode($input));
        $vobj = $parser->parse();
        $result = $vobj->serialize();

        $version = VObject\Version::VERSION;

        $expected = <<<VCF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject $version//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:foo
DTSTART;VALUE=DATE:20130526
DURATION:P1D
CATEGORIES:home,testing
CREATED:20130526T181000Z
ATTACH;VALUE=BINARY:YXR0YWNobWVudA==
ATTENDEE:mailto:armin@example.org
ATTENDEE;CN=Dominik;PARTSTAT=DECLINED:mailto:dominik@example.org
GEO:51.96668;7.61876
SEQUENCE:5
FREEBUSY:20130526T210213Z/PT1H,20130626T120000Z/20130626T130000Z
URL;VALUE=URI:http://example.org/
TZOFFSETFROM:+0500
RRULE:FREQ=WEEKLY;BYDAY=MO,TU
X-BOOL;VALUE=BOOLEAN:TRUE
X-TIME;VALUE=TIME:080000
REQUEST-STATUS:2.0;Success
REQUEST-STATUS:3.7;Invalid Calendar User;ATTENDEE:mailto:jsmith@example.org
BEGIN:VALARM
ACTION:DISPLAY
END:VALARM
END:VEVENT
END:VCALENDAR

VCF;
        self::assertEquals($expected, str_replace("\r", '', $result));

        self::assertEquals(
            $input,
            $vobj->jsonSerialize()
        );
    }

    public function testParseStreamArg(): void
    {
        $input = [
            'vcard',
            [
                [
                    'FN', new \stdClass(), 'text', 'foo',
                ],
            ],
        ];

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, json_encode($input));
        rewind($stream);

        $result = VObject\Reader::readJson($stream, 0);
        self::assertEquals('foo', $result->FN->getValue());
    }

    public function testParseInvalidData(): void
    {
        $this->expectException(ParseException::class);
        $json = new Json();
        $input = [
            'vlist',
            [
                [
                    'FN', new \stdClass(), 'text', 'foo',
                ],
            ],
        ];

        $json->parse(json_encode($input), 0);
    }

    #[DataProvider('invalidNames')]
    public function testParseInvalidName(array $input): void
    {
        $this->expectException(ParseException::class);
        (new Json())->parse(json_encode($input));
    }

    public static function invalidNames(): array
    {
        $prop = fn (string $name, array $parameters = []) => [$name, (object) $parameters, 'text', 'foo'];

        return [
            'property named end' => [['vcalendar', [$prop('end')]]],
            'property named begin' => [['vcalendar', [], [['vevent', [$prop('begin')]]]]],
            'line break in a property name' => [['vcalendar', [$prop("x-foo:bar\r\nEND")]]],
            'line break in a group' => [['vcard', [$prop('fn', ['group' => "a\r\nEND:VCARD\r\nX"])]]],
            'line break in a component name' => [['vcalendar', [], [["vevent\r\nX-FOO:bar", []]]]],
            'trailing line feed in a property name' => [['vcalendar', [], [['vevent', [$prop("x-a\n")]]]]],
            'trailing line feed in a group' => [['vcard', [$prop('email', ['group' => "item1\n"])]]],
            'trailing line feed in a component name' => [['vcalendar', [], [["vevent\n", []]]]],
            'nested vcalendar' => [['vcalendar', [], [['vevent', [], [['vcalendar', []]]]]]],
            'nested vcard' => [['vcard', [], [['vcard', []]]]],
        ];
    }

    #[DataProvider('malformedDocuments')]
    public function testParseMalformedDocument(string $input): void
    {
        $this->expectException(ParseException::class);
        (new Json())->parse($input);
    }

    public static function malformedDocuments(): array
    {
        $documents = [
            // Structure
            'root is not an array' => '"vcalendar"',
            'empty root' => '[]',
            'root without properties' => '["vcalendar"]',
            'root properties are not a list' => '["vcalendar","x"]',
            'root components are not a list' => '["vcalendar",[],"x"]',
            'component is a string' => '["vcalendar",[],["x"]]',
            'component is an integer' => '["vcalendar",[],[1]]',
            'component without properties' => '["vcalendar",[],[["vevent"]]]',
            'component properties are an object' => '["vcalendar",[],[["vevent",{}]]]',
            'sub-components are a string' => '["vcalendar",[],[["vevent",[],"x"]]]',
            'property is a string' => '["vcalendar",["x"]]',
            'property without parameters' => '["vcalendar",[["summary"]]]',
            'property without value type' => '["vcalendar",[["summary",{}]]]',
            'property name is an object' => '["vcalendar",[[{},{},"text","a"]]]',
            'value type is an object' => '["vcalendar",[["summary",{},{},"a"]]]',
            'value type is an array' => '["vcalendar",[["summary",{},[],"a"]]]',
            // Parameters
            'parameter is an object' => '["vcalendar",[["summary",{"x-a":{"b":1}},"text","a"]]]',
            'parameter is a list of objects' => '["vcalendar",[["summary",{"x-a":[{"b":1}]},"text","a"]]]',
            'line break in a parameter name' => '["vcalendar",[],[["vevent",[["uid",{},"text","a"],["summary",{"x-a=1:b\\r\\nATTENDEE;X-B":"1"},"text","c"]]]]]',
            'trailing line feed in a parameter name' => '["vcard",[["tel",{"fr\\n":"1"},"text","1"]]]',
            'colon in a parameter name' => '["vcard",[["email",{"g|o:":"item1"},"text","a@example.com"]]]',
            'empty parameter name' => '["vcard",[["version",{},"text","4.0"],["tel",{"":["a","b"]},"text","1"]]]',
            'parameter is a nested list' => '["vcalendar",[["summary",{"x-a":[["b"]]},"text","a"]]]',
            // Values
            'text is an object' => '["vcalendar",[["summary",{},"text",{"a":"b"}]]]',
            'text is nested too deep' => '["vcalendar",[["summary",{},"text",[[["a"]]]]]]',
            'nested list in a multi-valued text' => '["vcalendar",[],[["vevent",[["summary",{},"text","b",["c",["d"]]]]]]]',
            'list in a multi-valued text' => '["vcalendar",[],[["vevent",[["categories",{},"text","b",["c"]]]]]]',
            'nested list in a text that is not structured' => '["vcard",[["version",{},"text",["4.0",["x"]]]]]',
            'second structured text value' => '["vcard",[["adr",{},"text",["a"],["b"]]]]',
            'no value (text)' => '["vcalendar",[],[["vevent",[["summary",{},"text"]]]]]',
            'no value (uri)' => '["vcalendar",[],[["vevent",[["url",{},"uri"]]]]]',
            'no value (date-time)' => '["vcalendar",[],[["vevent",[["dtstamp",{},"date-time"]]]]]',
            'no value (date-and-or-time)' => '["vcard",[["bday",{},"date-and-or-time"]]]',
            'two integers' => '["vcalendar",[],[["vevent",[["priority",{},"integer",1,2]]]]]',
            'two binaries' => '["vcard",[["key",{},"binary","YQ==","Yg=="]]]',
            'two booleans' => '["vcalendar",[["x-a",{},"boolean",true,false]]]',
            'two float lists' => '["vcalendar",[],[["vevent",[["geo",{},"float",[1,2],[3]]]]]]',
            'second text value is an object' => '["vcalendar",[["summary",{},"text","a",{"a":"b"}]]]',
            'structured text has an object' => '["vcard",[["n",{},"text",["a",{"b":1}]]]]',
            'structured text is nested too deep' => '["vcard",[["adr",{},"text",["a",["b",["c"]]]]]]',
            'uri is an object' => '["vcalendar",[["url",{},"uri",{"a":"b"}]]]',
            'cal-address is an object' => '["vcalendar",[],[["vevent",[["attendee",{},"cal-address",{"a":1}]]]]]',
            'unknown is an object' => '["vcalendar",[["x-a",{},"unknown",{"a":1}]]]',
            'date-time is an object' => '["vcalendar",[],[["vevent",[["dtstart",{},"date-time",{"a":"b"}]]]]]',
            'date-time is an array' => '["vcalendar",[],[["vevent",[["dtstart",{},"date-time",["2026"]]]]]]',
            'duration is a nested array' => '["vcalendar",[],[["vevent",[["duration",{},"duration",[["a"]]]]]]]',
            'duration is an object' => '["vcalendar",[],[["vevent",[["duration",{},"duration",{"a":1}]]]]]',
            'period is a string' => '["vcalendar",[],[["vevent",[["freebusy",{},"period","a"]]]]]',
            'period is an object' => '["vcalendar",[],[["vevent",[["freebusy",{},"period",{"a":1}]]]]]',
            'recur has an object part' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":{"a":1}}]]]]]',
            'recur has a multi-valued until' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":"daily","until":["a","b"]}]]]]]',
            'recur is an integer' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",1]]]]]',
            'recur has two values' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":"daily"},{"freq":"daily"}]]]]]',
            'line break in a uri' => '["vcalendar",[],[["vevent",[["url",{},"uri","https://a.example/\\nATTENDEE:mailto:b@example.com"]]]]]',
            'line break in a date-time' => '["vcalendar",[],[["vevent",[["dtstamp",{},"date-time","2008-02-05T1\\nATTENDEE:mailto:b@example.com\\n9:12:24Z"]]]]]',
            'line break in a duration' => '["vcalendar",[],[["vevent",[["duration",{},"duration","PT1H\\r\\nBEGIN:VEVENT"]]]]]',
            'line break in a period' => '["vcalendar",[],[["vevent",[["rdate",{},"period",["2026-01-01T00:00:00Z","PT1H\\nX-A:b"]]]]]]',
            'line break in a recur part' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":"WEEK\\nATTENDEE:mailto:b@example.com\\nLY"}]]]]]',
            'line break in a recur list' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":"WEEKLY","byday":["MO","TU\\nX-A:b"]}]]]]]',
            'line break in a recur string' => '["vcalendar",[],[["vevent",[["rrule",{},"recur","FREQ=DAILY\\nX-A:b"]]]]]',
            'semicolon in a recur part' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq":"DAILY;COUNT=2"}]]]]]',
            'invalid recur part name' => '["vcalendar",[],[["vevent",[["rrule",{},"recur",{"freq;count":"DAILY"}]]]]]',
            'line break in a date-and-or-time' => '["vcard",[["bday",{},"date-and-or-time","2000\\nX-A:b"]]]',
            'line break in a uri-typed version' => '["vcard",[["version",{},"uri","4.0\\nX-A:b"]]]',
            'utc-offset is an object' => '["vcalendar",[],[["vtimezone",[],[["standard",[["tzoffsetfrom",{},"utc-offset",{"a":1}]]]]]]]',
            'binary is an object' => '["vcard",[["photo",{},"binary",{"a":1}]]]',
            'binary is an array' => '["vcalendar",[["attach",{},"binary",["a"]]]]',
            'time is an object' => '["vcard",[["bday",{},"time",{"a":1}]]]',
            'date is an object' => '["vcard",[["bday",{},"date",{"a":1}]]]',
            'timestamp is an object' => '["vcard",[["rev",{},"timestamp",{"a":1}]]]',
            'integer is an object' => '["vcalendar",[],[["vevent",[["sequence",{},"integer",{"a":1}]]]]]',
            'float is an object' => '["vcalendar",[],[["vevent",[["geo",{},"float",{"a":1}]]]]]',
            'language-tag is an object' => '["vcard",[["lang",{},"language-tag",{"a":1}]]]',
        ];

        return array_map(fn (string $document) => [$document], $documents);
    }

    public function testParseValidValueShapes(): void
    {
        $vcard = (new Json())->parse('["vcard",[["n",{"x-a":["b","c"]},"text",["Doe",["John","J"],"","",""]],'
            .'["geo",{},"float",[51.96668,7.61876]],["x-a",{"x-b":null},"text",null]]]');

        self::assertSame(['Doe', ['John', 'J'], '', '', ''], $vcard->N->getParts());
        self::assertSame("N;X-A=b,c:Doe;John,J;;;\r\n", $vcard->N->serialize());
        self::assertSame('51.96668;7.61876', (string) $vcard->GEO);
    }

    public function testValueTypeOfARegisteredClass(): void
    {
        $uriClass = new class(new VObject\Component\VCalendar(), 'URL') extends VObject\Property\Uri {};
        $valueMap = VObject\Component\VCalendar::$valueMap;
        VObject\Component\VCalendar::$valueMap['URI'] = $uriClass::class;
        try {
            $cal = (new Json())->parse('["vcalendar",[],[["vevent",[["uid",{},"text","a"],'
                .'["url",{},"uri","https://e.com/a"],["conference",{},"uri","https://e.com/b"],'
                .'["dtstart",{},"date","2026-01-01"]]]]]');
        } finally {
            VObject\Component\VCalendar::$valueMap = $valueMap;
        }

        // URI is the default type of URL, not of CONFERENCE.
        self::assertInstanceOf($uriClass::class, $cal->VEVENT->URL);
        self::assertFalse(isset($cal->VEVENT->URL['VALUE']));
        self::assertSame('URI', (string) $cal->VEVENT->CONFERENCE['VALUE']);
        self::assertSame('DATE', (string) $cal->VEVENT->DTSTART['VALUE']);
    }

    public function testLoneNullValueGivesADocumentThatRoundTrips(): void
    {
        $documents = [
            '["vcard",[["version",{},"text","4.0"],["fn",{},"text","A"],["bday",{},"date-and-or-time",null]]]',
            '["vcard",[["version",{},"text","4.0"],["fn",{},"text","A"],["url",{},"uri",null]]]',
            '["vcard",[["version",{},"text","4.0"],["fn",{},"text","A"],["x-a",{},"text",null]]]',
            '["vcalendar",[["version",{},"text","2.0"],["prodid",{},"text","p"]],[["vevent",[["uid",{},"text","a"],["dtstamp",{},"date-time","2026-01-01T00:00:00Z"],["dtstart",{},"date-time","2026-01-01T00:00:00Z"],["x-a",{},"uri",null]]]]]',
        ];
        foreach ($documents as $json) {
            $document = VObject\Reader::readJson($json);
            $document->validate();
            $again = json_encode($document->jsonSerialize(), JSON_THROW_ON_ERROR);
            self::assertSame($again, json_encode(VObject\Reader::readJson($again)->jsonSerialize(), JSON_THROW_ON_ERROR));
        }
    }

    #[DataProvider('emptyListValues')]
    public function testAnEmptyListIsNotAValue(string $property): void
    {
        $this->expectException(ParseException::class);
        VObject\Reader::readJson('["vcalendar",[],[["vevent",[["uid",{},"text","a"],'.$property.']]]]');
    }

    public static function emptyListValues(): array
    {
        return [
            'text' => ['["summary",{},"text",[]]'],
            'uri' => ['["x-a",{},"uri",[]]'],
            'cal-address' => ['["attendee",{},"cal-address",[]]'],
            'unknown' => ['["x-b",{},"unknown",[]]'],
            'float' => ['["x-c",{},"float",[]]'],
            'geo' => ['["geo",{},"float",[]]'],
        ];
    }

    public function testControlCharacterInStoredUriSurvivesJCardRoundTrip(): void
    {
        $vcard = VObject\Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nUID:a\r\nURL:http://a/\x1Cb\r\nEND:VCARD\r\n");
        self::assertContains(3, array_column($vcard->validate(VObject\Node::PROFILE_CARDDAV), 'level'));

        $vcard->validate(VObject\Node::PROFILE_CARDDAV | VObject\Node::REPAIR);

        self::assertSame('http://a/b', $vcard->URL->getValue());
        self::assertSame('http://a/b', (string) VObject\Reader::readJson(json_encode($vcard->jsonSerialize(), JSON_THROW_ON_ERROR))->URL);
    }

    public function testDateTimeOfAnUnknownPropertyKeepsAnUnresolvedTzid(): void
    {
        $vcal = VObject\Reader::readJson('["vcalendar",[],[["vevent",[["x-a",{"tzid":"Foo/Bar"},"date-time","2026-11-03T11:00:00"]],[]]]]');
        $property = $vcal->VEVENT->{'X-A'};

        self::assertSame('20261103T110000', $property->getValue());
        self::assertSame('Foo/Bar', (string) $property['TZID']);
        self::assertSame('DATE-TIME', (string) $property['VALUE']);
    }

    public function testDateTimeOfAnUnknownPropertyKeepsTheNameOfACustomVtimezone(): void
    {
        $vcal = VObject\Reader::readJson('["vcalendar",[],[["vevent",[["x-a",{"tzid":"Customized Time Zone"},"date-time","2026-11-03T11:00:00"]],[]],'
            .'["vtimezone",[["tzid",{},"text","Customized Time Zone"]],[["standard",[["dtstart",{},"date-time","1970-01-01T00:00:00"],["tzoffsetfrom",{},"utc-offset","+01:00"],["tzoffsetto",{},"utc-offset","+01:00"]],[]]]]]]');

        self::assertSame('Customized Time Zone', (string) $vcal->VEVENT->{'X-A'}['TZID']);
        self::assertSame('20261103T110000', $vcal->VEVENT->{'X-A'}->getValue());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function overflowingNumbers(): array
    {
        return [
            'text value' => ['["note",{},"text",1e999]'],
            'negative text value' => ['["note",{},"text",-1e999]'],
            'text list' => ['["categories",{},"text",["a",1e999]]'],
            'structured text' => ['["n",{},"text",["a",1e999,"","",""]]'],
            'parameter' => ['["note",{"x-a":1e999},"text","a"]'],
            'parameter list' => ['["note",{"x-a":["b",1e999]},"text","a"]'],
            'unknown value' => ['["x-a",{},"unknown",1e999]'],
            'integer value' => ['["x-a",{},"integer",1e999]'],
            'date value' => ['["bday",{},"date",1e999]'],
        ];
    }

    #[DataProvider('overflowingNumbers')]
    public function testOverflowingJsonNumberIsRefused(string $property): void
    {
        $this->expectException(ParseException::class);
        VObject\Reader::readJson('["vcard",[["version",{},"text","4.0"],["fn",{},"text","a"],'.$property.']]');
    }

    public function testOverflowingJsonNumberIsRefusedInARecurrenceRule(): void
    {
        foreach (['{"freq":"daily","until":1e999}', '{"freq":"daily","count":1e999}'] as $rule) {
            try {
                VObject\Reader::readJson('["vcalendar",[["version",{},"text","2.0"]],[["vevent",[["uid",{},"text","a"],["rrule",{},"recur",'.$rule.']]]]]');
                self::fail('Not refused: '.$rule);
            } catch (ParseException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLargeFiniteJsonNumberIsKept(): void
    {
        $card = VObject\Reader::readJson('["vcard",[["version",{},"text","4.0"],["fn",{},"text","a"],["note",{},"text",1e9]]]');

        self::assertNotFalse(json_encode($card->jsonSerialize()));
    }

    public function testNullUntilIsStillRefused(): void
    {
        $this->expectException(ParseException::class);
        VObject\Reader::readJson('["vcalendar",[["version",{},"text","2.0"]],[["vevent",[["uid",{},"text","a"],["rrule",{},"recur",{"freq":"daily","until":null}]]]]]');
    }

    public function testSeveralTimeValuesAreRefused(): void
    {
        $this->expectException(ParseException::class);
        VObject\Reader::readJson('["vcalendar",[],[["vevent",[["x-t",{},"time","10:00:00","11:00:00"]],[]]]]');
    }

    public function testOneTimeValueIsKept(): void
    {
        $calendar = VObject\Reader::readJson('["vcalendar",[],[["vevent",[["x-t",{},"time","10:00:00"]],[]]]]');

        self::assertSame("X-T;VALUE=TIME:100000\r\n", $calendar->VEVENT->{'X-T'}->serialize());
    }
}
