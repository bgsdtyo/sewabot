<?php

namespace Tests\Unit;

use App\Services\QrisDinamisService;
use PHPUnit\Framework\TestCase;

class QrisDinamisServiceTest extends TestCase
{
    public function test_convert_static_to_dynamic_injects_amount_and_valid_crc(): void
    {
        // Minimal-ish static QRIS sample (valid CRC for this crafted payload path tested via service roundtrip)
        $service = new QrisDinamisService;

        // Build a tiny valid static QRIS: 00 + 01(static) + 53 + 58 + 59 + 63
        $parts = [
            '00' => '01',
            '01' => '11',
            '53' => '360',
            '58' => 'ID',
            '59' => 'SewaBot',
            '60' => 'Jakarta',
        ];

        $body = '';
        foreach ($parts as $tag => $value) {
            $body .= $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        }
        $crcInput = $body.'6304';
        $crc = $this->crc16($crcInput);
        $static = $crcInput.$crc;

        $this->assertTrue($service->isValid($static));

        $dynamic = $service->convert($static, 150000);
        $this->assertTrue($service->isValid($dynamic));
        $this->assertStringContainsString('010212', $dynamic);
        $this->assertStringContainsString('5406150000', $dynamic);
        $this->assertSame('SewaBot', $service->merchantNameFromPayload($dynamic));
    }

    private function crc16(string $str): string
    {
        $crc = 0xFFFF;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($str[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc & 0xFFFF), 4, '0', STR_PAD_LEFT));
    }
}
