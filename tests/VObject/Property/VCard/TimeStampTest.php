<?php

namespace Sabre\VObject\Property\VCard;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCard;

class TimeStampTest extends TestCase
{
    public function testJsonValueOnlyHasSeparatorsForItsParts(): void
    {
        $card = new VCard();
        self::assertSame(['1997-11-15'], $card->createProperty('REV', '1997-11-15')->getJsonValue());
        self::assertSame(['T10:22'], $card->createProperty('REV', 'T1022')->getJsonValue());
        self::assertSame(['2026-01-01T10:22Z'], $card->createProperty('REV', '2026-01-01T10:22Z')->getJsonValue());
        self::assertSame(['1995-10-31T22:27:10Z'], $card->createProperty('REV', '19951031T222710Z')->getJsonValue());
    }

    public function testEmptyValueIsReported(): void
    {
        $card = new VCard();
        $levels = array_column($card->createProperty('REV', '')->validate(), 'level');
        self::assertContains(3, $levels);
    }

    public function testDateOnlyValueIsAccepted(): void
    {
        $card = new VCard();
        $levels = array_column($card->createProperty('REV', '1997-11-15')->validate(), 'level');
        self::assertNotContains(3, $levels);
    }
}
