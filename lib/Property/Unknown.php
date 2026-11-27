<?php

namespace Sabre\VObject\Property;

/**
 * Unknown property.
 *
 * This object represents any properties not recognized by the parser.
 * This type of value has been introduced by the jCal, jCard specs.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Unknown extends Text
{
    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     */
    public function getJsonValue(): array
    {
        return [$this->getRawMimeDirValue()];
    }

    /**
     * Sets the json value, as it would appear in a jCard or jCal object.
     *
     * The value of an unknown type is the unprocessed iCalendar / vCard
     * value text (RFC 7265 section 5, RFC 7095 section 5), as
     * getJsonValue() returns it, so it is parsed as such.
     */
    public function setJsonValue(array $value): void
    {
        if (!self::isScalarList($value)) {
            parent::setJsonValue($value);

            return;
        }

        $this->setRawMimeDirValue(implode(',', array_map(fn ($item) => (string) $item, $value)));
    }

    private static function isScalarList(array $value): bool
    {
        foreach ($value as $item) {
            if (!is_null($item) && !is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'UNKNOWN';
    }
}
