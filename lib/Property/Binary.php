<?php

namespace Sabre\VObject\Property;

use Sabre\VObject\Property;

/**
 * BINARY property.
 *
 * This object represents BINARY values.
 *
 * Binary values are most commonly used by the iCalendar ATTACH property, and
 * the vCard PHOTO property.
 *
 * This property will transparently encode and decode to base64.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Binary extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     */
    public string $delimiter = '';

    /**
     * The raw mimedir value, when it was not valid base64.
     *
     * It is written back unchanged, so that a value we cannot decode is not
     * erased by a parse / serialize round trip.
     */
    private ?string $invalidRawValue = null;

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     */
    public function setValue($value): void
    {
        $this->invalidRawValue = null;
        if (is_array($value)) {
            if (1 === count($value)) {
                $this->value = $value[0];
            } else {
                throw new \InvalidArgumentException('The argument must either be a string or an array with only one child');
            }
        } else {
            $this->value = $value;
        }
    }

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     */
    public function setRawMimeDirValue(string $val): void
    {
        $decoded = base64_decode($val, true);
        if (false === $decoded) {
            // Not valid base64: keep the raw value to write it back, and
            // expose the lenient decoding rather than false.
            $this->invalidRawValue = $val;
            $this->value = self::decodeLeniently($val);

            return;
        }
        $this->invalidRawValue = null;
        $this->value = $decoded;
    }

    /**
     * Decodes the base64 characters of a value, ignoring the other ones (as
     * base64_decode() does in non-strict mode).
     */
    private static function decodeLeniently(string $val): string
    {
        $val = (string) preg_replace('#[^A-Za-z0-9+/]#', '', $val);
        if (1 === strlen($val) % 4) {
            // A lone trailing character does not make up a byte.
            $val = substr($val, 0, -1);
        }

        return (string) base64_decode($val, true);
    }

    /**
     * Sets a multi-valued property.
     */
    public function setParts(array $parts): void
    {
        $this->invalidRawValue = null;
        parent::setParts($parts);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     */
    public function getRawMimeDirValue(): string
    {
        if (null !== $this->invalidRawValue) {
            return $this->invalidRawValue;
        }

        return base64_encode((string) $this->value);
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'BINARY';
    }

    /**
     * Returns the value, in the format it should be encoded for json.
     *
     * This method must always return an array.
     */
    public function getJsonValue(): array
    {
        if (null !== $this->invalidRawValue) {
            return [$this->invalidRawValue];
        }

        return [base64_encode((string) $this->getValue())];
    }

    /**
     * Sets the json value, as it would appear in a jCard or jCal object.
     *
     * The value must always be an array.
     */
    public function setJsonValue(array $value): void
    {
        $value = array_map(base64_decode(...), $value);
        parent::setJsonValue($value);
    }

    /**
     * Validates the node for correctness.
     *
     * A value that is not valid base64 is reported, as its decoding is
     * unreliable. It is kept as is.
     */
    public function validate(int $options = 0): array
    {
        $warnings = parent::validate($options);
        if (null !== $this->invalidRawValue) {
            $warnings[] = [
                'level' => 2,
                'message' => 'The value of '.$this->name.' is not valid base64',
                'node' => $this,
            ];
        }

        return $warnings;
    }
}
