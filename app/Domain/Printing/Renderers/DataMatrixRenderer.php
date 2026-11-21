<?php

namespace App\Domain\Printing\Renderers;

use App\Domain\Marking\Models\MarkingCode;
use Com\Tecnick\Barcode\Barcode;

class DataMatrixRenderer
{
    /** Square DataMatrix, GS1 mode: FNC1 and GS (\x1D) are encoded as codeword 232. */
    private const TYPE = 'DATAMATRIX,S,GS1';

    private Barcode $barcode;

    public function __construct()
    {
        $this->barcode = new Barcode();
    }

    public function render(MarkingCode $code, int $widthMm = 10): string
    {
        return $this->object($code)->getSvgCode();
    }

    /**
     * PNG data URI. Drawn as a 1-bit palette PNG without alpha: dompdf embeds those
     * directly, while truecolor/alpha PNGs go through a very slow conversion path.
     */
    public function renderAsBase64(MarkingCode $code, int $modulePx = 8): string
    {
        return $this->gridToPng($this->object($code)->getGridArray(), $modulePx);
    }

    /** PNG data URI straight from a GS1 payload (used by the category code tester). */
    public function pngFromPayload(string $payload, int $modulePx = 8): string
    {
        return $this->gridToPng($this->barcode->getBarcodeObj(self::TYPE, $payload, -1, -1)->getGridArray(), $modulePx);
    }

    private function gridToPng(array $grid, int $modulePx): string
    {
        $rows = count($grid);
        $cols = count($grid[0]);

        $img   = imagecreate($cols * $modulePx, $rows * $modulePx);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        foreach ($grid as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell === '1') {
                    imagefilledrectangle($img, $x * $modulePx, $y * $modulePx, ($x + 1) * $modulePx - 1, ($y + 1) * $modulePx - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img, null, 9);
        $png = ob_get_clean();

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * GS1 payload: leading FNC1 + data, GS separators after variable-length fields.
     */
    public function buildGs1Payload(MarkingCode $code): string
    {
        // The category's structure says where the GS separators go.
        $structure = $code->product_group_id ? $this->structure($code->product_group_id) : null;
        if ($structure && ($payload = \App\Domain\Marking\Parsers\MarkingCodeParser::gs1Payload($code->code, $structure))) {
            return $payload;
        }

        $fnc1 = "\xE8";

        if ($code->format === 'tobacco_short') {
            return $fnc1 . $code->code;
        }

        return $fnc1 . sprintf(
            "01%s21%s\x1D91%s\x1D92%s",
            $code->gtin,
            $code->serial,
            substr($code->crypto_tail ?? '', 0, 4),
            substr($code->crypto_tail ?? '', 4, 44)
        );
    }

    /** @var array<int, array|null> */
    private array $structures = [];

    private function structure(int $groupId): ?array
    {
        if (!array_key_exists($groupId, $this->structures)) {
            $this->structures[$groupId] = \App\Domain\Catalog\Models\ProductGroup::withTrashed()->find($groupId)?->structure;
        }

        return $this->structures[$groupId];
    }

    private function object(MarkingCode $code)
    {
        return $this->barcode->getBarcodeObj(self::TYPE, $this->buildGs1Payload($code), -10, -10, 'black', [0, 0, 0, 0]);
    }
}
