<?php

namespace Sabre\VObject\Component;

use Sabre\VObject;
use Sabre\VObject\InvalidDataException;

/**
 * VAlarm component.
 *
 * This component contains some additional functionality specific for VALARMs.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 *
 * @property VObject\Property\ICalendar\DateTime DTSTART
 * @property VObject\Property\ICalendar\DateTime DTEND
 * @property VObject\Property\ICalendar\Duration DURATION
 * @property VObject\Property\ICalendar\Duration|VObject\Property\ICalendar\DateTime TRIGGER
 * @property VObject\Property\IntegerValue REPEAT
 */
class VAlarm extends VObject\Component
{
    /**
     * Returns a DateTime object when this alarm is going to trigger.
     *
     * This ignores repeated alarm, only the first trigger is returned.
     *
     * @throws InvalidDataException
     */
    public function getEffectiveTriggerTime(): \DateTimeImmutable
    {
        $trigger = $this->TRIGGER;
        if ($trigger instanceof VObject\Property\ICalendar\Duration) {
            $triggerDuration = VObject\DateTimeParser::parseDuration($trigger);
            $related = (isset($trigger['RELATED']) && 'END' === strtoupper($trigger['RELATED'])) ? 'END' : 'START';

            /** @var VEvent|VTodo $parentComponent */
            $parentComponent = $this->parent;
            if ('START' === $related) {
                $effectiveTrigger = self::getAnchor($parentComponent, 'DTSTART');
            } else {
                if ('VTODO' === $parentComponent?->name) {
                    $endProp = 'DUE';
                } elseif ('VEVENT' === $parentComponent?->name) {
                    $endProp = 'DTEND';
                } else {
                    throw new InvalidDataException('time-range filters on VALARM components are only supported when they are a child of VTODO or VEVENT');
                }

                if (isset($parentComponent->$endProp)) {
                    $effectiveTrigger = self::getAnchor($parentComponent, $endProp);
                } elseif (isset($parentComponent->DURATION)) {
                    $effectiveTrigger = self::getAnchor($parentComponent, 'DTSTART');
                    $duration = VObject\DateTimeParser::parseDuration($parentComponent->DURATION);
                    $effectiveTrigger = $effectiveTrigger->add($duration);
                } else {
                    $effectiveTrigger = self::getAnchor($parentComponent, 'DTSTART');
                }
            }
            $effectiveTrigger = $effectiveTrigger->add($triggerDuration);
        } elseif ($trigger instanceof VObject\Property\ICalendar\DateTime) {
            $effectiveTrigger = $trigger->getDateTime();
        } else {
            throw new InvalidDataException('The TRIGGER of a VALARM must be a duration or a date-time');
        }

        return $effectiveTrigger;
    }

    /**
     * Returns true or false depending on if the event falls in the specified
     * time-range. This is used for filtering purposes.
     *
     * The rules used to determine if an event falls within the specified
     * time-range is based on the CalDAV specification.
     *
     * @throws InvalidDataException
     */
    public function isInTimeRange(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $effectiveTrigger = $this->getEffectiveTriggerTime();

        if (isset($this->DURATION)) {
            $duration = VObject\DateTimeParser::parseDuration($this->DURATION);
            $repeat = isset($this->REPEAT) ? max(0, (int) (string) $this->REPEAT) : 1;
            $step = $effectiveTrigger->add($duration)->getTimestamp() - $effectiveTrigger->getTimestamp();

            if ($repeat > 0 && $step > 0) {
                // Jump to the first repetition at or after $start instead of
                // walking through all of them: REPEAT may be huge.
                $index = 0;
                if ($start > $effectiveTrigger) {
                    $index = (int) ceil(($start->getTimestamp() - $effectiveTrigger->getTimestamp()) / $step);
                }
                $occurrence = $this->getRepetition($effectiveTrigger, $duration, $index);
                // The estimate is only off when a duration in days spans a
                // change of UTC offset.
                while ($index > 0 && ($previous = $this->getRepetition($effectiveTrigger, $duration, $index - 1)) >= $start) {
                    --$index;
                    $occurrence = $previous;
                }
                while ($occurrence < $start) {
                    ++$index;
                    $occurrence = $this->getRepetition($effectiveTrigger, $duration, $index);
                }

                return $index <= $repeat && $end > $occurrence;
            }
        }

        return $start <= $effectiveTrigger && $end > $effectiveTrigger;
    }

    /**
     * Returns the date and time a relative TRIGGER is related to.
     *
     * @throws InvalidDataException
     */
    private static function getAnchor(?VObject\Component $parent, string $name): \DateTimeImmutable
    {
        $property = $parent?->__get($name);
        if (!$property instanceof VObject\Property\ICalendar\DateTime) {
            throw new InvalidDataException('A VALARM with a TRIGGER relative to '.$name.' requires its parent component to have a '.$name.' date or date-time');
        }

        return $property->getDateTime();
    }

    /**
     * Returns the trigger time of the repetition number $index (0 being the
     * first trigger).
     */
    private function getRepetition(\DateTimeImmutable $trigger, \DateInterval $duration, int $index): \DateTimeImmutable
    {
        $interval = new \DateInterval('PT0S');
        $interval->y = $duration->y * $index;
        $interval->m = $duration->m * $index;
        $interval->d = $duration->d * $index;
        $interval->h = $duration->h * $index;
        $interval->i = $duration->i * $index;
        $interval->s = $duration->s * $index;
        $interval->invert = $duration->invert;

        return $trigger->add($interval);
    }

    /**
     * A simple list of validation rules.
     *
     * This is simply a list of properties, and how many times they either
     * must or must not appear.
     *
     * Possible values per property:
     *   * 0 - Must not appear.
     *   * 1 - Must appear exactly once.
     *   * + - Must appear at least once.
     *   * * - Can appear any number of times.
     *   * ? - May appear, but not more than once.
     */
    public function getValidationRules(): array
    {
        return [
            'ACTION' => 1,
            'TRIGGER' => 1,

            'DURATION' => '?',
            'REPEAT' => '?',

            'ATTACH' => '?',
        ];
    }
}
