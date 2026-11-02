<?php

namespace Sabre\VObject\Recur;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\InvalidDataException;

class RDateIteratorTest extends TestCase
{
    public function testSimple(): void
    {
        $utc = new \DateTimeZone('UTC');
        $it = new RDateIterator('20140901T000000Z,20141001T000000Z', new \DateTimeImmutable('2014-08-01 00:00:00', $utc));

        $expected = [
            new \DateTimeImmutable('2014-08-01 00:00:00', $utc),
            new \DateTimeImmutable('2014-09-01 00:00:00', $utc),
            new \DateTimeImmutable('2014-10-01 00:00:00', $utc),
        ];

        self::assertEquals(
            $expected,
            iterator_to_array($it)
        );

        self::assertFalse($it->isInfinite());
    }

    public function testEmptyDate(): void
    {
        $utc = new \DateTimeZone('UTC');

        $this->expectException(InvalidDataException::class);
        new RDateIterator('20140901T000000Z,', new \DateTimeImmutable('2014-08-01 00:00:00', $utc));
    }

    public function testTimezone(): void
    {
        $tz = new \DateTimeZone('Europe/Berlin');
        $it = new RDateIterator('20140901T000000,20141001T000000', new \DateTimeImmutable('2014-08-01 00:00:00', $tz));

        $expected = [
            new \DateTimeImmutable('2014-08-01 00:00:00', $tz),
            new \DateTimeImmutable('2014-09-01 00:00:00', $tz),
            new \DateTimeImmutable('2014-10-01 00:00:00', $tz),
        ];

        self::assertEquals(
            $expected,
            iterator_to_array($it)
        );

        self::assertFalse($it->isInfinite());
    }

    public function testFastForward(): void
    {
        $utc = new \DateTimeZone('UTC');
        $it = new RDateIterator('20140901T000000Z,20141001T000000Z', new \DateTimeImmutable('2014-08-01 00:00:00', $utc));

        $it->fastForward(new \DateTimeImmutable('2014-08-15 00:00:00'));

        $result = [];
        while ($it->valid()) {
            $result[] = $it->current();
            $it->next();
        }

        $expected = [
            new \DateTimeImmutable('2014-09-01 00:00:00', $utc),
            new \DateTimeImmutable('2014-10-01 00:00:00', $utc),
        ];

        self::assertEquals(
            $expected,
            $result
        );

        self::assertFalse($it->isInfinite());
    }

    public function testUnsortedDates(): void
    {
        $utc = new \DateTimeZone('UTC');
        $it = new RDateIterator(
            '20260601T090000Z,20260520T090000Z,20260401T090000Z',
            new \DateTimeImmutable('2026-05-10 09:00:00', $utc)
        );

        self::assertEquals(
            [
                new \DateTimeImmutable('2026-04-01 09:00:00', $utc),
                new \DateTimeImmutable('2026-05-10 09:00:00', $utc),
                new \DateTimeImmutable('2026-05-20 09:00:00', $utc),
                new \DateTimeImmutable('2026-06-01 09:00:00', $utc),
            ],
            iterator_to_array($it)
        );
    }

    public function testDuplicateDates(): void
    {
        $utc = new \DateTimeZone('UTC');
        $it = new RDateIterator(
            '20140901T000000Z,20140801T000000Z,20140901T000000Z',
            new \DateTimeImmutable('2014-08-01 00:00:00', $utc)
        );

        self::assertEquals(
            [
                new \DateTimeImmutable('2014-08-01 00:00:00', $utc),
                new \DateTimeImmutable('2014-09-01 00:00:00', $utc),
            ],
            iterator_to_array($it)
        );
    }

    public function testFastForwardUnsorted(): void
    {
        $utc = new \DateTimeZone('UTC');
        $it = new RDateIterator('20141001T000000Z,20140901T000000Z', new \DateTimeImmutable('2014-08-01 00:00:00', $utc));

        $it->fastForward(new \DateTimeImmutable('2014-08-15 00:00:00'));

        self::assertEquals(new \DateTimeImmutable('2014-09-01 00:00:00', $utc), $it->current());
    }

    public function testDurationValueIsInvalidData(): void
    {
        $this->expectException(InvalidDataException::class);
        new RDateIterator('PT1H', new \DateTimeImmutable('2014-08-01 00:00:00', new \DateTimeZone('UTC')));
    }
}
