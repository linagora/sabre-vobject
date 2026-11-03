<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\TestCase;
use Sabre\VObject;

class FloatTest extends TestCase
{
    public function testMimeDir(): void
    {
        $input = "BEGIN:VCARD\r\nVERSION:4.0\r\nX-FLOAT;VALUE=FLOAT:0.234;1.245\r\nEND:VCARD\r\n";
        $mimeDir = new VObject\Parser\MimeDir($input);

        $result = $mimeDir->parse($input);

        self::assertInstanceOf(FloatValue::class, $result->{'X-FLOAT'});

        self::assertEquals([
            0.234,
            1.245,
        ], $result->{'X-FLOAT'}->getParts());

        self::assertEquals(
            $input,
            $result->serialize()
        );
    }

    public function testInfiniteFloatIsReported(): void
    {
        $cal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20261103T100000Z\r\nDTSTART:20261103T100000Z\r\nGEO:1e999;0\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $errors = array_filter($cal->validate(), fn ($m) => 3 === $m['level']);
        self::assertCount(1, $errors);

        $cal = VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20261103T100000Z\r\nDTSTART:20261103T100000Z\r\nGEO:1.5;-2\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame([], $cal->validate());
    }

    public function testInfiniteFloatIsRejectedByTheJsonParser(): void
    {
        $this->expectException(VObject\ParseException::class);
        VObject\Reader::readJson('["vcard",[["version",{},"text","4.0"],["fn",{},"text","a"],["geo",{},"float",["1e999","0"]]]]');
    }
}
