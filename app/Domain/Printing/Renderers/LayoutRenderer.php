<?php

namespace App\Domain\Printing\Renderers;

use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Studio\BarcodeImage;
use Illuminate\Support\Carbon;

/**
 * Renders one label from an editor layout — the single renderer behind both the public label
 * designer and the super admin's Asl Belgisi templates.
 *
 * Element types:
 *   text        — fixed text (may contain {key} placeholders) or a bound `source`
 *   barcode     — qr / datamatrix / code128 / ean13 of fixed text or a bound `source`
 *   datamatrix  — the Asl Belgisi marking code itself, GS1 encoded (needs a MarkingCode)
 *   logo        — the company logo (set with withLogo(); none → nothing is drawn)
 *   image       — the public studio's own image, already validated and converted (`_img`)
 * $label['watermark'] adds the small "markor.uz" mark in the bottom-right corner (big enough labels only).
 * Sources: a key of $row (a spreadsheet column or an Asl Belgisi field), "@date" or "@serial".
 */
class LayoutRenderer
{
    /** @var array<string, string> base64 PNGs, keyed by symbology|value */
    private array $cache = [];

    /** Company logo for `logo` elements: ['uri', 'w', 'h'] or null. */
    private ?array $logo = null;

    public function __construct(private BarcodeImage $barcodes, private DataMatrixRenderer $marking) {}

