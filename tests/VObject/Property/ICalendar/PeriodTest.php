<?php

namespace Sabre\VObject\Property\ICalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class PeriodTest extends TestCase
{
    #[DataProvider('periods')]
    public function testValidate(string $period, bool $valid): void
    {
        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT', ['DTSTART' => '20261201T100000Z']);
        $vevent->add('RDATE', $period, ['VALUE' => 'PERIOD']);

        $messages = $vevent->RDATE->validate();
        if ($valid) {
            self::assertSame([], $messages);
        } else {
            self::assertSame(3, $messages[0]['level'] ?? null);
        }
    }

    public static function periods(): array
    {
        return [
            ['20261201T100000Z/20261201T110000Z', true],
            ['20261201T100000Z/PT1H', true],
            ['20261201T100000Z/PY;UNTIL=20261232T000000Z;END:VEVENTH;WKST=MO', false],
            ['20261201T100000Z', false],
            ['20261201/PT1H', false],
            ['20261201T100000Z/', false],
            ['20261201T100000Z/PT1H/PT1H', false],
        ];
    }

    public function testJsonKeepsTheDuration(): void
    {
        $vcal = Reader::readJson('["vcalendar",[],[["vevent",[["rdate",{},"period",["2026-12-01T10:00:00Z","PT1H"]],["freebusy",{},"period",["2026-12-01T10:00:00Z","2026-12-01T11:00:00Z"]]],[]]]]');
        self::assertSame('20261201T100000Z/PT1H', $vcal->VEVENT->RDATE->getValue());
        self::assertSame('20261201T100000Z/20261201T110000Z', $vcal->VEVENT->FREEBUSY->getValue());

        $vcal = Reader::readJson('["vcalendar",[],[["vevent",[["rdate",{},"period",["2026-12-01T10:00:00Z","-P:T-1H"]]],[]]]]');
        self::assertSame('20261201T100000Z/-P:T-1H', $vcal->VEVENT->RDATE->getValue());
    }
}
