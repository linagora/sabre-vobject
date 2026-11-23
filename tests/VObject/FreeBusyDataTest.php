<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

class FreeBusyDataTest extends TestCase
{
    public function testGetData(): void
    {
        $fb = new FreeBusyData(100, 200);

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testGetData
     */
    public function testAddBeginning(): void
    {
        $fb = new FreeBusyData(100, 200);

        // Overwriting the first half
        $fb->add(100, 150, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 150,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 150,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );

        // Overwriting the first half again
        $fb->add(100, 150, 'BUSY-TENTATIVE');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 150,
                    'type' => 'BUSY-TENTATIVE',
                ],
                [
                    'start' => 150,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testAddBeginning
     */
    public function testAddEnd(): void
    {
        $fb = new FreeBusyData(100, 200);

        // Overwriting the first half
        $fb->add(150, 200, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 150,
                    'type' => 'FREE',
                ],
                [
                    'start' => 150,
                    'end' => 200,
                    'type' => 'BUSY',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testAddEnd
     */
    public function testAddMiddle(): void
    {
        $fb = new FreeBusyData(100, 200);

        // Overwriting the first half
        $fb->add(150, 160, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 150,
                    'type' => 'FREE',
                ],
                [
                    'start' => 150,
                    'end' => 160,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 160,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testAddMiddle
     */
    public function testAddMultiple(): void
    {
        $fb = new FreeBusyData(100, 200);

        $fb->add(110, 120, 'BUSY');
        $fb->add(130, 140, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 110,
                    'type' => 'FREE',
                ],
                [
                    'start' => 110,
                    'end' => 120,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 120,
                    'end' => 130,
                    'type' => 'FREE',
                ],
                [
                    'start' => 130,
                    'end' => 140,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 140,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testAddMultiple
     */
    public function testAddMultipleOverlap(): void
    {
        $fb = new FreeBusyData(100, 200);

        $fb->add(110, 120, 'BUSY');
        $fb->add(130, 140, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 110,
                    'type' => 'FREE',
                ],
                [
                    'start' => 110,
                    'end' => 120,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 120,
                    'end' => 130,
                    'type' => 'FREE',
                ],
                [
                    'start' => 130,
                    'end' => 140,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 140,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );

        $fb->add(115, 135, 'BUSY-TENTATIVE');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 110,
                    'type' => 'FREE',
                ],
                [
                    'start' => 110,
                    'end' => 115,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 115,
                    'end' => 135,
                    'type' => 'BUSY-TENTATIVE',
                ],
                [
                    'start' => 135,
                    'end' => 140,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 140,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    /**
     * @depends testAddMultipleOverlap
     */
    public function testAddMultipleOverlapAndMerge(): void
    {
        $fb = new FreeBusyData(100, 200);

        $fb->add(110, 120, 'BUSY');
        $fb->add(130, 140, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 110,
                    'type' => 'FREE',
                ],
                [
                    'start' => 110,
                    'end' => 120,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 120,
                    'end' => 130,
                    'type' => 'FREE',
                ],
                [
                    'start' => 130,
                    'end' => 140,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 140,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );

        $fb->add(115, 135, 'BUSY');

        self::assertEquals(
            [
                [
                    'start' => 100,
                    'end' => 110,
                    'type' => 'FREE',
                ],
                [
                    'start' => 110,
                    'end' => 140,
                    'type' => 'BUSY',
                ],
                [
                    'start' => 140,
                    'end' => 200,
                    'type' => 'FREE',
                ],
            ],
            $fb->getData()
        );
    }

    public function testInvertedAndEmptyIntervalsAreIgnored(): void
    {
        $fb = new FreeBusyData(10, 66);
        $fb->add(58, 30, 'BUSY-TENTATIVE');
        $fb->add(40, 40, 'FREE');
        $fb->add(20, 58, 'BUSY-TENTATIVE');
        $fb->add(66, 80, 'BUSY');
        $fb->add(0, 10, 'BUSY');
        self::assertEquals([
            ['start' => 10, 'end' => 20, 'type' => 'FREE'],
            ['start' => 20, 'end' => 58, 'type' => 'BUSY-TENTATIVE'],
            ['start' => 58, 'end' => 66, 'type' => 'FREE'],
        ], $fb->getData());
    }

    public function testEmptyIntervalDoesNotSplitBusyTime(): void
    {
        $fb = new FreeBusyData(10, 70);
        $fb->add(20, 21, 'BUSY');
        $fb->add(30, 80, 'BUSY');
        $fb->add(40, 40, 'FREE');
        self::assertEquals([
            ['start' => 10, 'end' => 20, 'type' => 'FREE'],
            ['start' => 20, 'end' => 21, 'type' => 'BUSY'],
            ['start' => 21, 'end' => 30, 'type' => 'FREE'],
            ['start' => 30, 'end' => 70, 'type' => 'BUSY'],
        ], $fb->getData());
    }

    public function testPeriodAddedAfterGetDataOverridesTheEarlierOnes(): void
    {
        $fb = new FreeBusyData(0, 100);
        $fb->add(10, 50, 'BUSY');
        $fb->add(30, 40, 'BUSY-TENTATIVE');
        self::assertEquals([
            ['start' => 0, 'end' => 10, 'type' => 'FREE'],
            ['start' => 10, 'end' => 30, 'type' => 'BUSY'],
            ['start' => 30, 'end' => 40, 'type' => 'BUSY-TENTATIVE'],
            ['start' => 40, 'end' => 50, 'type' => 'BUSY'],
            ['start' => 50, 'end' => 100, 'type' => 'FREE'],
        ], $fb->getData());

        $fb->add(20, 45, 'FREE');
        self::assertEquals([
            ['start' => 0, 'end' => 10, 'type' => 'FREE'],
            ['start' => 10, 'end' => 20, 'type' => 'BUSY'],
            ['start' => 20, 'end' => 45, 'type' => 'FREE'],
            ['start' => 45, 'end' => 50, 'type' => 'BUSY'],
            ['start' => 50, 'end' => 100, 'type' => 'FREE'],
        ], $fb->getData());
    }

    /**
     * Each add() used to scan and splice the whole list: 2 * 10^4 periods in
     * order took about 20 seconds.
     */
    public function testManyPeriodsAreMergedInOneSweep(): void
    {
        $maxFreeBusyPeriods = Settings::$maxFreeBusyPeriods;
        Settings::$maxFreeBusyPeriods = -1;
        try {
            $count = 20000;
            $fb = new FreeBusyData(0, 10 * $count);
            for ($i = 0; $i < $count; ++$i) {
                $fb->add(10 * $i, 10 * $i + 5, 0 === $i % 2 ? 'BUSY' : 'BUSY-TENTATIVE');
            }
            $data = $fb->getData();
        } finally {
            Settings::$maxFreeBusyPeriods = $maxFreeBusyPeriods;
        }

        self::assertCount(2 * $count, $data);
        self::assertEquals(['start' => 0, 'end' => 5, 'type' => 'BUSY'], $data[0]);
        self::assertEquals(['start' => 10 * $count - 10, 'end' => 10 * $count - 5, 'type' => 'BUSY-TENTATIVE'], $data[2 * $count - 2]);
        self::assertEquals(['start' => 10 * $count - 5, 'end' => 10 * $count, 'type' => 'FREE'], $data[2 * $count - 1]);
    }

    public function testTooManyPeriodsInTheTimeRangeThrow(): void
    {
        $maxFreeBusyPeriods = Settings::$maxFreeBusyPeriods;
        Settings::$maxFreeBusyPeriods = 2;
        try {
            $fb = new FreeBusyData(10, 100);
            // Outside the time range, empty or inverted: not counted.
            $fb->add(100, 200, 'BUSY');
            $fb->add(0, 10, 'BUSY');
            $fb->add(50, 50, 'BUSY');
            $fb->add(60, 50, 'BUSY');
            $fb->add(20, 30, 'BUSY');
            $fb->add(40, 50, 'BUSY');

            $this->expectException(Recur\MaxInstancesExceededException::class);
            $fb->add(60, 70, 'BUSY');
        } finally {
            Settings::$maxFreeBusyPeriods = $maxFreeBusyPeriods;
        }
    }

    public function testInvertedTimeRangeIgnoresThePeriods(): void
    {
        $fb = new FreeBusyData(100, 50);
        $fb->add(0, 200, 'BUSY');
        self::assertEquals([
            ['start' => 100, 'end' => 50, 'type' => 'FREE'],
        ], $fb->getData());
    }
}
