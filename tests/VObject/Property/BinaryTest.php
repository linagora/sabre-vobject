<?php

namespace Sabre\VObject\Property;

use PHPUnit\Framework\TestCase;
use Sabre\VObject;

class BinaryTest extends TestCase
{
    public function testMimeDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $vcard = new VObject\Component\VCard(['VERSION' => '3.0']);
        $vcard->add('PHOTO', ['a', 'b']);
    }

    public function testInvalidBase64IsNotErased(): void
    {
        $card = VObject\Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nPHOTO:http://example.com/a.jpg\r\nEND:VCARD\r\n");
        self::assertNotFalse($card->PHOTO->getValue());
        self::assertStringContainsString("\r\nPHOTO:http://example.com/a.jpg\r\n", $card->serialize());
        self::assertSame(['http://example.com/a.jpg'], $card->PHOTO->getJsonValue());

        $warnings = $card->PHOTO->validate();
        self::assertCount(1, $warnings);
        self::assertSame(2, $warnings[0]['level']);

        $card->PHOTO->setValue('abc');
        self::assertStringContainsString("\r\nPHOTO:YWJj\r\n", $card->serialize());
        self::assertSame([], $card->PHOTO->validate());
    }

    public function testValidBase64IsDecoded(): void
    {
        $card = VObject\Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nPHOTO;ENCODING=b:YWJj\r\nEND:VCARD\r\n");
        self::assertSame('abc', $card->PHOTO->getValue());
        self::assertSame([], $card->PHOTO->validate());
    }
}
