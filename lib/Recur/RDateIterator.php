<?php

namespace Sabre\VObject\Recur;

use Iterator;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;

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
class RDateIterator implements \Iterator
{
    /**
     * Creates the Iterator.
     *
     * @param string|array $rrule
     */
    public function __construct($rrule, /**
     * The reference start date/time for the rrule.
     *
     * All calculations are based on this initial date.
     */
        protected \DateTimeInterface $startDate)
    {
        $this->parseRDate($rrule);
        $this->currentDate = $this->dates[0];
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
     * iterator.
     */
    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        return $this->counter < count($this->dates);
    }

    /**
     * Resets the iterator.
     */
    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        $this->counter = 0;
        $this->currentDate = $this->dates[0];
    }

    /**
     * Goes on to the next iteration.
     */
    #[\ReturnTypeWillChange]
    public function next(): void
    {
        ++$this->counter;
        if (!$this->valid()) {
            return;
        }

        $this->currentDate = $this->dates[$this->counter];
    }

    /* End of Iterator implementation }}} */

    /**
     * Returns true if this recurring event never ends.
     */
    public function isInfinite(): bool
    {
        return false;
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
    protected \DateTimeInterface $currentDate;

    /**
     * The current item in the list.
     *
     * You can get this number with the key() method.
     */
    protected int $counter = 0;

    /* }}} */

    /**
     * This method receives a string from an RDATE property, and populates this
     * class with all the values.
     *
     * The start date and the RDATE values are sorted chronologically, and a
     * value equal to an earlier one is only kept once, so that the iterator
     * yields the recurrence set in ascending order.
     *
     * @param string|array $rdate
     *
     * @throws InvalidDataException
     */
    protected function parseRDate($rdate): void
    {
        if (is_string($rdate)) {
            $rdate = explode(',', $rdate);
        }

        $dates = [$this->startDate->getTimestamp() => clone $this->startDate];
        foreach ($rdate as $value) {
            if (!is_string($value)) {
                throw new InvalidDataException('RDATE values must be dates or date-times');
            }
            $date = DateTimeParser::parse($value, $this->startDate->getTimezone());
            if (!$date instanceof \DateTimeInterface) {
                throw new InvalidDataException('RDATE values must be dates or date-times, got: '.$value);
            }
            $dates[$date->getTimestamp()] ??= $date;
        }
        ksort($dates);

        $this->dates = array_values($dates);
    }

    /**
     * The start date and the RDATE dates, in chronological order.
     *
     * @var \DateTimeInterface[]
     */
    protected array $dates = [];
}
