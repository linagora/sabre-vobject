<?php

namespace Sabre\VObject;

use PHPUnit\Framework\TestCase;

/**
 * This test is created to handle the issues brought forward by issue 40.
 *
 * https://github.com/fruux/sabre-vobject/issues/40
 */
class Issue40Test extends TestCase
{
    public function testEncode(): void
    {
        $card = new Component\VCard();
        $card->add('N', ['van der Harten', ['Rene', 'J.'], '', 'Sir', 'R.D.O.N.'], ['SORT-AS' => ['Harten', 'Rene']]);

        unset($card->UID);

        $expected = implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'PRODID:-//Sabre//Sabre VObject '.Version::VERSION.'//EN',
            'N;SORT-AS=Harten,Rene:van der Harten;Rene,J.;;Sir;R.D.O.N.',
            'END:VCARD',
            '',
        ]);

        self::assertEquals($expected, $card->serialize());
    }

    public function testDecode(): void
    {
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nN:van der Harten;Rene,J.;;Sir;R.D.O.N.\r\nADR:;;1 Main St,Apt 2;Paris;;75001;FR\r\nEND:VCARD\r\n");

        self::assertSame(['van der Harten', ['Rene', 'J.'], '', 'Sir', 'R.D.O.N.'], $card->N->getParts());
        self::assertSame(['', '', ['1 Main St', 'Apt 2'], 'Paris', '', '75001', 'FR'], $card->ADR->getParts());
        // an escaped comma or semicolon stays a literal
        $card->N->setRawMimeDirValue('a\,b;c\;d;\\\\;;');
        self::assertSame(['a,b', 'c;d', '\\', '', ''], $card->N->getParts());
    }

    public function testRoundTrip(): void
    {
        $card = new Component\VCard(['VERSION' => '4.0', 'FN' => 'x']);
        $card->add('N', ['van der Harten', ['Rene', 'J.'], '', 'Sir', 'R.D.O.N.']);
        $card->add('ADR', ['', '', ['1 Main St', 'Apt 2,3'], 'Paris', '', '75001', 'FR']);
        $again = Reader::read($card->serialize());

        self::assertSame($card->N->getParts(), $again->N->getParts());
        self::assertSame($card->N->serialize(), $again->N->serialize());
        self::assertSame($card->ADR->getParts(), $again->ADR->getParts());
        self::assertSame($card->serialize(), $again->serialize());
    }

    public function testComponentListOfAPropertyWithoutListsIsOneValue(): void
    {
        $card = new Component\VCard(['VERSION' => '4.0', 'FN' => 'x']);
        $card->add('ORG', [['a', 'b'], 'c']);
        self::assertSame("ORG:a\\,b;c\r\n", $card->ORG->serialize());

        $again = Reader::read($card->serialize());
        self::assertSame(['a,b', 'c'], $again->ORG->getParts());
        self::assertSame($again->serialize(), Reader::read($again->serialize())->serialize());

        // Commas are not separators in ORG.
        $card = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:x\r\nORG:Foo, Inc.;Dept\r\nEND:VCARD\r\n");
        self::assertSame(['Foo, Inc.', 'Dept'], $card->ORG->getParts());
    }
}
