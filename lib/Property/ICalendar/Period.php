<?php

namespace Sabre\VObject\Property\ICalendar;

use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\Xml;

/**
 * Period property.
 *
 * This object represents PERIOD values, as defined here:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.8.2.6
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Period extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     */
    public string $delimiter = ',';

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
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
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'PERIOD';
    }

    /**
     * Sets the json value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     */
    public function setJsonValue(array $value): void
    {
        // Only the date-times lose their jCal separators: a duration has
        // none, and is kept as it is.
        $value = array_map(
            fn ($item) => implode('/', array_map(
                fn ($part) => preg_match('/^[+-]?P/', (string) $part) ? $part : strtr((string) $part, [':' => '', '-' => '']),
                $item
            )),
            $value
        );
        parent::setJsonValue($value);
    }

    public function appendUtc(string $strDate)
    {
        return !str_ends_with($strDate, 'Z') ? '' : 'Z';
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
        $return = [];
        foreach ($this->getParts() as $item) {
            [$start, $end] = explode('/', (string) $item, 2) + [1 => ''];
            if ('' === $end) {
                throw new InvalidDataException('A period must have a start and an end or a duration: '.$item);
            }

            $startDt = DateTimeParser::parseDateTime($start)->format('Y-m-d\\TH:i:s').$this->appendUtc($start);

            // This is a duration value.
            if ('P' === $end[0]) {
                $return[] = [
                    $startDt,
                    $end,
                ];
            } else {
                $endDt = DateTimeParser::parseDateTime($end)->format('Y-m-d\\TH:i:s').$this->appendUtc($end);
                $return[] = [
                    $startDt,
                    $endDt,
                ];
            }
        }

        return $return;
    }

    /**
     * Validates the node for correctness.
     *
     * A PERIOD is date-time "/" (date-time / dur-value) (RFC 5545 section
     * 3.3.9).
     */
    public function validate(int $options = 0): array
    {
        $messages = parent::validate($options);

        foreach ($this->getParts() as $value) {
            $parts = explode('/', (string) $value);
            try {
                if (2 !== count($parts)) {
                    throw new InvalidDataException('A period has a start and an end');
                }
                DateTimeParser::parseDateTime($parts[0]);
                if ('P' === ($parts[1][0] ?? '')) {
                    DateTimeParser::parseDuration($parts[1]);
                } else {
                    DateTimeParser::parseDateTime($parts[1]);
                }
            } catch (InvalidDataException) {
                $messages[] = [
                    'level' => 3,
                    'message' => 'The supplied value ('.$value.') is not a correct PERIOD',
                    'node' => $this,
                ];

                return $messages;
            }
        }

        // getJsonValue() must be able to format any value accepted here.
        try {
            $this->getJsonValue();
        } catch (InvalidDataException) {
            $messages[] = [
                'level' => 3,
                'message' => 'The supplied value ('.$this->getRawMimeDirValue().') is not a correct PERIOD',
                'node' => $this,
            ];
        }

        return $messages;
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     *
     * @throws InvalidDataException
     */
    protected function xmlSerializeValue(Xml\Writer $writer): void
    {
        $writer->startElement(strtolower($this->getValueType()));
        $value = $this->getJsonValue();
        $writer->writeElement('start', $value[0][0]);

        if ('P' === $value[0][1][0]) {
            $writer->writeElement('duration', $value[0][1]);
        } else {
            $writer->writeElement('end', $value[0][1]);
        }

        $writer->endElement();
    }
}
