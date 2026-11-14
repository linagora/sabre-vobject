<?php

namespace Sabre\VObject\Property;

use Sabre\VObject\Property;

/**
 * Boolean property.
 *
 * This object represents BOOLEAN values. These are always the case-insensitive
 * string TRUE or FALSE.
 *
 * Automatic conversion to PHP's true and false are done.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Boolean extends Property
{
    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     */
    public function setRawMimeDirValue(string $val): void
    {
        $val = 'TRUE' === strtoupper($val);
        $this->setValue($val);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     */
    public function getRawMimeDirValue(): string
    {
        return $this->value ? 'TRUE' : 'FALSE';
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'BOOLEAN';
    }

    /**
     * Sets the JSON value, as it would appear in a jCard or jCal object.
     *
     * jCal and jCard write a BOOLEAN as a JSON true or false (RFC 7265
     * section 3.6.4). Any other value is read as the text form is: TRUE,
     * in any case, is true, anything else false.
     */
    public function setJsonValue(array $value): void
    {
        parent::setJsonValue(array_map(
            static fn ($value): bool => is_bool($value) ? $value : (is_string($value) && 'TRUE' === strtoupper($value)),
            $value
        ));
    }

    /**
     * Hydrate data from an XML subtree, as it would appear in a xCard or xCal
     * object.
     */
    public function setXmlValue(array $value): void
    {
        $value = array_map(
            fn ($value) => 'true' === $value,
            $value
        );
        parent::setXmlValue($value);
    }
}
