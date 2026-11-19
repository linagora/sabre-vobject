<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

class ParameterTest extends TestCase
{
    public function testSetup(): void
    {
        $cal = new Component\VCalendar();

        $param = new Parameter($cal, 'name', 'value');
        self::assertEquals('NAME', $param->name);
        self::assertEquals('value', $param->getValue());
    }

    public function testSetupNameLess(): void
    {
        $card = new Component\VCard();

        $param = new Parameter($card, null, 'URL');
        self::assertEquals('VALUE', $param->name);
        self::assertEquals('URL', $param->getValue());
        self::assertTrue($param->noName);
    }

    public function testModify(): void
    {
        $cal = new Component\VCalendar();

        $param = new Parameter($cal, 'name', null);
        $param->addValue(1);
        self::assertEquals([1], $param->getParts());

        $param->setParts([1, 2]);
        self::assertEquals([1, 2], $param->getParts());

        $param->addValue(3);
        self::assertEquals([1, 2, 3], $param->getParts());

        $param->setValue(4);
        $param->addValue(5);
        self::assertEquals([4, 5], $param->getParts());
    }

    public function testCastToString(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', 'value');
        self::assertEquals('value', $param->__toString());
        self::assertEquals('value', (string) $param);
    }

    public function testCastNullToString(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', null);
        self::assertEquals('', $param->__toString());
        self::assertEquals('', (string) $param);
    }

    public function testSerialize(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', 'value');
        self::assertEquals('NAME=value', $param->serialize());
    }

    public function testSerializeEmpty(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', null);
        self::assertEquals('NAME=', $param->serialize());
    }

    public function testSerializeComplex(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', ['val1', 'val2;', 'val3^', "val4\n", 'val5"']);
        self::assertEquals('NAME=val1,"val2;","val3^^","val4^n","val5^\'"', $param->serialize());
    }

    /**
     * iCal 7.0 (OSX 10.9) has major issues with the EMAIL property, when the
     * value contains a plus sign, and it's not quoted.
     *
     * So we specifically added support for that.
     */
    public function testSerializePlusSign(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'EMAIL', 'user+something@example.org');
        self::assertEquals('EMAIL="user+something@example.org"', $param->serialize());
    }

    public function testIterate(): void
    {
        $cal = new Component\VCalendar();

        $param = new Parameter($cal, 'name', [1, 2, 3, 4]);
        $result = [];

        foreach ($param as $value) {
            $result[] = $value;
        }

        self::assertEquals([1, 2, 3, 4], $result);
    }

    public function testSerializeColon(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', 'va:lue');
        self::assertEquals('NAME="va:lue"', $param->serialize());
    }

    public function testSerializeSemiColon(): void
    {
        $cal = new Component\VCalendar();
        $param = new Parameter($cal, 'name', 'va;lue');
        self::assertEquals('NAME="va;lue"', $param->serialize());
    }

    public function testBareCarriageReturnInParameterValueIsNotSerialized(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nNOTE;X-A=a\rb:x\r\nEND:VCARD\r\n");
        self::assertSame('ab', $card->NOTE['X-A']->getValue());

        $card->NOTE['X-B'] = "c\rd";
        $out = $card->serialize();
        self::assertSame(substr_count($out, "\r"), substr_count($out, "\r\n"));
        self::assertStringContainsString('NOTE;X-A=ab;X-B=cd:x', $out);
    }

    public function testValuelessParameterRoundTrip(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nEMAIL;FOO:a@example.com\r\nEND:VCARD\r\n");
        self::assertSame([''], $card->EMAIL['FOO']->getParts());
        $reread = Reader::read($card->serialize());
        self::assertSame($card->EMAIL['FOO']->getParts(), $reread->EMAIL['FOO']->getParts());
        self::assertSame($card->serialize(), $reread->serialize());
    }

    public function testValidateReportsNonUtf8Value(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\n"
            ."DTSTAMP:20261103T100000Z\r\nDTSTART:20261103T100000Z\r\nSUMMARY;X-P=\xFF;X-Q=a,b\xE9:a\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        $messages = $vcal->validate();
        self::assertCount(2, $messages);
        self::assertSame(3, $messages[0]['level']);

        $messages = $vcal->validate(Node::REPAIR);
        self::assertCount(2, $messages);
        self::assertSame(1, $messages[0]['level']);
        self::assertSame("\u{FF}", (string) $vcal->VEVENT->SUMMARY['X-P']);
        self::assertSame(['a', "b\u{E9}"], $vcal->VEVENT->SUMMARY['X-Q']->getParts());
        self::assertSame([], $vcal->validate());
        self::assertIsString(json_encode($vcal->jsonSerialize(), JSON_THROW_ON_ERROR));
    }

    public function testSerializeManyValuesIsLinear(): void
    {
        $cal = new Component\VCalendar();
        $prop = $cal->add('X-VALUES', 'v');
        $prop['X-P'] = array_fill(0, 200000, '!:!');

        $start = microtime(true);
        $out = $prop['X-P']->serialize();
        $elapsed = microtime(true) - $start;

        self::assertSame('X-P='.implode(',', array_fill(0, 200000, '"!:!"')), $out);
        // Quadratic before: minutes for this list.
        self::assertLessThan(2.0, $elapsed);
    }

    public function testSerializeMixesPlainAndQuotedValues(): void
    {
        $cal = new Component\VCalendar();
        $prop = $cal->add('X-VALUES', 'v');
        $prop['X-P'] = ['a', 'b:c', "d\ne^f\"g", '+'];

        self::assertSame('X-P=a,"b:c","d^ne^^f^\'g","+"', $prop['X-P']->serialize());
    }

    public function testCarriageReturnInJsonParameterIsNormalizedLikeInMimeDir(): void
    {
        $card = Reader::readJson(json_encode(['vcard', [
            ['version', new \stdClass(), 'text', '4.0'],
            ['fn', new \stdClass(), 'text', 'x'],
            ['note', ['x-a' => "a\rb", 'x-b' => ["\r", 'c']], 'text', 'x'],
        ]]));
        $reread = Reader::read($card->serialize());

        self::assertSame(['ab'], $reread->NOTE['X-A']->getParts());
        self::assertSame(['ab'], $card->NOTE['X-A']->getParts());
        self::assertSame('ab', $card->NOTE->jsonSerialize()[1]->{'x-a'});
        self::assertSame(['', 'c'], $card->NOTE['X-B']->getParts());
        self::assertSame(['', 'c'], $reread->NOTE['X-B']->getParts());

        $card->NOTE['X-A']->addValue("d\re");
        $card->NOTE['X-B']->setParts(["f\rg"]);
        self::assertSame(['ab', 'de'], $card->NOTE['X-A']->getParts());
        self::assertSame(['fg'], $card->NOTE['X-B']->getParts());
    }
}
