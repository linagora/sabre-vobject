<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use Sabre\VObject\Component;
use Sabre\VObject\Component\VTimeZone;
use Sabre\VObject\Property;

/**
 * Builds a time zone from the STANDARD and DAYLIGHT observances of a
 * VTIMEZONE whose TZID is not a known name (RFC 5545 section 3.6.5).
 *
 * The UTC offset changes that the observances give for the years around
 * the current one are compared with the PHP time zones: the first zone
 * with exactly the same offsets and changes is returned. A VTIMEZONE
 * without any change in that period gives a fixed-offset zone.
 *
 * Limits: only yearly RRULEs on one month (BYMONTH with an ordinal BYDAY,
 * a BYMONTHDAY list with a BYDAY weekday, or a single BYMONTHDAY), RDATEs
 * and plain DTSTART onsets are understood, and only the rules of these
 * years are compared (a matching PHP zone brings its own history for
 * other years). When the changes match no PHP zone, the offset of the
 * STANDARD observance is used as a fixed offset, unless $failIfUncertain
 * is set.
 */
class GuessFromObservances implements TimezoneGuesser
{
    /**
     * The zone names found, keyed by $failIfUncertain, the current year
     * (findZoneName() compares the years around it) and the md5 of the
     * observances (see fingerprint()), least recently used first.
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    private const CACHE_LIMIT = 100;

    /**
     * The last key and zone name per VTIMEZONE object, so that a document
     * pays for each of its VTIMEZONEs once, however many distinct ones it
     * holds: past CACHE_LIMIT, the process-wide cache alone would evict
     * them before they are used again. The values hold no node, so that
     * the entry goes away with its VTIMEZONE.
     *
     * @var \WeakMap<VTimeZone, array{string, string|null}>|null
     */
    private ?\WeakMap $memo = null;

    public function guess(VTimeZone $vtimezone, ?bool $failIfUncertain = false): ?\DateTimeZone
    {
        $key = ($failIfUncertain ? '1' : '0').gmdate('Y').md5($this->fingerprint($vtimezone));
        $this->memo ??= new \WeakMap();
        $memo = $this->memo[$vtimezone] ?? null;
        if (null !== $memo && $memo[0] === $key) {
            $name = $memo[1];
        } else {
            $name = $this->cachedZoneName($key, $vtimezone, (bool) $failIfUncertain);
            $this->memo[$vtimezone] = [$key, $name];
        }

        return null === $name ? null : new \DateTimeZone($name);
    }

    /**
     * The values findZoneName() reads, in the order it reads them: cheaper
     * than serializing the VTIMEZONE, and changes whenever its result may.
     */
    private function fingerprint(VTimeZone $vtimezone): string
    {
        $values = [];
        foreach ($vtimezone->children() as $child) {
            if (!$child instanceof Component || !in_array($child->name, ['STANDARD', 'DAYLIGHT'], true)) {
                continue;
            }
            $rdates = [];
            foreach ($child->select('RDATE') as $rdate) {
                if ($rdate instanceof Property) {
                    $rdates[] = $rdate->getParts();
                }
            }
            $values[] = [
                $child->name,
                (string) $this->firstProperty($child, 'TZOFFSETFROM'),
                (string) $this->firstProperty($child, 'TZOFFSETTO'),
                (string) $this->firstProperty($child, 'DTSTART'),
                $rdates,
                $this->firstProperty($child, 'RRULE')?->getParts(),
            ];
        }

        return serialize($values);
    }

