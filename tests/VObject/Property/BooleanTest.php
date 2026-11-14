<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\TestCase;
use Sabre\VObject;

class BooleanTest extends TestCase
{
    /**
     * @throws VObject\ParseException
     */
    public function testMimeDir(): void
    {
        $input = "BEGIN:VCARD\r\nX-AWESOME;VALUE=BOOLEAN:TRUE\r\nX-SUCKS;VALUE=BOOLEAN:FALSE\r\nEND:VCARD\r\n";

        $vcard = VObject\Reader::read($input);
        self::assertTrue($vcard->{'X-AWESOME'}->getValue());
        self::assertFalse($vcard->{'X-SUCKS'}->getValue());

        self::assertEquals('BOOLEAN', $vcard->{'X-AWESOME'}->getValueType());
        self::assertEquals($input, $vcard->serialize());
    }

    /**
     * A jCal BOOLEAN given as a string is read as the text form is, and is
     * not serialized as TRUE because it is a non-empty string.
     */
    public function testJsonStringValueSerializesToItsMeaning(): void
    {
        $vcal = VObject\Reader::readJson(
            '["vcalendar",[],[["vevent",[["x-flag",{},"boolean","FALSE"],["x-flag2",{},"boolean","true"],'
            .'["x-flag3",{},"boolean","yes"],["x-flag4",{},"boolean",false],["x-flag5",{},"boolean",true]],[]]]]'
        );

        self::assertSame('FALSE', $vcal->VEVENT->{'X-FLAG'}->getRawMimeDirValue());
        self::assertFalse($vcal->VEVENT->{'X-FLAG'}->getValue());
        self::assertSame('TRUE', $vcal->VEVENT->{'X-FLAG2'}->getRawMimeDirValue());
        self::assertSame('FALSE', $vcal->VEVENT->{'X-FLAG3'}->getRawMimeDirValue());
        self::assertSame('FALSE', $vcal->VEVENT->{'X-FLAG4'}->getRawMimeDirValue());
        self::assertSame('TRUE', $vcal->VEVENT->{'X-FLAG5'}->getRawMimeDirValue());
        self::assertSame([false], $vcal->VEVENT->{'X-FLAG'}->getJsonValue());
    }
}
