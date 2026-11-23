<?php

namespace Sabre\VObject;

/**
 * FreeBusyData is a helper class that manages freebusy information.
 *
 * add() only records a period. getData() computes the list in one sorted
 * sweep, in O(n log n) for n periods: inserting each period in the list as
 * it came was O(n) per period, so O(n²) for a free-busy query.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class FreeBusyData
{
    /**
     * A list of free-busy times. add() does not update it: only getData()
     * brings it up to date.
     */
    protected array $data;

    /**
     * All the periods added, as [start, end, type], clamped to the time
     * range, in the order they were added. getData() sweeps them all again
     * after an add().
     *
     * @var list<array{int, int, string}>
     */
    private array $periods = [];

    /**
     * Whether $data holds the periods added so far.
     */
    private bool $computed = true;

    public function __construct(/**
     * Start timestamp.
     */
        protected int $start, /**
     * End timestamp.
     */
        protected int $end)
    {
        $this->data = [];

        $this->data[] = [
            'start' => $this->start,
            'end' => $this->end,
            'type' => 'FREE',
        ];
    }

    /**
     * Adds free or busy time to the data.
     *
     * A period overrides the parts of the periods added before it that it
     * overlaps.
     *
     * @param string $type FREE, BUSY, BUSY-UNAVAILABLE or BUSY-TENTATIVE
     *
     * @throws Recur\MaxInstancesExceededException when more than
     *                                             Settings::$maxFreeBusyPeriods periods fall in the time range
     */
    public function add(int $start, int $end, string $type): void
    {
        if ($start >= $this->end || $end <= $this->start || $start >= $end || $this->start >= $this->end) {
            // This new data is outside our time range, or empty, or inverted,
            // or the time range itself is.
            return;
        }

        if (Settings::$maxFreeBusyPeriods >= 0 && count($this->periods) >= Settings::$maxFreeBusyPeriods) {
            throw new Recur\MaxInstancesExceededException('Free-busy data is only allowed to hold '.Settings::$maxFreeBusyPeriods.' periods');
        }

        $this->periods[] = [
            max($start, $this->start),
            min($end, $this->end),
            $type,
        ];
        $this->computed = false;
    }

    public function getData(): array
    {
        if (!$this->computed) {
            $this->data = $this->sweep();
            $this->computed = true;
        }

        return $this->data;
    }

    /**
     * Cuts the time range at every start and end of a period. Each piece
     * takes the type of the last added period that covers it, or FREE, and
     * adjacent pieces of the same type are merged.
     */
    private function sweep(): array
    {
        $starts = [];
        $ends = [];
        foreach ($this->periods as $index => [$start, $end]) {
            $starts[$start][] = $index;
            $ends[$end] = true;
        }
        $ends[$this->start] = true;
        $ends[$this->end] = true;
        $times = array_keys($starts + $ends);
        sort($times);

        // The periods that started, the last added on top. A period that
        // ended is only taken out when it reaches the top.
        $active = new \SplMaxHeap();
        $data = [];
        $last = count($times) - 1;
        for ($i = 0; $i < $last; ++$i) {
            $time = $times[$i];
            foreach ($starts[$time] ?? [] as $index) {
                $active->insert($index);
            }
            while (!$active->isEmpty() && $this->periods[$active->top()][1] <= $time) {
                $active->extract();
            }
            $type = $active->isEmpty() ? 'FREE' : $this->periods[$active->top()][2];

            if ([] !== $data && $data[count($data) - 1]['type'] === $type) {
                $data[count($data) - 1]['end'] = $times[$i + 1];
            } else {
                $data[] = [
                    'start' => $time,
                    'end' => $times[$i + 1],
                    'type' => $type,
                ];
            }
        }

        return $data;
    }
}
