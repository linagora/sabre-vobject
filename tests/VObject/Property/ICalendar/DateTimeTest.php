<?php

namespace Sabre\VObject\Property\ICalendar;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\InvalidDataException;

class DateTimeTest extends TestCase
{
    protected $vcal;

    public function setUp(): void
    {
        $this->vcal = new VCalendar();
    }

    public function testSetDateTime(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt);

        self::assertEquals('19850704T013000', (string) $elem);
        self::assertEquals('Europe/Amsterdam', (string) $elem['TZID']);
        self::assertNull($elem['VALUE']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetDateTimeLOCAL(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt, true);

        self::assertEquals('19850704T013000', (string) $elem);
        self::assertNull($elem['TZID']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetDateTimeUTC(): void
    {
        $tz = new \DateTimeZone('GMT');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt);

        self::assertEquals('19850704T013000Z', (string) $elem);
        self::assertNull($elem['TZID']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetDateTimeFromUnixTimestamp(): void
    {
        // When initialized from a Unix timestamp, the timezone is set to "+00:00".
        $dt = new \DateTime('@489288600');

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt);

        self::assertEquals('19850704T013000Z', (string) $elem);
        self::assertNull($elem['TZID']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetDateTimeLOCALTZ(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt);

        self::assertEquals('19850704T013000', (string) $elem);
        self::assertEquals('Europe/Amsterdam', (string) $elem['TZID']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetDateTimeDATE(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem['VALUE'] = 'DATE';
        $elem->setDateTime($dt);

        self::assertEquals('19850704', (string) $elem);
        self::assertNull($elem['TZID']);
        self::assertEquals('DATE', $elem['VALUE']);

        self::assertFalse($elem->hasTime());
    }

    public function testSetValue(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setValue($dt);

        self::assertEquals('19850704T013000', (string) $elem);
        self::assertEquals('Europe/Amsterdam', (string) $elem['TZID']);
        self::assertNull($elem['VALUE']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetValueArray(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt1 = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt2 = new \DateTime('1985-07-04 02:30:00', $tz);
        $dt1->setTimeZone($tz);
        $dt2->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setValue([$dt1, $dt2]);

        self::assertEquals('19850704T013000,19850704T023000', (string) $elem);
        self::assertEquals('Europe/Amsterdam', (string) $elem['TZID']);
        self::assertNull($elem['VALUE']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetParts(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt1 = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt2 = new \DateTime('1985-07-04 02:30:00', $tz);
        $dt1->setTimeZone($tz);
        $dt2->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setParts([$dt1, $dt2]);

        self::assertEquals('19850704T013000,19850704T023000', (string) $elem);
        self::assertEquals('Europe/Amsterdam', (string) $elem['TZID']);
        self::assertNull($elem['VALUE']);

        self::assertTrue($elem->hasTime());
    }

    public function testSetPartsStrings(): void
    {
        $dt1 = '19850704T013000Z';
        $dt2 = '19850704T023000Z';

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setParts([$dt1, $dt2]);

        self::assertEquals('19850704T013000Z,19850704T023000Z', (string) $elem);
        self::assertNull($elem['VALUE']);

        self::assertTrue($elem->hasTime());
    }

    public function testGetDateTimeCached(): void
    {
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTimeImmutable('1985-07-04 01:30:00', $tz);
        $dt = $dt->setTimeZone($tz);

        $elem = $this->vcal->createProperty('DTSTART');
        $elem->setDateTime($dt);

        self::assertEquals($elem->getDateTime(), $dt);
    }

    public function testGetDateTimeDateNULL(): void
    {
        $elem = $this->vcal->createProperty('DTSTART');
        $dt = $elem->getDateTime();

        self::assertNull($dt);
    }

    public function testGetDateTimeDateDATE(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704');
        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 00:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testGetDateTimeDateDATEReferenceTimeZone(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704');

        $tz = new \DateTimeZone('America/Toronto');
        $dt = $elem->getDateTime($tz);
        $dt = $dt->setTimeZone(new \DateTimeZone('UTC'));

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 04:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testGetDateTimeDateFloating(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000');
        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testGetDateTimeDateFloatingReferenceTimeZone(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000');

        $tz = new \DateTimeZone('America/Toronto');
        $dt = $elem->getDateTime($tz);
        $dt = $dt->setTimeZone(new \DateTimeZone('UTC'));

        self::assertInstanceOf('DateTimeInterface', $dt);
        self::assertEquals('1985-07-04 05:30:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testGetDateTimeDateUTC(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000Z');
        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        self::assertEquals('UTC', $dt->getTimeZone()->getName());
    }

    public function testGetDateTimeDateLOCALTZ(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000');
        $elem['TZID'] = 'Europe/Amsterdam';

        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        self::assertEquals('Europe/Amsterdam', $dt->getTimeZone()->getName());
    }

    public function testGetDateTimeDateInvalid(): void
    {
        $this->expectException(InvalidDataException::class);
        $elem = $this->vcal->createProperty('DTSTART', 'bla');
        $elem->getDateTime();
    }

    public function testGetDateTimeWeirdTZ(): void
    {
        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000');
        $elem['TZID'] = '/freeassociation.sourceforge.net/Tzfile/Europe/Amsterdam';

        $event = $this->vcal->createComponent('VEVENT');
        $event->add($elem);

        $timezone = $this->vcal->createComponent('VTIMEZONE');
        $timezone->TZID = '/freeassociation.sourceforge.net/Tzfile/Europe/Amsterdam';
        $timezone->{'X-LIC-LOCATION'} = 'Europe/Amsterdam';

        $this->vcal->add($event);
        $this->vcal->add($timezone);

        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        self::assertEquals('Europe/Amsterdam', $dt->getTimeZone()->getName());
    }

    public function testGetDateTimeBadTimeZone(): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('Canada/Eastern');

        $elem = $this->vcal->createProperty('DTSTART', '19850704T013000');
        $elem['TZID'] = 'Moon';

        $event = $this->vcal->createComponent('VEVENT');
        $event->add($elem);

        $timezone = $this->vcal->createComponent('VTIMEZONE');
        $timezone->TZID = 'Moon';
        $timezone->{'X-LIC-LOCATION'} = 'Moon';

        $this->vcal->add($event);
        $this->vcal->add($timezone);

        $dt = $elem->getDateTime();

        self::assertInstanceOf('DateTimeImmutable', $dt);
        self::assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        self::assertEquals('Canada/Eastern', $dt->getTimeZone()->getName());
        date_default_timezone_set($default);
    }

    public function testUpdateValueParameter(): void
    {
        $dtStart = $this->vcal->createProperty('DTSTART', new \DateTime('2013-06-07 15:05:00'));
        $dtStart['VALUE'] = 'DATE';

        self::assertEquals("DTSTART;VALUE=DATE:20130607\r\n", $dtStart->serialize());
    }

    public static function validateDateTimeProvider(): array
    {
        return [
            [
                '-00011130T143000Z',
                1,
                3,
            ],
            [
                '20260627T100000',
                0,
                0,
            ],
            [
                '20260627T100000Z',
                0,
                0,
            ],
            [
                '20260627T100000Z+0200',
                1,
                3,
            ],
        ];
    }

    #[DataProvider('validateDateTimeProvider')]
    public function testValidate(string $invalidDateTime, int $expectedMessageCount, int $expectedMessageLevel): void
    {
        $exDate = $this->vcal->createProperty('EXDATE', $invalidDateTime);
        $messages = $exDate->validate();
        self::assertCount($expectedMessageCount, $messages);
        if ($expectedMessageCount > 0) {
            self::assertEquals($expectedMessageLevel, $messages[0]['level']);
        }
    }

    /**
     * This issue was discovered on the sabredav mailing list.
     */
    public function testCreateDatePropertyThroughAdd(): void
    {
        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT');

        $dtstart = $vevent->add(
            'DTSTART',
            new \DateTime('2014-03-07'),
            ['VALUE' => 'DATE']
        );

        self::assertEquals("DTSTART;VALUE=DATE:20140307\r\n", $dtstart->serialize());
    }

    /**
     * A DATE-TIME property holding a duration must not fatal when it is
     * read as a date or serialized to jCal.
     */
    #[DataProvider('durationValues')]
    public function testDurationValueIsInvalidData(string $name, string $value, string $method): void
    {
        $event = $this->vcal->add('VEVENT', ['UID' => 'evt-1']);
        $property = $event->add($this->vcal->createProperty($name, $value));

        self::assertInstanceOf(DateTime::class, $property);

        $this->expectException(InvalidDataException::class);
        match ($method) {
            'getJsonValue' => $property->getJsonValue(),
            'getDateTime' => $property->getDateTime(),
            'getDateTimes' => $property->getDateTimes(),
        };
    }

    public static function durationValues(): array
    {
        $result = [];
        foreach (['DTSTAMP' => 'PT1H', 'DTSTART' => 'P1D', 'DTEND' => '-PT5M', 'RECURRENCE-ID' => 'P'] as $name => $value) {
            foreach (['getJsonValue', 'getDateTime', 'getDateTimes'] as $method) {
                $result[$name.' '.$method] = [$name, $value, $method];
            }
        }

        return $result;
    }

    public function testJsonSerializeOfADurationValueIsInvalidData(): void
    {
        $vcal = \Sabre\VObject\Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:evt-1\r\nDTSTAMP:PT1H\r\n"
            ."DTSTART:20361005T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );

        $this->expectException(InvalidDataException::class);
        $vcal->jsonSerialize();
    }

    public function testSetDateTimeOffsetZoneKeepsInstant(): void
    {
        $vevent = $this->vcal->add('VEVENT', ['DTSTART' => '20261103T090000']);
        $vevent->DTSTART['TZID'] = 'GMT+0100';
        $dt = $vevent->DTSTART->getDateTime();
        self::assertSame('2026-11-03T08:00:00+00:00', $dt->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        $vevent->DTSTART->setDateTime($dt);
        self::assertSame($dt->getTimestamp(), $vevent->DTSTART->getDateTime()->getTimestamp());
    }

    public function testExpandOffsetTzid(): void
    {
        $vcal = \Sabre\VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\nDTSTAMP:20260101T000000Z\r\n"
            ."DTSTART;TZID=GMT-0100:20260111T153000\r\nDTEND;TZID=GMT-0100:20260111T163000\r\nRRULE:FREQ=DAILY;COUNT=2\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n");
        $expanded = $vcal->expand(new \DateTimeImmutable('2026-01-01T00:00:00Z'), new \DateTimeImmutable('2026-02-01T00:00:00Z'));
        self::assertSame('20260111T163000Z', (string) $expanded->VEVENT[0]->DTSTART);
        self::assertSame('20260111T173000Z', (string) $expanded->VEVENT[0]->DTEND);
        self::assertSame('20260111T163000Z', (string) $expanded->VEVENT[0]->{'RECURRENCE-ID'});
    }

    public function testDateWithTzidUsesReferenceZone(): void
    {
        $vevent = $this->vcal->add('VEVENT', ['DTSTART' => '20260320']);
        $vevent->DTSTART['VALUE'] = 'DATE';
        $vevent->DTSTART['TZID'] = 'Asia/Tokyo';
        $paris = new \DateTimeZone('Europe/Paris');

        self::assertTrue($vevent->DTSTART->isFloating());
        $before = $vevent->DTSTART->getDateTime($paris);
        self::assertEquals('2026-03-20T00:00:00+01:00', $before->format('c'));

        $vevent->DTSTART->setDateTime($before);
        self::assertEquals($before->getTimestamp(), $vevent->DTSTART->getDateTime($paris)->getTimestamp());
    }

    public static function valueTypeMismatchProvider(): array
    {
        return [
            'date in a DATE-TIME DTSTART' => ['DTSTART', '20260320', [], "DTSTART;VALUE=DATE:20260320\r\n"],
            'dates in a DATE-TIME EXDATE with TZID' => ['EXDATE', ['20260101', '20260301'], ['TZID' => 'Europe/Paris'], "EXDATE;VALUE=DATE:20260101,20260301\r\n"],
            'date-times in a VALUE=DATE RDATE' => ['RDATE', ['20260101T090000', '20260301T090000Z'], ['VALUE' => 'DATE'], "RDATE:20260101T090000,20260301T090000Z\r\n"],
            'date-time in a VALUE=DATE RECURRENCE-ID with TZID' => ['RECURRENCE-ID', '20261101T090000', ['VALUE' => 'DATE', 'TZID' => 'Europe/Paris'], "RECURRENCE-ID;TZID=Europe/Paris:20261101T090000\r\n"],
        ];
    }

    #[DataProvider('valueTypeMismatchProvider')]
    public function testValidateReportsAValueTypeMismatch(string $name, string|array $value, array $parameters, string $repaired): void
    {
        $property = $this->vcal->add('VEVENT')->add($name, $value, $parameters);
        $before = $property->serialize();

        $messages = $property->validate();
        self::assertCount(1, $messages);
        self::assertSame(3, $messages[0]['level']);
        self::assertSame($before, $property->serialize());
    }

    #[DataProvider('valueTypeMismatchProvider')]
    public function testValidateRepairsAValueTypeMismatch(string $name, string|array $value, array $parameters, string $repaired): void
    {
        $property = $this->vcal->add('VEVENT')->add($name, $value, $parameters);

        $messages = $property->validate(\Sabre\VObject\Node::REPAIR);
        self::assertCount(1, $messages);
        self::assertSame(1, $messages[0]['level']);
        self::assertSame($repaired, $property->serialize());
        self::assertSame([], $property->validate());
    }

    public static function unrepairableValueTypeMismatchProvider(): array
    {
        return [
            'mixed values in a DATE-TIME EXDATE' => ['EXDATE', ['20260101', '20260301T090000'], []],
            'mixed values in a VALUE=DATE EXDATE' => ['EXDATE', ['20260101', '20260301T090000'], ['VALUE' => 'DATE']],
            'date in a DTSTAMP' => ['DTSTAMP', '20260320', []],
            'neither date nor date-time' => ['DTSTART', 'tomorrow', []],
        ];
    }

    #[DataProvider('unrepairableValueTypeMismatchProvider')]
    public function testValidateDoesNotRepairOtherMismatches(string $name, string|array $value, array $parameters): void
    {
        $property = $this->vcal->add('VEVENT')->add($name, $value, $parameters);
        $before = $property->serialize();

        $messages = $property->validate(\Sabre\VObject\Node::REPAIR);
        self::assertCount(1, $messages);
        self::assertSame(3, $messages[0]['level']);
        self::assertSame($before, $property->serialize());
    }

    public function testJsonValueOfUnresolvedTzidIsNotUtc(): void
    {
        $vcal = \Sabre\VObject\Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Customized Time Zone\r\n"
            ."BEGIN:STANDARD\r\nDTSTART:16010101T000000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20261103T100000Z\r\nDTSTART;TZID=Customized Time Zone:20261103T100000\r\n"
            ."DTEND;TZID=Foo/Bar:20261103T110000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertSame(['2026-11-03T10:00:00'], $vcal->VEVENT->DTSTART->getJsonValue());
        self::assertSame(['2026-11-03T11:00:00'], $vcal->VEVENT->DTEND->getJsonValue());
        self::assertSame(['2026-11-03T10:00:00Z'], $vcal->VEVENT->DTSTAMP->getJsonValue());

        $again = \Sabre\VObject\Reader::readJson(json_encode($vcal->jsonSerialize()));
        self::assertSame('20261103T100000', $again->VEVENT->DTSTART->getValue());
        self::assertSame('20261103T100000Z', $again->VEVENT->DTSTAMP->getValue());
    }

    #[DataProvider('mixedUtcValues')]
    public function testJsonValueMixedUtcKeepsInstants(array $values, array $parameters, array $json): void
    {
        $vcal = new VCalendar();
        $prop = $vcal->add('VEVENT')->add('EXDATE', $values, $parameters);
        $before = array_map(fn ($d) => $d->getTimestamp(), $prop->getDateTimes());
        self::assertSame($json, $prop->getJsonValue());

        $copy = clone $prop;
        $copy->setJsonValue($prop->getJsonValue());
        self::assertSame($before, array_map(fn ($d) => $d->getTimestamp(), $copy->getDateTimes()));
    }

    public static function mixedUtcValues(): array
    {
        return [
            'tzid then utc' => [['20261020T090000', '20261021T100000Z'], ['TZID' => 'Europe/Paris'], ['2026-10-20T09:00:00', '2026-10-21T10:00:00Z']],
            'utc then tzid' => [['20261021T100000Z', '20261020T090000'], ['TZID' => 'Europe/Paris'], ['2026-10-21T10:00:00Z', '2026-10-20T09:00:00']],
            'floating then utc' => [['20261026T023000', '20261026T022000Z'], [], ['2026-10-26T02:30:00', '2026-10-26T02:20:00Z']],
        ];
    }

    public function testSetDateTimesKeepsSecondOccurrenceOfRepeatedHour(): void
    {
        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT');
        $prop = $vevent->add('EXDATE', ['20261024T023000', '20261025T010000Z'], ['TZID' => 'Europe/Paris']);
        $dts = $prop->getDateTimes();
        $before = array_map(fn ($d) => $d->getTimestamp(), $dts);

        $prop->setDateTimes($dts);

        self::assertSame($before, array_map(fn ($d) => $d->getTimestamp(), $prop->getDateTimes()));
        self::assertFalse(isset($prop['TZID']));
    }

    public function testSetDateTimeKeepsTheTzidOfTheFirstOccurrenceOfRepeatedHour(): void
    {
        $vcal = new VCalendar();
        $prop = $vcal->createProperty('DTSTART');
        // 01:15 EDT is the first 01:15 of that day.
        $prop->setDateTime((new \DateTimeImmutable('2026-11-01T05:15:00Z'))->setTimezone(new \DateTimeZone('America/New_York')));

        self::assertSame('America/New_York', (string) $prop['TZID']);
        self::assertSame('20261101T011500', $prop->getValue());
    }

    public function testSetDateTimeWritesSecondOccurrenceOfRepeatedHourInUtc(): void
    {
        $vcal = new VCalendar();
        $prop = $vcal->createProperty('DTSTART');
        // 01:15 EST is the second 01:15 of that day.
        $prop->setDateTime((new \DateTimeImmutable('2026-11-01T06:15:00Z'))->setTimezone(new \DateTimeZone('America/New_York')));

        self::assertFalse(isset($prop['TZID']));
        self::assertSame('20261101T061500Z', $prop->getValue());
    }
}
