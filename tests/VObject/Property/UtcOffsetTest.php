<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class UtcOffsetTest extends TestCase
{
    #[DataProvider('offsets')]
    public function testValidate(string $offset, bool $valid): void
    {
        $vcal = new VCalendar();
        $tz = $vcal->add('VTIMEZONE', ['TZID' => 'X']);
        $std = $tz->add($vcal->createComponent('STANDARD', ['DTSTART' => '19701025T030000', 'TZOFFSETFROM' => '+0200']));
        $std->add('TZOFFSETTO', $offset);

        $messages = $std->TZOFFSETTO->validate();
        if ($valid) {
            self::assertSame([], $messages);
        } else {
            self::assertSame(3, $messages[0]['level'] ?? null);
        }
    }

    public static function offsets(): array
    {
        return [
            ['+0100', true],
            ['-0530', true],
            ['+013045', true],
            ['a:b+0100', false],
            ['+01', false],
            ['+2400', false],
            ['+01ôZ', false],
            ['', false],
        ];
    }

    public function testValidateVCard(): void
    {
        foreach (['-05', '-0500', '-05:00'] as $offset) {
            $card = new VCard(['VERSION' => '4.0']);
            $card->add('TZ', $offset, ['VALUE' => 'UTC-OFFSET']);
            self::assertSame([], $card->TZ->validate(), $offset);
        }
    }

    public function testJsonKeepsInvalidValues(): void
    {
        foreach (['+0100' => '+01:00', '-013045' => '-01:30:45', 'a:b+0100' => 'a:b+0100', '+01ôZ' => '+01ôZ'] as $offset => $json) {
            $vcal = new VCalendar();
            $vcal->add('TZOFFSETTO', $offset);
            $serialized = $vcal->TZOFFSETTO->jsonSerialize();
            self::assertSame($json, $serialized[3]);
            self::assertNotFalse(json_encode($vcal));

            $reread = Reader::readJson(json_encode($vcal));
            self::assertSame($offset, $reread->TZOFFSETTO->getValue());
        }
    }
}
