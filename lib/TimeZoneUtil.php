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
     * Keyed by $failIfUncertain ("0" or "1") followed by the TZID, least
     * recently used first.
     *
     * @var array<string, \DateTimeZone|false>
     */
    private array $finderCache = [];

    /**
     * Bound of $finderCache, so that a document with many distinct TZIDs
     * cannot grow it without limit: past it, the least recently used entry
     * is evicted.
     */
    private const FINDER_CACHE_LIMIT = 100;

    /**
     * The VTIMEZONE found per TZID, per document, so that a lookup does not
     * scan all the VTIMEZONEs of a document that holds many. Weak
     * references: on PHP 8.2, a WeakMap value that references its key (a
     * node references its parent) keeps the entry alive. A hit is checked
     * against the document, which may have changed since.
     *
     * @var \WeakMap<Component, array<string, array<\WeakReference<Component\VTimeZone>>>>
     */
    private \WeakMap $vtimezoneIndex;

    private function __construct()
    {
        $this->vtimezoneIndex = new \WeakMap();
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

        // Each VTIMEZONE with that TZID in turn, as a guesser may fail on
        // one of them.
        foreach ($vcalendar ? $this->findVTimeZones($tzid, $vcalendar) : [] as $vtimezone) {
            foreach ($this->timezoneGuessers as $timezoneGuesser) {
                $timezone = $timezoneGuesser->guess($vtimezone, $failIfUncertain);
                if ($timezone instanceof \DateTimeZone) {
                    return $timezone;
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
     * Returns the VTIMEZONEs of the document with that TZID, in document
     * order. Those found for a TZID are remembered for the document, and
     * used while they are all still in it under that TZID. A VTIMEZONE
     * added or renamed to that TZID afterwards is only seen once one of
     * them changes.
     *
     * @return array<Component\VTimeZone>
     */
    private function findVTimeZones(string $tzid, Component $vcalendar): array
    {
        // select() also returns a property of that name.
        $vtimezones = $vcalendar->select('VTIMEZONE');
        $index = $this->vtimezoneIndex[$vcalendar] ?? [];
        if (isset($index[$tzid])) {
            $known = [];
            foreach ($index[$tzid] as $reference) {
                $vtimezone = $reference->get();
                if (null === $vtimezone || self::tzidOf($vtimezone) !== $tzid || !in_array($vtimezone, $vtimezones, true)) {
                    $known = null;
                    break;
                }
                $known[] = $vtimezone;
            }
            if (null !== $known) {
                return $known;
            }
        }

        $found = [];
        foreach ($vtimezones as $vtimezone) {
            if ($vtimezone instanceof Component\VTimeZone && self::tzidOf($vtimezone) === $tzid) {
                $found[] = $vtimezone;
            }
        }
        if ([] !== $found) {
            $index[$tzid] = array_map(\WeakReference::create(...), $found);
            $this->vtimezoneIndex[$vcalendar] = $index;
        }

        return $found;
    }

    private static function tzidOf(Component\VTimeZone $vtimezone): ?string
    {
        return $vtimezone->TZID instanceof Property ? (string) $vtimezone->TZID : null;
    }

    /**
     * Runs the finders, memoized per TZID. Returning the same instance again
     * is safe: DateTimeZone is immutable.
     */
    private function findWithFinders(string $tzid, bool $failIfUncertain): ?\DateTimeZone
    {
        $key = ($failIfUncertain ? '1' : '0').$tzid;
        if (isset($this->finderCache[$key])) {
            $found = $this->finderCache[$key];
            // Moved to the end: the most recently used.
            unset($this->finderCache[$key]);
            $this->finderCache[$key] = $found;

            return false === $found ? null : $found;
        }

        $found = false;
        foreach ($this->timezoneFinders as $timezoneFinder) {
            $timezone = $timezoneFinder->find($tzid, $failIfUncertain);
            if ($timezone instanceof \DateTimeZone) {
                $found = $timezone;
                break;
            }
        }

        if (count($this->finderCache) >= self::FINDER_CACHE_LIMIT) {
            unset($this->finderCache[array_key_first($this->finderCache)]);
        }
        $this->finderCache[$key] = $found;

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
