<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeZoneUtilTest extends TestCase
{
    public function setUp(): void
    {
        TimeZoneUtil::clean();
    }

    #[DataProvider('getMapping')]
    public function testCorrectTZ(string $timezoneName): void
    {
        try {
            $tz = new \DateTimeZone($timezoneName);
            self::assertInstanceOf('DateTimeZone', $tz);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Unknown or bad timezone')) {
                self::markTestSkipped($timezoneName.' is not (yet) supported in this PHP version. Update pecl/timezonedb');
            } else {
                throw $e;
            }
        }
    }

    public static function getMapping(): array
    {
        $map = array_merge(
            include __DIR__.'/../../lib/timezonedata/windowszones.php',
            include __DIR__.'/../../lib/timezonedata/lotuszones.php',
            include __DIR__.'/../../lib/timezonedata/exchangezones.php',
            include __DIR__.'/../../lib/timezonedata/php-workaround.php'
        );

        // PHPUNit requires an array of arrays
        return array_map(
            fn ($value) => [$value],
            $map
        );
    }

    public function testExchangeMap(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
X-MICROSOFT-CDO-TZID:2
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
 000
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Lisbon');

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWhetherMicrosoftIsStillInsane(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:(GMT+01.00) Sarajevo/Warsaw/Zagreb
X-MICROSOFT-CDO-TZID:2
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('(GMT+01.00) Sarajevo/Warsaw/Zagreb', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Sarajevo');

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testUnknownExchangeId(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
X-MICROSOFT-CDO-TZID:2000
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
DTEND;TZID="Sarajevo, Skopje, Sofija, Vilnius, Warsaw, Zagreb":20120418T103
 000
END:VEVENT
END:VCALENDAR
HI;

        // The unknown TZID follows the rules of its VTIMEZONE (CET / CEST).
        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable('2012-01-18T10:00:00Z')));
        self::assertSame(7200, $tz->getOffset(new \DateTimeImmutable('2012-04-18T10:00:00Z')));
    }

    public function testEmptyTimeZone(): void
    {
        $tz = TimeZoneUtil::getTimeZone('');
        $ex = new \DateTimeZone('UTC');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWindowsTimeZone(): void
    {
        $tz = TimeZoneUtil::getTimeZone('Eastern Standard Time');
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    #[DataProvider('getPHPTimeZoneIdentifiers')]
    public function testTimeZoneIdentifiers(string $tzid): void
    {
        $tz = TimeZoneUtil::getTimeZone($tzid);
        $ex = new \DateTimeZone($tzid);

        self::assertEquals($ex->getName(), $tz->getName());
    }

    #[DataProvider('getPHPTimeZoneBCIdentifiers')]
    public function testTimeZoneBCIdentifiers(string $tzid): void
    {
        /*
         * A regression was introduced in PHP 8.1.14 and 8.2.1
         * Timezone ids containing a "+" like "GMT+10" do not work.
         * See https://github.com/php/php-src/issues/10218
         * The regression should be fixed in the next patch releases of PHP
         * that should be released in Feb 2023.
         */
        $versionOfPHP = \phpversion();
        if ((('8.1.14' === $versionOfPHP) || ('8.2.1' === $versionOfPHP)) && \str_contains($tzid, '+')) {
            self::markTestSkipped("Timezone ids containing '+' do not work on PHP $versionOfPHP");
        }
        $tz = TimeZoneUtil::getTimeZone($tzid);
        $ex = new \DateTimeZone($tzid);

        self::assertEquals($ex->getName(), $tz->getName());
    }

    public static function getPHPTimeZoneIdentifiers(): array
    {
        // PHPUNit requires an array of arrays
        return array_map(
            fn ($value) => [$value],
            \DateTimeZone::listIdentifiers()
        );
    }

    public static function getPHPTimeZoneBCIdentifiers(): array
    {
        // PHPUNit requires an array of arrays
        return array_map(
            fn ($value) => [$value],
            include __DIR__.'/../../lib/timezonedata/php-bc.php'
        );
    }

    public function testTimezoneOffset(): void
    {
        $tz = TimeZoneUtil::getTimeZone('GMT-0400', null, true);

        if (version_compare(PHP_VERSION, '5.5.10', '>=') && !defined('HHVM_VERSION')) {
            $ex = new \DateTimeZone('-04:00');
        } else {
            $ex = new \DateTimeZone('Etc/GMT-4');
        }
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testTimezoneFail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeZoneUtil::getTimeZone('FooBar', null, true);
    }

    public function testFallBack(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
METHOD:REQUEST
VERSION:2.0
BEGIN:VTIMEZONE
TZID:foo
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=10;BYDAY=-1SU
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;WKST=MO;INTERVAL=1;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:20120416T092149Z
DTSTART;TZID="foo":20120418T1
 00000
SUMMARY:Begin Unterhaltsreinigung
UID:040000008200E00074C5B7101A82E0080000000010DA091DC31BCD01000000000000000
 0100000008FECD2E607780649BE5A4C9EE6418CBC
 000
END:VEVENT
END:VCALENDAR
HI;

        // The unknown TZID follows the rules of its VTIMEZONE (CET / CEST).
        $tz = TimeZoneUtil::getTimeZone('foo', Reader::read($vobj));
        self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable('2012-01-18T10:00:00Z')));
        self::assertSame(7200, $tz->getOffset(new \DateTimeImmutable('2012-04-18T10:00:00Z')));
    }

    public function testLjubljanaBug(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana
X-LIC-LOCATION:Europe/Ljubljana
BEGIN:STANDARD
TZNAME:CET
DTSTART:19701028T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:CEST
DTSTART:19700325T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
UID:foo
DTSTART;TZID=/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana:
 20121003T080000
DTEND;TZID=/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana:
 20121003T083000
TRANSP:OPAQUE
SEQUENCE:2
SUMMARY:testing
CREATED:20121002T172613Z
LAST-MODIFIED:20121002T172613Z
END:VEVENT
END:VCALENDAR

HI;

        $tz = TimeZoneUtil::getTimeZone('/freeassociation.sourceforge.net/Tzfile/Europe/Ljubljana', Reader::read($vobj));
        $ex = new \DateTimeZone('Europe/Ljubljana');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testWeirdSystemVLICs(): void
    {
        $vobj = <<<HI
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT
X-LIC-LOCATION:SystemV/EST5EDT
BEGIN:STANDARD
TZNAME:EST
DTSTART:19701104T020000
RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11
TZOFFSETFROM:-0400
TZOFFSETTO:-0500
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:EDT
DTSTART:19700311T020000
RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3
TZOFFSETFROM:-0500
TZOFFSETTO:-0400
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
UID:20121026T021107Z-6301-1000-1-0@chAir
DTSTAMP:20120905T172126Z
DTSTART;TZID=/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT:
 20121026T153000
DTEND;TZID=/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT:
 20121026T160000
TRANSP:OPAQUE
SEQUENCE:5
SUMMARY:pick up Ibby
CLASS:PUBLIC
CREATED:20121026T021108Z
LAST-MODIFIED:20121026T021118Z
X-EVOLUTION-MOVE-CALENDAR:1
END:VEVENT
END:VCALENDAR
HI;

        $tz = TimeZoneUtil::getTimeZone('/freeassociation.sourceforge.net/Tzfile/SystemV/EST5EDT', Reader::read($vobj), true);
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testPrefixedOffsetExchangeIdentifier(): void
    {
        $tz = TimeZoneUtil::getTimeZone('(UTC-05:00) Eastern Time (US & Canada)');
        $ex = new \DateTimeZone('America/New_York');
        self::assertEquals($ex->getName(), $tz->getName());
    }

    public function testGmtOffsetWithTrailingNewlineDoesNotThrow(): void
    {
        $tz = TimeZoneUtil::getTimeZone("GMT+2000\n");
        self::assertInstanceOf(\DateTimeZone::class, $tz);
    }

    public function testGmtOffsetWithTrailingNewlineKeepsTheSign(): void
    {
        $tz = TimeZoneUtil::getTimeZone("GMT+0100\n");
        // Either the default zone (an unknown TZID) or UTC+1, but never UTC-1.
        self::assertNotSame(-3600, $tz->getOffset(new \DateTimeImmutable('2026-01-15T12:00:00Z')));
    }

    public function testGmtOffsetWithTrailingNewlineIsUncertain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeZoneUtil::getTimeZone("GMT+0100\n", null, true);
    }

    public function testDtStartWithEscapedNewlineInTzid(): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:x\r\n"
            ."DTSTART;TZID=GMT+2000^n:20260115T100000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"
        );
        self::assertInstanceOf(\DateTimeImmutable::class, $vcal->VEVENT->DTSTART->getDateTime());
    }

    public function testOffsetFinderKeepsSignAndMinutes(): void
    {
        $finder = new TimezoneGuesser\FindFromOffset();
        self::assertSame('+01:00', $finder->find('GMT+0100')?->getName());
        self::assertSame('-05:30', $finder->find('GMT-0530')?->getName());
        self::assertNull($finder->find("GMT+0100\n"));
    }

    public function testOffsetZoneNameResolves(): void
    {
        self::assertSame('+01:00', TimeZoneUtil::getTimeZone('+01:00', null, true)->getName());
        self::assertSame('-05:30', TimeZoneUtil::getTimeZone('-05:30', null, true)->getName());
    }

    private static function customVTimeZone(string $observances, string $extra = ''): Component\VCalendar
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:Custom\r\n".$extra.$observances."END:VTIMEZONE\r\nEND:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $vcal);

        return $vcal;
    }

    private const CET_OBSERVANCES = "BEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\n"
        ."RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\n"
        ."BEGIN:DAYLIGHT\r\nDTSTART:19700329T020000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\n"
        ."RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nEND:DAYLIGHT\r\n";

    public function testUnknownTzidUsesTheVTimezoneRules(): void
    {
        $year = (int) gmdate('Y');
        $tz = TimeZoneUtil::getTimeZone('Custom', self::customVTimeZone(self::CET_OBSERVANCES), true);
        self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable($year.'-01-15T12:00:00Z')));
        self::assertSame(7200, $tz->getOffset(new \DateTimeImmutable($year.'-07-15T12:00:00Z')));
        $paris = new \DateTimeZone('Europe/Paris');
        foreach ($tz->getTransitions(gmmktime(0, 0, 0, 1, 1, $year), gmmktime(0, 0, 0, 1, 1, $year + 1)) as $transition) {
            self::assertSame($paris->getOffset(new \DateTimeImmutable('@'.$transition['ts'])), $transition['offset']);
        }
    }

    public function testObservancesStartingInATwoDigitYearAreNotReadAs20xx(): void
    {
        $observances = str_replace(['19701025', '19700329'], ['00501025', '00500329'], self::CET_OBSERVANCES);
        $year = (int) gmdate('Y');
        foreach ([false, true] as $failIfUncertain) {
            $tz = TimeZoneUtil::getTimeZone('Custom', self::customVTimeZone($observances), $failIfUncertain);
            self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable($year.'-01-15T12:00:00Z')));
            self::assertSame(7200, $tz->getOffset(new \DateTimeImmutable($year.'-07-15T12:00:00Z')));
        }
    }

    public function testAnUntilInATwoDigitYearEndsTheRule(): void
    {
        $observances = str_replace(
            ['19701025', '19700329', 'BYMONTH=3;BYDAY=-1SU'],
            ['00011025', '00010329', 'BYMONTH=3;BYDAY=-1SU;UNTIL=00600328T010000Z'],
            self::CET_OBSERVANCES
        );
        $year = (int) gmdate('Y');
        $tz = TimeZoneUtil::getTimeZone('Custom', self::customVTimeZone($observances));
        self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable($year.'-07-15T12:00:00Z')));
    }

    public function testUnknownLicLocationFallsThroughToTheObservances(): void
    {
        $vcal = self::customVTimeZone(self::CET_OBSERVANCES, "X-LIC-LOCATION:Nowhere/Custom\r\n");
        $tz = TimeZoneUtil::getTimeZone('Custom', $vcal);
        self::assertSame(3600, $tz->getOffset(new \DateTimeImmutable(gmdate('Y').'-01-15T12:00:00Z')));
    }

    public function testUnknownTzidWithoutDstIsAFixedOffset(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal, true)->getName());
    }

    public function testUnknownTzidWithUnmatchedRulesUsesTheStandardOffset(): void
    {
        // DST on the first Sunday of February: no PHP zone does that.
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nTZOFFSETFROM:+0400\r\nTZOFFSETTO:+0300\r\n"
            ."RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nEND:STANDARD\r\n"
            ."BEGIN:DAYLIGHT\r\nDTSTART:19700201T020000\r\nTZOFFSETFROM:+0300\r\nTZOFFSETTO:+0400\r\n"
            ."RRULE:FREQ=YEARLY;BYMONTH=2;BYDAY=1SU\r\nEND:DAYLIGHT\r\n");
        self::assertSame('+03:00', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        $this->expectException(\InvalidArgumentException::class);
        TimeZoneUtil::getTimeZone('Custom', $vcal, true);
    }

    public function testUnknownTzidWithUnsupportedRuleUsesTheStandardOffset(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\n"
            ."RRULE:FREQ=SECONDLY\r\nEND:STANDARD\r\n"
            ."BEGIN:DAYLIGHT\r\nDTSTART:19700329T020000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\n"
            ."RRULE:FREQ=YEARLY;BYMONTH=1.;BYDAY=-1SU\r\nEND:DAYLIGHT\r\n");
        self::assertSame('+01:00', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    #[DataProvider('nonNumericCdoIds')]
    public function testNonNumericCdoIdFallsThroughToTheObservances(string $cdoId): void
    {
        $vcal = self::customVTimeZone(
            "BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n",
            "X-MICROSOFT-CDO-TZID:$cdoId\r\n"
        );
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public static function nonNumericCdoIds(): array
    {
        return [['1a'], ['Customized Time Zone'], ['']];
    }

    #[DataProvider('observanceChildComponents')]
    public function testComponentNamedLikeAnObservanceProperty(string $name): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\n"
            ."DTSTART:20200101T030000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\nBEGIN:$name\r\nEND:$name\r\n"
            ."END:STANDARD\r\nEND:VTIMEZONE\r\nEND:VCALENDAR\r\n"
        );

        self::assertSame('+01:00', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public static function observanceChildComponents(): array
    {
        return [['RDATE'], ['RRULE'], ['TZOFFSETTO'], ['DTSTART']];
    }

    #[DataProvider('vtimezoneChildComponents')]
    public function testComponentNamedLikeAVtimezoneProperty(string $name): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:$name\r\nEND:$name\r\nBEGIN:STANDARD\r\n"
            ."DTSTART:20200101T030000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\n"
            ."END:STANDARD\r\nEND:VTIMEZONE\r\nEND:VCALENDAR\r\n"
        );

        self::assertSame('+01:00', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public static function vtimezoneChildComponents(): array
    {
        return [['X-LIC-LOCATION'], ['X-MICROSOFT-CDO-TZID']];
    }

    public function testObservanceWithSeveralFreqValues(): void
    {
        $vcal = Reader::read(
            "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\n"
            ."DTSTART:20200101T030000\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nRRULE:FREQ=YEARLY,MONTHLY\r\n"
            ."END:STANDARD\r\nEND:VTIMEZONE\r\nEND:VCALENDAR\r\n"
        );

        // As esn-sabre's esn.php does.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertSame('+01:00', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
        } finally {
            restore_error_handler();
        }
    }

    public function testPropertyNamedVtimezone(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nVTIMEZONE:x\r\nEND:VCALENDAR\r\n");

        // As esn-sabre's esn.php does.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertSame(date_default_timezone_get(), TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
        } finally {
            restore_error_handler();
        }
    }

    public function testComponentNamedTzidInAVtimezone(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nBEGIN:TZID\r\nEND:TZID\r\nEND:VTIMEZONE\r\nEND:VCALENDAR\r\n");

        self::assertSame(date_default_timezone_get(), TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function offsetsWithSeconds(): array
    {
        return [
            'east' => ['+010030', 3630],
            'west' => ['-053015', -19815],
            'less than a minute' => ['-000030', -30],
        ];
    }

    #[DataProvider('offsetsWithSeconds')]
    public function testUnknownTzidWithSecondsInItsOffset(string $offset, int $seconds): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:16010101T030000\r\nTZOFFSETFROM:$offset\r\nTZOFFSETTO:$offset\r\nEND:STANDARD\r\n");

        $timeZone = TimeZoneUtil::getTimeZone('Custom', $vcal, true);

        self::assertSame($seconds, $timeZone->getOffset(new \DateTimeImmutable('2026-01-15T12:00:00Z')));
    }

    /**
     * setDateTime() writes the name of the zone as the TZID.
     */
    public function testOffsetZoneWithSecondsResolvesBack(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260115T120000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $vcal);
        $date = new \DateTimeImmutable('2026-01-15T12:00:00', new \DateTimeZone('+01:00:30'));

        $vcal->VEVENT->DTSTART->setDateTime($date);
        $read = Reader::read($vcal->serialize());

        self::assertSame('+01:00:30', (string) $read->VEVENT->DTSTART['TZID']);
        self::assertSame($date->getTimestamp(), $read->VEVENT->DTSTART->getDateTime()->getTimestamp());
    }

    public function testEventInAZoneWithSecondsInItsOffset(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\nBEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\n"
            ."DTSTART:16010101T030000\r\nTZOFFSETFROM:+010030\r\nTZOFFSETTO:+010030\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;TZID=Custom:20260115T120000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");

        self::assertSame(
            (new \DateTimeImmutable('2026-01-15T10:59:30Z'))->getTimestamp(),
            $vcal->VEVENT->DTSTART->getDateTime()->getTimestamp()
        );
    }

    public function testRepeatedLookupReturnsTheSameZone(): void
    {
        $first = TimeZoneUtil::getTimeZone('Europe/Paris');

        self::assertSame('Europe/Paris', $first->getName());
        self::assertSame($first, TimeZoneUtil::getTimeZone('Europe/Paris'));
    }

    /**
     * The finders are memoized, the VTIMEZONE of each document is not.
     */
    public function testUnknownTzidStillUsesTheVTimezoneOfEachDocument(): void
    {
        $plusOne = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:16010101T000000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0100\r\nEND:STANDARD\r\n");
        $plusTwo = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:16010101T000000\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0200\r\nEND:STANDARD\r\n");
        $at = new \DateTimeImmutable('2026-01-15T12:00:00Z');

        self::assertSame(3600, TimeZoneUtil::getTimeZone('Custom', $plusOne)->getOffset($at));
        self::assertSame(7200, TimeZoneUtil::getTimeZone('Custom', $plusTwo)->getOffset($at));
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom')->getName());
    }

    public function testFinderAddedAfterALookupIsUsed(): void
    {
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('My Zone')->getName());

        TimeZoneUtil::addTimezoneFinder('mine', new class implements TimezoneGuesser\TimezoneFinder {
            public function find(string $tzid, ?bool $failIfUncertain = false): ?\DateTimeZone
            {
                return 'My Zone' === $tzid ? new \DateTimeZone('Asia/Tokyo') : null;
            }
        });

        self::assertSame('Asia/Tokyo', TimeZoneUtil::getTimeZone('My Zone')->getName());
    }

    public function testManyDistinctTzidsStillResolve(): void
    {
        for ($i = 0; $i < 250; ++$i) {
            self::assertSame('UTC', TimeZoneUtil::getTimeZone('Unknown '.$i)->getName());
        }
        self::assertSame('Europe/Paris', TimeZoneUtil::getTimeZone('Europe/Paris')->getName());
        self::assertSame('Europe/Paris', TimeZoneUtil::getTimeZone('Europe/Paris')->getName());
    }

    /**
     * Each zone has its own offset and a DST rule no PHP zone follows, so
     * that it is matched against all the PHP zones. Past 100 of them in
     * rotation, the process-wide cache used to empty itself before a zone
     * was used again, and every lookup scanned all the PHP zones.
     */
    public function testManyCustomVTimezonesInRotationAreGuessedOncePerDocument(): void
    {
        $zones = 150;
        $overrides = 300;
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\n";
        for ($i = 0; $i < $zones; ++$i) {
            $standard = sprintf('+01%02d', $i % 60);
            $daylight = sprintf('+02%02d', $i % 60);
            $month = 2 + intdiv($i, 60);
            $ics .= "BEGIN:VTIMEZONE\r\nTZID:X-$i\r\n"
                ."BEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n"
                ."TZOFFSETFROM:$daylight\r\nTZOFFSETTO:$standard\r\nEND:STANDARD\r\n"
                ."BEGIN:DAYLIGHT\r\nDTSTART:19700201T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=$month;BYDAY=1SU\r\n"
                ."TZOFFSETFROM:$standard\r\nTZOFFSETTO:$daylight\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE\r\n";
        }
        $ics .= "BEGIN:VEVENT\r\nUID:a\r\nDTSTART;TZID=X-0:20260101T100000\r\nDTEND;TZID=X-0:20260101T110000\r\n"
            ."RRULE:FREQ=DAILY;COUNT=$overrides\r\nEND:VEVENT\r\n";
        $first = new \DateTimeImmutable('2026-01-01');
        for ($i = 0; $i < $overrides; ++$i) {
            $tzid = 'X-'.($i % $zones);
            $day = $first->modify("+$i day")->format('Ymd');
            $ics .= "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID;TZID=X-0:{$day}T100000\r\n"
                ."DTSTART;TZID=$tzid:{$day}T120000\r\nDTEND;TZID=$tzid:{$day}T130000\r\nEND:VEVENT\r\n";
        }
        $vcal = Reader::read($ics);

        $start = microtime(true);
        $expanded = $vcal->expand(new \DateTime('2025-01-01'), new \DateTime('2028-01-01'));
        // About 0.1 s; it took 3.5 s when every lookup missed the cache.
        self::assertLessThan(1.5, microtime(true) - $start);

        self::assertCount($overrides, $expanded->VEVENT);
        // X-1 is at +01:01 in winter.
        self::assertSame('20260102T105900Z', (string) $expanded->VEVENT[1]->DTSTART);
    }

    public function testTzidRenamedAfterALookupIsFoundUnderItsNewName(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        $vcal->VTIMEZONE->TZID = 'Renamed';

        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Renamed', $vcal)->getName());
    }

    public function testVTimezoneRemovedAfterALookupIsNotUsed(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        $vcal->remove($vcal->VTIMEZONE);

        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public function testVTimezoneChangedAfterALookupIsGuessedAgain(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        $vcal->VTIMEZONE->STANDARD->TZOFFSETTO = '+0545';

        self::assertSame('+05:45', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public function testDuplicateTzidFallsThroughToTheNextVTimezone(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            ."BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:bad\r\nTZOFFSETTO:bad\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."END:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $vcal);

        foreach ([false, true] as $failIfUncertain) {
            self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal, $failIfUncertain)->getName());
            self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal, $failIfUncertain)->getName());
        }
    }

    /**
     * A document whose VTIMEZONEs repeat one TZID, or hold many other
     * TZIDs: each lookup used to check every remembered duplicate against
     * the whole list (29 s for this document), or to scan the list when the
     * TZID has no VTIMEZONE (6.6 s).
     */
    #[DataProvider('manyVTimezonesProvider')]
    public function testManyVTimezonesDoNotSlowEachLookup(string $tzid, string $otherTzid, string $expected): void
    {
        $vtimezones = 3000;
        $overrides = 300;
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\n"
            ."BEGIN:VTIMEZONE\r\nTZID:X-A\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";
        for ($i = 0; $i < $vtimezones; ++$i) {
            $ics .= "BEGIN:VTIMEZONE\r\nTZID:".sprintf($otherTzid, $i)."\r\nEND:VTIMEZONE\r\n";
        }
        $ics .= "BEGIN:VEVENT\r\nUID:a\r\nDTSTART;TZID=$tzid:20260101T100000\r\nDTEND;TZID=$tzid:20260101T110000\r\n"
            ."RRULE:FREQ=DAILY;COUNT=$overrides\r\nEND:VEVENT\r\n";
        $first = new \DateTimeImmutable('2026-01-01');
        for ($i = 0; $i < $overrides; ++$i) {
            $day = $first->modify("+$i day")->format('Ymd');
            $ics .= "BEGIN:VEVENT\r\nUID:a\r\nRECURRENCE-ID;TZID=$tzid:{$day}T100000\r\n"
                ."DTSTART;TZID=$tzid:{$day}T120000\r\nDTEND;TZID=$tzid:{$day}T130000\r\nEND:VEVENT\r\n";
        }
        $vcal = Reader::read($ics."END:VCALENDAR\r\n");

        $start = microtime(true);
        $expanded = $vcal->expand(new \DateTime('2025-01-01'), new \DateTime('2028-01-01'));
        // About 0.07 s.
        self::assertLessThan(1.5, microtime(true) - $start);

        self::assertCount($overrides, $expanded->VEVENT);
        self::assertSame($expected, (string) $expanded->VEVENT[0]->DTSTART);
    }

    public static function manyVTimezonesProvider(): array
    {
        return [
            'duplicates of the TZID' => ['X-A', 'X-A', '20260101T063000Z'],
            'TZID without a VTIMEZONE' => ['X-Z', 'X-B%d', '20260101T120000Z'],
        ];
    }

    public function testVTimezoneAddedAfterALookupIsUsed(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Other', $vcal)->getName());

        $added = clone $vcal->VTIMEZONE;
        $added->TZID = 'Other';
        $vcal->add($added);

        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Other', $vcal)->getName());
    }

    public function testVTimezoneReplacedAfterALookupIsUsed(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        // Same number of VTIMEZONEs, the same object under another key.
        $vtimezone = $vcal->VTIMEZONE;
        $vcal->remove($vtimezone);
        $replacement = clone $vtimezone;
        $replacement->STANDARD->TZOFFSETTO = '+0545';
        $replacement->STANDARD->TZOFFSETFROM = '+0545';
        $vcal->add($replacement);

        self::assertSame('+05:45', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    /**
     * Only the first VTIMEZONES_PER_TZID_LIMIT VTIMEZONEs of a TZID are tried.
     */
    public function testDuplicatesOfATzidAreTriedUpToALimit(): void
    {
        $broken = "BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:bad\r\nTZOFFSETTO:bad\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";
        $valid = "BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";

        $within = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".str_repeat($broken, 9).$valid."END:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $within);
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $within)->getName());

        $beyond = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".str_repeat($broken, 10).$valid."END:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $beyond);
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom', $beyond)->getName());
    }

    public function testVTimezonesReplacedBySameNumberAfterALookupAreUsed(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        // __set() empties the list and adds again from key 0.
        $other = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0545\r\nTZOFFSETTO:+0545\r\nEND:STANDARD\r\n")->VTIMEZONE;
        $other->TZID = 'Other';
        $vcal->VTIMEZONE = $other;

        self::assertSame('+05:45', TimeZoneUtil::getTimeZone('Other', $vcal)->getName());
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    public function testTzidSetAfterAMissIsFound(): void
    {
        $vcal = self::customVTimeZone("BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\n");
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Later', $vcal)->getName());

        $vcal->VTIMEZONE->TZID = 'Later';

        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Later', $vcal)->getName());
    }

    public function testVTimezoneRenamedToAnIndexedTzidIsTried(): void
    {
        $vcal = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"
            ."BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:bad\r\nTZOFFSETTO:bad\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."BEGIN:VTIMEZONE\r\nTZID:Other\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0530\r\nTZOFFSETTO:+0530\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n"
            ."END:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $vcal);
        self::assertSame('UTC', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());

        $vcal->select('VTIMEZONE')[1]->TZID = 'Custom';

        self::assertSame('+05:30', TimeZoneUtil::getTimeZone('Custom', $vcal)->getName());
    }

    private static function calendarWithVTimeZone(string $tzid, string $observances): Component\VCalendar
    {
        $calendar = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VTIMEZONE\r\nTZID:$tzid\r\n$observances"."END:VTIMEZONE\r\nEND:VCALENDAR\r\n");
        self::assertInstanceOf(Component\VCalendar::class, $calendar);

        return $calendar;
    }

    public function testKnownNameWithContradictingVTimeZoneFollowsTheVTimeZone(): void
    {
        $calendar = self::calendarWithVTimeZone('W. Europe Standard Time', "BEGIN:DAYLIGHT\r\nDTSTART:16010111T020000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0020\r\nEND:DAYLIGHT\r\n");

        $tz = TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar);
        self::assertSame(20 * 60, $tz->getOffset(new \DateTimeImmutable('2026-01-15T12:00:00Z')));
        self::assertSame(20 * 60, $tz->getOffset(new \DateTimeImmutable('2026-07-15T12:00:00Z')));
    }

    public function testKnownIanaNameWithContradictingVTimeZoneFollowsTheVTimeZone(): void
    {
        $calendar = self::calendarWithVTimeZone('Europe/Berlin', "BEGIN:STANDARD\r\nDTSTART:19700101T000000\r\nTZOFFSETFROM:+0900\r\nTZOFFSETTO:+0900\r\nEND:STANDARD\r\n");

        self::assertSame(9 * 3600, TimeZoneUtil::getTimeZone('Europe/Berlin', $calendar)->getOffset(new \DateTimeImmutable('2026-01-15T12:00:00Z')));
    }

    public function testKnownNameWithOutlookVTimeZoneKeepsTheMappedZone(): void
    {
        // Outlook's definition: only the 1601 rules.
        $calendar = self::calendarWithVTimeZone('W. Europe Standard Time', "BEGIN:STANDARD\r\nDTSTART:16010101T030000\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nDTSTART:16010101T020000\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nRRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\nEND:DAYLIGHT\r\n");

        self::assertSame('Europe/Berlin', TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getName());
    }

    public function testKnownNameWithAStaleOffsetKeepsTheMappedZone(): void
    {
        // Moscow was at +04:00 from 2011 to 2014: an old definition still
        // names the zone.
        $calendar = self::calendarWithVTimeZone('Russian Standard Time', "BEGIN:STANDARD\r\nDTSTART:16010101T000000\r\nTZOFFSETFROM:+0400\r\nTZOFFSETTO:+0400\r\nEND:STANDARD\r\n");

        self::assertSame('Europe/Moscow', TimeZoneUtil::getTimeZone('Russian Standard Time', $calendar)->getName());
    }

    public function testKnownNameWithVTimeZoneWithoutOffsetsKeepsTheMappedZone(): void
    {
        $calendar = self::calendarWithVTimeZone('W. Europe Standard Time', '');

        self::assertSame('Europe/Berlin', TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getName());
    }

    public function testObservancesAddedAfterALookupAreSeen(): void
    {
        $calendar = new Component\VCalendar();
        $vtimezone = $calendar->add('VTIMEZONE', ['TZID' => 'W. Europe Standard Time']);
        self::assertSame('Europe/Berlin', TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getName());

        $observance = $vtimezone->add($calendar->createComponent('STANDARD', ['DTSTART' => '16010101T000000']));
        self::assertSame('Europe/Berlin', TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getName());

        $observance->add('TZOFFSETFROM', '+0020');
        $observance->add('TZOFFSETTO', '+0020');
        self::assertSame(20 * 60, TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getOffset(new \DateTimeImmutable('2026-01-15T12:00:00Z')));

        $vtimezone->remove($observance);
        self::assertSame('Europe/Berlin', TimeZoneUtil::getTimeZone('W. Europe Standard Time', $calendar)->getName());
    }
}
