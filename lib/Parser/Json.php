<?php

namespace Sabre\VObject\Parser;

use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Document;
use Sabre\VObject\EofException;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property;
use Sabre\VObject\Property\FlatText;
use Sabre\VObject\Property\Text;

/**
 * Json Parser.
 *
 * This parser parses both the jCal and jCard formats.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Json extends Parser
{
    /**
     * The input data.
     */
    protected ?array $input = null;

    /**
     * Root component.
     */
    protected ?Document $root = null;

    /**
     * This method starts the parsing process.
     *
     * If the input was not supplied during construction, it's possible to pass
     * it here instead.
     *
     * If either input or options are not supplied, the defaults will be used.
     *
     * @param resource|string|array|null $input
     *
     * @throws EofException
     * @throws ParseException
     * @throws InvalidDataException
     */
    public function parse($input = null, int $options = 0): ?Document
    {
        if (!is_null($input)) {
            $this->setInput($input);
        }
        if (is_null($this->input)) {
            throw new EofException('End of input stream, or no input supplied');
        }

        if (0 !== $options) {
            $this->options = $options;
        }

        $this->root = match ($this->input[0] ?? null) {
            'vcalendar' => new VCalendar([], false),
            'vcard' => new VCard([], false),
            default => throw new ParseException('The root component must either be a vcalendar, or a vcard'),
        };
        self::assertComponentShape($this->input);
        foreach ($this->input[1] as $prop) {
            $this->root->add($this->parseProperty($prop));
        }
        if (isset($this->input[2])) {
            foreach ($this->input[2] as $comp) {
                $this->root->add($this->parseComponent($comp));
            }
        }

        // Resetting the input so that we can throw an feof exception the next time.
        $this->input = null;

        return $this->root;
    }

    /**
     * Parses a component.
     *
     * @throws InvalidDataException
     * @throws ParseException
     */
    public function parseComponent(mixed $jComp): Component
    {
        if (!is_array($jComp)) {
            throw new ParseException('A component must be an array');
        }
        self::assertComponentShape($jComp);
        // The name is written as-is when serializing to MimeDir.
        if (!is_string($jComp[0] ?? null) || !preg_match('/^[A-Z0-9-]+\z/i', $jComp[0])) {
            throw new ParseException('Invalid component name');
        }
        if (strtoupper($jComp[0]) === $this->root->name) {
            throw new ParseException('Unexpected component: '.$jComp[0].' in document type '.$this->root->name);
        }

        // We can remove $self from PHP 5.4 onward.
        $self = $this;

        $properties = array_map(
            $self->parseProperty(...),
            $jComp[1]
        );

        if (isset($jComp[2])) {
            $components = array_map(
                $self->parseComponent(...),
                $jComp[2]
            );
        } else {
            $components = [];
        }

        return $this->root->createComponent(
            $jComp[0],
            array_merge($properties, $components),
            false
        );
    }

    /**
     * Parses properties.
     *
     * @throws InvalidDataException
     * @throws ParseException
     */
    public function parseProperty(mixed $jProp): Property
    {
        if (!is_array($jProp) || !array_is_list($jProp) || count($jProp) < 3) {
            throw new ParseException('A property must be an array of a name, parameters, a value type and values');
        }

        list(
            $propertyName,
            $parameters,
            $valueType,
        ) = $jProp;

        if (!is_string($propertyName) || !is_string($valueType)
            || (!is_array($parameters) && !$parameters instanceof \stdClass)) {
            throw new ParseException('A property must be an array of a name, parameters, a value type and values');
        }

        $propertyName = strtoupper($propertyName);

        // The name is written as-is when serializing to MimeDir: it must not
        // be able to produce a component boundary or another line.
        if (!preg_match('/^[A-Z0-9-]+\z/', $propertyName) || 'BEGIN' === $propertyName || 'END' === $propertyName) {
            throw new ParseException('Invalid property name: '.$propertyName);
        }

        // This is the default class we would be using if we didn't know the
        // value type. We're using this value later in this function.
        $defaultPropertyClass = $this->root->getClassNameForPropertyName($propertyName);

        $parameters = (array) $parameters;
        foreach ($parameters as $parameterName => $parameterValue) {
            // The name is written as-is when serializing to MimeDir as well.
            if (!preg_match('/^[A-Z0-9-]+\z/i', (string) $parameterName)) {
                throw new ParseException('Invalid parameter name for property '.$propertyName);
            }
            // The key "group" holds the property group; another spelling
            // would be a parameter that jsonSerialize() cannot tell apart.
            if ('group' !== $parameterName && 0 === strcasecmp((string) $parameterName, 'group')) {
                throw new ParseException('Invalid parameter name for property '.$propertyName);
            }
            if (!self::isScalarOrList($parameterValue)) {
                throw new ParseException('Invalid value for parameter '.$parameterName.' of property '.$propertyName);
            }
        }

        $value = array_slice($jProp, 3);

        $valueType = strtoupper($valueType);

        if (isset($parameters['group'])) {
            if (!is_string($parameters['group']) || !preg_match('/^[A-Z0-9-]+\z/i', $parameters['group'])) {
                throw new ParseException('Invalid group name for property '.$propertyName);
            }
            $propertyName = $parameters['group'].'.'.$propertyName;
            unset($parameters['group']);
        }

        $prop = $this->root->createProperty($propertyName, null, $parameters, $valueType);
        self::assertValues($prop, $value);
        $prop->setJsonValue($value);

        // We have to do something awkward here. FlatText as well as Text
        // represents TEXT values. We have to normalize these here. In the
        // future we can get rid of FlatText once we're allowed to break BC
        // again.
        if (FlatText::class === $defaultPropertyClass) {
            $defaultPropertyClass = Text::class;
        }

        // If the value type we received (e.g.: TEXT) was not the default value
        // type for the given property (e.g.: BDAY), we need to add a VALUE=
        // parameter. A class registered for the value type that is not the
        // default class (e.g. a subclass of Uri for URL) needs none, as long
        // as both classes have the received value type.
        if ($defaultPropertyClass !== $prop::class
            && ($prop->getValueType() !== $valueType
                || (new $defaultPropertyClass($this->root, $prop->name))->getValueType() !== $valueType)) {
            // Only declares the type: offsetSet() would rewrite the value of a
            // date-time, through the time zone that its TZID resolves to.
            unset($prop['VALUE']);
            $prop->add('VALUE', $valueType);
        }

        return $prop;
    }

    /**
     * Checks a component (or the root) is an array of a name, a list of
     * properties and an optional list of sub-components.
     *
     * @throws ParseException
     */
    private static function assertComponentShape(array $jComp): void
    {
        if (!is_array($jComp[1] ?? null) || (isset($jComp[2]) && !is_array($jComp[2]))) {
            throw new ParseException('A component must be an array of a name, a list of properties and a list of components');
        }
    }

    /**
     * Checks the values of a property have the shape its class can handle, so
     * that malformed values are rejected now rather than failing later, e.g.
     * when the document is serialized.
     *
     * @throws ParseException
     */
    private static function assertValues(Property $prop, array $values): void
    {
        // A property has one or more values (RFC 7265 section 3.4).
        if ([] === $values) {
            throw new ParseException('Missing value for property '.$prop->name);
        }
        $single = 1 === count($values);
        foreach ($values as $value) {
            $valid = match (true) {
                // A recurrence rule is an object of (lists of) scalars.
                $prop instanceof Property\ICalendar\Recur => 1 === count($values) && (is_string($value) || (
                    (is_array($value) || $value instanceof \stdClass)
                    && self::all((array) $value, fn ($part, $key) => 'until' === $key ? is_scalar($part) : self::isScalarOrList($part))
                )),
                // A period is a [start, end or duration] pair.
                $prop instanceof Property\ICalendar\Period => is_array($value) && array_is_list($value) && self::all($value, self::isScalar(...)),
                // GEO is a [latitude, longitude] pair. INF and NAN have no
                // JSON (nor iCalendar / vCard) representation.
                $prop instanceof Property\FloatValue => (self::isScalar($value) || ($single && [] !== $value && self::isScalarOrList($value)))
                    && self::all((array) $value, fn ($item) => is_finite((float) $item)),
                // These classes hold a single value.
                $prop instanceof Property\Binary,
                $prop instanceof Property\Boolean,
                $prop instanceof Property\IntegerValue => $single && self::isScalar($value),
                $prop instanceof Property\Time,
                $prop instanceof Property\UtcOffset,
                $prop instanceof Property\VCard\TimeStamp => self::isScalar($value),
                // Structured text (N, ADR, ...) is a single list of
                // components, which may themselves have several values.
                // Other text values are scalars, or a single list of them.
                $prop instanceof Text => self::isScalar($value) || ($single
                    && is_array($value) && [] !== $value && array_is_list($value)
                    && self::all($value, ';' === $prop->delimiter ? self::isScalarOrList(...) : self::isScalar(...))
                ),
                default => self::isScalar($value),
            };
            if (!$valid) {
                throw new ParseException('Invalid value for property '.$prop->name);
            }
        }

        // Only TEXT values are escaped when serialized to MimeDir: a line
        // break (or another control character) in a value of another type
        // would be written as-is and start a new line.
        if ((!$prop instanceof Text || $prop instanceof Property\Uri) && !$prop instanceof Property\Binary
            && self::containsMatch($values, '/[\x00-\x08\x0A-\x1F\x7F]/')) {
            throw new ParseException('Invalid character in the value of property '.$prop->name);
        }
        // The parts of a recurrence rule are joined with ; and =.
        if ($prop instanceof Property\ICalendar\Recur && !is_string($values[0] ?? null)) {
            foreach ((array) ($values[0] ?? []) as $key => $part) {
                if (!preg_match('/^[A-Z0-9-]+\z/i', (string) $key) || self::containsMatch($part, '/[;=]/')) {
                    throw new ParseException('Invalid recurrence rule part for property '.$prop->name);
                }
            }
        }
    }

    /**
     * Returns true if a string in $value (which may be a nested list or
     * object) matches $regex.
     */
    private static function containsMatch(mixed $value, string $regex): bool
    {
        if (is_array($value) || $value instanceof \stdClass) {
            return !self::all((array) $value, fn ($item) => !self::containsMatch($item, $regex));
        }

        return is_string($value) && 1 === preg_match($regex, $value);
    }

    /**
     * Returns true if $callback returns true for every item of $array.
     */
    private static function all(array $array, callable $callback): bool
    {
        foreach ($array as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }

        return true;
    }

    private static function isScalar(mixed $value): bool
    {
        return null === $value || is_scalar($value);
    }

    private static function isScalarOrList(mixed $value): bool
    {
        return self::isScalar($value) || (is_array($value) && array_is_list($value) && self::all($value, self::isScalar(...)));
    }

    /**
     * Sets the input data.
     *
     * @param resource|string|array $input
     *
     * @throws ParseException
     */
    public function setInput($input): void
    {
        if (is_resource($input)) {
            $input = stream_get_contents($input);
        }
        if (is_string($input)) {
            $input = json_decode($input);
        }
        if (!is_null($input) && !is_array($input)) {
            throw new ParseException('A jCal or jCard document must be an array');
        }
        $this->input = $input;
    }
}
