<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Sweeps the transitions of several zones: longer than the 1 s limit of a
 * small test once coverage is on.
 */
#[Medium]
class DateTimeParserTransitionsTest extends TestCase
{
    public function testJsonDateTimeIsTheFormatOfTheParsedValueAroundTransitions(): void
    {
        $zones = ['Europe/Paris', 'America/New_York', 'America/Sao_Paulo', 'Pacific/Apia', 'Australia/Lord_Howe',
            'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Asia/Manila', 'America/St_Johns', 'Antarctica/Troll', 'UTC', '+05:30'];
        foreach ($zones as $name) {
            $tz = new \DateTimeZone($name);
            // Past 2037, the transitions come from the rule at the end of the
            // zone's data, which the cache must list as well.
            $instants = [0, gmmktime(0, 0, 0, 1, 1, 2026)];
            foreach ([[1900, 1950], [2020, 2030], [2036, 2040], [2050, 2051], [2200, 2201], [9998, 9999]] as [$from, $to]) {
                $transitions = $tz->getTransitions(gmmktime(0, 0, 0, 1, 1, $from), gmmktime(0, 0, 0, 12, 31, $to));
                $instants = array_merge($instants, array_column(false === $transitions ? [] : $transitions, 'ts'));
            }
            foreach ($instants as $ts) {
                // Every 50 minutes from 26 hours before to 26 hours after,
                // read as local digits and as UTC digits.
                for ($t = $ts - 26 * 3600; $t <= $ts + 26 * 3600; $t += 3000) {
                    foreach ([gmdate('Ymd\\THis', $t), (new \DateTimeImmutable('@'.$t))->setTimezone($tz)->format('Ymd\\THis')] as $value) {
                        $expected = DateTimeParser::parseDateTime($value, $tz)->format('Y-m-d\\TH:i:s');
                        self::assertSame($expected, DateTimeParser::toJsonDateTime($value, $tz), $name.' '.$value);
                    }
                }
            }
        }
    }
}
