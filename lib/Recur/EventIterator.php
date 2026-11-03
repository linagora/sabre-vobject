<?php

namespace Sabre\VObject\Recur;

use Sabre\VObject\Component;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\VObject\Settings;
use Sabre\VObject\TimeZoneUtil;

/**
 * This class is used to determine new for a recurring event, when the next
 * events occur.
 *
 * This iterator may loop infinitely in the future, therefore it is important
 * that if you use this class, you set hard limits for the amount of iterations
 * you want to handle.
 *
 * Note that currently there is not full support for the entire iCalendar
 * specification, as it's very complex and contains a lot of permutations
 * that's not yet used very often in software.
 *
 * For the focus has been on features as they actually appear in Calendaring
 * software, but this may well get expanded as needed / on demand
 *
 * The following RRULE properties are supported
 *   * UNTIL
 *   * INTERVAL
 *   * COUNT
 *   * FREQ=DAILY
 *     * BYDAY
 *     * BYHOUR
 *     * BYMONTH
 *   * FREQ=WEEKLY
 *     * BYDAY
 *     * BYHOUR
 *     * WKST
 *   * FREQ=MONTHLY
 *     * BYMONTHDAY
 *     * BYDAY
 *     * BYSETPOS
 *   * FREQ=YEARLY
 *     * BYMONTH
 *     * BYYEARDAY
 *     * BYWEEKNO
 *     * BYMONTHDAY (only if BYMONTH is also set)
 *     * BYDAY (only if BYMONTH is also set)
 *
 * Anything beyond this is 'undefined', which means that it may get ignored, or
 * you may get unexpected results. The effect is that in some applications the
 * specified recurrence may look incorrect, or is missing.
 *
 * The recurrence iterator also does not yet support THISANDFUTURE.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class EventIterator implements \Iterator
{
    /**
     * Reference timeZone for floating dates and times.
     */
    protected \DateTimeZone $timeZone;

    /**
     * True if we're iterating an all-day event.
     */
    protected bool $allDay = false;

    /**
     * Creates the iterator.
     *
     * There's three ways to set up the iterator.
     *
     * 1. You can pass a VCALENDAR component and a UID.
     * 2. You can pass an array of VEVENTs (all UIDS should match).
     * 3. You can pass a single VEVENT component.
     *
     * Only the second method is recommended. The other 1 and 3 will be removed
     * at some point in the future.
     *
     * The $uid parameter is only required for the first method.
     *
     * @param Component|Component\VCalendar|array $input
     * @param \DateTimeZone|null                  $timeZone reference timezone for floating dates and
     *                                                      times
     *
     * @throws MaxInstancesExceededException
     * @throws NoInstancesException
     * @throws InvalidDataException
     */
    public function __construct($input, ?string $uid = null, ?\DateTimeZone $timeZone = null)
    {
        $timeZone ??= new \DateTimeZone('UTC');
        $this->timeZone = $timeZone;

        if (is_array($input)) {
            $events = $input;
        } elseif ($input instanceof VEvent) {
            // Single instance mode.
            $events = [$input];
        } else {
            // Calendar + UID mode.
            if (!$uid) {
                throw new \InvalidArgumentException('The UID argument is required when a VCALENDAR is passed to this constructor');
            }
            if (!isset($input->VEVENT)) {
                throw new \InvalidArgumentException('No events found in this calendar');
            }
            $events = $input->getByUID($uid);
        }

        /** @var VEvent[] $events */
        foreach ($events as $vevent) {
            self::assertDateTimeProperties($vevent);
            if (!isset($vevent->{'RECURRENCE-ID'})) {
                $this->masterEvent = $vevent;
            } else {
                $this->exceptions[
                    $vevent->{'RECURRENCE-ID'}->getDateTime($this->timeZone)->getTimeStamp()
                ] = true;
                $this->overriddenEvents[] = $vevent;
            }
        }

        $masterless = false;
        if (!$this->masterEvent) {
            // No base event was found. CalDAV does allow cases where only
            // overridden instances are stored.
            //
            // In this particular case, the overridden instances are the only
            // instances. The first one is used as the master for the values
            // that apply to the whole event, but it generates no instance of
            // its own: it is yielded as an overridden instance, like the
            // others.
            if (!count($this->overriddenEvents)) {
                throw new \InvalidArgumentException('This VCALENDAR did not have an event with UID: '.$uid);
            }
            $this->masterEvent = $this->overriddenEvents[0];
            $masterless = true;
        }

        if (isset($this->masterEvent->DURATION) && !$this->masterEvent->DURATION instanceof Property\ICalendar\Duration) {
            throw new InvalidDataException('DURATION must be a duration value');
        }

        $this->startDate = $this->masterEvent->DTSTART->getDateTime($this->timeZone);
        $this->allDay = !$this->masterEvent->DTSTART->hasTime();

        if (isset($this->masterEvent->EXDATE)) {
            foreach ($this->masterEvent->EXDATE as $exDate) {
                foreach ($exDate->getDateTimes($this->timeZone) as $dt) {
                    $this->exceptions[$dt->getTimeStamp()] = true;
                }
            }
        }

        // The duration of an all-day event is a number of whole days. It is
        // measured in UTC, so that a change of UTC offset in the reference
        // timezone does not shorten or lengthen it.
        $durationTimeZone = $this->allDay ? new \DateTimeZone('UTC') : $this->timeZone;
        $durationStart = $this->masterEvent->DTSTART->getDateTime($durationTimeZone);
        if (isset($this->masterEvent->DTEND)) {
            $this->eventDuration =
                $this->masterEvent->DTEND->getDateTime($durationTimeZone)->getTimeStamp() -
                $durationStart->getTimeStamp();
        } elseif (isset($this->masterEvent->DURATION)) {
            $duration = $this->masterEvent->DURATION->getDateInterval();
            $end = $durationStart->add($duration);
            $this->eventDuration = $end->getTimeStamp() - $durationStart->getTimeStamp();
        } elseif ($this->allDay) {
            $this->eventDuration = 3600 * 24;
        } else {
            $this->eventDuration = 0;
        }

        if ($masterless) {
            // The single instance of the default rule below is the start of
            // the first overridden instance, which is yielded as such.
            $this->exceptions[$this->startDate->getTimestamp()] = true;
        }

        if (!$masterless && isset($this->masterEvent->RDATE)) {
            $rdateValues = [];
            foreach ($this->masterEvent->RDATE as $rdate) {
                $rdateValues = array_merge($rdateValues, $this->rdateStarts($rdate));
            }
            $this->recurIterator = new RDateIterator(
                $rdateValues,
                $this->startDate
            );
            if (isset($this->masterEvent->RRULE)) {
                // The recurrence set is the union of the RRULE and RDATE
                // instances.
                $this->recurIterator = new RecurrenceSetIterator(
                    new RRuleIterator($this->masterEvent->RRULE->getParts(), $this->startDate),
                    $this->recurIterator
                );
            }
        } elseif (!$masterless && isset($this->masterEvent->RRULE)) {
            $this->recurIterator = new RRuleIterator(
                $this->masterEvent->RRULE->getParts(),
                $this->startDate
            );
        } else {
            $this->recurIterator = new RRuleIterator(
                [
                    'FREQ' => 'DAILY',
                    'COUNT' => 1,
                ],
                $this->startDate
            );
        }

        $this->rewind();
        if (!$this->valid()) {
            throw new NoInstancesException('This recurrence rule does not generate any valid instances');
        }
    }

    /**
     * Ensures the date properties the iterator reads are date or date-time
     * values, so that malformed events (e.g. RECURRENCE-ID;VALUE=PERIOD)
     * are rejected with an InvalidDataException.
     *
     * @throws InvalidDataException
     */
    private static function assertDateTimeProperties(VEvent $vevent): void
    {
        if (!isset($vevent->DTSTART)) {
            throw new InvalidDataException('A VEVENT must have a DTSTART to be expanded');
        }
        foreach (['DTSTART', 'DTEND', 'RECURRENCE-ID', 'EXDATE'] as $name) {
            foreach ($vevent->select($name) as $property) {
                if (!$property instanceof Property\ICalendar\DateTime) {
                    throw new InvalidDataException($name.' must be a date or date-time value');
                }
            }
        }
    }

    /**
     * Returns the instance starts listed by an RDATE property.
     *
     * Each RDATE property is resolved in its own time zone: its TZID, a UTC
     * value, or the time zone of DTSTART for a floating or DATE value. For a
     * PERIOD value, the instance starts at the start of the period.
     *
     * @return \DateTimeInterface[]
     *
     * @throws InvalidDataException
     */
    private function rdateStarts(Property $rdate): array
    {
        $timeZone = $this->startDate->getTimezone();

        if ($rdate instanceof Property\ICalendar\DateTime) {
            return $rdate->getDateTimes($timeZone);
        }

        if ($rdate instanceof Property\ICalendar\Period) {
            if (isset($rdate['TZID'])) {
                $timeZone = TimeZoneUtil::getTimeZone((string) $rdate['TZID'], $rdate->root);
            }
            $starts = [];
            foreach ($rdate->getParts() as $period) {
                $start = DateTimeParser::parse(explode('/', (string) $period)[0], $timeZone);
                if (!$start instanceof \DateTimeInterface) {
                    throw new InvalidDataException('RDATE periods must start with a date-time, got: '.$period);
                }
                $starts[] = $start;
            }

            return $starts;
        }

        throw new InvalidDataException('RDATE values must be dates, date-times or periods');
    }

    /**
     * Returns the date for the current position of the iterator.
     */
    #[\ReturnTypeWillChange]
    public function current(): ?\DateTimeImmutable
    {
        if ($this->currentDate) {
            return clone $this->currentDate;
        }

        return null;
    }

    /**
     * This method returns the start date for the current iteration of the
     * event.
     */
    public function getDtStart(): ?\DateTimeImmutable
    {
        if ($this->currentDate) {
            return clone $this->currentDate;
        }

        return null;
    }

    /**
     * This method returns the end date for the current iteration of the
     * event.
     *
     * @throws MaxInstancesExceededException|InvalidDataException
     */
    public function getDtEnd(): ?\DateTimeImmutable
    {
        if (!$this->valid()) {
            return null;
        }
        if ($this->currentOverriddenEvent && $this->currentOverriddenEvent->DTEND) {
            return $this->currentOverriddenEvent->DTEND->getDateTime($this->timeZone);
        }
        if ($this->allDay) {
            // All-day events last a number of whole days: add them to the
            // wall-clock date, whatever the UTC offset changes in between.
            return $this->currentDate->modify('+'.$this->eventDuration.' seconds');
        }

        // The duration is elapsed time: modify('+N seconds') would add it to
        // the wall-clock time, which is off by the offset change when the
        // instance spans a DST transition.
        return $this->currentDate->setTimestamp($this->currentDate->getTimestamp() + $this->eventDuration);
    }

    /**
     * Returns a VEVENT for the current iterations of the event.
     *
     * This VEVENT will have a recurrence id, and its DTSTART and DTEND
     * altered.
     *
     * @throws MaxInstancesExceededException
     * @throws InvalidDataException
     */
    public function getEventObject(): VEvent
    {
        if ($this->currentOverriddenEvent) {
            return $this->currentOverriddenEvent;
        }

        /** @var VEvent $event */
        $event = clone $this->masterEvent;

        // Ignoring the following block, because PHPUnit's code coverage
        // ignores most of these lines, and this messes with our stats.
        //
        // @codeCoverageIgnoreStart
        unset(
            $event->RRULE,
            $event->EXDATE,
            $event->RDATE,
            $event->EXRULE,
            $event->{'RECURRENCE-ID'}
        );
        // @codeCoverageIgnoreEnd

        $event->DTSTART->setDateTime($this->getDtStart(), $event->DTSTART->isFloating());
        if (isset($event->DTEND)) {
            $event->DTEND->setDateTime($this->getDtEnd(), $event->DTEND->isFloating());
        }
        $recurid = clone $event->DTSTART;
        $recurid->name = 'RECURRENCE-ID';
        $event->add($recurid);

        return $event;
    }

    /**
     * Returns the current position of the iterator.
     *
     * This is for us simply a 0-based index.
     */
    #[\ReturnTypeWillChange]
    public function key(): int
    {
        // The counter is always 1 ahead.
        return $this->counter - 1;
    }

    /**
     * This is called after next, to see if the iterator is still at a valid
     * position, or if it's at the end.
     *
     * @throws MaxInstancesExceededException
     */
    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        if ($this->counter > Settings::$maxRecurrences && -1 !== Settings::$maxRecurrences) {
            throw new MaxInstancesExceededException('Recurring events are only allowed to generate '.Settings::$maxRecurrences);
        }

        return (bool) $this->currentDate;
    }

    /**
     * Sets the iterator back to the starting point.
     *
     * @throws InvalidDataException
     */
    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        $this->recurIterator->rewind();
        // re-creating overridden event index.
        $index = [];
        foreach ($this->overriddenEvents as $key => $event) {
            /** @var VEvent $event */
            $stamp = $event->DTSTART->getDateTime($this->timeZone)->getTimeStamp();
            $index[$stamp][] = $key;
        }
        krsort($index);
        $this->counter = 0;
        $this->overriddenEventsIndex = $index;
        $this->currentOverriddenEvent = null;

        $this->nextDate = null;
        $this->currentDate = clone $this->startDate;

        $this->next();
    }

    /**
     * Advances the iterator with one step.
     *
     * @throws InvalidDataException
     */
    #[\ReturnTypeWillChange]
    public function next(): void
    {
        $this->currentOverriddenEvent = null;
        ++$this->counter;
        if ($this->nextDate) {
            // We had a stored value.
            $nextDate = $this->nextDate;
            $this->nextDate = null;
        } else {
            // We need to ask rruleparser for the next date.
            // We need to do this until we find a date that's not in the
            // exception list.
            do {
                if (!$this->recurIterator->valid()) {
                    $nextDate = null;
                    break;
                }
                $nextDate = $this->recurIterator->current();
                $this->recurIterator->next();
            } while (isset($this->exceptions[$nextDate->getTimeStamp()]));
        }

        // $nextDate now contains what rrule thinks is the next one, but an
        // overridden event may cut ahead.
        if ($this->overriddenEventsIndex) {
            $offsets = end($this->overriddenEventsIndex);
            $timestamp = key($this->overriddenEventsIndex);
            $offset = end($offsets);
            if (!$nextDate || $timestamp < $nextDate->getTimeStamp()) {
                // Overridden event comes first.
                $this->currentOverriddenEvent = $this->overriddenEvents[$offset];

                // Putting the rrule next date aside.
                $this->nextDate = $nextDate;
                $this->currentDate = $this->currentOverriddenEvent->DTSTART->getDateTime($this->timeZone);

                // Ensuring that this item will only be used once.
                array_pop($this->overriddenEventsIndex[$timestamp]);
                if (!$this->overriddenEventsIndex[$timestamp]) {
                    array_pop($this->overriddenEventsIndex);
                }

                // Exit point!
                return;
            }
        }

        $this->currentDate = $nextDate;
    }

    /**
     * Quickly jump to a date in the future.
     *
     * @throws MaxInstancesExceededException|InvalidDataException
     */
    public function fastForward(\DateTimeInterface $dateTime): void
    {
        while ($this->valid() && $this->getDtEnd() <= $dateTime) {
            $this->next();
        }
    }

    /**
     * Returns true if this recurring event never ends.
     */
    public function isInfinite(): bool
    {
        return $this->recurIterator->isInfinite();
    }

    /**
     * RRULE parser.
     *
     * @var RRuleIterator|RDateIterator|RecurrenceSetIterator
     */
    protected \Iterator $recurIterator;

    /**
     * The duration, in seconds, of the master event.
     *
     * We use this to calculate the DTEND for subsequent events.
     */
    protected $eventDuration;

    /**
     * A reference to the main (master) event.
     */
    protected ?VEvent $masterEvent = null;

    /**
     * List of overridden events.
     */
    protected array $overriddenEvents = [];

    /**
     * Overridden event index.
     *
     * Key is timestamp, value is the list of indexes of the item in the $overriddenEvent
     * property.
     */
    protected array $overriddenEventsIndex;

    /**
     * A list of recurrence-id's that are either part of EXDATE, or are
     * overridden.
     */
    protected array $exceptions = [];

    /**
     * Internal event counter.
     */
    protected int $counter = 0;

    /**
     * The very start of the iteration process.
     */
    protected ?\DateTimeImmutable $startDate;

    /**
     * Where we are currently in the iteration process.
     */
    protected ?\DateTimeImmutable $currentDate = null;

    /**
     * The next date from the rrule parser.
     *
     * Sometimes we need to temporary store the next date, because an
     * overridden event came before.
     */
    protected ?\DateTimeImmutable $nextDate = null;

    /**
     * The event that overwrites the current iteration.
     */
    protected ?VEvent $currentOverriddenEvent = null;
}
