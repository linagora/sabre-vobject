<?php

declare(strict_types=1);

namespace Sabre\VObject\TimezoneGuesser;

/**
 * Some clients add 'X-LIC-LOCATION' with the olson name.
 */
class FindFromOffset implements TimezoneFinder
{
    public function find(string $tzid, ?bool $failIfUncertain = false): ?\DateTimeZone
    {
        // Maybe the author was hyper-lazy and just included an offset. We
        // support it, but we aren't happy about it.
        //
        // The offset zone is built from the offset itself: the Etc/GMT±h
        // zones use the POSIX sign convention (Etc/GMT+1 is UTC-1), only
        // cover whole hours and only go from -14 to +12.
        //
        // "+01:00" and "+01:00:30" are the names PHP gives an offset zone:
        // setDateTime() writes them as the TZID of a date in such a zone, so
        // they must resolve back.
        if (
            preg_match('/^GMT([+-][0-9]{4})\z/', $tzid, $matches)
            || preg_match('/^([+-][0-9]{2}:[0-9]{2}(?::[0-9]{2})?)\z/', $tzid, $matches)
        ) {
            try {
                return new \DateTimeZone($matches[1]);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
