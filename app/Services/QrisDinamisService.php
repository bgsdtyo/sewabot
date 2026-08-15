<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use InvalidArgumentException;

/**
 * Port of https://github.com/verssache/qris-dinamis core (static → dynamic QRIS).
 */
class QrisDinamisService
{
    /**
     * Convert static QRIS payload to dynamic with fixed amount (IDR).
     */
    public function convert(string $qrisString, int|float|string $amount): string
    {
        $qrisString = trim($qrisString);
        if ($qrisString === '') {
            throw new InvalidArgumentException('QRIS static string kosong.');
        }

        $amountStr = $this->formatAmount($amount);
        $elements = $this->parseTlv($qrisString);

        if ($elements === []) {
            throw new InvalidArgumentException('Format QRIS tidak valid.');
        }

        $result = [];
        $amountInserted = false;
        $managed = ['54', '55', '56', '57', '63'];

        foreach ($elements as $el) {
            if (in_array($el['tag'], $managed, true)) {
                continue;
            }

            if ($el['tag'] === '01') {
                $result[] = $this->makeTlv('01', '12');
                continue;
            }

            if ($el['tag'] === '58' && ! $amountInserted) {
                $result[] = $this->makeTlv('54', $amountStr);
                $amountInserted = true;
            }

            $result[] = $el;
        }

        if (! $amountInserted) {
            $result[] = $this->makeTlv('54', $amountStr);
        }

        $withoutCrc = $this->buildTlvString($result);
        $crcInput = $withoutCrc.'6304';
        $crc = $this->crc16($crcInput);

        return $crcInput.$crc;
    }

    public function isValid(string $qrisString): bool
    {
        $qrisString = trim($qrisString);
        if (strlen($qrisString) < 20 || ! str_starts_with($qrisString, '000201')) {
            return false;
        }

        $crc = substr($qrisString, -4);
        $body = substr($qrisString, 0, -4);

        return strtoupper($crc) === $this->crc16($body);
    }

    public function merchantNameFromPayload(string $qrisString): ?string
    {
        foreach ($this->parseTlv($qrisString) as $el) {
            if ($el['tag'] === '59') {
                return $el['value'] !== '' ? $el['value'] : null;
            }
        }

        return null;
    }

    /**
     * PNG binary of dynamic QRIS for the given amount.
     */
    public function png(string $staticQris, int|float|string $amount, int $size = 400): string
    {
        $dynamic = $this->convert($staticQris, $amount);

        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $dynamic,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        ))->build();

        return $result->getString();
    }

    public function dataUri(string $staticQris, int|float|string $amount, int $size = 400): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($staticQris, $amount, $size));
    }

    protected function formatAmount(int|float|string $amount): string
    {
        if (is_string($amount)) {
            $amount = preg_replace('/[^\d.]/', '', $amount) ?: '0';
        }

        $n = (float) $amount;
        if ($n <= 0) {
            throw new InvalidArgumentException('Nominal QRIS harus lebih dari 0.');
        }

        // Whole rupiah without decimals (same as verssache CLI examples).
        if (abs($n - round($n)) < 0.001) {
            return (string) (int) round($n);
        }

        return number_format($n, 2, '.', '');
    }

    /**
     * @return list<array{tag: string, length: int, value: string, children?: list<array>}>
     */
    protected function parseTlv(string $data): array
    {
        $elements = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos + 4 <= $len) {
            $tag = substr($data, $pos, 2);
            $length = (int) substr($data, $pos + 2, 2);

            if ($length < 0 || $pos + 4 + $length > $len) {
                break;
            }

            $value = substr($data, $pos + 4, $length);
            $element = [
                'tag' => $tag,
                'length' => $length,
                'value' => $value,
            ];

            $tagNum = (int) $tag;
            if (($tagNum >= 26 && $tagNum <= 51) || $tag === '62') {
                $element['children'] = $this->parseTlv($value);
            }

            $elements[] = $element;
            $pos += 4 + $length;
        }

        return $elements;
    }

    /**
     * @param  list<array{tag: string, value: string, children?: list<array>}>  $elements
     */
    protected function buildTlvString(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            $value = isset($el['children'])
                ? $this->buildTlvString($el['children'])
                : $el['value'];
            $out .= $el['tag'].str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        }

        return $out;
    }

    /**
     * @return array{tag: string, length: int, value: string}
     */
    protected function makeTlv(string $tag, string $value): array
    {
        return [
            'tag' => $tag,
            'length' => strlen($value),
            'value' => $value,
        ];
    }

    /**
     * CRC16-CCITT (poly 0x1021, init 0xFFFF) — EMVCo / QRIS.
     */
    protected function crc16(string $str): string
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
