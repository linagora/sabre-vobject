<?php

namespace Sabre\VObject\ITip;

class BrokerProcessMessageTest extends BrokerTester
{
    public function testRequestNew()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REQUEST
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $result = $this->process($itip, null, $expected);
    }

    public function testRequestUpdate()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:REQUEST
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $result = $this->process($itip, $old, $expected);
    }

    public function testCancel()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:CANCEL
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
SEQUENCE:1
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
UID:foobar
STATUS:CANCELLED
SEQUENCE:2
END:VEVENT
END:VCALENDAR
ICS;

        $result = $this->process($itip, $old, $expected);
    }

    public function testCancelNoExistingEvent()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:CANCEL
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $result = $this->process($itip, $old, $expected);
    }

    public function testCancelInstanceOfRecurring()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
METHOD:CANCEL
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID;TZID=Romance Standard Time:20180320T160000
SUMMARY:CANCEL
DTSTART;TZID=Romance Standard Time:20180320T160000
DTEND;TZID=Romance Standard Time:20180320T163000
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART;TZID=Romance Standard Time:20180319T160000
DTEND;TZID=Romance Standard Time:20180319T163000
DTSTAMP:20180316T132615Z
SEQUENCE:0
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART;TZID=Romance Standard Time:20180319T160000
DTEND;TZID=Romance Standard Time:20180319T163000
DTSTAMP:20180316T132615Z
SEQUENCE:0
EXDATE:20180320T150000Z
END:VEVENT
END:VCALENDAR
ICS;

        $result = $this->process($itip, $old, $expected);
    }

    public function testCancelExistingInstanceOfRecurring() {
        $itip = <<<ICS
BEGIN:VCALENDAR
METHOD:CANCEL
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:CANCEL
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:CANCEL
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:1
END:VEVENT
END:VCALENDAR
ICS;

        $old = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART:20180319T160000Z
DTEND:20180319T163000Z
DTSTAMP:20180316T132615Z
SEQUENCE:0
END:VEVENT
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
UID:uid
RECURRENCE-ID:20180320T160000Z
SUMMARY:Modified Test
DTSTART:20180320T160000Z
DTEND:20180320T163000Z
DTSTAMP:20180316T132714Z
STATUS:CANCELLED
SEQUENCE:0
END:VEVENT
END:VCALENDAR
ICS;

        $expected = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
ORGANIZER;CN=Test:MAILTO:test@linagora.com
ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=user:MAILT
 O:one@example.org
RRULE:FREQ=DAILY;UNTIL=20180323T150000Z;INTERVAL=1
UID:uid
SUMMARY:Rec Test
DTSTART:20180319T160000Z
DTEND:20180319T163000Z
DTSTAMP:20180316T132615Z
SEQUENCE:0
EXDATE:20180320T160000Z
END:VEVENT
END:VCALENDAR
ICS;

        $result = $this->process($itip, $old, $expected);
    }

    public function testUnsupportedComponent()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
SEQUENCE:2
UID:foobar
END:VTODO
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $result = $this->process($itip, $old, $expected);
    }

    public function testUnsupportedMethod()
    {
        $itip = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
METHOD:PUBLISH
BEGIN:VEVENT
SEQUENCE:2
UID:foobar
END:VEVENT
END:VCALENDAR
ICS;

        $old = null;
        $expected = null;

        $result = $this->process($itip, $old, $expected);
    }
}
