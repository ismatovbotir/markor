<?php

namespace App\Domain\Studio;

use Com\Tecnick\Barcode\Barcode;

/** Generic barcodes for the public label studio, as 1-bit PNGs (fast for dompdf). */
class BarcodeImage
{
    public const TYPES = [
        'datamatrix' => 'DATAMATRIX',
        'qr'         => 'QRCODE,M',
        'code128'    => 'C128',
        'ean13'      => 'EAN13',
    ];

    public const SQUARE = ['datamatrix', 'qr'];

    /** Linear codes that can print their digits under the bars (human-readable text). */
    public const WITH_DIGITS = ['ean13', 'code128'];

    private const MODULE = 3;   // px per bar module of linear codes
    private const BARS_H = 80;  // px bar height
    private const FONT_PX = 22; // px digit height

    /**
     * @param bool $digits print the human-readable digits under the bars (EAN-13 / Code 128 only)
     * @throws \Throwable when the data cannot be encoded in this symbology
     */
    public function png(string $type, string $data, int $modulePx = 6, bool $digits = false): string
    {
        $grid = (new Barcode())->getBarcodeObj(self::TYPES[$type], $data, -1, -1)->getGridArray();

        if (count($grid) === 1) {
            $digits = $digits && in_array($type, self::WITH_DIGITS, true);
            $img = $type === 'ean13' && $digits
                ? $this->ean13WithDigits($grid[0], $data)
                : $this->linear($grid[0], $digits ? $data : null);
        } else {
            $img = $this->matrix($grid, $modulePx);
        }

        ob_start();
        imagepng($img, null, 9);

        return ob_get_clean();
    }

    private function matrix(array $grid, int $px): \GdImage
    {
        $img = imagecreate(count($grid[0]) * $px, count($grid) * $px);
        imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        foreach ($grid as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell === '1') {
                    imagefilledrectangle($img, $x * $px, $y * $px, ($x + 1) * $px - 1, ($y + 1) * $px - 1, $black);
                }
            }
        }

        return $img;
    }

    /** Bars only, or bars with the text centred underneath (Code 128). */
    private function linear(array $bars, ?string $text): \GdImage
    {
        $m = self::MODULE;
        $textH = $text !== null ? self::FONT_PX + 8 : 0;
        $img = imagecreate(count($bars) * $m, self::BARS_H + $textH);
        imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        foreach ($bars as $x => $cell) {
            if ($cell === '1') {
                imagefilledrectangle($img, $x * $m, 0, ($x + 1) * $m - 1, self::BARS_H - 1, $black);
            }
        }
        if ($text !== null) {
            $this->text($img, $text, imagesx($img) / 2, self::BARS_H + 4, $black);
        }

        return $img;
    }

    /**
     * Retail EAN-13 look: first digit left of the bars, 6 digits under each half,
     * start / centre / end guard bars longer than the data bars.
     */
    private function ean13WithDigits(array $bars, string $data): \GdImage
    {
        $m = self::MODULE;
        $code = self::ean13Full($data);
        $left = 9;   // modules of room for the first digit (quiet zone)
        $right = 5;
        $guardExtra = (int) (self::FONT_PX * 0.55);
        $height = self::BARS_H + self::FONT_PX + 6;

        $img = imagecreate(($left + count($bars) + $right) * $m, $height);
        imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        $guards = [0, 1, 2, 45, 46, 47, 48, 49, 92, 93, 94];
        foreach ($bars as $x => $cell) {
            if ($cell === '1') {
                $bottom = in_array($x, $guards, true) ? self::BARS_H + $guardExtra : self::BARS_H;
                imagefilledrectangle($img, ($left + $x) * $m, 0, ($left + $x + 1) * $m - 1, $bottom - 1, $black);
            }
        }

        $y = self::BARS_H + 3;
        $this->text($img, $code[0], ($left * $m) / 2, $y, $black);
        $this->text($img, substr($code, 1, 6), ($left + 3 + 21) * $m, $y, $black);   // under the left half (modules 3–44)
        $this->text($img, substr($code, 7, 6), ($left + 50 + 21) * $m, $y, $black);  // under the right half (modules 50–91)

        return $img;
    }

    /** 13 digits: the 12 given + check digit, or the 13 given. */
    public static function ean13Full(string $data): string
    {
        $digits = preg_replace('/\D/', '', $data);
        if (strlen($digits) === 13) {
            return $digits;
        }
        $digits = str_pad(substr($digits, 0, 12), 12, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $d) {
            $sum += (int) $d * ($i % 2 ? 3 : 1);
        }

        return $digits . ((10 - $sum % 10) % 10);
    }

    /** Text centred on $cx with its top at $top (DejaVu Sans Mono shipped with dompdf). */
    private function text(\GdImage $img, string $text, float $cx, int $top, int $color): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono.ttf');
        $size = self::FONT_PX * 0.75; // px → pt
        $box = imagettfbbox($size, 0, $font, $text);
        $w = $box[2] - $box[0];
        imagettftext($img, $size, 0, (int) round($cx - $w / 2), $top + self::FONT_PX - 4, $color, $font, $text);
    }
}
