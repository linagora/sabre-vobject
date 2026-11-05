<?php

namespace Sabre\VObject\Property;

use Sabre\VObject\Property;
use Sabre\Xml;

/**
 * Float property.
 *
 * This object represents FLOAT values. These can be 1 or more floating-point
 * numbers.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class FloatValue extends Property
{
    /**
     * In case this is a multi-value property. This string will be used as a
     * delimiter.
     */
    public string $delimiter = ';';

    /**
     * Sets a raw value coming from a mimedir (iCalendar/vCard) file.
     *
     * This has been 'unfolded', so only 1 line will be passed. Unescaping is
     * not yet done, but parameters are not included.
     */
    public function setRawMimeDirValue(string $val): void
    {
        $val = explode($this->delimiter, $val);
        foreach ($val as &$item) {
            $item = (float) $item;
        }
        $this->setParts($val);
    }

    /**
     * Returns a raw mime-dir representation of the value.
     */
    public function getRawMimeDirValue(): string
    {
        return implode(
            $this->delimiter,
            array_map(self::formatFloat(...), $this->getParts())
        );
    }

    /**
     * Writes a float as RFC 5545 section 3.3.7 defines it, without an exponent,
     * with the fewest digits that read back as the same float, whatever the
     * precision setting of PHP.
     */
    private static function formatFloat(mixed $value): string
    {
        $float = (float) $value;
        if (!is_finite($float)) {
            return (string) $float;
        }

        $digits = '';
        $exponent = 0;
        for ($precision = 0; $precision <= 17; ++$precision) {
            $scientific = str_replace(',', '.', sprintf('%.'.$precision.'e', abs($float)));
            if ((float) $scientific === abs($float)) {
                [$mantissa, $exponent] = explode('e', $scientific);
                $digits = str_replace('.', '', $mantissa);
                $exponent = (int) $exponent;
                break;
            }
        }

        $integerDigits = $exponent + 1;
        if ($integerDigits <= 0) {
            $plain = '0.'.str_repeat('0', -$integerDigits).$digits;
        } elseif ($integerDigits >= strlen($digits)) {
            $plain = $digits.str_repeat('0', $integerDigits - strlen($digits));
        } else {
            $plain = substr($digits, 0, $integerDigits).'.'.substr($digits, $integerDigits);
        }

        return ($float < 0 ? '-' : '').$plain;
    }

    /**
     * Returns the type of value.
     *
     * This corresponds to the VALUE= parameter. Every property also has a
     * 'default' valueType.
     */
    public function getValueType(): string
    {
        return 'FLOAT';
    }

    /**
     * Returns the value, in the format it should be encoded for JSON.
     *
     * This method must always return an array.
     */
    public function getJsonValue(): array
    {
        $val = array_map(floatval(...), $this->getParts());

        // Special-casing the GEO property.
        //
        // See:
        // http://tools.ietf.org/html/draft-ietf-jcardcal-jcal-04#section-3.4.1.2
        if ('GEO' === $this->name) {
            return [$val];
        }

        return $val;
    }

    /**
     * Hydrate data from an XML subtree, as it would appear in a xCard or xCal
     * object.
     */
    public function setXmlValue(array $value): void
    {
        $value = array_map(floatval(...), $value);
        parent::setXmlValue($value);
    }

    /**
     * This method serializes only the value of a property. This is used to
     * create xCard or xCal documents.
     */
    protected function xmlSerializeValue(Xml\Writer $writer): void
    {
        // Special-casing the GEO property.
        //
        // See:
        // http://tools.ietf.org/html/rfc6321#section-3.4.1.2
        if ('GEO' === $this->name) {
            $value = array_map(floatval(...), $this->getParts());

            $writer->writeElement('latitude', $value[0]);
            $writer->writeElement('longitude', $value[1]);
        } else {
            parent::xmlSerializeValue($writer);
        }
    }

    /**
     * Validates the node for correctness.
     *
     * A value too large for a float (1e999) becomes INF, which neither JSON
     * nor iCalendar / vCard can represent.
     */
    public function validate(int $options = 0): array
    {
        $messages = parent::validate($options);
        foreach ($this->getParts() as $part) {
            if (!is_finite((float) $part)) {
                $messages[] = [
                    'level' => 3,
                    'message' => 'The value of '.$this->name.' is not a finite FLOAT',
                    'node' => $this,
                ];
                break;
            }
        }

        return $messages;
    }
}