    public function withLogo(?array $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @param array{width_mm:int|float, height_mm:int|float, elements:array} $label
     * @param array<string,string> $row      values available to bound elements
     * @param array<string,string> $captions source key => human name, for "Name: value" captions
     */
    public function render(array $label, array $row, int $index, \DateTimeInterface $now, ?MarkingCode $code = null, array $captions = []): string
    {
        $html = sprintf(
            '<div style="position:relative;width:%smm;height:%smm;overflow:hidden;font-family:\'DejaVu Sans\',sans-serif;color:#000;">',
            LabelTemplate::mm($label['width_mm']), LabelTemplate::mm($label['height_mm'])
        );

        foreach ($label['elements'] as $el) {
            [$w, $h] = LabelTemplate::innerSize($el);

            if ($el['type'] === 'mark') {
                if ($image = self::mark((string) ($el['mark'] ?? ''))) {
                    $html .= LabelTemplate::elementBox($el, '', self::fittedImage($image, $w, $h));
                }
                continue;
            }

            if ($el['type'] === 'logo' || $el['type'] === 'image') {
                $image = $el['type'] === 'logo' ? $this->logo : ($el['_img'] ?? null);
                if ($image) {
                    $html .= LabelTemplate::elementBox($el, '', self::fittedImage($image, $w, $h));
                }
                continue;
            }

            if ($el['type'] === 'datamatrix') {
                if ($code) {
                    $html .= LabelTemplate::elementBox($el, '', sprintf(
                        '<img src="%s" style="display:block;width:%smm;height:%smm;">',
                        $this->marking->renderAsBase64($code), LabelTemplate::mm($w), LabelTemplate::mm($h)
                    ));
                }
                continue;
            }

            $source = (string) ($el['source'] ?? '');
            $value  = match (true) {
                $source === '@date'   => self::formatDate($now, $el['fmt'] ?? null, (int) ($el['offset_days'] ?? 0)),
                $source === '@serial' => self::serialValue($el, $index),
                $source !== ''        => (string) ($row[$source] ?? ''),
                default               => preg_replace_callback('/\{([^{}]+)\}/', fn ($m) => $row[$m[1]] ?? $m[0], (string) ($el['text'] ?? '')),
            };

            if ($el['type'] === 'barcode') {
                $type = $el['symbology'] ?? 'qr';
                if ($type === \App\Domain\Studio\MarkingTrial::SYMBOLOGY) {
                    // a real GS1 mark only from the uploaded table's column — never typed text, placeholders or generators
                    $value = ($source !== '' && !str_starts_with($source, '@')) ? (string) ($row[$source] ?? '') : '';
                }
                if (trim($value) === '') {
                    continue;
                }
                $digits = !empty($el['hrt']);
                $key = $type . '|' . (int) $digits . '|' . $value;
                try {
                    $this->cache[$key] ??= $type === \App\Domain\Studio\MarkingTrial::SYMBOLOGY
                        // Asl Belgisi code from a spreadsheet column, real GS1 DataMatrix (designer trial)
                        ? substr(app(\App\Domain\Studio\MarkingTrial::class)->png($value), strlen('data:image/png;base64,'))
                        : base64_encode($this->barcodes->png($type, $value, 6, $digits));
                } catch (\Throwable) {
                    $html .= LabelTemplate::elementBox($el, 'font-size:5pt;color:#b00;', e($type) . '?');
                    continue;
                }
                $html .= LabelTemplate::elementBox($el, '', sprintf(
                    '<img src="data:image/png;base64,%s" style="display:block;width:%smm;height:%smm;">',
                    $this->cache[$key], LabelTemplate::mm($w), LabelTemplate::mm($h)
                ));
                continue;
            }

            $generated = in_array($source, ['@date', '@serial'], true);
            if ($source !== '' && !$generated && !empty($el['caption'])) {
                $value = ($captions[$source] ?? $source) . ': ' . $value;
            }
            $html .= LabelTemplate::elementBox($el, LabelTemplate::textCss($el), nl2br(e($value), false));
        }

        if (!empty($label['watermark']) && LabelTemplate::watermarkFits($label['width_mm'], $label['height_mm'])) {
            $html .= self::watermark($label['width_mm'], $label['height_mm']);
        }

        return $html . '</div>';
    }

    /** The image fitted inside a w×h mm box (contain, centred) — dompdf has no object-fit. */
    /** Standard signs a label can carry (public/images/marks/{key}.png, black and white). */
    public const MARKS = ['aslbelgi'];

    /** One sign as ['uri', 'w', 'h'], read once per process (thousands of labels share it). */
    public static function mark(string $key): ?array
    {
        static $cache = [];
        if (!in_array($key, self::MARKS, true)) {
            return null;
        }
        if (!array_key_exists($key, $cache)) {
            $path = public_path("images/marks/$key.png");
            $info = is_file($path) ? @getimagesize($path) : false;
            $cache[$key] = $info ? ['uri' => 'data:image/png;base64,' . base64_encode(file_get_contents($path)), 'w' => $info[0], 'h' => $info[1]] : null;
        }

        return $cache[$key];
    }

    public static function fittedImage(array $image, float $w, float $h): string
    {
        $ratio = max(0.0001, $image['w']) / max(0.0001, $image['h']);
        [$iw, $ih] = $w / max(0.0001, $h) > $ratio ? [$h * $ratio, $h] : [$w, $w / $ratio];

        return sprintf(
            '<img src="%s" style="display:block;position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;">',
            $image['uri'], LabelTemplate::mm(($w - $iw) / 2), LabelTemplate::mm(($h - $ih) / 2), LabelTemplate::mm($iw), LabelTemplate::mm($ih)
        );
    }

    /** "markor.uz", black 4 pt, right-aligned in the bottom-right box, on top of everything. */
    public static function watermark(float $widthMm, float $heightMm): string
    {
        $m = LabelTemplate::WATERMARK_MARGIN;

        return sprintf(
            '<div style="position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;overflow:visible;z-index:10;'
            . 'font-family:\'DejaVu Sans\',sans-serif;font-size:%dpt;line-height:%smm;font-weight:normal;color:#000;text-align:right;white-space:nowrap;">%s</div>',
            LabelTemplate::mm($widthMm - $m - LabelTemplate::WATERMARK_W), LabelTemplate::mm($heightMm - $m - LabelTemplate::WATERMARK_H),
            LabelTemplate::mm(LabelTemplate::WATERMARK_W), LabelTemplate::mm(LabelTemplate::WATERMARK_H),
            // line-height 1.4 mm puts the baseline ≈0.55 mm above the label edge in dompdf (measured)
            LabelTemplate::WATERMARK_PT, '1.4', LabelTemplate::WATERMARK_TEXT
        );
    }

    /** Same tokens as the editor preview: YYYY YY MM DD HH mm ss; everything else is printed as is. */
    public static function formatDate(\DateTimeInterface $now, ?string $format, int $offsetDays): string
    {
        $date = Carbon::instance($now)->addDays($offsetDays);
        $map  = ['YYYY' => 'Y', 'YY' => 'y', 'MM' => 'm', 'DD' => 'd', 'HH' => 'H', 'mm' => 'i', 'ss' => 's'];

        return preg_replace_callback('/YYYY|YY|MM|DD|HH|mm|ss/', fn ($m) => $date->format($map[$m[0]]), $format ?: 'DD.MM.YYYY');
    }

    public static function serialValue(array $el, int $index): string
    {
        $number = (int) ($el['start'] ?? 1) + $index * (int) ($el['step'] ?? 1);
        $digits = str_pad((string) abs($number), min(12, max(0, (int) ($el['pad'] ?? 0))), '0', STR_PAD_LEFT);

        return ($el['prefix'] ?? '') . ($number < 0 ? '-' : '') . $digits . ($el['suffix'] ?? '');
    }

    /** The values an Asl Belgisi template can bind to, for one marking code. */
    public static function markingRow(MarkingCode $code, ?string $batchName): array
    {
        $product = $code->product?->forCompany($code->company_id); // the company's own name, if it gave one

        return [
            'gtin'   => (string) $code->gtin,
            'serial' => (string) $code->serial,
            'name'   => (string) ($product?->name ?: $code->gtin), // not named (unknown to the classifier): the GTIN
            'sku'    => (string) ($product?->sku ?? ''),
            'batch'  => (string) ($batchName ?? ''),
            'code'   => (string) $code->code,
            'date'   => now()->format('d.m.Y'), // legacy {date} placeholder
        ];
    }

    /** Field keys of Asl Belgisi templates with their display names (for the editor dropdown and captions). */
    public static function markingFields(): array
    {
        return [
            'gtin'   => 'GTIN',
            'serial' => __('templates.field.serial'),
            'name'   => __('templates.field.name'),
            'sku'    => 'SKU',
            'batch'  => __('templates.field.batch'),
            'code'   => __('templates.field.code'),
        ];
    }
}
