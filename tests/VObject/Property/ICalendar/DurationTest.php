<?php

namespace Sabre\VObject\Property\ICalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;

class DurationTest extends TestCase
{
    public function testGetDateInterval(): void
    {
        $vcal = new VCalendar();
        $event = $vcal->add('VEVENT', ['DURATION' => ['PT1H']]);

        self::assertEquals(
            new \DateInterval('PT1H'),
            $event->{'DURATION'}->getDateInterval()
        );
    }

    #[DataProvider('invalidDurations')]
    public function testValidateReportsInvalidDuration(string $name, string $value): void
    {
        $vcal = new VCalendar();
        $prop = $vcal->createProperty($name, $value);
        self::assertInstanceOf(Duration::class, $prop);
        self::assertSame([3], array_column($prop->validate(), 'level'));
    }

    public static function invalidDurations(): array
    {
        return [['DURATION', 'PT1X'], ['DURATION', '.PT30M'], ['TRIGGER', '-P-1D'], ['TRIGGER', 'P5M'],
            ['DURATION', 'P222222222222222222222222222222D']];
    }

    #[DataProvider('validDurations')]
    public function testValidateAcceptsValidDuration(string $name, string $value): void
    {
        $vcal = new VCalendar();
        self::assertSame([], $vcal->createProperty($name, $value)->validate());
    }

    public static function validDurations(): array
    {
        return [['DURATION', 'PT1H'], ['DURATION', 'P1W'], ['TRIGGER', '-PT15M'], ['TRIGGER', '+P1DT2H3M4S']];
    }
}
