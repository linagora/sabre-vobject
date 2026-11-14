<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

use Sabre\VObject\Component\VTimeZone;
use Sabre\VObject\Property;
use Sabre\VObject\TimeZoneUtil;

/**
 * Some clients add 'X-LIC-LOCATION' with the olson name.
 */
class GuessFromLicEntry implements TimezoneGuesser
{
    public function guess(VTimeZone $vtimezone, ?bool $failIfUncertain = false): ?\DateTimeZone
    {
        if (!$vtimezone->{'X-LIC-LOCATION'} instanceof Property) {
            return null;
        }

        $lic = (string) $vtimezone->{'X-LIC-LOCATION'};

        // Libical generators may specify strings like
        // "SystemV/EST5EDT". For those we must remove the
        // SystemV part.
        if (str_starts_with($lic, 'SystemV/')) {
            $lic = substr($lic, 8);
        }

        // An unknown name gives null, so that the next guessers can still
        // read the VTIMEZONE.
        try {
            return TimeZoneUtil::getTimeZone($lic, null, true);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
