<?php

namespace Sabre\VObject\Property\ICalendar;

use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Node;
use Sabre\VObject\Property;
use Sabre\VObject\Recur\RRuleIterator;
use Sabre\Xml;

/**
 * Recur property.
 *
 * This object represents RECUR properties.
 * These values are just used for RRULE and the now deprecated EXRULE.
 *
 * The RRULE property may look something like this:
 *
 * RRULE:FREQ=MONTHLY;BYDAY=1,2,3;BYHOUR=5.
 *
 * This property exposes this as a key=>value array that is accessible using
 * getParts, and may be set using setParts.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Recur extends Property
{
    /**
     * Reference to the parent object, if this is not the top object.
     */
    public ?Node $parent = null;

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array|object $value
     */
    public function setValue($value): void
    {
        // If we're getting the data from json, we'll be receiving an object
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $newVal = [];
            foreach ($value as $k => $v) {
                if (is_string($v)) {
                    $v = strtoupper($v);

                    // The value had multiple sub-values
                    if (str_contains($v, ',')) {
                        $v = explode(',', $v);
                    }
                    if (0 === strcmp((string) $k, 'until')) {
                        $v = strtr($v, [':' => '', '-' => '']);
                    }
                } elseif (is_array($v)) {
                    $v = array_map(strtoupper(...), $v);
                }

                $newVal[strtoupper((string) $k)] = $v;
            }
            $this->value = $newVal;
        } elseif (is_string($value)) {
            $this->value = self::stringToArray($value);
        } else {
            throw new \InvalidArgumentException('You must either pass a string, or a key=>value array');
        }
    }

    /**
     * Returns the current value.
     *
     * This method will always return a singular value. If this was a
     * multi-value object, some decision will be made first on how to represent
     * it as a string.
     *
     * To get the correct multi-value version, use getParts.
     */
    public function getValue(): string
    {
        $out = [];
        foreach ($this->value as $key => $value) {
            $out[] = $key.'='.(is_array($value) ? implode(',', $value) : $value);
        }

        return strtoupper(implode(';', $out));
    }

    /**
     * Sets a multi-valued property.
     */
    public function setParts(array $parts): void
    {
        $this->setValue($parts);
    }

    /**
     * Returns a multi-valued property.
     *
     * This method always returns an array, if there was only a single value,
     * it will still be wrapped in an array.
     */
    public function getParts(): array
    {
        return $this->value;
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     */
    public function setRawMimeDirValue(string $val): void
    {
        $this->setValue($val);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     */
    public function getRawMimeDirValue(): string
    {
        return $this->getValue();
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'RECUR';
    }

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     *
     * @throws InvalidDataException
     */
    public function getJsonValue(): array
    {
        $values = [];
        foreach ($this->getParts() as $k => $v) {
            if (0 === strcmp((string) $k, 'UNTIL')) {
                // UNTIL has the value type of DTSTART: DATE or DATE-TIME.
                $date = new DateTime($this->root, null, $v, str_contains((string) $v, 'T') ? [] : ['VALUE' => 'DATE']);
                $values[strtolower((string) $k)] = $date->getJsonValue()[0];
            } elseif (0 === strcmp((string) $k, 'COUNT')) {
                $values[strtolower((string) $k)] = intval($v);
            } else {
                $values[strtolower((string) $k)] = $v;
            }
        }

        return [$values];
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     */
    protected function xmlSerializeValue(Xml\Writer $writer): void
    {
        $valueType = strtolower($this->getValueType());

        foreach ($this->getJsonValue() as $value) {
            $writer->writeElement($valueType, $value);
        }
    }

    /**
     * Parses an RRULE value string, and turns it into a struct-ish array.
     */
    public static function stringToArray(string $value): array
    {
        $value = strtoupper($value);
        $newValue = [];
        foreach (explode(';', $value) as $part) {
            // Skipping empty parts.
            if ('' === $part) {
                continue;
            }

            $parts = explode('=', $part);

            if (2 !== count($parts)) {
                throw new InvalidDataException('The supplied iCalendar RRULE part is incorrect: '.$part);
            }

            [$partName, $partValue] = $parts;

            // The value itself had multiple values..
            if (str_contains($partValue, ',')) {
                $partValue = explode(',', $partValue);
            }
            $newValue[$partName] = $partValue;
        }

        return $newValue;
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
        $repair = ($options & self::REPAIR);

        $warnings = parent::validate($options);
        $values = $this->getParts();

        foreach ($values as $key => $value) {
            if ('' === $value) {
                $warnings[] = [
                    'level' => $repair ? 1 : 3,
                    'message' => 'Invalid value for '.$key.' in '.$this->name,
                    'node' => $this,
                ];
                if ($repair) {
                    unset($values[$key]);
                }
            } elseif ('UNTIL' === $key) {
                // getJsonValue() formats UNTIL as a DATE or DATE-TIME.
                try {
                    if (!is_string($value)) {
                        throw new InvalidDataException('UNTIL must have a single value');
                    }
                    DateTimeParser::parse($value);
                } catch (InvalidDataException) {
                    $warnings[] = [
                        'level' => 3,
                        'message' => 'UNTIL in '.$this->name.' must be a DATE or DATE-TIME value',
                        'node' => $this,
                    ];
                }
            } elseif ('BYMONTH' === $key) {
                $byMonth = (array) $value;
                foreach ($byMonth as $i => $v) {
                    if (!is_numeric($v) || (int) $v < 1 || (int) $v > 12) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYMONTH in RRULE must have value(s) between 1 and 12!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && ([] === $values[$key])) {
                    unset($values[$key]);
                }
            } elseif ('BYWEEKNO' === $key) {
                $byWeekNo = (array) $value;
                foreach ($byWeekNo as $i => $v) {
                    if (!is_numeric($v) || (int) $v < -53 || 0 === (int) $v || (int) $v > 53) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYWEEKNO in RRULE must have value(s) from -53 to -1, or 1 to 53!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && ([] === $values[$key])) {
                    unset($values[$key]);
                }
            } elseif ('BYYEARDAY' === $key) {
                $byYearDay = (array) $value;
                foreach ($byYearDay as $i => $v) {
                    if (!is_numeric($v) || (int) $v < -366 || 0 === (int) $v || (int) $v > 366) {
                        $warnings[] = [
                            'level' => $repair ? 1 : 3,
                            'message' => 'BYYEARDAY in RRULE must have value(s) from -366 to -1, or 1 to 366!',
                            'node' => $this,
                        ];
                        if ($repair) {
                            if (is_array($value)) {
                                unset($values[$key][$i]);
                            } else {
                                unset($values[$key]);
                            }
                        }
                    }
                }
                // if there is no valid entry left, remove the whole value
                if (is_array($value) && ([] === $values[$key])) {
                    unset($values[$key]);
                }
            }
        }
        if (!isset($values['FREQ'])) {
            $warnings[] = [
                'level' => $repair ? 1 : 3,
                'message' => 'FREQ is required in '.$this->name,
                'node' => $this,
            ];
            if ($repair) {
                $this->parent->remove($this);
            }
        }
        if ($repair) {
            $this->setValue($values);
        }

        // A problem left unrepaired (level 3) is already reported once.
        if (isset($values['FREQ']) && !in_array(3, array_column($warnings, 'level'), true)) {
            $warnings = array_merge($warnings, $this->validateAsIterated($values));
        }

        return $warnings;
    }

    /**
     * Reports what the recurrence iterator would refuse: the read paths
     * (expand, time-range queries, free-busy) must accept what validate()
     * accepts. These problems are not repaired, as dropping a part would
     * change the meaning of the rule.
     */
    private function validateAsIterated(array $values): array
    {
        $message = null;
        foreach (['COUNT', 'INTERVAL'] as $key) {
            // RFC 5545 3.3.10: 1*DIGIT; the iterator casts the value.
            if (isset($values[$key]) && is_string($values[$key]) && !preg_match('/^[0-9]+$/', $values[$key])) {
                $message = $key.' in '.$this->name.' must be a positive integer!';
            }
        }

        if (null === $message) {
            try {
                new class($values) extends RRuleIterator {
                    // Only parse the rule: iterating is not needed to
                    // validate it, and the parent constructor iterates.
                    // @phpstan-ignore constructor.missingParentCall
                    public function __construct(array $values)
                    {
                        $this->startDate = new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC'));
                        $this->parseRRule($values);
                    }
                };
            } catch (\Exception $e) {
                $message = 'Invalid '.$this->name.': '.$e->getMessage();
            }
        }

        if (null === $message) {
            return [];
        }

        return [[
            'level' => 3,
            'message' => $message,
            'node' => $this,
        ]];
    }
}
