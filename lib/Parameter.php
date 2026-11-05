<?php

namespace Sabre\VObject;

use Sabre\Xml;

/**
 * VObject Parameter.
 *
 * This class represents a parameter. A parameter is always tied to a property.
 * In the case of:
 *   DTSTART;VALUE=DATE:20101108
 * VALUE=DATE would be the parameter name and value.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Parameter extends Node implements \Stringable
{
    /**
     * Parameter name.
     */
    public string $name;

    /**
     * vCard 2.1 allows parameters to be encoded without a name.
     *
     * We can deduce the parameter name based on its value.
     */
    public bool $noName = false;

    /**
     * Parameter value.
     *
     * @var string|array|null
     */
    protected $value;

    /**
     * Sets up the object.
     *
     * It's recommended to use the create:: factory method instead.
     *
     * @param string|array|null $value
     */
    public function __construct(Document $root, ?string $name, $value = null)
    {
        $this->root = $root;
        if (is_null($name)) {
            $this->noName = true;
            $this->name = static::guessParameterNameByValue($value);
        } else {
            $this->name = strtoupper($name);
        }

        // If guessParameterNameByValue() returns an empty string
        // above, we're actually dealing with a parameter that has no value.
        // In that case we have to move the value to the name.
        if ('' === $this->name) {
            $this->noName = false;
            $this->name = strtoupper($value);
        } else {
            $this->setValue($value);
        }
    }

    /**
     * Try to guess property name by value, can be used for vCard 2.1 nameless parameters.
     *
     * Figuring out what the name should have been. Note that a ton of
     * these are rather silly in 2014 and would probably rarely be
     * used, but we like to be complete.
     */
    public static function guessParameterNameByValue(string $value): string
    {
        $name = match (strtoupper($value)) {
            '7-BIT', 'QUOTED-PRINTABLE', 'BASE64' => 'ENCODING',
            'WORK', 'HOME', 'PREF', 'DOM', 'INTL', 'POSTAL', 'PARCEL', 'VOICE', 'FAX', 'MSG', 'CELL', 'PAGER', 'BBS', 'MODEM', 'CAR', 'ISDN', 'VIDEO', 'AOL', 'APPLELINK', 'ATTMAIL', 'CIS', 'EWORLD', 'INTERNET', 'IBMMAIL', 'MCIMAIL', 'POWERSHARE', 'PRODIGY', 'TLX', 'X400', 'GIF', 'CGM', 'WMF', 'BMP', 'DIB', 'PICT', 'TIFF', 'PDF', 'PS', 'JPEG', 'MPEG', 'MPEG2', 'AVI', 'QTIME', 'WAVE', 'PCM', 'AIFF', 'X509', 'PGP' => 'TYPE',
            'INLINE', 'URL', 'CONTENT-ID', 'CID' => 'VALUE',
            default => '',
        };

        return $name;
    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     */
    public function setValue($value): void
    {
        $this->value = self::withoutCarriageReturn($value);
    }

    /**
     * Returns the current value.
     *
     * This method will always return a string, or null. If there were multiple
     * values, it will automatically concatenate them (separated by comma).
     */
    public function getValue(): ?string
    {
        if (is_array($this->value)) {
            return implode(',', $this->value);
        }

        return $this->value;
    }

    /**
     * Sets multiple values for this parameter.
     */
    public function setParts(array $value): void
    {
        $this->value = self::withoutCarriageReturn($value);
    }

    /**
     * Returns all values for this parameter.
     *
     * If there were no values, an empty array will be returned.
     */
    public function getParts(): array
    {
        if (is_array($this->value)) {
            return $this->value;
        } elseif (is_null($this->value)) {
            return [];
        }

        return [$this->value];
    }

    /**
     * Adds a value to this parameter.
     *
     * If the argument is specified as an array, all items will be added to the
     * parameter value list.
     *
     * @param string|array $part
     */
    public function addValue($part): void
    {
        $part = self::withoutCarriageReturn($part);
        if (is_null($this->value)) {
            $this->value = $part;
        } else {
            $this->value = array_merge((array) $this->value, (array) $part);
        }
    }

    /**
     * A bare carriage return cannot be encoded in a parameter value (RFC 6868
     * only encodes LF), and would end the line for many readers: it is dropped
     * as soon as the value is set, so every path reads the same value.
     */
    private static function withoutCarriageReturn(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::withoutCarriageReturn(...), $value);
        }

        return is_string($value) ? str_replace("\r", '', $value) : $value;
    }

    /**
     * Checks if this parameter contains the specified value.
     *
     * This is a case-insensitive match. It makes sense to call this for
     * the TYPE parameter, for instance, to see if it contains a keyword such as
     * 'WORK' or 'FAX'.
     */
    public function has(string $value): bool
    {
        return in_array(
            strtolower($value),
            array_map(strtolower(...), (array) $this->value),
            true
        );
    }

    /**
     * Turns the object back into a serialized blob.
     */
    public function serialize(): string
    {
        $value = $this->getParts();

        if (0 === count($value)) {
            return $this->name.'=';
        }

        if (Document::VCARD21 === $this->root->getDocumentType() && $this->noName) {
            return implode(';', $value);
        }

        // Each value is encoded on its own and the list is joined once:
        // concatenating in an array_reduce() callback copied the whole
        // string for every value (quadratic time on long lists).
        return $this->name.'='.implode(',', array_map(
            function ($item) {
                $item = (string) $item;

                // If there's no special characters in the string, we'll use the simple
                // format.
                //
                // The list of special characters is defined as:
                //
                // Any character except CONTROL, DQUOTE, ";", ":", ","
                //
                // by the iCalendar spec:
                // https://tools.ietf.org/html/rfc5545#section-3.1
                //
                // And we add ^ to that because of:
                // https://tools.ietf.org/html/rfc6868
                //
                // But we've found that iCal (7.0, shipped with OSX 10.9)
                // severely trips on + characters not being quoted, so we
                // added + as well.
                if (!preg_match('#(?: [\n":;\^,\+] )#x', $item)) {
                    return $item;
                }

                // Enclosing in double-quotes, and using RFC6868 for encoding any
                // special characters
                return '"'.strtr(
                    $item,
                    [
                        '^' => '^^',
                        "\n" => '^n',
                        '"' => '^\'',
                    ]
                ).'"';
            },
            $value
        ));
    }

    /**
     * Validates the node for correctness.
     *
     * A value that is not UTF-8 is reported (and converted with REPAIR), as
     * it would make the jCal / jCard of the document impossible to encode.
     */
    public function validate(int $options = 0): array
    {
        $parts = $this->getParts();
        $invalid = array_filter($parts, fn ($part) => is_string($part) && !mb_check_encoding($part, 'UTF-8'));
        if ([] === $invalid) {
            return [];
        }

        $repair = (bool) ($options & self::REPAIR);
        if ($repair) {
            $parts = array_map(fn ($part) => is_string($part) ? StringUtil::convertToUTF8($part) : $part, $parts);
            if (is_array($this->value)) {
                $this->setParts($parts);
            } else {
                $this->setValue($parts[0]);
            }
        }

        return [[
            'level' => $repair ? 1 : 3,
            'message' => 'The value of parameter '.$this->name.' is not valid UTF-8',
            'node' => $this,
        ]];
    }

    /**
     * This method returns an array, with the representation as it should be
     * encoded in JSON. This is used to create jCard or jCal documents.
     *
     * @return array|string|null
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->value;
    }

    /**
     * This method serializes the data into XML. This is used to create xCard or
     * xCal documents.
     *
     * @param Xml\Writer $writer XML writer
     */
    public function xmlSerialize(Xml\Writer $writer): void
    {
        foreach (explode(',', $this->value) as $value) {
            $writer->writeElement('text', $value);
        }
    }

    /**
     * Called when this object is being cast to a string.
     */
    public function __toString(): string
    {
        return (string) $this->getValue();
    }

    /**
     * Returns the iterator for this object.
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): ElementList
    {
        if (!is_null($this->iterator)) {
            return $this->iterator;
        }

        return $this->iterator = new ElementList((array) $this->value);
    }
}
