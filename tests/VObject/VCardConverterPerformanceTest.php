<?php

namespace Sabre\VObject;

use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

/**
 * Inputs large enough to tell linear from quadratic conversion. Parsing them
 * alone can take most of a second with coverage on, so these tests get the
 * medium time limit.
 */
#[Medium]
class VCardConverterPerformanceTest extends TestCase
{
    public function testManyAnniversariesConvertInLinearTime(): void
    {
        $label = '_$!<Anniversary>!$_';
        $vcard4 = Reader::read("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:a\r\nUID:a\r\n".str_repeat("ANNIVERSARY:20000306\r\n", 5000)."END:VCARD\r\n");
        $vcard3 = Reader::read(
            "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:a\r\nUID:a\r\n"
            .implode('', array_map(fn ($i) => 'X-ANNIVERSARY:2000'.sprintf('%04d', $i)."\r\nITEM$i.X-ABDATE:2001".sprintf('%04d', $i)."\r\nITEM$i.X-ABLABEL:$label\r\n", range(1, 5000)))
            ."END:VCARD\r\n"
        );

        $start = microtime(true);
        $output3 = $vcard4->convert(Document::VCARD30);
        $output4 = $vcard3->convert(Document::VCARD40);
        self::assertLessThan(1.0, microtime(true) - $start);
        self::assertCount(5000, array_unique(array_map(fn ($date) => $date->group, $output3->select('X-ABDATE'))));
        self::assertSame('20010001', (string) $output4->ANNIVERSARY);
    }
}
