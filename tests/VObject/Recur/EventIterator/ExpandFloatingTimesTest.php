<?php

namespace Sabre\VObject\Recur\EventIterator;

use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class ExpandFloatingTimesTest extends TestCase
{
    use \Sabre\VObject\PHPUnitAssertions;

    public function testExpand(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foo
DTSTART:20150109T090000
DTEND:20150109T100000
RRULE:FREQ=WEEKLY;INTERVAL=1;UNTIL=20191002T070000Z;BYDAY=FR
END:VEVENT
END:VCALENDAR
ICS;

        $vcal = Reader::read($input);
        self::assertInstanceOf(VCalendar::class, $vcal);

        $vcal = $vcal->expand(new \DateTime('2015-01-01'), new \DateTime('2015-01-31'));
        $output = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foo
DTSTART:20150109T090000Z
DTEND:20150109T100000Z
RECURRENCE-ID:20150109T090000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150116T090000Z
DTEND:20150116T100000Z
RECURRENCE-ID:20150116T090000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150123T090000Z
DTEND:20150123T100000Z
RECURRENCE-ID:20150123T090000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150130T090000Z
DTEND:20150130T100000Z
RECURRENCE-ID:20150130T090000Z
END:VEVENT
END:VCALENDAR

ICS;
        self::assertVObjectEqualsVObject($output, $vcal);
    }

    public function testExpandWithReferenceTimezone(): void
    {
        $input = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foo
DTSTART:20150109T090000
DTEND:20150109T100000
RRULE:FREQ=WEEKLY;INTERVAL=1;UNTIL=20191002T070000Z;BYDAY=FR
END:VEVENT
END:VCALENDAR
ICS;

        $vcal = Reader::read($input);
        self::assertInstanceOf(VCalendar::class, $vcal);

        $vcal = $vcal->expand(
            new \DateTime('2015-01-01'),
            new \DateTime('2015-01-31'),
            new \DateTimeZone('Europe/Berlin')
        );

        $output = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:foo
DTSTART:20150109T080000Z
DTEND:20150109T090000Z
RECURRENCE-ID:20150109T080000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150116T080000Z
DTEND:20150116T090000Z
RECURRENCE-ID:20150116T080000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150123T080000Z
DTEND:20150123T090000Z
RECURRENCE-ID:20150123T080000Z
END:VEVENT
BEGIN:VEVENT
UID:foo
DTSTART:20150130T080000Z
DTEND:20150130T090000Z
RECURRENCE-ID:20150130T080000Z
END:VEVENT
END:VCALENDAR

ICS;
        self::assertVObjectEqualsVObject($output, $vcal);
    }

    public function testZonedRDateOfAFloatingMasterKeepsItsInstant(): void
    {
        $vcal = Reader::read(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:u
DTSTART:20261020T100000
DTEND:20261020T110000
RDATE;TZID=Europe/Paris:20261023T133000
RDATE:20261024T133000Z
END:VEVENT
END:VCALENDAR
ICS);
        $expanded = $vcal->expand(new \DateTimeImmutable('2026-10-01T00:00:00Z'), new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $instances = [];
        foreach ($expanded->VEVENT as $vevent) {
            $instances[] = $vevent->DTSTART.' '.$vevent->DTEND.' '.$vevent->{'RECURRENCE-ID'};
        }
        self::assertSame([
            '20261020T100000Z 20261020T110000Z 20261020T100000Z',
            '20261023T113000Z 20261023T123000Z 20261023T113000Z',
            '20261024T133000Z 20261024T143000Z 20261024T133000Z',
        ], $instances);

        $expanded = $vcal->expand(new \DateTimeImmutable('2026-10-01T00:00:00Z'), new \DateTimeImmutable('2026-11-01T00:00:00Z'), new \DateTimeZone('Europe/Paris'));
        self::assertSame('20261024T133000Z', (string) $expanded->VEVENT[2]->DTSTART);
    }
}
