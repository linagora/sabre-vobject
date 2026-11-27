<?php

namespace Sabre\VObject\Recur;

/**
 * Merges the instances of an RRULE and of RDATE values.
 *
 * Both iterators yield their dates in ascending order. This iterator yields
 * the union of both, in ascending order, and an instant produced by both
 * only once (RFC 5545 section 3.8.5.2).
 *
 * @license http://sabre.io/license/ Modified BSD License
 */
class RecurrenceSetIterator implements \Iterator
{
    /**
     * The current item in the list.
     */
    protected int $counter = 0;

    public function __construct(
        protected RRuleIterator $rruleIterator,
        protected RDateIterator $rdateIterator,
    ) {
    }

    #[\ReturnTypeWillChange]
    public function current(): ?\DateTimeInterface
    {
        $rrule = $this->rruleIterator->valid() ? $this->rruleIterator->current() : null;
        $rdate = $this->rdateIterator->valid() ? $this->rdateIterator->current() : null;

        if (null === $rrule) {
            return $rdate;
        }
        if (null === $rdate) {
            return $rrule;
        }

        return $rdate < $rrule ? $rdate : $rrule;
    }

    #[\ReturnTypeWillChange]
    public function key(): int
    {
        return $this->counter;
    }

    #[\ReturnTypeWillChange]
    public function valid(): bool
    {
        return $this->rruleIterator->valid() || $this->rdateIterator->valid();
    }

    #[\ReturnTypeWillChange]
    public function rewind(): void
    {
        $this->rruleIterator->rewind();
        $this->rdateIterator->rewind();
        $this->counter = 0;
    }

    #[\ReturnTypeWillChange]
    public function next(): void
    {
        $current = $this->current();
        if (null === $current) {
            return;
        }
        $timestamp = $current->getTimestamp();

        // Skip the current instant in both iterators, so that an instant
        // listed by both is only yielded once.
        self::skipUntilAfter($this->rruleIterator, $timestamp);
        self::skipUntilAfter($this->rdateIterator, $timestamp);
        ++$this->counter;
    }

    /**
     * Advances $iterator until its current date is after $timestamp.
     */
    private static function skipUntilAfter(RRuleIterator|RDateIterator $iterator, int $timestamp): void
    {
        while ($iterator->valid() && $iterator->current()->getTimestamp() <= $timestamp) {
            $iterator->next();
        }
    }

    /**
     * Returns true if this recurring event never ends.
     */
    public function isInfinite(): bool
    {
        return $this->rruleIterator->isInfinite();
    }

    /**
     * Goes to the first occurrence at or after the specified date.
     */
    public function fastForward(\DateTimeInterface $dt): void
    {
        $this->rruleIterator->fastForward($dt);
        $this->rdateIterator->fastForward($dt);
    }
}
