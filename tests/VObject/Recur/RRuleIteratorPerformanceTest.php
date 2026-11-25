<?php

namespace Sabre\VObject\Recur;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Rules whose lists repeat a value many times: each instance must cost the
 * same as with the value written once.
 */
#[Medium]
class RRuleIteratorPerformanceTest extends TestCase
{
    /**
     * @return string[]
     */
    private static function instances(string $rule): array
    {
        $result = [];
        foreach (new RRuleIterator($rule, new \DateTimeImmutable('2026-01-01 10:00:00', new \DateTimeZone('Europe/Paris'))) as $instance) {
            $result[] = $instance->format('Y-m-d H:i');
        }

        return $result;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function repeatedValues(): array
    {
        return [
            'monthly BYDAY' => ['FREQ=MONTHLY;COUNT=10;BYDAY=', 'MO', ''],
            'monthly ordinal BYDAY' => ['FREQ=MONTHLY;COUNT=10;BYDAY=-1FR,', '2TU', ''],
            'signed ordinal BYDAY' => ['FREQ=MONTHLY;COUNT=10;BYDAY=2TU,', '+2TU', ''],
            'yearly BYDAY' => ['FREQ=YEARLY;COUNT=10;BYMONTH=3;BYDAY=', 'WE', ''],
            'weekly BYDAY' => ['FREQ=WEEKLY;COUNT=10;BYDAY=', 'TH', ''],
            'BYMONTHDAY' => ['FREQ=MONTHLY;COUNT=10;BYMONTHDAY=', '-1', ''],
            'BYYEARDAY' => ['FREQ=YEARLY;COUNT=10;BYYEARDAY=', '100', ''],
            'BYWEEKNO' => ['FREQ=YEARLY;COUNT=10;BYDAY=MO;BYWEEKNO=', '20', ''],
            'BYHOUR' => ['FREQ=DAILY;COUNT=10;BYHOUR=', '7', ''],
            'BYMONTH' => ['FREQ=YEARLY;COUNT=10;BYMONTH=', '+4', ''],
            'BYSETPOS' => ['FREQ=MONTHLY;COUNT=10;BYDAY=MO,TU;BYSETPOS=', '2', ''],
        ];
    }

    #[DataProvider('repeatedValues')]
    public function testRepeatedValuesCostLikeOne(string $prefix, string $value, string $suffix): void
    {
        $once = self::instances($prefix.$value.$suffix);

        $start = microtime(true);
        $repeated = self::instances($prefix.implode(',', array_fill(0, 20000, $value)).$suffix);
        self::assertLessThan(3.0, microtime(true) - $start);

        self::assertCount(10, $once);
        self::assertSame($once, $repeated);
    }
}
