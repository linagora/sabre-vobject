<?php

namespace Sabre\VObject;

use Sabre\VObject\TimezoneGuesser\FindFromOffset;
use Sabre\VObject\TimezoneGuesser\FindFromTimezoneIdentifier;
use Sabre\VObject\TimezoneGuesser\FindFromTimezoneMap;
use Sabre\VObject\TimezoneGuesser\GuessFromLicEntry;
use Sabre\VObject\TimezoneGuesser\GuessFromMsTzId;
use Sabre\VObject\TimezoneGuesser\GuessFromObservances;
use Sabre\VObject\TimezoneGuesser\TimezoneFinder;
use Sabre\VObject\TimezoneGuesser\TimezoneGuesser;

/**
 * Time zone name translation.
 *
 * This file translates well-known time zone names into "Olson database" time zone names.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Frank Edelhaeuser (fedel@users.sourceforge.net)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class TimeZoneUtil
{
    private static ?TimeZoneUtil $instance = null;

    /** @var TimezoneGuesser[] */
    private array $timezoneGuessers = [];

    /** @var TimezoneFinder[] */
    private array $timezoneFinders = [];

    /**
     * What the finders returned per TZID, false for nothing: the same TZID is
     * looked up for every property, every expanded instance and every jCal
     * value. Keyed by $failIfUncertain, then by TZID. Only the finders are
     * cached: the guessers and the default-zone fallback depend on the
     * document and on date_default_timezone_get().
     *
     * @var array<int, array<string, \DateTimeZone|false>>
     */
    private array $finderCache = [];

    private int $finderCacheSize = 0;

    /**
     * Bound of $finderCache, so that a document with many distinct TZIDs
     * cannot grow it without limit.
     */
    private const FINDER_CACHE_LIMIT = 100;

    private function __construct()
    {
        $this->addGuesser('lic', new GuessFromLicEntry());
        $this->addGuesser('msTzId', new GuessFromMsTzId());
        $this->addGuesser('observances', new GuessFromObservances());
        $this->addFinder('tzid', new FindFromTimezoneIdentifier());
        $this->addFinder('tzmap', new FindFromTimezoneMap());
        $this->addFinder('offset', new FindFromOffset());
    }

    private static function getInstance(): self
    {
        self::$instance ??= new self();

        return self::$instance;
    }

    private function addGuesser(string $key, TimezoneGuesser $guesser): void
    {
        $this->timezoneGuessers[$key] = $guesser;
    }

    private function addFinder(string $key, TimezoneFinder $finder): void
    {
        $this->timezoneFinders[$key] = $finder;
        $this->finderCache = [];
        $this->finderCacheSize = 0;
    }

    /**
     * This method will try to find out the correct timezone for an iCalendar
     * date-time value.
     *
     * You must pass the contents of the TZID parameter, as well as the full
     * calendar.
     *
     * If the lookup fails, this method will return the default PHP timezone
     * (as configured using date_default_timezone_set, or the date.timezone ini
     * setting).
     *
     * Alternatively, if $failIfUncertain is set to true, it will throw an
     * exception if we cannot accurately determine the timezone.
     */
    private function findTimeZone(string $tzid, ?Component $vcalendar = null, bool $failIfUncertain = false): \DateTimeZone
    {
        $timezone = $this->findWithFinders($tzid, $failIfUncertain);
        if ($timezone instanceof \DateTimeZone) {
            return $timezone;
        }

        if ($vcalendar) {
            // If that didn't work, we will scan VTIMEZONE objects
            foreach ($vcalendar->select('VTIMEZONE') as $vtimezone) {
                // select() also returns a property of that name.
                if ($vtimezone instanceof Component\VTimeZone && $vtimezone->TZID instanceof Property && (string) $vtimezone->TZID === $tzid) {
                    foreach ($this->timezoneGuessers as $timezoneGuesser) {
                        $timezone = $timezoneGuesser->guess($vtimezone, $failIfUncertain);
                        if (!$timezone instanceof \DateTimeZone) {
                            continue;
                        }

                        return $timezone;
                    }
                }
            }
        }

        if ($failIfUncertain) {
            throw new \InvalidArgumentException('We were unable to determine the correct PHP timezone for tzid: '.$tzid);
        }

        // If we got all the way here, we default to whatever has been set as the PHP default timezone.
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Runs the finders, memoized per TZID. Returning the same instance again
     * is safe: DateTimeZone is immutable.
     */
    private function findWithFinders(string $tzid, bool $failIfUncertain): ?\DateTimeZone
    {
        $key = (int) $failIfUncertain;
        if (isset($this->finderCache[$key][$tzid])) {
            $cached = $this->finderCache[$key][$tzid];

            return false === $cached ? null : $cached;
        }

        $found = false;
        foreach ($this->timezoneFinders as $timezoneFinder) {
            $timezone = $timezoneFinder->find($tzid, $failIfUncertain);
            if ($timezone instanceof \DateTimeZone) {
                $found = $timezone;
                break;
            }
        }

        if ($this->finderCacheSize >= self::FINDER_CACHE_LIMIT) {
            $this->finderCache = [];
            $this->finderCacheSize = 0;
        }
        $this->finderCache[$key][$tzid] = $found;
        ++$this->finderCacheSize;

        return false === $found ? null : $found;
    }

    public static function addTimezoneGuesser(string $key, TimezoneGuesser $guesser): void
    {
        self::getInstance()->addGuesser($key, $guesser);
    }

    public static function addTimezoneFinder(string $key, TimezoneFinder $finder): void
    {
        self::getInstance()->addFinder($key, $finder);
    }

    public static function getTimeZone(string $tzid, ?Component $vcalendar = null, bool $failIfUncertain = false): \DateTimeZone
    {
        return self::getInstance()->findTimeZone($tzid, $vcalendar, $failIfUncertain);
    }

    public static function clean(): void
    {
        self::$instance = null;
    }
}
