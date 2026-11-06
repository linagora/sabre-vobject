<?php

namespace Sabre\VObject\Property\ICalendar;

use DateTimeZone;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\VObject\TimeZoneUtil;

/**
 * DateTime property.
 *
 * This object represents DATE-TIME values, as defined here:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.3.4
 *
 * This particular object has a bit of hackish magic that it may also in some
 * cases represent a DATE value. This is because it's a common usecase to be
 * able to change a DATE-TIME into a DATE.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class DateTime extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     *
     * @var string|null
     */
    public string $delimiter = ',';

    /**
     * Sets a multi-valued property.
     *
     * You may also specify DateTime objects here.
     *
     * @throws InvalidDataException
     */
    public function setParts(array $parts): void
    {
        if (isset($parts[0]) && $parts[0] instanceof \DateTimeInterface) {
            $this->setDateTimes($parts);
        } else {
            parent::setParts($parts);
        }
    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * Instead of strings, you may also use DateTime here.
     *
     * @param string|array|\DateTimeInterface $value
     *
     * @throws InvalidDataException
     */
    public function setValue($value): void
    {
        if (is_array($value) && isset($value[0]) && $value[0] instanceof \DateTimeInterface) {
            $this->setDateTimes($value);
        } elseif ($value instanceof \DateTimeInterface) {
            $this->setDateTimes([$value]);
        } else {
            parent::setValue($value);
        }
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     *
     * @throws InvalidDataException
     */
    public function setRawMimeDirValue(string $val): void
    {
        $this->setValue(explode($this->delimiter, $val));
    }

    /**
     * Returns a raw mime-dir representation of the value.
     */
    public function getRawMimeDirValue(): string
    {
        return implode($this->delimiter, $this->getParts());
    }

    /**
     * Returns true if this is a DATE-TIME value, false if it's a DATE.
     */
    public function hasTime(): bool
    {
        return 'DATE' !== strtoupper((string) $this['VALUE']);
    }

    /**
     * Returns true if this is a floating DATE or DATE-TIME.
     *
     * Note that DATE is always floating.
     */
    public function isFloating(): bool
    {
        return
            !$this->hasTime()
            || (
                !isset($this['TZID'])
                && !str_contains((string) $this->getValue(), 'Z')
            );
    }

    /**
     * Returns a date-time value.
     *
     * Note that if this property contained more than 1 date-time, only the
     * first will be returned. To get an array with multiple values, call
     * getDateTimes.
     *
     * If no timezone information is known, because it's either an all-day
     * property or floating time, we will use the DateTimeZone argument to
     * figure out the exact date.
     *
     * @throws InvalidDataException
     */
    public function getDateTime(?\DateTimeZone $timeZone = null): ?\DateTimeImmutable
    {
        $dt = $this->getDateTimes($timeZone);
        if (!$dt) {
            return null;
        }

        return $dt[0];
    }

    /**
     * Returns multiple date-time values.
     *
     * If no timezone information is known, because it's either an all-day
     * property or floating time, we will use the DateTimeZone argument to
     * figure out the exact date.
     *
     * @return \DateTimeImmutable[]
     *
     * @throws InvalidDataException
     */
    public function getDateTimes(?\DateTimeZone $timeZone = null): array
    {
        // Does the property have a TZID?
        /** @var Property\FlatText $tzid */
        $tzid = $this['TZID'];

        // TZID only applies to DATE-TIME values (RFC 5545 section 3.2.19): a
        // DATE is floating and uses the reference time zone.
        if ($tzid && $this->hasTime()) {
            $timeZone = TimeZoneUtil::getTimeZone((string) $tzid, $this->root);
        }

        $dts = [];
        foreach ($this->getParts() as $part) {
            $dt = DateTimeParser::parse((string) $part, $timeZone);
            // parse() also accepts durations, which are not valid here.
            if (!$dt instanceof \DateTimeImmutable) {
                throw new InvalidDataException('The supplied value ('.$part.') is not a correct '.$this->getValueType());
            }
            $dts[] = $dt;
        }

        return $dts;
    }

    /**
     * Sets the property as a DateTime object.
     *
     * @param bool isFloating If set to true, timezones will be ignored
     *
     * @throws InvalidDataException
     */
    public function setDateTime(\DateTimeInterface $dt, $isFloating = false): void
    {
        $this->setDateTimes([$dt], $isFloating);
    }

    /**
     * Sets the property as multiple date-time objects.
     *
     * The first value will be used as a reference for the timezones, and all
     * the other values will be adjusted for that timezone
     *
     * @param \DateTimeInterface[] $dt
     * @param bool isFloating If set to true, timezones will be ignored
     *
     * @throws InvalidDataException
     */
    public function setDateTimes(array $dt, $isFloating = false): void
    {
        $values = [];

        if ($this->hasTime()) {
            $tz = null;
            $isUtc = false;
            $keepsInstants = true;

            foreach ($dt as $d) {
                if ($isFloating) {
                    $values[] = $d->format('Ymd\\THis');
                    $keepsInstants = $keepsInstants && self::localTimeKeepsInstant($d);
                    continue;
                }
                if (is_null($tz)) {
                    $tz = $d->getTimeZone();
                    $isUtc = in_array($tz->getName(), ['UTC', 'GMT', 'Z', '+00:00'], true);
                    if (!$isUtc) {
                        $this->offsetSet('TZID', $tz->getName());
                    }
                } else {
                    $d = $d->setTimeZone($tz);
                }

                if ($isUtc) {
                    $values[] = $d->format('Ymd\\THis\\Z');
                } else {
                    $values[] = $d->format('Ymd\\THis');
                    $keepsInstants = $keepsInstants && self::localTimeKeepsInstant($d);
                }
            }
            // The second occurrence of a local time that occurs twice cannot
            // be written as a local time, which refers to the first (RFC 5545
            // section 3.3.5): such a property is written in UTC.
            if (!$keepsInstants) {
                $values = [];
                foreach ($dt as $d) {
                    $values[] = $d->setTimeZone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
                }
                $isUtc = true;
            }
            if ($isUtc || $isFloating) {
                $this->offsetUnset('TZID');
            }
        } else {
            foreach ($dt as $d) {
                $values[] = $d->format('Ymd');
            }
            $this->offsetUnset('TZID');
        }

        $this->value = $values;
    }

    /**
     * Returns true if the local time of a date, read back in its time zone,
     * is the same instant.
     */
    private static function localTimeKeepsInstant(\DateTimeInterface $date): bool
    {
        try {
            return DateTimeParser::parseDateTime($date->format('Ymd\\THis'), $date->getTimezone())->getTimestamp() === $date->getTimestamp();
        } catch (InvalidDataException) {
            // A year that the text form cannot hold: nothing to compare with.
            return true;
        }
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return $this->hasTime() ? 'DATE-TIME' : 'DATE';
    }

    /**
     * Returns the value, in the format it should be encoded for JSON.
     *
     * This method must always return an array.
     *
     * @throws InvalidDataException
     */
    public function getJsonValue(): array
    {
        $dts = $this->getDateTimes();
        $hasTime = $this->hasTime();
        // Only a value written in UTC gets a Z: a value with a TZID is a
        // local time, even when the TZID could not be resolved (and was
        // replaced by the default time zone). This is decided for each
        // value, as a multi-valued property may mix both forms.
        $parts = array_values($this->getParts());

        return array_map(
            function (\DateTimeInterface $dt, int $i) use ($hasTime, $parts) {
                if ($hasTime) {
                    $isUtc = str_ends_with(strtoupper((string) ($parts[$i] ?? '')), 'Z');

                    return $dt->format('Y-m-d\\TH:i:s').($isUtc ? 'Z' : '');
                }

                return $dt->format('Y-m-d');
            },
            $dts,
            array_keys($dts)
        );
    }

    /**
     * Sets the json value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     *
     * @throws InvalidDataException
     */
    public function setJsonValue(array $value): void
    {
        // dates and times in jCal have one difference to dates and times in
        // iCalendar. In jCal date-parts are separated by dashes, and
        // time-parts are separated by colons. It makes sense to just remove
        // those.
        $this->setValue(
            array_map(
                fn ($item) => strtr($item, [':' => '', '-' => '']),
                $value
            )
        );
    }

    /**
     * Returns the first value that is not a correct $valueType (DATE or
     * DATE-TIME), or null if they all are.
     */
    private static function firstInvalidValue(array $values, string $valueType): ?string
    {
        foreach ($values as $value) {
            try {
                if ('DATE' === $valueType) {
                    DateTimeParser::parseDate((string) $value);
                } else {
                    DateTimeParser::parseDateTime((string) $value);
                }
            } catch (InvalidDataException) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * We need to intercept offsetSet, because it may be used to alter the
     * VALUE from DATE-TIME to DATE or vice-versa.
     *
     * @param string|int $offset
     *
     * @throws InvalidDataException
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        parent::offsetSet($offset, $value);
        if ('VALUE' !== strtoupper((string) $offset)) {
            return;
        }

        // This will ensure that dates are correctly encoded.
        $this->setDateTimes($this->getDateTimes());
    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   Node::REPAIR - May attempt to automatically repair the problem.
     *
     * This method returns an array with detected problems.
     * Every element has the following properties:
     *
     *  * level - problem level.
     *  * message - A human-readable string describing the issue.
     *  * node - A reference to the problematic node.
     *
     * The level means:
     *   1 - The issue was repaired (only happens if REPAIR was turned on)
     *   2 - An inconsequential issue
     *   3 - A severe issue.
     */
    public function validate(int $options = 0): array
    {
        $messages = parent::validate($options);
        $valueType = $this->getValueType();
        $values = $this->getParts();
        $invalidValue = self::firstInvalidValue($values, $valueType);
        if (null === $invalidValue) {
            return $messages;
        }

        // Every value has the form of the other type (DTSTART:20260320, or
        // EXDATE;VALUE=DATE:20260101T090000): the VALUE parameter is the
        // part that is wrong, for the properties that take both types.
        $otherType = 'DATE' === $valueType ? 'DATE-TIME' : 'DATE';
        if (
            ($options & self::REPAIR)
            && in_array(strtoupper($this->name), ['DTSTART', 'DTEND', 'DUE', 'RECURRENCE-ID', 'EXDATE', 'RDATE'], true)
            && null === self::firstInvalidValue($values, $otherType)
        ) {
            // The parent methods leave the values as they are; offsetSet()
            // of this class would re-encode them.
            if ('DATE' === $otherType) {
                parent::offsetSet('VALUE', 'DATE');
                // TZID only applies to DATE-TIME values (RFC 5545 3.2.19).
                parent::offsetUnset('TZID');
            } else {
                parent::offsetUnset('VALUE');
            }
            $messages[] = [
                'level' => 1,
                'message' => 'The supplied value ('.$invalidValue.') is not a correct '.$valueType.'. VALUE was set to '.$otherType,
                'node' => $this,
            ];

            return $messages;
        }

        $messages[] = [
            'level' => 3,
            'message' => 'The supplied value ('.$invalidValue.') is not a correct '.$valueType,
            'node' => $this,
        ];

        return $messages;
    }
}
