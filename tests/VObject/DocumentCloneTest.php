<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

class DocumentCloneTest extends TestCase
{
    private const ICS = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject//EN
BEGIN:VTIMEZONE
TZID:Custom Eastern
X-LIC-LOCATION:America/New_York
BEGIN:STANDARD
TZOFFSETFROM:-0400
TZOFFSETTO:-0400
DTSTART:19700101T000000
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
UID:clone-test
DTSTAMP:20260101T000000Z
DTSTART;TZID=Custom Eastern:20260322T090000
SUMMARY:Test
ATTENDEE;CN=Attendee;PARTSTAT=NEEDS-ACTION:mailto:attendee@example.org
BEGIN:VALARM
ACTION:DISPLAY
TRIGGER;RELATED=START:-PT15M
DESCRIPTION:Reminder
END:VALARM
END:VEVENT
END:VCALENDAR
ICS;

    public function testClonedDocumentIsTheRootOfAllItsNodes(): void
    {
        $doc = Reader::read(self::ICS);

        $copy = clone $doc;

        $nodes = $this->collectNodes($copy);
        self::assertContains($copy->VEVENT->VALARM, $nodes);
        self::assertContains($copy->VEVENT->VALARM->TRIGGER['RELATED'], $nodes);
        foreach ($nodes as $node) {
            self::assertSame($copy, $this->getRoot($node), $node->name.' should belong to the copy');
        }
    }

    public function testClonedDocumentDoesNotChangeTheOriginal(): void
    {
        $doc = Reader::read(self::ICS);

        clone $doc;

        foreach ($this->collectNodes($doc) as $node) {
            self::assertSame($doc, $this->getRoot($node), $node->name.' should belong to the original');
        }
    }

    public function testClonedDocumentResolvesCustomTimezoneAfterOriginalIsDestroyed(): void
    {
        $doc = Reader::read(self::ICS);

        $copy = clone $doc;
        $doc->destroy();

        self::assertEquals(
            new \DateTimeImmutable('2026-03-22T13:00:00Z'),
            $copy->VEVENT->DTSTART->getDateTime()
        );
    }

    public function testClonedComponentKeepsTheOriginalRoot(): void
    {
        $doc = Reader::read(self::ICS);

        $event = clone $doc->VEVENT;

        foreach ($this->collectNodes($event) as $node) {
            self::assertSame($doc, $this->getRoot($node), $node->name.' should belong to the original document');
        }
    }

    public function testClonedPropertyKeepsTheOriginalRoot(): void
    {
        $doc = Reader::read(self::ICS);

        $dtStart = clone $doc->VEVENT->DTSTART;

        self::assertSame($doc, $this->getRoot($dtStart));
        self::assertSame($doc, $this->getRoot($dtStart['TZID']));
        self::assertSame($dtStart, $dtStart['TZID']->parent);
    }

    public function testComponentAddedToAnotherDocumentResolvesTzidThroughItsNewRoot(): void
    {
        $vtimezone = fn (string $offset) => "BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19700101T000000\r\n"
            ."TZOFFSETFROM:$offset\r\nTZOFFSETTO:$offset\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\n";
        $source = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\n".$vtimezone('+0300')
            ."BEGIN:VEVENT\r\nUID:a\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;TZID=Custom:20260301T100000\r\nEND:VEVENT\r\n"
            ."END:VCALENDAR\r\n");
        $target = Reader::read("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//a//EN\r\n".$vtimezone('+0500')."END:VCALENDAR\r\n");

        $moved = $target->add(clone $source->VEVENT);
        $source->destroy();

        foreach ($this->collectNodes($moved) as $node) {
            self::assertSame($target, $this->getRoot($node), $node->name.' should belong to the target document');
        }
        self::assertSame('2026-03-01T05:00:00+00:00', $moved->DTSTART->getDateTime()->setTimezone(new \DateTimeZone('UTC'))->format('c'));
    }

    public function testPropertyAddedToAnotherDocumentTakesItsRoot(): void
    {
        $source = Reader::read(self::ICS);
        $target = new Component\VCalendar();
        $event = $target->add('VEVENT');

        $dtStart = $event->add(clone $source->VEVENT->DTSTART);

        self::assertSame($target, $this->getRoot($dtStart));
        foreach ($dtStart->parameters() as $parameter) {
            self::assertSame($target, $this->getRoot($parameter));
        }
    }

    /**
     * @return Node[]
     */
    private function collectNodes(Node $node): array
    {
        $nodes = [$node];
        if ($node instanceof Component) {
            foreach ($node->children() as $child) {
                array_push($nodes, ...$this->collectNodes($child));
            }
        } elseif ($node instanceof Property) {
            array_push($nodes, ...array_values($node->parameters()));
        }

        return $nodes;
    }

    private function getRoot(Node $node): ?Component
    {
        return (new \ReflectionProperty(Node::class, 'root'))->getValue($node);
    }

    public function testValidateRepairOnACloneWithARepeatedPropertyWithParameters(): void
    {
        $copy = clone Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:u\r\nN;X-A=1:a;;;;\r\nN;X-A=1:a;;;;\r\nEND:VCARD\r\n");

        $messages = $copy->validate(Node::REPAIR);

        self::assertCount(1, $copy->select('N'));
        self::assertNotContains(3, array_column($messages, 'level'));
    }

    public function testValidateRepairOnACloneKeepsRepeatedPropertiesThatDiffer(): void
    {
        $copy = clone Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:u\r\nN;X-A=1:a;;;;\r\nN;X-A=1:b;;;;\r\nEND:VCARD\r\n");

        $messages = $copy->validate(Node::REPAIR);

        self::assertCount(2, $copy->select('N'));
        self::assertContains(3, array_column($messages, 'level'));
    }

    public function testValidateRepairMergesAParsedDuplicateWhateverTheOrderOfItsParameters(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:u\r\nN;X-A=1;X-B=2:a;;;;\r\nN;X-B=2;X-A=1:a;;;;\r\nEND:VCARD\r\n");

        $card->validate(Node::REPAIR);

        self::assertCount(1, $card->select('N'));
    }
}
