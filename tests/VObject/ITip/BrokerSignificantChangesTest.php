<?php

namespace Sabre\VObject\ITip;

use PHPUnit\Framework\Attributes\DataProvider;

class BrokerSignificantChangesTest extends BrokerTester
{
    /**
     * Check significant changes detection (no change).
     */
    public function testSignificantChangesNoChange(): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
END:VCALENDAR
ICS;

        $new = $old;
        $expected = [['significantChange' => false]];

        $this->parse($old, $new, $expected, 'mailto:martin@fruux.com');
    }

    /**
     * Check significant changes detection (no change).
     */
    public function testSignificantChangesRRuleNoChange(): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
END:VCALENDAR
ICS;

        $new = str_replace('FREQ=WEEKLY', 'FREQ=WEEKLY;INTERVAL=1', $old);
        $expected = [['significantChange' => false]];

        $this->parse($old, $new, $expected, 'mailto:martin@fruux.com');
    }

    /**
     * Check significant changes detection (no change).
     */
    public function testSignificantChangesRRuleOrderNoChange(): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY;BYDAY=MO
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
END:VCALENDAR
ICS;

        $new = str_replace('FREQ=WEEKLY;BYDAY=MO', 'BYDAY=MO;FREQ=WEEKLY', $old);
        $expected = [['significantChange' => false]];

        $this->parse($old, $new, $expected, 'mailto:martin@fruux.com');
    }

    /**
     * Check significant changes detection (no change).
     * Reordering of the attendees should not be a significant change (#540)
     * https://github.com/sabre-io/vobject/issues/540.
     */
    public function testSignificantChangesAttendeesOrderNoChange(): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY;BYDAY=MO
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=de:MAILTO:holger@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
END:VCALENDAR
ICS;

        $new = str_replace('holger@fruux.com', 'dominik1@fruux.com', $old);
        $new = str_replace('dominik@fruux.com', 'holger@fruux.com', $new);
        $new = str_replace('dominik1@fruux.com', 'dominik@fruux.com', $new);
        $expected = [];
        $expected[] = ['significantChange' => false];
        $expected[] = ['significantChange' => false];

        $this->parse($old, $new, $expected, 'mailto:martin@fruux.com');
    }

    /**
     * Check significant changes detection (no change).
     * Reordering of vevent in a recurring event with exceptions should
     * not be a significant change
     * https://github.com/sabre-io/vobject/issues/542.
     */
    public function testSignificantChangesVeventOrderNoChange(): void
    {
        $vevent1 = <<<ICS
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY;BYDAY=MO
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
ICS;
        // This event is slightly different. DTSTAMP is in 2021
        $vevent2 = <<<ICS
BEGIN:VEVENT
UID:20140813T153116Z-12176-1000-1065-6@johnny-lubuntu
DTSTAMP:20210813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Evo makes a Meeting
LOCATION:fruux HQ
CLASS:PUBLIC
RRULE:FREQ=WEEKLY;BYDAY=MO
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=
 TRUE;LANGUAGE=en:MAILTO:dominik@fruux.com
CREATED:20140813T153211Z
LAST-MODIFIED:20140813T155353Z
END:VEVENT
ICS;

        $head = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
ICS;

        $old = $head;
        $old .= "\n".$vevent1;
        $old .= "\n".$vevent2;
        $old .= "\nEND:VCALENDAR";

        $new = $head;
        $new .= "\n".$vevent1;
        $new .= "\n".$vevent2;
        $new .= "\nEND:VCALENDAR";

        $expected = [['significantChange' => false]];

        $this->parse($old, $new, $expected, 'mailto:martin@fruux.com');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rruleNumberSpellings(): array
    {
        return [
            'COUNT' => ['FREQ=WEEKLY;COUNT=05', 'FREQ=WEEKLY;COUNT=5'],
            'INTERVAL' => ['FREQ=WEEKLY;INTERVAL=02', 'FREQ=WEEKLY;INTERVAL=2'],
            'default INTERVAL' => ['FREQ=WEEKLY;INTERVAL=01', 'FREQ=WEEKLY'],
        ];
    }

    #[DataProvider('rruleNumberSpellings')]
    public function testSignificantChangesRRuleNumberSpellingNoChange(string $oldRule, string $newRule): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:number-spelling
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Meeting
RRULE:$oldRule
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;PARTSTAT=NEEDS-ACTION:MAILTO:dominik@fruux.com
END:VEVENT
END:VCALENDAR
ICS;
        $new = str_replace("RRULE:$oldRule", "RRULE:$newRule", $old);

        $this->parse($old, $new, [['significantChange' => false]], 'mailto:martin@fruux.com');
    }

    public function testSignificantChangesRRuleOtherCount(): void
    {
        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:number-spelling
DTSTAMP:20140813T142829Z
DTSTART;TZID=America/Toronto:20140815T110000
SEQUENCE:2
SUMMARY:Meeting
RRULE:FREQ=WEEKLY;COUNT=05
ORGANIZER:MAILTO:martin@fruux.com
ATTENDEE;PARTSTAT=NEEDS-ACTION:MAILTO:dominik@fruux.com
END:VEVENT
END:VCALENDAR
ICS;
        $new = str_replace('COUNT=05', 'COUNT=50', $old);

        $this->parse($old, $new, [['significantChange' => true]], 'mailto:martin@fruux.com');
    }
}
