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
     * A type or subtype name of a media type (RFC 6838 section 4.2).
     */
    private const MEDIA_TYPE_TOKEN = '[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]{0,126}';

    /**
     * The source card the anniversaries were read from, and them by kind.
     *
     * @var array{Component\VCard, array<string, array<string, true>>}|null
     */
    private ?array $sourceAnniversaries = null;

    /**
     * The source card the X-ABLABEL properties were read from, and the first
     * one of each group.
     *
     * @var array{Component\VCard, array<string, Property>}|null
     */
    private ?array $labels = null;

    /**
     * The groups the vCard 3.0 output may not use for a new ITEMx group: the
     * output card, the number of properties of the source card in each
     * group, the ITEMx groups written, and the lowest x that may be free.
     *
     * @var array{Component\VCard, array<string, int>, array<string, true>, int}|null
     */
    private ?array $itemGroups = null;

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
        $this->sourceAnniversaries = null;
        $this->itemGroups = null;
        $this->labels = null;

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
                    if (isset($parameters['TYPE']) && !isset($binaryProperty['TYPE'])) {
                        // The vCard 4 TYPE (work, home) would be read back as
                        // the format: the media type, then without one in
                        // TYPE, goes first.
                        $binaryProperty['TYPE'] = 'application/octet-stream';
                    }
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
                    $x = $this->freeItemGroup($input, $output, $property);
                    // They are added after X-ANNIVERSARY, so that converting
                    // back to vCard 4.0 keeps that one (and its group).
                    // It marks the same year as omitted, also one the
                    // source marks itself.
                    $extraProperties[] = $output->createProperty(
                        'ITEM'.$x.'.X-ABDATE',
                        $newProperty->getValue(),
                        ['VALUE' => 'DATE-AND-OR-TIME'] + match (true) {
                            isset($newProperty['X-APPLE-OMIT-YEAR']) => ['X-APPLE-OMIT-YEAR' => '1604'],
                            isset($parameters['X-APPLE-OMIT-YEAR']) => ['X-APPLE-OMIT-YEAR' => (string) $parameters['X-APPLE-OMIT-YEAR']->getValue()],
                            default => [],
                        }
                    );
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
            } elseif (isset($input->KIND) && in_array($property->name, ['X-ABSHOWAS', 'X-ADDRESSBOOKSERVER-KIND'], true)) {
                // A native KIND wins. KIND:individual is implicit, and
                // KIND:org or group are written as one of these extensions:
                // another one that says otherwise would become the KIND of
                // the card when converted back to vCard 4.0.
                // The first KIND, read without $input->KIND, which copies
                // them all for each extension.
                $kind = strtolower((string) current($input->select('KIND'))->getValue());
                $extensionKind = match (strtoupper($property->name.':'.$property->getValue())) {
                    'X-ABSHOWAS:COMPANY' => 'org',
                    'X-ADDRESSBOOKSERVER-KIND:GROUP' => 'group',
                    default => null,
                };
                if (null !== $extensionKind && $extensionKind !== $kind && in_array($kind, ['individual', 'org', 'group'], true)) {
                    return;
                }
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
                $newProperty->setValue($this->withoutOmittedYear($property->getValue(), (string) $parameters['X-APPLE-OMIT-YEAR']->getValue()));

                // Regardless if the year matched or not, we do need to strip
                // X-APPLE-OMIT-YEAR.
                unset($parameters['X-APPLE-OMIT-YEAR']);
            }
            switch ($property->name) {
                case 'X-ABSHOWAS':
                    if ('COMPANY' === strtoupper((string) $property->getValue())) {
                        if (self::hasValue($output, 'KIND', 'ORG', true)) {
                            return;
                        }
                        // A native KIND wins, whatever the order of the lines.
                        if (!isset($output->KIND) && !isset($input->KIND)) {
                            $newProperty = $output->createProperty('KIND', 'ORG');
                        }
                    }
                    break;
                case 'X-ADDRESSBOOKSERVER-KIND':
                    if ('GROUP' === strtoupper((string) $property->getValue())) {
                        if (self::hasValue($output, 'KIND', 'GROUP', true)) {
                            return;
                        }
                        // A native KIND wins, whatever the order of the lines.
                        if (!isset($output->KIND) && !isset($input->KIND)) {
                            $newProperty = $output->createProperty('KIND', 'GROUP');
                        }
                    }
                    break;
                case 'KIND':
                    // KIND appears at most once in a vCard 4.0 (RFC 6350
                    // section 6.1.4): another value stays as an extension.
                    if (self::hasValue($output, 'KIND', (string) $property->getValue(), true)) {
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
                    // The values are compared by what they mean: a year marked
                    // as omitted is another date.
                    $anniversary = $this->anniversaryValue($newProperty, $parameters);
                    if ($this->hasAnniversary($output, 'ANNIVERSARY', $anniversary)
                        || isset($this->sourceAnniversaries($input, 'ANNIVERSARY')[$anniversary])) {
                        return;
                    }
                    // The date labelled as the anniversary is the one, if any.
                    // It is compared by meaning as well: a year marked as
                    // omitted is another date than the labelled full date.
                    $labelled = $this->sourceAnniversaries($input, 'labelled');
                    if (!isset($output->ANNIVERSARY) && !isset($input->ANNIVERSARY)
                        && ([] === $labelled || isset($labelled[$anniversary]))) {
                        $newProperty = $this->renameToAnniversary($output, $newProperty, $parameters);
                    }
                    break;
                case 'ANNIVERSARY':
                    if (self::hasValue($output, 'ANNIVERSARY', (string) $newProperty->getValue())) {
                        return;
                    }
                    if (isset($output->ANNIVERSARY)) {
                        $newProperty->name = 'X-ANNIVERSARY';
                    } else {
                        // A date-and-or-time or a text (RFC 6350 section
                        // 6.2.6), as for the extensions.
                        $newProperty = $this->renameToAnniversary($output, $newProperty, $parameters);
                    }
                    break;
                case 'X-ABDATE':
                    // Find out what the label was, if it exists. A group may
                    // be named 0.
                    if (null === $property->group) {
                        break;
                    }
                    $label = $this->firstLabel($input, $property->group);

                    // We only support converting anniversaries.
                    if (!$label || '_$!<Anniversary>!$_' !== $label->getValue()) {
                        break;
                    }

                    // If we already have an anniversary property with the same
                    // value, ignore. X-ANNIVERSARY holds the same anniversary
                    // with its own group, whichever is converted first.
                    $anniversary = $this->anniversaryValue($newProperty, $parameters);
                    if ($this->hasAnniversary($output, 'ANNIVERSARY', $anniversary)
                        || isset($this->sourceAnniversaries($input, 'ANNIVERSARY')[$anniversary])
                        || isset($this->sourceAnniversaries($input, 'X-ANNIVERSARY')[$anniversary])
                        // The twin of an X-ANNIVERSARY, as written without
                        // X-APPLE-OMIT-YEAR before (one that has it says
                        // what it means).
                        || (!isset($property['X-APPLE-OMIT-YEAR'])
                            && isset($this->sourceAnniversaries($input, 'raw X-ANNIVERSARY')[(string) $property->getValue()]))) {
                        return;
                    }
                    if (isset($output->ANNIVERSARY) || isset($input->ANNIVERSARY)) {
                        // Another anniversary stays as an extension, with its
                        // label, which is otherwise dropped below.
                        $output->add($output->createProperty($property->group.'.X-ABLABEL', $label->getValue()));
                        break;
                    }
                    $newProperty = $this->renameToAnniversary($output, $newProperty, $parameters);
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
     * Removes the year of a date when it is the one X-APPLE-OMIT-YEAR marks as
     * a placeholder.
     *
     * @throws InvalidDataException
     */
    private function withoutOmittedYear(string $value, string $omittedYear): string
    {
        $parts = DateTimeParser::parseVCardDateTime($value);
        if ($parts['year'] !== $omittedYear) {
            return $value;
        }

        $time = $this->getTimePart($value);
        // Without a month, the year is all the value says: it is kept. Without
        // a day, only --MM has no year (RFC 6350 section 4.3.1).
        if (!is_null($parts['month']) && !is_null($parts['date'])) {
            return '--'.$parts['month'].$parts['date'].$time;
        }

        return !is_null($parts['month']) && '' === $time ? '--'.$parts['month'] : $value;
    }

    /**
     * Returns the value an extension of an unknown type has as an ANNIVERSARY:
     * without the year marked as omitted.
     */
    private function anniversaryValue(Property $property, array $parameters): string
    {
        $value = (string) $property->getValue();
        if (!$property instanceof Property\Unknown || !isset($parameters['X-APPLE-OMIT-YEAR'])) {
            return $value;
        }

        try {
            return $this->withoutOmittedYear($value, (string) $parameters['X-APPLE-OMIT-YEAR']->getValue());
        } catch (InvalidDataException) {
            return $value;
        }
    }

    /**
     * Returns true if a card has a property of that name which means that
     * anniversary, once the year X-APPLE-OMIT-YEAR marks as omitted is
     * removed.
     */
    private function hasAnniversary(Component\VCard $card, string $name, string $value): bool
    {
        foreach ($card->select($name) as $property) {
            if ($this->storedAnniversary($property) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the anniversary a stored property means: its value without the
     * year X-APPLE-OMIT-YEAR marks as omitted.
     */
    private function storedAnniversary(Property $property): string
    {
        $stored = (string) $property->getValue();
        if (isset($property['X-APPLE-OMIT-YEAR'])
            && ($property instanceof Property\Unknown || $property instanceof Property\VCard\DateAndOrTime)) {
            try {
                $stored = $this->withoutOmittedYear($stored, (string) $property['X-APPLE-OMIT-YEAR']->getValue());
            } catch (InvalidDataException) {
            }
        }

        return $stored;
    }

    /**
     * Turns X-ANNIVERSARY or X-ABDATE into an ANNIVERSARY.
     *
     * Without a VALUE parameter these extensions have no known type, and with
     * one they may have any type (a uri, an integer...), which an ANNIVERSARY
     * cannot have: RFC 6350 section 6.2.6 only allows a date and/or time, or a
     * text.
     *
     * @param array $parameters list of parameters that will eventually be added to
     *                          the new property
     */
    private function renameToAnniversary(Component\VCard $output, Property $newProperty, array &$parameters): Property
    {
        if ($newProperty instanceof Property\VCard\DateAndOrTime || 'TEXT' === $newProperty->getValueType()) {
            $newProperty->name = 'ANNIVERSARY';
            // An unknown VALUE is kept by convertProperty, but an ANNIVERSARY
            // is only a date-and-or-time or a text: the VALUE is written
            // back from the class when it is needed. X-APPLE-OMIT-YEAR only
            // marks the year of a vCard 3 date: it has no meaning on a text,
            // and a date has lost it already.
            unset($parameters['VALUE'], $parameters['X-APPLE-OMIT-YEAR']);

            return $newProperty;
        }

        // A boolean is read as written: TRUE, not 1.
        $value = $newProperty instanceof Property\Text
            ? $this->anniversaryValue($newProperty, $parameters)
            : implode(',', array_map(
                fn ($part) => is_bool($part) ? ($part ? 'TRUE' : 'FALSE') : (string) $part,
                $newProperty->getParts()
            ));
        unset($parameters['VALUE'], $parameters['X-APPLE-OMIT-YEAR']);
        try {
            DateTimeParser::parseVCardDateTime($value);
        } catch (InvalidDataException) {
            $text = $output->createProperty('ANNIVERSARY', null, [], 'TEXT');
            if ($newProperty instanceof Property\Unknown) {
                // getValue() is already unescaped: the text is read again
                // from what the extension holds.
                $text->setRawMimeDirValue($newProperty->getRawMimeDirValue());
            } else {
                $text->setValue($value);
            }

            return $text;
        }

        return $output->createProperty('ANNIVERSARY', $value);
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

        $mimeType = null;

        // See if we can find a better mimetype. TYPE names the format (RFC
        // 2426 sections 3.1.4, 3.5.3 and 3.6.6): an IANA subtype of the
        // image, or of the sound, or a whole media type.
        if (isset($parameters['TYPE'])) {
            $newTypes = [];
            foreach ($parameters['TYPE']->getParts() as $typePart) {
                $typePart = (string) $typePart;
                if ('PREF' === strtoupper($typePart)) {
                    // A preference, not a format: it becomes PREF=1.
                    $newTypes[] = $typePart;
                } elseif (null === $mimeType && preg_match('/^'.self::MEDIA_TYPE_TOKEN.'\/'.self::MEDIA_TYPE_TOKEN.'\z/', $typePart)) {
                    $mimeType = strtolower($typePart);
                } elseif (null === $mimeType && preg_match('/^'.self::MEDIA_TYPE_TOKEN.'\z/', $typePart)) {
                    $mimeType = ('SOUND' === $newProperty->name ? 'audio/' : 'image/').strtolower($typePart);
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

        $newProperty->setValue('data:'.($mimeType ?? 'application/octet-stream').';base64,'.base64_encode((string) $value));

        return $newProperty;
    }

    /**
     * Returns the anniversaries of a kind in the source card, as keys:
     * - ANNIVERSARY and X-ANNIVERSARY: what these properties mean,
     * - raw X-ANNIVERSARY: the values of the X-ANNIVERSARY properties,
     * - labelled: what the X-ABDATE properties labelled as an anniversary
     *   mean (the label of a group is its first X-ABLABEL).
     *
     * They are read once per conversion, as each extension looks them up.
     *
     * @return array<string, true>
     */
    private function sourceAnniversaries(Component\VCard $card, string $kind): array
    {
        if (null === $this->sourceAnniversaries || $this->sourceAnniversaries[0] !== $card) {
            $this->sourceAnniversaries = [$card, []];
        }
        if (isset($this->sourceAnniversaries[1][$kind])) {
            return $this->sourceAnniversaries[1][$kind];
        }

        $values = [];
        switch ($kind) {
            case 'raw X-ANNIVERSARY':
                foreach ($card->select('X-ANNIVERSARY') as $property) {
                    $values[(string) $property->getValue()] = true;
                }
                break;
            case 'labelled':
                $extensions = $this->sourceAnniversaries($card, 'X-ANNIVERSARY');
                $rawExtensions = [];
                foreach ($card->select('X-ANNIVERSARY') as $extension) {
                    $rawExtensions[(string) $extension->getValue()] ??= $this->storedAnniversary($extension);
                }
                foreach ($card->select('X-ABDATE') as $date) {
                    if (null === $date->group || '_$!<Anniversary>!$_' !== $this->firstLabel($card, $date->group)?->getValue()) {
                        continue;
                    }
                    $value = $this->storedAnniversary($date);
                    // An X-ABDATE written without X-APPLE-OMIT-YEAR next to a
                    // yearless X-ANNIVERSARY (before 47ec822c) is its twin,
                    // when no X-ANNIVERSARY means the date itself.
                    if (!isset($date['X-APPLE-OMIT-YEAR']) && !isset($extensions[$value])
                        && isset($rawExtensions[(string) $date->getValue()])) {
                        $value = $rawExtensions[(string) $date->getValue()];
                    }
                    $values[$value] = true;
                }
                break;
            default:
                foreach ($card->select($kind) as $property) {
                    $values[$this->storedAnniversary($property)] = true;
                }
        }

        return $this->sourceAnniversaries[1][$kind] = $values;
    }

    /**
     * Returns the first X-ABLABEL of a group of the source card, as
     * $card->{$group.'.X-ABLABEL'} does, from an index of the labels built
     * once per conversion.
     */
    private function firstLabel(Component\VCard $card, string $group): ?Property
    {
        if (null === $this->labels || $this->labels[0] !== $card) {
            $labels = [];
            foreach ($card->select('X-ABLABEL') as $label) {
                if ($label instanceof Property && null !== $label->group) {
                    $labels[strtoupper($label->group)] ??= $label;
                }
            }
            $this->labels = [$card, $labels];
        }

        return $this->labels[1][strtoupper($group)] ?? null;
    }

    /**
     * Returns the lowest x such that the ITEMx group is used neither in the
     * output nor in the source card, where only the property itself may use
     * it.
     *
     * The groups are indexed once per conversion: looking each candidate up
     * in both cards made the conversion of n anniversaries cubic. The output
     * holds the groups of the source and the ITEMx groups written here.
     */
    private function freeItemGroup(Component\VCard $input, Component\VCard $output, Property $property): int
    {
        if (null === $this->itemGroups || $this->itemGroups[0] !== $output) {
            $counts = [];
            $taken = [];
            foreach ([$input, $output] as $card) {
                foreach ($card->children() as $child) {
                    if ($child instanceof Property && null !== $child->group) {
                        $group = strtoupper($child->group);
                        if ($card === $input) {
                            $counts[$group] = ($counts[$group] ?? 0) + 1;
                        } else {
                            $taken[$group] = true;
                        }
                    }
                }
            }
            $this->itemGroups = [$output, $counts, $taken, 1];
        }
        // The arrays are updated in place: a copy of them would be made for
        // each anniversary.
        $groups = &$this->itemGroups;

        // A group used by another property stays used: the lowest free one
        // only moves up, but for the group of the property itself.
        while (isset($groups[2]['ITEM'.$groups[3]]) || isset($groups[1]['ITEM'.$groups[3]])) {
            ++$groups[3];
        }
        $x = $groups[3];
        $own = null !== $property->group ? strtoupper($property->group) : '';
        if (preg_match('/^ITEM([1-9][0-9]*)$/', $own, $matches) && (int) $matches[1] < $x
            && !isset($groups[2][$own]) && 1 === ($groups[1][$own] ?? 0)) {
            $x = (int) $matches[1];
        }
        $groups[2]['ITEM'.$x] = true;

        return $x;
    }

    /**
     * Returns true if a card has a property with that value.
     *
     * A KIND is compared whatever its case (RFC 6350 section 6.1.4); an
     * ANNIVERSARY may be a text, compared exactly (RFC 6350 section 6.2.6).
     */
    private static function hasValue(Component\VCard $card, string $name, string $value, bool $caseInsensitive = false): bool
    {
        foreach ($card->select($name) as $property) {
            $stored = (string) $property->getValue();
            if ($caseInsensitive ? 0 === strcasecmp($stored, $value) : $stored === $value) {
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
            $mimeType = substr($metadata, 0, -7);
            if (str_contains($mimeType, ';')) {
                return $newProperty;
            }
            $data = base64_decode($payload, true);
            if (false === $data) {
                // Not valid base64: keep the uri rather than an empty value.
                return $newProperty;
            }
        } elseif (str_contains($metadata, ';')) {
            return $newProperty;
        } else {
            $data = rawurldecode($payload);
            $mimeType = $metadata;
        }

        if ('' === $mimeType) {
            // No media type means text/plain (RFC 2397): a BINARY without TYPE
            // would come back as application/octet-stream.
            return $newProperty;
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
        // The format goes into TYPE: the subtype for the image of a PHOTO or
        // a LOGO, or the sound of a SOUND, else the whole media type.
        $mimeType = strtolower($mimeType);
        if (preg_match('/^('.self::MEDIA_TYPE_TOKEN.')\/('.self::MEDIA_TYPE_TOKEN.')\z/', $mimeType, $matches)
            && 'application/octet-stream' !== $mimeType) {
            $newProperty['TYPE'] = ('SOUND' === $newProperty->name ? 'audio' : 'image') === $matches[1]
                ? strtoupper($matches[2])
                : $mimeType;
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
                // A property preferred twice (PREF=1 and TYPE=PREF, or
                // TYPE=PREF,PREF) is PREF=1 once.
                //
                // The other parts are added at once: adding them one by one
                // would copy the list for each part.
                case 'TYPE':
                    $types = [];
                    $prefFirst = null;
                    foreach ($param->getParts() as $paramPart) {
                        if ('PREF' === strtoupper((string) $paramPart)) {
                            $prefFirst ??= true;
                        } else {
                            $prefFirst ??= false;
                            $types[] = $paramPart;
                        }
                    }
                    if (true === $prefFirst && !isset($newProperty['PREF'])) {
                        $newProperty->add('PREF', '1');
                    }
                    if ([] !== $types) {
                        $newProperty->add($param->name, $types);
                    }
                    if (false === $prefFirst && count($types) < count($param->getParts()) && !isset($newProperty['PREF'])) {
                        $newProperty->add('PREF', '1');
                    }
                    break;
                case 'PREF':
                    // An explicit PREF says more than TYPE=PREF.
                    $newProperty[$param->name] = array_values(array_unique($param->getParts()));
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
        // Whether TYPE=PREF is written yet: a property preferred twice
        // (PREF=1 and TYPE=PREF, or TYPE=PREF,PREF) is TYPE=PREF once.
        $preferred = false;

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
                    if (!$preferred && ['1'] === array_values(array_unique(array_map(strval(...), $param->getParts())))) {
                        $newProperty->add('TYPE', 'PREF');
                        $preferred = true;
                    }
                    break;

                case 'TYPE':
                    $types = [];
                    foreach ($param->getParts() as $paramPart) {
                        if ('PREF' === strtoupper((string) $paramPart)) {
                            if ($preferred) {
                                continue;
                            }
                            $preferred = true;
                        }
                        $types[] = $paramPart;
                    }
                    if ([] !== $types) {
                        $newProperty->add($param->name, $types);
                    }
                    break;

                default:
                    $newProperty->add($param->name, $param->getParts());
                    break;
            }
        }
    }
}