    /**
     * Looks the zone name up in the process-wide LRU cache, finding it on a
     * miss and evicting the least recently used entry when full.
     */
    private function cachedZoneName(string $key, VTimeZone $vtimezone, bool $failIfUncertain): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            $name = self::$cache[$key];
            unset(self::$cache[$key]);
        } else {
            $name = $this->findZoneName($vtimezone, $failIfUncertain);
            if (count(self::$cache) >= self::CACHE_LIMIT) {
                unset(self::$cache[array_key_first(self::$cache)]);
            }
        }
        self::$cache[$key] = $name;

        return $name;
    }

    private function findZoneName(VTimeZone $vtimezone, bool $failIfUncertain): ?string
    {
        $year = (int) gmdate('Y');
        $firstYear = $year - 1;
        $lastYear = $year + 1;

        $observances = [];
        foreach ($vtimezone->children() as $child) {
            if (!$child instanceof Component || !in_array($child->name, ['STANDARD', 'DAYLIGHT'], true)) {
                continue;
            }
            $observance = $this->parseObservance($child);
            if (null !== $observance) {
                $observances[] = $observance;
            }
        }
        if ([] === $observances) {
            return null;
        }

        // UTC instant => [offset from, offset to], from the year before the
        // period on, to know the offset in effect when it starts.
        $periodStart = self::gmTimestamp(0, 0, 0, 1, 1, $firstYear);
        $changes = [];
        $understood = true;
        foreach ($observances as $observance) {
            $onsets = $this->onsets($observance, $firstYear - 1, $lastYear);
            if (null === $onsets) {
                $understood = false;
                continue;
            }
            foreach ($onsets as $onset) {
                $changes[$onset - $observance['from']] = [$observance['from'], $observance['to']];
            }
        }
        ksort($changes);

        // The offset in effect before the period: the last onset before it,
        // else the observance that started last before it.
        $before = null;
        $beforeStart = null;
        foreach ($changes as $instant => [, $to]) {
            if ($instant < $periodStart) {
                $before = $to;
                unset($changes[$instant]);
            }
        }
        foreach ($observances as $observance) {
            if (null === $before && $observance['start'] < $periodStart && (null === $beforeStart || $observance['start'] > $beforeStart)) {
                $beforeStart = $observance['start'];
                $before = $observance['to'];
            }
        }

        $offset = $before ?? ([] === $changes ? null : reset($changes)[0]);
        $initial = $offset;
        $transitions = [];
        foreach ($changes as $instant => [, $to]) {
            if ($to !== $offset) {
                $transitions[] = [$instant, $to];
                $offset = $to;
            }
        }

        if ($understood && null !== $initial) {
            if ([] === $transitions) {
                return $this->offsetName($initial);
            }
            $name = $this->matchPhpZone($initial, $transitions, $periodStart, self::gmTimestamp(0, 0, 0, 1, 1, $lastYear + 1));
            if (null !== $name) {
                return $name;
            }
        }

        if ($failIfUncertain) {
            return null;
        }

        // Fall back to the offset of the (latest) STANDARD observance.
        $standard = null;
        foreach ($observances as $observance) {
            if (null === $standard || ('STANDARD' === $observance['name'] && ('STANDARD' !== $standard['name'] || $observance['start'] > $standard['start']))) {
                $standard = $observance;
            }
        }

        return $this->offsetName($standard['to']);
    }

    /**
     * The first PHP zone per signature (offset at $begin, then the offset
     * changes until $end), for the period it was built for: built once,
     * so that a VTIMEZONE is matched with a lookup instead of a scan of
     * all the PHP zones.
     *
     * @var array{int, int, array<string, string>}|null
     */
    private static ?array $phpZones = null;

    /**
     * @param array<int, array{int, int}> $transitions
     */
    private function matchPhpZone(int $initial, array $transitions, int $begin, int $end): ?string
    {
        if (null === self::$phpZones || self::$phpZones[0] !== $begin || self::$phpZones[1] !== $end) {
            $signatures = [];
            foreach (\DateTimeZone::listIdentifiers() as $identifier) {
                $phpTransitions = (new \DateTimeZone($identifier))->getTransitions($begin, $end);
                if (false === $phpTransitions || [] === $phpTransitions) {
                    continue;
                }
                $offset = $phpTransitions[0]['offset'];
                $list = [];
                foreach (array_slice($phpTransitions, 1) as $transition) {
                    if ($transition['offset'] !== $offset) {
                        $list[] = [$transition['ts'], $transition['offset']];
                        $offset = $transition['offset'];
                    }
                }
                $signatures[self::signature($phpTransitions[0]['offset'], $list)] ??= $identifier;
            }
            self::$phpZones = [$begin, $end, $signatures];
        }

        return self::$phpZones[2][self::signature($initial, $transitions)] ?? null;
    }

    /**
     * @param array<int, array{int, int}> $transitions
     */
    private static function signature(int $initial, array $transitions): string
    {
        $signature = (string) $initial;
        foreach ($transitions as [$instant, $offset]) {
            $signature .= ','.$instant.':'.$offset;
        }

        return $signature;
    }

    /**
     * @return array{name: string, start: int, local: array<int>, from: int, to: int, rrule: ?array, rdates: array<int>}|null
     */
    private function parseObservance(Component $observance): ?array
    {
        $from = $this->parseOffset((string) $this->firstProperty($observance, 'TZOFFSETFROM'));
        $to = $this->parseOffset((string) $this->firstProperty($observance, 'TZOFFSETTO'));
        $local = $this->parseLocal((string) $this->firstProperty($observance, 'DTSTART'));
        if (null === $from || null === $to || null === $local) {
            return null;
        }

        $rdates = [];
        foreach ($observance->select('RDATE') as $rdate) {
            if (!$rdate instanceof Property) {
                continue;
            }
            foreach ($rdate->getParts() as $value) {
                $parsed = $this->parseLocal((string) $value);
                if (null !== $parsed) {
                    $rdates[] = self::gmTimestamp(...$parsed);
                }
            }
        }

        $rrule = $this->firstProperty($observance, 'RRULE')?->getParts();

        return [
            'name' => $observance->name,
            'start' => self::gmTimestamp(...$local) - $from,
            'local' => $local,
            'from' => $from,
            'to' => $to,
            'rrule' => $rrule,
            'rdates' => $rdates,
        ];
    }

    /**
     * Returns the first property of that name: an observance may also hold a
     * component with the same name, which has no value.
     */
    private function firstProperty(Component $observance, string $name): ?Property
    {
        foreach ($observance->select($name) as $child) {
            if ($child instanceof Property) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Returns the onsets (local times as UTC timestamps) of an observance
     * between two years, or null if its RRULE is not understood.
     *
     * @return array<int>|null
     */
    private function onsets(array $observance, int $firstYear, int $lastYear): ?array
    {
        $onsets = [];
        [$hour, $minute, $second, $month, $day, $startYear] = $observance['local'];
        $localStart = self::gmTimestamp($hour, $minute, $second, $month, $day, $startYear);
        if ($startYear >= $firstYear && $startYear <= $lastYear) {
            $onsets[] = $localStart;
        }
        foreach ($observance['rdates'] as $rdate) {
            $year = (int) gmdate('Y', $rdate);
            if ($year >= $firstYear && $year <= $lastYear) {
                $onsets[] = $rdate;
            }
        }

        $rrule = $observance['rrule'];
        if (null === $rrule) {
            return $onsets;
        }

        $rrule = array_change_key_case($rrule, CASE_UPPER);
        $supported = ['FREQ', 'INTERVAL', 'BYMONTH', 'BYDAY', 'BYMONTHDAY', 'UNTIL', 'COUNT', 'WKST'];
        foreach ($rrule as $part => $value) {
            if (is_array($value) && 'BYMONTHDAY' !== $part) {
                return null;
            }
        }
        if ('YEARLY' !== strtoupper((string) ($rrule['FREQ'] ?? '')) || [] !== array_diff(array_keys($rrule), $supported)) {
            return null;
        }
        $interval = (int) ($rrule['INTERVAL'] ?? 1);
        $byMonth = isset($rrule['BYMONTH']) ? (int) $rrule['BYMONTH'] : $month;
        if ($interval < 1 || $byMonth < 1 || $byMonth > 12) {
            return null;
        }
        $until = null;
        if (isset($rrule['UNTIL'])) {
            $parsed = $this->parseLocal((string) $rrule['UNTIL']);
            if (null === $parsed) {
                return null;
            }
            // UNTIL is in UTC (RFC 5545 section 3.3.10); a floating UNTIL
            // is read in the observance's offset.
            $until = self::gmTimestamp(...$parsed) - (str_ends_with(strtoupper((string) $rrule['UNTIL']), 'Z') ? 0 : $observance['from']);
        }
        $count = isset($rrule['COUNT']) ? (int) $rrule['COUNT'] : null;

        for ($year = max($firstYear, $startYear); $year <= $lastYear; ++$year) {
            if (0 !== ($year - $startYear) % $interval) {
                continue;
            }
            if (null !== $count && intdiv($year - $startYear, $interval) >= $count) {
                break;
            }
            $date = $this->dayInYear($rrule, $year, $byMonth, $day);
            if (false === $date) {
                return null;
            }
            if (null === $date) {
                continue;
            }
            $onset = self::gmTimestamp($hour, $minute, $second, $byMonth, $date, $year);
            if ($onset < $localStart || (null !== $until && $onset - $observance['from'] > $until)) {
                continue;
            }
            $onsets[] = $onset;
        }

        return array_values(array_unique($onsets));
    }

    /**
     * Returns the day of the month of the onset in a year, null if there
     * is none that year, false if the rule is not understood.
     */
    private function dayInYear(array $rrule, int $year, int $month, int $defaultDay): int|false|null
    {
        $daysInMonth = (int) gmdate('t', self::gmTimestamp(0, 0, 0, $month, 1, $year));
        $weekdays = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];

        $monthDays = null;
        if (isset($rrule['BYMONTHDAY'])) {
            $monthDays = [];
            foreach ((array) $rrule['BYMONTHDAY'] as $monthDay) {
                if (!preg_match('/^-?[0-9]{1,2}\z/', (string) $monthDay)) {
                    return false;
                }
                $monthDay = (int) $monthDay;
                $monthDay = $monthDay < 0 ? $daysInMonth + $monthDay + 1 : $monthDay;
                if ($monthDay >= 1 && $monthDay <= $daysInMonth) {
                    $monthDays[] = $monthDay;
                }
            }
            sort($monthDays);
        }

        if (!isset($rrule['BYDAY'])) {
            if (null === $monthDays) {
                return $defaultDay <= $daysInMonth ? $defaultDay : null;
            }

            return 1 === count($monthDays) ? $monthDays[0] : false;
        }

        if (!preg_match('/^([+-]?[0-9]{0,2})(SU|MO|TU|WE|TH|FR|SA)\z/', strtoupper((string) $rrule['BYDAY']), $matches)) {
            return false;
        }
        $weekday = $weekdays[$matches[2]];

        if (null !== $monthDays) {
            if ('' !== $matches[1]) {
                return false;
            }
            foreach ($monthDays as $monthDay) {
                if ((int) gmdate('w', self::gmTimestamp(0, 0, 0, $month, $monthDay, $year)) === $weekday) {
                    return $monthDay;
                }
            }

            return null;
        }

        $ordinal = (int) $matches[1];
        if (0 === $ordinal || $ordinal > 5 || $ordinal < -5) {
            return false;
        }
        if ($ordinal > 0) {
            $first = (int) gmdate('w', self::gmTimestamp(0, 0, 0, $month, 1, $year));
            $monthDay = 1 + ($weekday - $first + 7) % 7 + 7 * ($ordinal - 1);
        } else {
            $last = (int) gmdate('w', self::gmTimestamp(0, 0, 0, $month, $daysInMonth, $year));
            $monthDay = $daysInMonth - ($last - $weekday + 7) % 7 + 7 * ($ordinal + 1);
        }

        return $monthDay >= 1 && $monthDay <= $daysInMonth ? $monthDay : null;
    }

    /**
     * Parses a UTC offset ("+0100", "-053000") into seconds.
     */
    private function parseOffset(string $offset): ?int
    {
        if (!preg_match('/^([+-])([0-9]{2})([0-9]{2})([0-9]{2})?\z/', $offset, $matches)) {
            return null;
        }
        $seconds = (int) $matches[2] * 3600 + (int) $matches[3] * 60 + (int) ($matches[4] ?? 0);
        if ($seconds >= 86400) {
            return null;
        }

        return '-' === $matches[1] ? -$seconds : $seconds;
    }

    /**
     * Parses a DATE-TIME (or DATE) value into gmTimestamp() arguments.
     *
     * @return array{int, int, int, int, int, int}|null
     */
    private function parseLocal(string $value): ?array
    {
        if (!preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})(?:T([0-9]{2})([0-9]{2})([0-9]{2})Z?)?\z/i', $value, $matches)) {
            return null;
        }

        return [(int) ($matches[4] ?? 0), (int) ($matches[5] ?? 0), (int) ($matches[6] ?? 0), (int) $matches[2], (int) $matches[3], (int) $matches[1]];
    }

    /**
     * gmmktime() with the year taken as written: gmmktime() reads a year
     * from 0 to 100 as 1970-2069, so 0050 would be 2050. Such a year is
     * computed 400 years later, as the Gregorian calendar repeats every 400
     * years (146097 days), and moved back.
     */
    private static function gmTimestamp(int $hour, int $minute, int $second, int $month, int $day, int $year): int
    {
        if ($year > 100) {
            return gmmktime($hour, $minute, $second, $month, $day, $year);
        }

        return gmmktime($hour, $minute, $second, $month, $day, $year + 400) - 146097 * 86400;
    }

    /**
     * The name of a fixed-offset zone ("+01:00", or "+01:00:30" with seconds,
     * which RFC 5545 section 3.3.14 allows), which TimeZoneUtil resolves back.
     */
    private function offsetName(int $offset): string
    {
        $seconds = abs($offset);
        $name = sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));

        return 0 === $seconds % 60 ? $name : sprintf('%s:%02d', $name, $seconds % 60);
    }
}
