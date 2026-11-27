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
     * The VTIMEZONEs of each document by TZID, so that a lookup does not
     * scan all the VTIMEZONEs of a document that holds many. The index is
     * valid while the document's VTIMEZONE revision is unchanged. Each
     * VTIMEZONE is also kept with its key in the document's list, so that a
     * hit is checked against the document in constant time. Weak
     * references: on PHP 8.2, a WeakMap value that references its key (a
     * node references its parent) keeps the entry alive.
     *
     * @var \WeakMap<Component, array{revision: int, tzids: array<string, list<array{int|string, \WeakReference<Component\VTimeZone>}>>}>
     */
    private \WeakMap $vtimezoneIndex;

    /**
     * How many VTIMEZONEs of the same TZID are tried, in document order: a
     * document may repeat a TZID, but a lookup must not cost one guess per
     * duplicate.
     */
    private const VTIMEZONES_PER_TZID_LIMIT = 10;

    /**
     * The UTC offsets, in seconds, a PHP zone has ever had, per zone name.
     *
     * @var array<string, array<int, true>>
     */
    private array $zoneOffsets = [];

    private const ZONE_OFFSETS_LIMIT = 200;

    /**
     * Per document, whether the zone of a known TZID name contradicts its
     * VTIMEZONEs, while the document's VTIMEZONE revision is unchanged (it
     * changes with the observances and their TZOFFSETTO too): a known name
     * is looked up for every DATE-TIME. A TZOFFSETTO changed in place with
     * setValue() is not seen until then.
     *
     * @var \WeakMap<Component, array{revision: int, tzids: array<string, bool>}>
     */
    private \WeakMap $contradictions;

    private function __construct()
    {
        $this->vtimezoneIndex = new \WeakMap();
        $this->contradictions = new \WeakMap();
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
        // A known name (Windows, Exchange, Lotus, IANA) is preferred to the
        // document's VTIMEZONE, which Outlook often writes with its 1601
        // rules only, unless that VTIMEZONE gives none of the offsets the
        // zone has ever had: it then defines another zone.
        $mapped = $this->findWithFinders($tzid, $failIfUncertain);
        if ($mapped instanceof \DateTimeZone) {
            if (!$vcalendar) {
                return $mapped;
            }
            $revision = $vcalendar->getVTimeZoneRevision();
            $memo = $this->contradictions[$vcalendar] ?? null;
            if (null === $memo || $memo['revision'] !== $revision) {
                $memo = ['revision' => $revision, 'tzids' => []];
            }
            if (!isset($memo['tzids'][$tzid])) {
                $memo['tzids'][$tzid] = $this->contradicts($mapped, $this->findVTimeZones($tzid, $vcalendar));
                $this->contradictions[$vcalendar] = $memo;
            }
            if (!$memo['tzids'][$tzid]) {
                return $mapped;
            }
        }

        $vtimezones = $vcalendar ? $this->findVTimeZones($tzid, $vcalendar) : [];

        // Each VTIMEZONE with that TZID in turn, as a guesser may fail on
        // one of them.
        foreach ($vtimezones as $vtimezone) {
            foreach ($this->timezoneGuessers as $timezoneGuesser) {
                $timezone = $timezoneGuesser->guess($vtimezone, $failIfUncertain);
                // A guess from a name (X-LIC-LOCATION, X-MICROSOFT-CDO-TZID)
                // may contradict the observances as well.
                if ($timezone instanceof \DateTimeZone && !($mapped && $this->contradicts($timezone, [$vtimezone]))) {
                    return $timezone;
                }
            }
        }

        // Nothing follows the VTIMEZONE better than the known name.
        if ($mapped instanceof \DateTimeZone) {
            return $mapped;
        }

        if ($failIfUncertain) {
            throw new \InvalidArgumentException('We were unable to determine the correct PHP timezone for tzid: '.$tzid);
        }

        // If we got all the way here, we default to whatever has been set as the PHP default timezone.
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Returns the first VTIMEZONEs of the document with that TZID, in
     * document order, at most VTIMEZONES_PER_TZID_LIMIT of them. The
     * document's VTIMEZONEs are indexed by TZID once, and the index is
     * rebuilt when a VTIMEZONE or the TZID of one is added or removed
     * (Component::getVTimeZoneRevision()), or when one found for the TZID
     * left the document or changed its TZID. A TZID changed in place with
     * setValue() to that TZID is only seen once the index is rebuilt.
     *
     * Each lookup costs at most VTIMEZONES_PER_TZID_LIMIT checks, whatever
     * the number of VTIMEZONEs.
     *
     * @return array<Component\VTimeZone>
     */
    private function findVTimeZones(string $tzid, Component $vcalendar): array
    {
        // select() also returns a property of that name, and keeps the keys
        // of the document's list.
        $vtimezones = $vcalendar->select('VTIMEZONE');
        $index = $this->vtimezoneIndex[$vcalendar] ?? null;
        $revision = $vcalendar->getVTimeZoneRevision();
        if (null !== $index && $index['revision'] === $revision) {
            $known = [];
            foreach ($index['tzids'][$tzid] ?? [] as [$key, $reference]) {
                $vtimezone = $reference->get();
                if (null === $vtimezone || ($vtimezones[$key] ?? null) !== $vtimezone || self::tzidOf($vtimezone) !== $tzid) {
                    $known = null;
                    break;
                }
                $known[] = $vtimezone;
            }
            if (null !== $known) {
                return $known;
            }
        }

        $tzids = [];
        $found = [];
        foreach ($vtimezones as $key => $vtimezone) {
            if (!$vtimezone instanceof Component\VTimeZone) {
                continue;
            }
            $name = self::tzidOf($vtimezone);
            if (null !== $name && count($tzids[$name] ?? []) < self::VTIMEZONES_PER_TZID_LIMIT) {
                $tzids[$name][] = [$key, \WeakReference::create($vtimezone)];
                if ($name === $tzid) {
                    $found[] = $vtimezone;
                }
            }
        }
        $this->vtimezoneIndex[$vcalendar] = [
            'revision' => $revision,
            'tzids' => $tzids,
        ];

        return $found;
    }

    /**
     * Whether the VTIMEZONEs give UTC offsets (TZOFFSETTO) and none of them
     * is an offset the zone has ever had.
     *
     * @param array<Component\VTimeZone> $vtimezones
     */
    private function contradicts(\DateTimeZone $zone, array $vtimezones): bool
    {
        if (!$vtimezones) {
            return false;
        }
        $name = $zone->getName();
        if (!isset($this->zoneOffsets[$name])) {
            if (count($this->zoneOffsets) >= self::ZONE_OFFSETS_LIMIT) {
                $this->zoneOffsets = [];
            }
            $this->zoneOffsets[$name] = self::offsetsOf($zone);
        }
        $zoneOffsets = $this->zoneOffsets[$name];

        $given = false;
        foreach ($vtimezones as $vtimezone) {
            foreach (self::observanceOffsetsOf($vtimezone) as $offset) {
                if (isset($zoneOffsets[$offset])) {
                    return false;
                }
                $given = true;
            }
        }

        return $given;
    }

    /**
     * @return array<int, true>
     */
    private static function offsetsOf(\DateTimeZone $zone): array
    {
        $offsets = [];
        $transitions = $zone->getTransitions();
        foreach (false === $transitions ? [] : $transitions as $transition) {
            $offsets[$transition['offset']] = true;
        }
        // A fixed-offset zone (e.g. "+01:00") has no transitions.
        $offsets[$zone->getOffset(new \DateTimeImmutable('@0'))] = true;

        return $offsets;
    }

    /**
     * @return list<int>
     */
    private static function observanceOffsetsOf(Component\VTimeZone $vtimezone): array
    {
        $offsets = [];
        foreach ($vtimezone->getComponents() as $observance) {
            if (!in_array($observance->name, ['STANDARD', 'DAYLIGHT'], true)) {
                continue;
            }
            foreach ($observance->select('TZOFFSETTO') as $offset) {
                if (preg_match('/^([+-])([0-9]{2})([0-9]{2})([0-9]{2})?$/D', trim((string) $offset), $matches)) {
                    $seconds = (int) $matches[2] * 3600 + (int) $matches[3] * 60 + (int) ($matches[4] ?? 0);
                    $offsets[] = '-' === $matches[1] ? -$seconds : $seconds;
                }
            }
        }

        return $offsets;
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
