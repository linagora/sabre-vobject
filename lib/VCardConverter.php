<?php

namespace Sabre\VObject;

use Sabre\VObject\Property\Binary;
use Sabre\VObject\Property\Uri;

/**
 * This utility converts vcards from one version to another.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class VCardConverter
{
    /**
     * Converts a vCard object to a new version.
     *
     * targetVersion must be one of:
     *   Document::VCARD21
     *   Document::VCARD30
     *   Document::VCARD40
     *
     * Currently only 3.0 and 4.0 as input and output versions.
     *
     * 2.1 has some minor support for the input version, it's incomplete at the
     * moment though.
     *
     * If input and output version are identical, a clone is returned.
     *
     * @throws InvalidDataException
     */
    public function convert(Component\VCard $input, int $targetVersion): Component\VCard
    {
        $inputVersion = $input->getDocumentType();
        if ($inputVersion === $targetVersion) {
            return clone $input;
        }

        if (!in_array($inputVersion, [Document::VCARD21, Document::VCARD30, Document::VCARD40], true)) {
            throw new \InvalidArgumentException('Only vCard 2.1, 3.0 and 4.0 are supported for the input data');
        }
        if (!in_array($targetVersion, [Document::VCARD30, Document::VCARD40], true)) {
            throw new \InvalidArgumentException('You can only use vCard 3.0 or 4.0 for the target version');
        }

        $newVersion = Document::VCARD40 === $targetVersion ? '4.0' : '3.0';

        $output = new Component\VCard([
            'VERSION' => $newVersion,
        ]);

        // We might have generated a default UID. Remove it!
        unset($output->UID);

        foreach ($input->children() as $property) {
            $this->convertProperty($input, $output, $property, $targetVersion);
        }

        return $output;
    }

    /**
     * Handles conversion of a single property.
     *
     * @throws InvalidDataException
     */
    protected function convertProperty(Component\VCard $input, Component\VCard $output, Property $property, int $targetVersion): void
    {
        // Skipping these, those are automatically added.
        if (in_array($property->name, ['VERSION', 'PRODID'], true)) {
            return;
        }

        $parameters = $property->parameters();
        $valueType = null;
        $extraProperties = [];
        if (isset($parameters['VALUE'])) {
            $valueType = (string) $parameters['VALUE']->getValue();
            if ('' !== $valueType && null === $output->getClassNameForPropertyValue($valueType)) {
                // An unknown value type: the property keeps the class of the
                // source, and the parameter is kept as is.
                $valueType = null;
            } else {
                unset($parameters['VALUE']);
            }
        }
        if (!$valueType) {
            $valueType = $property->getValueType();
        }
        if (Document::VCARD30 !== $targetVersion && 'PHONE-NUMBER' === $valueType) {
            $valueType = null;
        }
        $newProperty = $output->createProperty(
            $property->name,
            $property->getParts(),
            [], // parameters will get added a bit later.
            $valueType
        );

        if (Document::VCARD30 === $targetVersion) {
            if ($newProperty instanceof Uri && in_array($property->name, ['PHOTO', 'LOGO', 'SOUND'], true)) {
                $binaryProperty = $this->convertUriToBinary($output, $newProperty);
                if ($binaryProperty !== $newProperty) {
                    // The value is no longer of the unknown type, if any.
                    unset($parameters['VALUE']);
                }
                $newProperty = $binaryProperty;
            } elseif ($property instanceof Property\VCard\DateAndOrTime) {
                // In vCard 4, the birth year may be optional. This is not the
                // case for vCard 3. Apple has a workaround for this that
                // allows applications that support Apple's extension still
                // omit birth years in vCard 3, but applications that do not
                // support this, will just use a random birth year. We're
                // choosing 1604 for the birth year, because that's what apple
                // uses.
                // This only applies to a month and a day (--MMDD), any other
                // value without a year (a time, a day, a month) is kept.
                $parts = DateTimeParser::parseVCardDateTime($property->getValue());
                if (is_null($parts['year']) && !is_null($parts['month']) && !is_null($parts['date'])) {
                    $time = $this->getTimePart($property->getValue());
                    $newValue = '' === $time
                        ? '1604-'.$parts['month'].'-'.$parts['date']
                        : '1604'.$parts['month'].$parts['date'].$time;
                    $newProperty->setValue($newValue);
                    $newProperty['X-APPLE-OMIT-YEAR'] = '1604';
                }

                if ('ANNIVERSARY' === $newProperty->name) {
                    // Microsoft non-standard anniversary
                    $newProperty->name = 'X-ANNIVERSARY';

                    // We also need to add a new apple property for the same
                    // purpose. This apple property needs a 'label' in the same
                    // group, so we first need to find a groupname that doesn't
                    // exist yet, neither in the output nor in the source card
                    // (where only the anniversary itself may use it).
                    $x = 1;
                    while ($output->select('ITEM'.$x.'.') || array_filter(
                        $input->select('ITEM'.$x.'.'),
                        fn ($other) => $other !== $property
                    )) {
                        ++$x;
                    }
                    // They are added after X-ANNIVERSARY, so that converting
                    // back to vCard 4.0 keeps that one (and its group).
                    $extraProperties[] = $output->createProperty('ITEM'.$x.'.X-ABDATE', $newProperty->getValue(), ['VALUE' => 'DATE-AND-OR-TIME']);
                    $extraProperties[] = $output->createProperty('ITEM'.$x.'.X-ABLABEL', '_$!<Anniversary>!$_');
                }
            } elseif ('KIND' === $property->name) {
                switch (strtolower((string) $property->getValue())) {
                    case 'org':
                        // vCard 3.0 does not have an equivalent to KIND:ORG,
                        // but apple has an extension that means the same
                        // thing.
                        $newProperty = $output->createProperty('X-ABSHOWAS', 'COMPANY');
                        break;

                    case 'individual':
                        // Individual is implicit, so we skip it.
                        return;

                    case 'group':
                        // OS X addressbook property
                        $newProperty = $output->createProperty('X-ADDRESSBOOKSERVER-KIND', 'GROUP');
                        break;
                }
            } elseif ('MEMBER' === $property->name) {
                $newProperty = $output->createProperty('X-ADDRESSBOOKSERVER-MEMBER', $property->getValue());
            }
        } elseif (Document::VCARD40 === $targetVersion) {
            // These properties were removed in vCard 4.0
            if (in_array($property->name, ['NAME', 'MAILER', 'LABEL', 'CLASS'], true)) {
                return;
            }

            if ($newProperty instanceof Binary) {
                // The value is no longer of the unknown type, if any.
                unset($parameters['VALUE']);
                $newProperty = $this->convertBinaryToUri($output, $newProperty, $parameters);
            } elseif ($property instanceof Property\VCard\DateAndOrTime && isset($parameters['X-APPLE-OMIT-YEAR'])) {
                // If a property such as BDAY contained 'X-APPLE-OMIT-YEAR',
                // then we're stripping the year from the vcard 4 value.
                $parts = DateTimeParser::parseVCardDateTime($property->getValue());
                if ($parts['year'] === $property['X-APPLE-OMIT-YEAR']->getValue()) {
                    $time = $this->getTimePart($property->getValue());
                    // Without a month, the year is all the value says: it is
                    // kept. Without a day, only --MM has no year (RFC 6350
                    // section 4.3.1).
                    if (!is_null($parts['month']) && !is_null($parts['date'])) {
                        $newProperty->setValue('' === $time
                            ? '--'.$parts['month'].'-'.$parts['date']
                            : '--'.$parts['month'].$parts['date'].$time);
                    } elseif (!is_null($parts['month']) && '' === $time) {
                        $newProperty->setValue('--'.$parts['month']);
                    }
                }

                // Regardless if the year matched or not, we do need to strip
                // X-APPLE-OMIT-YEAR.
                unset($parameters['X-APPLE-OMIT-YEAR']);
            }
            switch ($property->name) {
                case 'X-ABSHOWAS':
                    if ('COMPANY' === strtoupper((string) $property->getValue())) {
                        if (self::hasValue($output, 'KIND', 'ORG')) {
                            return;
                        }
                        if (!isset($output->KIND)) {
                            $newProperty = $output->createProperty('KIND', 'ORG');
                        }
                    }
                    break;
                case 'X-ADDRESSBOOKSERVER-KIND':
                    if ('GROUP' === strtoupper((string) $property->getValue())) {
                        if (self::hasValue($output, 'KIND', 'GROUP')) {
                            return;
                        }
                        if (!isset($output->KIND)) {
                            $newProperty = $output->createProperty('KIND', 'GROUP');
                        }
                    }
                    break;
                case 'KIND':
                    // KIND appears at most once in a vCard 4.0 (RFC 6350
                    // section 6.1.4): another value stays as an extension.
                    if (self::hasValue($output, 'KIND', (string) $property->getValue())) {
                        return;
                    }
                    if (isset($output->KIND)) {
                        $newProperty->name = 'X-KIND';
                    }
                    break;
                case 'X-ADDRESSBOOKSERVER-MEMBER':
                    // The source is a TEXT value, and a line break it holds
                    // (an escaped \n) cannot appear raw in a URI value: it is
                    // written back as \n.
                    $newProperty = $output->createProperty(
                        'MEMBER',
                        str_replace(["\r\n", "\r", "\n"], '\n', (string) $property->getValue())
                    );
                    break;
                case 'X-ANNIVERSARY':
                    // If we already have an anniversary property with the same
                    // value, ignore. ANNIVERSARY appears at most once in a
                    // vCard 4.0 (RFC 6350 section 6.2.6): another value stays
                    // as an extension.
                    // A native ANNIVERSARY wins, whatever the order of the lines.
                    if (self::hasValue($output, 'ANNIVERSARY', (string) $newProperty->getValue())
                        || self::hasValue($input, 'ANNIVERSARY', (string) $newProperty->getValue())) {
                        return;
                    }
                    // The date labelled as the anniversary is the one, if any.
                    $labelled = self::labelledAnniversaries($input);
                    if (!isset($output->ANNIVERSARY) && !isset($input->ANNIVERSARY)
                        && ([] === $labelled || in_array((string) $newProperty->getValue(), $labelled, true))) {
                        $newProperty->name = 'ANNIVERSARY';
                    }
                    break;
                case 'ANNIVERSARY':
                    if (self::hasValue($output, 'ANNIVERSARY', (string) $newProperty->getValue())) {
                        return;
                    }
                    if (isset($output->ANNIVERSARY)) {
                        $newProperty->name = 'X-ANNIVERSARY';
                    }
                    break;
                case 'X-ABDATE':
                    // Find out what the label was, if it exists.
                    if (!$property->group) {
                        break;
                    }
                    $label = $input->{$property->group.'.X-ABLABEL'};

                    // We only support converting anniversaries.
                    if (!$label || '_$!<Anniversary>!$_' !== $label->getValue()) {
                        break;
                    }

                    // If we already have an anniversary property with the same
                    // value, ignore. X-ANNIVERSARY holds the same anniversary
                    // with its own group, whichever is converted first.
                    if (self::hasValue($output, 'ANNIVERSARY', (string) $newProperty->getValue())
                        || self::hasValue($input, 'ANNIVERSARY', (string) $newProperty->getValue())
                        || self::hasValue($input, 'X-ANNIVERSARY', (string) $newProperty->getValue())) {
                        return;
                    }
                    if (isset($output->ANNIVERSARY) || isset($input->ANNIVERSARY)) {
                        // Another anniversary stays as an extension, with its
                        // label, which is otherwise dropped below.
                        $output->add($output->createProperty($property->group.'.X-ABLABEL', $label->getValue()));
                        break;
                    }
                    $newProperty->name = 'ANNIVERSARY';
                    break;
                    // Apple's per-property label system.
                case 'X-ABLABEL':
                    if ('_$!<Anniversary>!$_' === $newProperty->getValue()) {
                        // We can safely remove these, as they are converted to
                        // ANNIVERSARY properties.
                        return;
                    }
                    break;
            }
        }

        // set property group
        $newProperty->group = $property->group;

        if (Document::VCARD40 === $targetVersion) {
            $this->convertParameters40($newProperty, $parameters);
        } else {
            $this->convertParameters30($newProperty, $parameters);
        }

        // Lastly, we need to see if there's a need for a VALUE parameter.
        //
        // We can do that by instantiating an empty property with that name, and
        // seeing if the default valueType is identical to the current one.
        $tempProperty = $output->createProperty($newProperty->name);
        if ($tempProperty->getValueType() !== $newProperty->getValueType()) {
            $newProperty['VALUE'] = $newProperty->getValueType();
        }

        $output->add($newProperty);
        foreach ($extraProperties as $extraProperty) {
            $output->add($extraProperty);
        }
    }

    /**
     * Returns the time part of a date and/or time value ("T102200Z"), or an
     * empty string.
     */
    private function getTimePart(string $value): string
    {
        $time = strstr($value, 'T');

        return false === $time ? '' : $time;
    }

    /**
     * Converts a BINARY property to a URI property.
     *
     * vCard 4.0 no longer supports BINARY properties.
     *
     * @param array $parameters list of parameters that will eventually be added to
     *                          the new property
     *
     * @throws InvalidDataException
     */
    protected function convertBinaryToUri(Component\VCard $output, Binary $newProperty, array &$parameters): Uri
    {
        $value = $newProperty->getValue();
        /** @var Uri $newProperty */
        $newProperty = $output->createProperty(
            $newProperty->name,
            null, // no value
            [], // no parameters yet
            'URI' // Forcing the BINARY type
        );

        $mimeType = 'application/octet-stream';

        // See if we can find a better mimetype.
        if (isset($parameters['TYPE'])) {
            $newTypes = [];
            foreach ($parameters['TYPE']->getParts() as $typePart) {
                if (in_array(
                    strtoupper((string) $typePart),
                    ['JPEG', 'PNG', 'GIF'],
                    true
                )) {
                    $mimeType = 'image/'.strtolower((string) $typePart);
                } else {
                    $newTypes[] = $typePart;
                }
            }

            // If there were any parameters we're not converting to a
            // mime-type, we need to keep them.
            if ($newTypes) {
                $parameters['TYPE']->setParts($newTypes);
            } else {
                unset($parameters['TYPE']);
            }
        }

        $newProperty->setValue('data:'.$mimeType.';base64,'.base64_encode((string) $value));

        return $newProperty;
    }

    /**
     * Returns the values of the X-ABDATE properties labelled as an anniversary.
     *
     * @return string[]
     */
    private static function labelledAnniversaries(Component\VCard $card): array
    {
        $values = [];
        foreach ($card->select('X-ABDATE') as $date) {
            $label = $date->group ? $card->{$date->group.'.X-ABLABEL'} : null;
            if ($label && '_$!<Anniversary>!$_' === $label->getValue()) {
                $values[] = (string) $date->getValue();
            }
        }

        return $values;
    }

    /**
     * Returns true if a card has a property with that value, whatever its case.
     */
    private static function hasValue(Component\VCard $card, string $name, string $value): bool
    {
        foreach ($card->select($name) as $property) {
            if (0 === strcasecmp((string) $property->getValue(), $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts a URI property to a BINARY property.
     *
     * In vCard 4.0 attachments are encoded as data: uri. Even though these may
     * be valid in vCard 3.0 as well, we should convert those to BINARY if
     * possible, to improve compatibility.
     *
     * @return Binary|Uri|null
     *
     * @throws InvalidDataException
     */
    protected function convertUriToBinary(Component\VCard $output, Uri $newProperty): Property
    {
        $value = $newProperty->getValue();

        // Only converting data: uris
        $comma = strpos((string) $value, ',');
        if (!str_starts_with((string) $value, 'data:') || false === $comma) {
            return $newProperty;
        }

        // data:[<mediatype>][;base64],<data> (RFC 2397): the payload is base64
        // exactly when the metadata ends in ;base64, else percent-encoded. A
        // uri with other parameters keeps them, which vCard 3 allows.
        $metadata = substr((string) $value, 5, $comma - 5);
        $payload = substr((string) $value, $comma + 1);
        if (str_ends_with(strtolower($metadata), ';base64')) {
            $data = base64_decode($payload, true);
            if (false === $data) {
                // Not valid base64: keep the uri rather than an empty value.
                return $newProperty;
            }
            $mimeType = substr($metadata, 0, (int) strpos($metadata, ';'));
        } elseif (str_contains($metadata, ';')) {
            return $newProperty;
        } else {
            $data = rawurldecode($payload);
            $mimeType = $metadata;
        }

        /** @var Binary $newProperty */
        $newProperty = $output->createProperty(
            $newProperty->name,
            null, // no value
            [], // no parameters yet
            'BINARY'
        );

        $newProperty->setValue($data);
        unset($value);

        $newProperty['ENCODING'] = 'b';
        switch ($mimeType) {
            case 'image/jpeg':
                $newProperty['TYPE'] = 'JPEG';
                break;
            case 'image/png':
                $newProperty['TYPE'] = 'PNG';
                break;
            case 'image/gif':
                $newProperty['TYPE'] = 'GIF';
                break;
        }

        return $newProperty;
    }

    /**
     * Adds parameters to a new property for vCard 4.0.
     */
    protected function convertParameters40(Property $newProperty, array $parameters): void
    {
        // Adding all parameters.
        foreach ($parameters as $param) {
            // vCard 2.1 allowed parameters with no name
            if ($param->noName) {
                $param->noName = false;
            }

            switch ($param->name) {
                // We need to see if there's any TYPE=PREF, because in vCard 4
                // that's now PREF=1.
                case 'TYPE':
                    foreach ($param->getParts() as $paramPart) {
                        if ('PREF' === strtoupper((string) $paramPart)) {
                            $newProperty->add('PREF', '1');
                        } else {
                            $newProperty->add($param->name, $paramPart);
                        }
                    }
                    break;
                    // These no longer exist in vCard 4
                case 'ENCODING':
                case 'CHARSET':
                    break;

                default:
                    $newProperty->add($param->name, $param->getParts());
                    break;
            }
        }
    }

    /**
     * Adds parameters to a new property for vCard 3.0.
     */
    protected function convertParameters30(Property $newProperty, array $parameters): void
    {
        // Adding all parameters.
        foreach ($parameters as $param) {
            // vCard 2.1 allowed parameters with no name
            if ($param->noName) {
                $param->noName = false;
            }

            switch ($param->name) {
                case 'ENCODING':
                    // This value only existed in vCard 2.1, and should be
                    // removed for anything else.
                    if ('QUOTED-PRINTABLE' !== strtoupper((string) $param->getValue())) {
                        $newProperty->add($param->name, $param->getParts());
                    }
                    break;

                    /*
                     * Converting PREF=1 to TYPE=PREF.
                     *
                     * Any other PREF numbers we'll drop.
                     */
                case 'PREF':
                    if ('1' === (string) $param->getValue()) {
                        $newProperty->add('TYPE', 'PREF');
                    }
                    break;

                default:
                    $newProperty->add($param->name, $param->getParts());
                    break;
            }
        }
    }
}
