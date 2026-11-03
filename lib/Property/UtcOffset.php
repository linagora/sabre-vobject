<?php

namespace Sabre\VObject\Property;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\InvalidDataException;

/**
 * UtcOffset property.
 *
 * This object encodes UTC-OFFSET values.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class UtcOffset extends Text
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     */
    public string $delimiter = '';

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'UTC-OFFSET';
    }

    /**
     * Sets the JSON value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     *
     * @throws InvalidDataException
     */
    public function setJsonValue(array $value): void
    {
        // jCal and jCard write "+hh:mm[:ss]": only these separators are
        // removed, any other value is kept as it is.
        $value = array_map(
            fn ($value) => is_string($value) ? preg_replace('/^([+-]\d\d):(\d\d)(?::(\d\d))?$/', '$1$2$3', $value) : $value,
            $value
        );
        parent::setJsonValue($value);
    }

    /**
     * Returns the value, in the format it should be encoded for JSON.
     *
     * This method must always return an array.
     */
    public function getJsonValue(): array
    {
        return array_map(
            function ($value) {
                $value = (string) $value;
                if (!preg_match('/^([+-]\d\d)(\d\d)(\d\d)?$/', $value, $matches)) {
                    // Not a MIME-dir offset (an invalid value, or a vCard
                    // 3.0 "-05:00"): kept as it is.
                    return $value;
                }

                return $matches[1].':'.$matches[2].(isset($matches[3]) ? ':'.$matches[3] : '');
            },
            parent::getJsonValue()
        );
    }

    /**
     * Validates the node for correctness.
     *
     * An iCalendar UTC-OFFSET is ("+" / "-") hhmm[ss] (RFC 5545 section
     * 3.3.14); a vCard one is ("+" / "-") hh[mm] (RFC 6350 section 4.7),
     * or hh:mm in vCard 3.0 (RFC 2426 section 4).
     */
    public function validate(int $options = 0): array
    {
        $messages = parent::validate($options);

        $regex = $this->root instanceof VCard
            ? '/^[+-](?:[01]\d|2[0-3])(?::?[0-5]\d)?$/'
            : '/^[+-](?:[01]\d|2[0-3])[0-5]\d(?:[0-5]\d)?$/';
        foreach ($this->getParts() as $value) {
            if (!preg_match($regex, (string) $value)) {
                $messages[] = [
                    'level' => 3,
                    'message' => 'The supplied value ('.$value.') is not a correct UTC-OFFSET',
                    'node' => $this,
                ];
                break;
            }
        }

        return $messages;
    }
}
