<?php

namespace Sabre\VObject\Recur;

use DateTimeImmutable;
use Iterator;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;

/**
 * RRuleParser.
 *
 * This class receives an RRULE string, and allows you to iterate to get a list
 * of dates in that recurrence.
 *
 * For instance, passing: FREQ=DAILY;LIMIT=5 will cause the iterator to contain
 * 5 items, one for each day.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class RRuleIterator implements \Iterator
{
    /**
     * Constant denoting the upper limit on how long into the future
     * we want to iterate. The value is a unix timestamp and currently
     * corresponds to the datetime 9999-12-31 11:59:59 UTC.
     */
    public const dateUpperLimit = 253402300799;

    /**
     * The Gregorian calendar repeats every 400 years (4800 months, 146097
     * days, a whole number of weeks): what BYDAY, BYMONTHDAY and BYSETPOS
     * select in a month only depends on its place in that cycle.
     */
    private const GREGORIAN_CYCLE_MONTHS = 4800;

    /**
     * For each frequency, the number of periods in 10000 years: a single
     * step of a larger INTERVAL leaves the supported date range anyway.
     */
    private const maxInterval = [
        'secondly' => 315569520000,
        'minutely' => 5259492000,
        'hourly' => 87658200,
        'daily' => 3652425,
        'weekly' => 521775,
        'monthly' => 120000,
        'yearly' => 10000,
    ];

    /**
     * Creates the Iterator.
     *
     * @param string|array $rrule
     *
     * @throws InvalidDataException
     */
    public function __construct($rrule, /**
     * The reference start date/time for the rrule.
     *
     * All calculations are based on this initial date.
     */
        protected \DateTimeInterface $startDate)
    {
        $this->parseRRule($rrule);
        $this->currentDate = clone $this->startDate;
        $this->skipInvalidStart();
    }

    /**
     * For YEARLY frequency with BYMONTH, check if DTSTART matches the rules.
     * If not, advance to the first valid occurrence without incrementing the counter.
     * See: https://github.com/linagora/esn-sabre/issues/50.
     */
    private function skipInvalidStart(): void
    {
        if ('yearly' === $this->frequency && $this->byMonth) {
            $currentMonth = (int) $this->currentDate->format('n');
            $validMonths = array_map(static fn ($month): int => (int) $month, $this->byMonth);
            if (!in_array($currentMonth, $validMonths, true)) {
                // DTSTART month is not in BYMONTH, advance to first valid occurrence
                $this->nextYearly();
                $this->useFirstOccurrenceOfLocalTime($this->startDate);
            } elseif ($this->byMonthDay || $this->byDay) {
                // Month is valid, but check if day is valid
                $currentDay = (int) $this->currentDate->format('j');
                $validDays = array_map(static fn ($day): int => (int) $day, $this->getMonthlyOccurrences());
                if (!in_array($currentDay, $validDays, true)) {
                    // Day is not valid, advance to first valid occurrence
                    $this->nextYearly();
                    $this->useFirstOccurrenceOfLocalTime($this->startDate);
                }
            }
        }
    }

    /* Implementation of the Iterator interface {{{ */

    #[\ReturnTypeWillChange]
    public function current(): ?\DateTimeInterface
    {
        if (!$this->valid()) {
            return null;
        }

        return clone $this->currentDate;
    }

    /**
     * Returns the current item number.
     */
    #[\ReturnTypeWillChange]
    public function key(): int
    {
        return $this->counter;
    }

    /**
     * Returns whether the current item is a valid item for the recurrence
     * iterator. This will return false if we've gone beyond the UNTIL or COUNT
     * statements.
     */
    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        if (null === $this->currentDate) {
            return false;
        }
        // RFC 5545 forbids COUNT with UNTIL; when a rule has both, it ends
        // with whichever comes first.
        if (!is_null($this->count) && $this->counter >= $this->count) {
            return false;
        }

        return is_null($this->until) || $this->currentDate <= $this->until;
    }

    /**
     * Resets the iterator.
     */
    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        $this->currentDate = clone $this->startDate;
        $this->counter = 0;
        $this->skipInvalidStart();
    }

    /**
     * Goes on to the next iteration.
     */
    #[\ReturnTypeWillChange]
    public function next(): void
    {
        // The iteration already ended (past 9999-12-31).
        if (null === $this->currentDate) {
            return;
        }
        // A clone: a \DateTime start makes currentDate mutable.
        $previous = clone $this->currentDate;

        // Otherwise, we find the next event in the normal RRULE
        // sequence.
        switch ($this->frequency) {
            case 'secondly':
                $this->nextSecondly();
                break;

            case 'minutely':
                $this->nextMinutely();
                break;

            case 'hourly':
                $this->nextHourly();
                break;

            case 'daily':
                $this->nextDaily();
                break;

            case 'weekly':
                $this->nextWeekly();
                break;

            case 'monthly':
                $this->nextMonthly();
                break;

            case 'yearly':
                $this->nextYearly();
                break;
        }

        // Not every advance checks the upper limit itself.
        if (null !== $this->currentDate && $this->isPastUpperLimit()) {
            $this->currentDate = null;
        }
        $this->useFirstOccurrenceOfLocalTime($previous);
        ++$this->counter;
    }

    /* End of Iterator implementation }}} */

    /**
     * Returns true if this recurring event never ends.
     */
    public function isInfinite(): bool
    {
        return !$this->count && !$this->until;
    }

    /**
     * This method allows you to quickly go to the next occurrence after the
     * specified date.
     */
    public function fastForward(\DateTimeInterface $dt): void
    {
        while ($this->valid() && $this->currentDate < $dt) {
            $this->next();
        }
    }

    /**
     * The date of the current iteration. You can get this by calling
     * ->current().
     */
    protected ?\DateTimeInterface $currentDate;

    /**
     * The number of hours that the next occurrence of an event
     * jumped forward, usually because summer time started and
     * the requested time-of-day like 0230 did not exist on that
     * day. And so the event was scheduled 1 hour later at 0330.
     */
    protected int $hourJump = 0;

    /**
     * Frequency is one of: secondly, minutely, hourly, daily, weekly, monthly,
     * yearly.
     */
    protected string $frequency;

    /**
     * The number of recurrences, or 'null' if infinitely recurring.
     */
    protected ?int $count = null;

    /**
     * The interval.
     *
     * If for example frequency is set to daily, interval = 2 would mean every
     * 2 days.
     */
    protected int $interval = 1;

    /**
     * The last instance of this recurrence, inclusively.
     */
    protected ?\DateTimeInterface $until = null;

    /**
     * Which seconds to recur.
     *
     * This is an array of integers (between 0 and 60)
     */
    protected ?array $bySecond = null;

    /**
     * Which minutes to recur.
     *
     * This is an array of integers (between 0 and 59)
     */
    protected ?array $byMinute = null;

    /**
     * Which hours to recur.
     *
     * This is an array of integers (between 0 and 23)
     */
    protected ?array $byHour = null;

    /**
     * The current item in the list.
     *
     * You can get this number with the key() method.
     */
    protected int $counter = 0;

    /**
     * Which weekdays to recur.
     *
     * This is an array of weekdays
     *
     * This may also be preceded by a positive or negative integer. If present,
     * this indicates the nth occurrence of a specific day within the monthly or
     * yearly rrule. For instance, -2TU indicates the second-last tuesday of
     * the month, or year.
     */
    protected ?array $byDay = null;

    /**
     * Which days of the month to recur.
     *
     * This is an array of days of the months (1-31). The value can also be
     * negative. -5 for instance means the 5th last day of the month.
     */
    protected ?array $byMonthDay = null;

    /**
     * Which days of the year to recur.
     *
     * This is an array with days of the year (1 to 366). The values can also
     * be negative. For instance, -1 will always represent the last day of the
     * year. (December 31st).
     */
    protected ?array $byYearDay = null;

    /**
     * Which week numbers to recur.
     *
     * This is an array of integers from 1 to 53. The values can also be
     * negative. -1 will always refer to the last week of the year.
     */
    protected ?array $byWeekNo = null;

    /**
     * Which months to recur.
     *
     * This is an array of integers from 1 to 12.
     */
    protected ?array $byMonth = null;

    /**
     * Which items in an existing st to recur.
     *
     * These numbers work together with an existing by* rule. It specifies
     * exactly which items of the existing by-rule to filter.
     *
     * Valid values are 1 to 366 and -1 to -366. As an example, this can be
     * used to recur the last workday of the month.
     *
     * This would be done by setting frequency to 'monthly', byDay to
     * 'MO,TU,WE,TH,FR' and bySetPos to -1.
     */
    protected ?array $bySetPos = null;

    /**
     * When the week starts.
     */
    protected string $weekStart = 'MO';

    /* Functions that advance the iterator {{{ */

    /**
     * Returns true once currentDate is past 9999-12-31, in UTC or in its own
     * time zone: east of UTC, the local date reaches year 10000 before the
     * timestamp passes dateUpperLimit, and a 5-digit year cannot be written
     * (nor parsed back by the 'Y-m-1' round trips below).
     */
    private function isPastUpperLimit(): bool
    {
        return $this->currentDate->getTimestamp() > self::dateUpperLimit
            || (int) $this->currentDate->format('Y') > 9999;
    }

    /**
     * Gets the original start time of the RRULE.
     *
     * The value is formatted as a string with 24-hour:minute:second
     */
    protected function startTime(): string
    {
        return $this->startDate->format('H:i:s');
    }

    /**
     * Advances currentDate by the interval.
     * The time is set from the original startDate.
     * If the recurrence is on a day when summer time started, then the
     * time on that day may have jumped forward, for example, from 0230 to 0330.
     * Using the original time means that the next recurrence will be calculated
     * based on the original start time and the day/week/month/year interval.
     * So the start time of the next occurrence can correctly revert to 0230.
     */
    protected function advanceTheDate(string $interval): void
    {
        $this->currentDate = $this->currentDate->modify($interval.' '.$this->startTime());
    }

    /**
     * Moves currentDate to the first occurrence of its local time when that
     * time occurs twice (when summer time ends), as RFC 5545 section 3.3.5
     * requires: PHP resolves such a local time to its second occurrence, or
     * keeps the UTC offset of the date a relative modify() started from.
     *
     * Only the DAILY to YEARLY frequencies work on the local time, and the
     * first occurrence is only used if it still comes after the previous
     * instance (a BYHOUR rule walking hour by hour reaches the second
     * occurrence from the first one).
     */
    private function useFirstOccurrenceOfLocalTime(\DateTimeInterface $previous): void
    {
        if (null === $this->currentDate || !in_array($this->frequency, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            return;
        }
        // If the clocks went back during the last day, the same local time
        // may also exist that much earlier.
        $timestamp = $this->currentDate->getTimestamp();
        $shift = $this->currentDate->getTimezone()->getOffset(new \DateTimeImmutable('@'.($timestamp - 86400)))
            - $this->currentDate->getOffset();
        if ($shift <= 0) {
            return;
        }
        $first = \DateTimeImmutable::createFromInterface($this->currentDate)->setTimestamp($timestamp - $shift);
        if ($first->format('Y-m-d H:i:s') === $this->currentDate->format('Y-m-d H:i:s') && $first > $previous) {
            $this->currentDate = $this->currentDate->setTimestamp($timestamp - $shift);
        }
    }

    /**
     * Does the processing for adjusting the time of multi-hourly events when summer time starts.
     */
    protected function adjustForTimeJumpsOfHourlyEvent(\DateTimeInterface $previousEventDateTime): void
    {
        if (0 === $this->hourJump) {
            // Remember if the clock time jumped forward on the next occurrence.
            // That happens if the next event time is on a day when summer time starts
            // and the event time is in the non-existent hour of the day.
            // For example, an event that normally starts at 02:30 will
            // have to start at 03:30 on that day.
            // If the interval is just 1 hour, then there is no "jumping back" to do.
            // The events that day will happen, for example, at 0030 0130 0330 0430 0530...
            if ($this->interval > 1) {
                $expectedHourOfNextDate = ((int) $previousEventDateTime->format('G') + $this->interval) % 24;
                $actualHourOfNextDate = (int) $this->currentDate->format('G');
                $this->hourJump = $actualHourOfNextDate - $expectedHourOfNextDate;
            }
        } else {
            // The hour "jumped" for the previous occurrence, to avoid the non-existent time.
            // currentDate got set ahead by (usually) 1 hour on that day.
            // Adjust it back for this next occurrence.
            $this->currentDate = $this->currentDate->sub(new \DateInterval('PT'.$this->hourJump.'H'));
            $this->hourJump = 0;
        }
    }

    /**
     * Does the processing for advancing the iterator for secondly frequency.
     *
     * The interval is elapsed time: modify() would add it to the wall-clock
     * time and skip or repeat an hour on a DST change.
     */
    protected function nextSecondly(): void
    {
        $this->currentDate = $this->currentDate->setTimestamp($this->currentDate->getTimestamp() + $this->interval);
    }

    /**
     * Does the processing for advancing the iterator for minutely frequency.
     */
    protected function nextMinutely(): void
    {
        $this->currentDate = $this->currentDate->setTimestamp($this->currentDate->getTimestamp() + $this->interval * 60);
    }

    /**
     * Does the processing for advancing the iterator for hourly frequency.
     */
    protected function nextHourly(): void
    {
        $previousEventDateTime = clone $this->currentDate;
        $this->currentDate = $this->currentDate->modify('+'.$this->interval.' hours');
        $this->adjustForTimeJumpsOfHourlyEvent($previousEventDateTime);
    }

    /**
     * Does the processing for advancing the iterator for daily frequency.
     */
    protected function nextDaily(): void
    {
        if (!$this->byHour && !$this->byDay) {
            $this->advanceTheDate('+'.$this->interval.' days');

            return;
        }

        $recurrenceHours = [];
        if ($this->byHour) {
            $recurrenceHours = $this->getHours();
        }

        $recurrenceDays = [];
        if ($this->byDay) {
            $recurrenceDays = $this->getDays();
        }

        $recurrenceMonths = [];
        if ($this->byMonth) {
            $recurrenceMonths = $this->getMonths();
        }

        do {
            if ($this->byHour) {
                if ('23' === $this->currentDate->format('G')) {
                    // to obey the interval rule
                    $this->currentDate = $this->currentDate->modify('+'.($this->interval - 1).' days');
                }

                $this->currentDate = $this->currentDate->modify('+1 hours');
            } else {
                $this->currentDate = $this->currentDate->modify('+'.$this->interval.' days');
            }

            // Current month of the year
            $currentMonth = (int) $this->currentDate->format('n');

            // Current day of the week
            $currentDay = $this->currentDate->format('w');

            // Current hour of the day
            $currentHour = (int) $this->currentDate->format('G');

            if ($this->isPastUpperLimit()) {
                $this->currentDate = null;

                return;
            }
        } while (
            ($this->byDay && !in_array((int) $currentDay, $recurrenceDays, true))
            || ($this->byHour && !in_array($currentHour, $recurrenceHours, true))
            || ($this->byMonth && !in_array($currentMonth, $recurrenceMonths, true))
        );
    }

    /**
     * Does the processing for advancing the iterator for weekly frequency.
     */
    protected function nextWeekly(): void
    {
        if (!$this->byHour && !$this->byDay) {
            $this->advanceTheDate('+'.$this->interval.' weeks');

            return;
        }

        $recurrenceHours = [];
        if ($this->byHour) {
            $recurrenceHours = $this->getHours();
        }

        // Without BYDAY, the weekday comes from DTSTART (RFC 5545 section
        // 3.3.10): BYHOUR only expands the hours of that day.
        $recurrenceDays = [(int) $this->startDate->format('w')];
        if ($this->byDay) {
            $recurrenceDays = $this->getDays();
        }

        // First day of the week:
        $firstDay = $this->dayMap[$this->weekStart];

        do {
            if ($this->byHour) {
                $this->currentDate = $this->currentDate->modify('+1 hours');
            } else {
                $this->advanceTheDate('+1 days');
            }

            // Current day of the week
            $currentDay = (int) $this->currentDate->format('w');

            // Current hour of the day
            $currentHour = (int) $this->currentDate->format('G');

            // We need to roll over to the next week
            if ($currentDay === $firstDay && (!$this->byHour || 0 === $currentHour)) {
                $this->currentDate = $this->currentDate->modify('+'.($this->interval - 1).' weeks');

                // We need to go to the first day of this week, but only if we
                // are not already on this first day of this week.
                if ((int) $this->currentDate->format('w') !== $firstDay) {
                    $this->currentDate = $this->currentDate->modify('last '.$this->dayNames[$this->dayMap[$this->weekStart]]);
                }
            }

            if ($this->isPastUpperLimit()) {
                $this->currentDate = null;

                return;
            }

            // We have a match
        } while (!in_array($currentDay, $recurrenceDays, true) || ($this->byHour && !in_array($currentHour, $recurrenceHours, true)));
    }

    /**
     * Does the processing for advancing the iterator for monthly frequency.
     *
     * @throws \Exception
     */
    protected function nextMonthly(): void
    {
        $currentDayOfMonth = $this->currentDate->format('j');
        if (!$this->byMonthDay && !$this->byDay) {
            // If the current day is higher than the 28th, rollover can
            // occur to the next month. We Must skip these invalid
            // entries.
            if ($currentDayOfMonth < 29) {
                $this->advanceTheDate('+'.$this->interval.' months');
            } else {
                $increase = 0;
                do {
                    ++$increase;
                    $tempDate = clone $this->currentDate;
                    $tempDate = $tempDate->modify('+ '.($this->interval * $increase).' months '.$this->startTime());
                } while ($tempDate->format('j') !== $currentDayOfMonth);
                $this->currentDate = $tempDate;
            }

            return;
        }

        $selectedOccurrence = -1;
        $emptyMonths = 0;
        while (true) {
            $occurrences = $this->getMonthlyOccurrences();

            foreach ($occurrences as $occurrence) {
                // The first occurrence that's higher than the current
                // day of the month wins.
                if ($occurrence > $currentDayOfMonth) {
                    $selectedOccurrence = $occurrence;
                    break 2;
                }
            }

            // If we made it all the way here, it means there were no
            // valid occurrences, and we need to advance to the next
            // month.
            //
            // This line does not currently work in hhvm. Temporary workaround
            // follows:
            // $this->currentDate->modify('first day of this month');
            $this->currentDate = new \DateTimeImmutable($this->currentDate->format('Y-m-1 H:i:s'), $this->currentDate->getTimezone());
            // end of workaround
            $this->currentDate = $this->currentDate->modify('+ '.$this->interval.' months');

            // This goes to 0 because we need to start counting at the
            // beginning.
            $currentDayOfMonth = 0;

            // For some reason the "until" parameter was not being used here,
            // that's why the workaround of the 10000-year bug was needed at all
            // let's stop it before the "until" parameter date.
            // The first of the month at the start time is not an occurrence
            // in itself: when it equals UNTIL, we still need to check if that
            // day is an occurrence, so only stop once we are past UNTIL.
            if ($this->until && $this->currentDate->getTimestamp() > $this->until->getTimestamp()) {
                return;
            }

            // To prevent running this forever (better: until we hit the max date of DateTimeImmutable) we simply
            // stop at 9999-12-31. Looks like the year 10000 problem is not solved in php ....
            // The days a month yields only depend on its place in the
            // 400-year Gregorian cycle (4800 months): a rule that matched
            // none of 4800 candidate months in a row never will, whatever
            // its INTERVAL, so the scan ends there too.
            if (++$emptyMonths > self::GREGORIAN_CYCLE_MONTHS || $this->isPastUpperLimit()) {
                $this->currentDate = null;

                return;
            }
        }

        // Set the currentDate to the year and month that we are in, and the day of the month that we have selected.
        // That day could be a day when summer time starts, and if the time of the event is, for example, 0230,
        // then 0230 will not be a valid time on that day. So always apply the start time from the original startDate.
        // The "modify" method will set the time forward to 0330, for example, if needed.
        $this->currentDate = $this->currentDate->setDate(
            (int) $this->currentDate->format('Y'),
            (int) $this->currentDate->format('n'),
            (int) $selectedOccurrence
        )->modify($this->startTime());
    }

    /**
     * Does the processing for advancing the iterator for yearly frequency.
     */
    protected function nextYearly(): void
    {
        $currentMonth = $this->currentDate->format('n');
        $currentYear = $this->currentDate->format('Y');
        $currentDayOfMonth = $this->currentDate->format('j');

        // No sub-rules, so we just advance by year
        if (!$this->byMonth) {
            // Unless it was a leap day! (BYWEEKNO and BYYEARDAY, below,
            // do not take the day of the month from the current instance.)
            if (2 === (int) $currentMonth && 29 === (int) $currentDayOfMonth
                && null === $this->byWeekNo && null === $this->byYearDay) {
                $counter = 0;
                do {
                    ++$counter;
                    // Here we increase the year count by the interval, until
                    // we hit a date that's also in a leap year.
                    //
                    // We could just find the next interval that's dividable by
                    // 4, but that would ignore the rule that there's no leap
                    // year every year that's dividable by a 100, but not by
                    // 400. (1800, 1900, 2100). So we just rely on the datetime
                    // functions instead.
                    $nextDate = clone $this->currentDate;
                    $nextDate = $nextDate->modify('+ '.($this->interval * $counter).' years');
                } while (2 !== (int) $nextDate->format('n'));

                $this->currentDate = $nextDate;

                return;
            }

            if (null !== $this->byWeekNo) { // byWeekNo is an array with values from -53 to -1, or 1 to 53
                $dayOffsets = [];
                if ($this->byDay) {
                    // An ordinal makes no sense here (RFC 5545 section
                    // 3.3.10 forbids it with BYWEEKNO): getDays() drops it.
                    // ISO days run from 1 (Monday) to 7 (Sunday).
                    $dayOffsets = array_map(static fn (int $day): int => 0 === $day ? 7 : $day, $this->getDays());
                } else {
                    // Without BYDAY, the weekday comes from DTSTART
                    // (RFC 5545 section 3.3.10).
                    $dayOffsets[] = (int) $this->startDate->format('N');
                }

                $currentYear = $this->currentDate->format('Y');

                while (true) {
                    $checkDates = [];

                    // loop through all WeekNo and Days to check all the combinations
                    foreach ($this->byWeekNo as $byWeekNo) {
                        foreach ($dayOffsets as $dayOffset) {
                            $date = clone $this->currentDate;
                            $date = $date->setISODate($currentYear, $byWeekNo, $dayOffset);

                            if ($date > $this->currentDate) {
                                $checkDates[] = $date;
                            }
                        }
                    }

                    if (count($checkDates) > 0) {
                        $this->currentDate = min($checkDates);

                        return;
                    }

                    // if there is no date found, check the next year
                    $currentYear += $this->interval;

                    // Stop at 9999-12-31, as the other loops do.
                    if ($currentYear > 9999) {
                        $this->currentDate = null;

                        return;
                    }
                }
            }

            if (null !== $this->byYearDay) { // byYearDay is an array with values from -366 to -1, or 1 to 366
                $dayOffsets = [];
                if ($this->byDay) {
                    // An ordinal makes no sense here (RFC 5545 section
                    // 3.3.10 forbids it with BYWEEKNO): getDays() drops it.
                    // ISO days run from 1 (Monday) to 7 (Sunday).
                    $dayOffsets = array_map(static fn (int $day): int => 0 === $day ? 7 : $day, $this->getDays());
                } else {   // default is Monday-Sunday
                    $dayOffsets = [1, 2, 3, 4, 5, 6, 7];
                }

                $currentYear = $this->currentDate->format('Y');

                while (true) {
                    $checkDates = [];

                    // loop through all YearDay and Days to check all the combinations
                    foreach ($this->byYearDay as $byYearDay) {
                        $date = clone $this->currentDate;
                        if ($byYearDay > 0) {
                            $date = $date->setDate($currentYear, 1, 1);
                            $date = $date->add(new \DateInterval('P'.($byYearDay - 1).'D'));
                        } else {
                            $date = $date->setDate($currentYear, 12, 31);
                            $date = $date->sub(new \DateInterval('P'.abs($byYearDay + 1).'D'));
                        }

                        if ($date > $this->currentDate && in_array((int) $date->format('N'), $dayOffsets, true)) {
                            $checkDates[] = $date;
                        }
                    }

                    if (count($checkDates) > 0) {
                        $this->currentDate = min($checkDates);

                        return;
                    }

                    // if there is no date found, check the next year
                    $currentYear += $this->interval;

                    // Stop at 9999-12-31, as the other loops do.
                    if ($currentYear > 9999) {
                        $this->currentDate = null;

                        return;
                    }
                }
            }

            // The easiest form
            $this->advanceTheDate('+'.$this->interval.' years');

            return;
        }

        $currentMonth = $this->currentDate->format('n');
        $currentYear = $this->currentDate->format('Y');
        $currentDayOfMonth = $this->currentDate->format('j');

        $advancedToNewMonth = false;

        // If we got a byDay or getMonthDay filter, we must first expand
        // further.
        if ($this->byDay || $this->byMonthDay) {
            $selectedOccurrence = -1;
            $emptyYears = 0;
            while (true) {
                $occurrences = $this->getMonthlyOccurrences();

                foreach ($occurrences as $occurrence) {
                    // The first occurrence that's higher than the current
                    // day of the month wins.
                    // If we advanced to the next month or year, the first
                    // occurrence is always correct.
                    if ($occurrence > $currentDayOfMonth || $advancedToNewMonth) {
                        // only consider byMonth matches,
                        // otherwise, we don't follow RRule correctly
                        if (in_array((int) $currentMonth, $this->byMonth, true)) {
                            $selectedOccurrence = $occurrence;
                            break 2;
                        }
                    }
                }

                // If we made it here, it means we need to advance to
                // the next month or year.
                $currentDayOfMonth = 1;
                $advancedToNewMonth = true;
                do {
                    ++$currentMonth;
                    if ($currentMonth > 12) {
                        $currentYear += $this->interval;
                        $currentMonth = 1;
                        ++$emptyYears;
                    }
                } while (!in_array($currentMonth, $this->byMonth, true));

                // As in nextMonthly(): no match in a whole 400-year cycle
                // of candidate years means no match ever.
                if ($emptyYears > self::GREGORIAN_CYCLE_MONTHS / 12) {
                    $this->currentDate = null;

                    return;
                }

                $this->currentDate = $this->currentDate->setDate(
                    (int) $currentYear,
                    $currentMonth,
                    $currentDayOfMonth
                );

                // To prevent running this forever (better: until we hit the max date of DateTimeImmutable) we simply
                // stop at 9999-12-31. Looks like the year 10000 problem is not solved in php ....
                if ($this->isPastUpperLimit()) {
                    $this->currentDate = null;

                    return;
                }
            }

            // If we made it here, it means we got a valid occurrence
            $this->currentDate = $this->currentDate->setDate(
                (int) $currentYear,
                (int) $currentMonth,
                (int) $selectedOccurrence
            )->modify($this->startTime());

            return;
        }
        // These are the 'byMonth' rules, if there are no byDay or
        // byMonthDay sub-rules. The day of the month is the one of DTSTART,
        // and a month without that day (30 February) is skipped
        // (RFC 5545 section 3.3.10).
        $dayOfMonth = (int) $this->startDate->format('j');
        do {
            do {
                ++$currentMonth;
                if ($currentMonth > 12) {
                    $currentYear += $this->interval;
                    $currentMonth = 1;
                }
            } while (!in_array($currentMonth, $this->byMonth, true));

            if ((int) $currentYear > 9999) {
                $this->currentDate = null;

                return;
            }
        } while (!checkdate($currentMonth, $dayOfMonth, (int) $currentYear));
        $this->currentDate = $this->currentDate->setDate(
            (int) $currentYear,
            $currentMonth,
            $dayOfMonth
        )->modify($this->startTime());
    }

    /* }}} */

    /**
     * This method receives a string from an RRULE property, and populates this
     * class with all the values.
     *
     * @param string|array $rrule
     *
     * @throws InvalidDataException
     */
    protected function parseRRule($rrule): void
    {
        if (is_string($rrule)) {
            $rrule = Property\ICalendar\Recur::stringToArray($rrule);
        }

        foreach ($rrule as $key => $value) {
            $key = strtoupper((string) $key);
            switch ($key) {
                case 'FREQ':
                    $value = strtolower(self::scalarPart($key, $value));
                    if (!in_array(
                        $value,
                        ['secondly', 'minutely', 'hourly', 'daily', 'weekly', 'monthly', 'yearly'],
                        true
                    )) {
                        throw new InvalidDataException('Unknown value for FREQ='.strtoupper($value));
                    }
                    $this->frequency = $value;
                    break;

                case 'UNTIL':
                    $this->until = DateTimeParser::parse(self::scalarPart($key, $value), $this->startDate->getTimezone());

                    // In some cases events are generated with an UNTIL=
                    // parameter before the actual start of the event.
                    //
                    // Not sure why this is happening. We assume that the
                    // intention was that the event only recurs once.
                    //
                    // So we are modifying the parameter so our code doesn't
                    // break.
                    if ($this->until < $this->startDate) {
                        $this->until = $this->startDate;
                    }
                    break;

                case 'INTERVAL':
                case 'COUNT':
                    $val = (int) self::scalarPart($key, $value);
                    if ($val < 1) {
                        throw new InvalidDataException(strtoupper($key).' in RRULE must be a positive integer!');
                    }
                    $key = strtolower($key);
                    $this->$key = $val;
                    break;

                case 'BYSECOND':
                    $this->bySecond = (array) $value;
                    break;

                case 'BYMINUTE':
                    $this->byMinute = (array) $value;
                    break;

                case 'BYHOUR':
                    $this->byHour = self::unsignedParts($value, 0, 23, 'BYHOUR in RRULE must have value(s) between 0 and 23!');
                    break;

                case 'BYDAY':
                    $this->byDay = [];
                    foreach ((array) $value as $part) {
                        // A sign needs an ordinal, and nothing may follow
                        // the day name (D: no trailing line feed).
                        if (!is_scalar($part) || !preg_match('#^(?:[+-]?[1-5])?(?:MO|TU|WE|TH|FR|SA|SU)$#Di', (string) $part)) {
                            throw new InvalidDataException('Invalid part in BYDAY clause: '.(is_scalar($part) ? $part : gettype($part)));
                        }
                        $this->byDay[] = strtoupper((string) $part);
                    }
                    break;

                case 'BYMONTHDAY':
                    $this->byMonthDay = self::integerParts($key, $value, 31);
                    break;

                case 'BYYEARDAY':
                    $this->byYearDay = self::integerParts($key, $value, 366);
                    break;

                case 'BYWEEKNO':
                    $this->byWeekNo = self::integerParts($key, $value, 53);
                    break;

                case 'BYMONTH':
                    $this->byMonth = self::unsignedParts($value, 1, 12, 'BYMONTH in RRULE must have value(s) between 1 and 12!');
                    break;

                case 'BYSETPOS':
                    $this->bySetPos = self::integerParts($key, $value, 366);
                    break;

                case 'WKST':
                    $value = strtoupper(self::scalarPart($key, $value));
                    if (!isset($this->dayMap[$value])) {
                        throw new InvalidDataException('Invalid value for WKST in RRULE: '.$value);
                    }
                    $this->weekStart = $value;
                    break;

                default:
                    throw new InvalidDataException('Not supported: '.strtoupper($key));
            }
        }

        if (!isset($this->frequency)) {
            throw new InvalidDataException('FREQ is required in RRULE');
        }

        // Clamping a huge INTERVAL changes no instance, and keeps the date
        // arithmetic away from integer overflows.
        $this->interval = min($this->interval, self::maxInterval[$this->frequency]);
    }

    /**
     * Mappings between the day number and english day name.
     */
    protected array $dayNames = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Returns all the occurrences for a monthly frequency with a 'byDay' or
     * 'byMonthDay' expansion for the current month.
     *
     * The returned list is an array of integers with the day of month (1-31).
     *
     * @throws \Exception
     */
    protected function getMonthlyOccurrences(): array
    {
        $startDate = clone $this->currentDate;

        $byDayResults = [];

        // Our strategy is to simply go through the byDays, advance the date to
        // that point and add it to the results.
        if ($this->byDay) {
            foreach ($this->byDay as $day) {
                $dayName = $this->dayNames[$this->dayMap[substr((string) $day, -2)]];

                // Dayname will be something like 'wednesday'. Now we need to find
                // all wednesdays in this month.
                $dayHits = [];

                // workaround for missing 'first day of the month' support in hhvm
                $checkDate = new \DateTime($startDate->format('Y-m-1'));
                // workaround modify always advancing the date even if the current day is a $dayName in hhvm
                if ($checkDate->format('l') !== $dayName) {
                    $checkDate = $checkDate->modify($dayName);
                }

                do {
                    $dayHits[] = $checkDate->format('j');
                    $checkDate = $checkDate->modify('next '.$dayName);
                } while ($checkDate->format('n') === $startDate->format('n'));

                // So now we have 'all wednesdays' for month. It is however
                // possible that the user only really wanted the 1st, 2nd or last
                // wednesday.
                if (strlen((string) $day) > 2) {
                    $offset = (int) substr((string) $day, 0, -2);

                    if ($offset > 0) {
                        // It is possible that the day does not exist, such as a
                        // 5th or 6th wednesday of the month.
                        if (isset($dayHits[$offset - 1])) {
                            $byDayResults[] = $dayHits[$offset - 1];
                        }
                    } else {
                        // if it was negative we count from the end of the array
                        // might not exist, fx. -5th tuesday
                        if (isset($dayHits[count($dayHits) + $offset])) {
                            $byDayResults[] = $dayHits[count($dayHits) + $offset];
                        }
                    }
                } else {
                    // There was no counter (first, second, last wednesdays), so we
                    // just need to add the all to the list.
                    $byDayResults = array_merge($byDayResults, $dayHits);
                }
            }
        }

        $byMonthDayResults = [];
        if ($this->byMonthDay) {
            foreach ($this->byMonthDay as $monthDay) {
                // Removing values that are out of range for this month
                if ($monthDay > $startDate->format('t')
                    || $monthDay < 0 - $startDate->format('t')) {
                    continue;
                }
                if ($monthDay > 0) {
                    $byMonthDayResults[] = $monthDay;
                } else {
                    // Negative values
                    $byMonthDayResults[] = (int) $startDate->format('t') + 1 + $monthDay;
                }
            }
        }

        // If there was just byDay or just byMonthDay, they just specify our
        // (almost) final list. If both were provided, then byDay limits the
        // list.
        if ($this->byMonthDay && $this->byDay) {
            $result = array_intersect($byMonthDayResults, $byDayResults);
        } elseif ($this->byMonthDay) {
            $result = $byMonthDayResults;
        } else {
            $result = $byDayResults;
        }
        $result = array_unique($result);
        sort($result, SORT_NUMERIC);

        // The last thing that needs checking is the BYSETPOS. If it's set, it
        // means only certain items in the set survive the filter.
        if (!$this->bySetPos) {
            return $result;
        }

        $filteredResult = [];
        foreach ($this->bySetPos as $setPos) {
            if ($setPos < 0) {
                $setPos = count($result) + ($setPos + 1);
            }
            if (isset($result[$setPos - 1])) {
                $filteredResult[] = $result[$setPos - 1];
            }
        }

        sort($filteredResult, SORT_NUMERIC);

        return $filteredResult;
    }

    /**
     * Returns the value of an RRULE part that only takes a single value.
     *
     * @throws InvalidDataException
     */
    private static function scalarPart(string $key, mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new InvalidDataException($key.' in RRULE must have a single value!');
        }

        return (string) $value;
    }

    /**
     * Returns the values of an RRULE part that takes a list of integers from
     * 1 to $max or -$max to -1.
     *
     * @return int[]
     *
     * @throws InvalidDataException
     */
    private static function integerParts(string $key, mixed $value, int $max): array
    {
        $result = [];
        foreach ((array) $value as $part) {
            if (!is_scalar($part) || !preg_match('/^[+-]?[0-9]+$/D', (string) $part)
                || 0 === (int) $part || abs((int) $part) > $max) {
                throw new InvalidDataException($key.' in RRULE must have value(s) from 1 to '.$max.', or -'.$max.' to -1!');
            }
            $result[] = (int) $part;
        }

        return $result;
    }

    /**
     * Returns the values of an RRULE part that takes a list of integers from
     * $min to $max, without a minus sign.
     *
     * Only digits (and a leading "+") are accepted: is_numeric() would let
     * "2.3", "1e1" or " 3" through, and these are not integers.
     *
     * @return int[]
     *
     * @throws InvalidDataException
     */
    private static function unsignedParts(mixed $value, int $min, int $max, string $message): array
    {
        $result = [];
        foreach ((array) $value as $part) {
            if (!is_scalar($part) || !preg_match('/^\+?[0-9]+$/D', (string) $part)
                || (int) $part < $min || (int) $part > $max) {
                throw new InvalidDataException($message);
            }
            $result[] = (int) $part;
        }

        return $result;
    }

    /**
     * Simple mapping from iCalendar day names to day numbers.
     */
    protected array $dayMap = [
        'SU' => 0,
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
    ];

    protected function getHours(): array
    {
        $recurrenceHours = [];
        foreach ($this->byHour as $byHour) {
            $recurrenceHours[] = (int) $byHour;
        }

        return $recurrenceHours;
    }

    protected function getDays(): array
    {
        $recurrenceDays = [];
        foreach ($this->byDay as $byDay) {
            // The day may be preceded with a positive (+n) or
            // negative (-n) integer. However, this does not make
            // sense in 'weekly' so we ignore it here.
            $recurrenceDays[] = $this->dayMap[substr((string) $byDay, -2)];
        }

        return $recurrenceDays;
    }

    protected function getMonths(): array
    {
        $recurrenceMonths = [];
        foreach ($this->byMonth as $byMonth) {
            $recurrenceMonths[] = $byMonth;
        }

        return $recurrenceMonths;
    }
}
