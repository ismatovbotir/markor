<?php

namespace App\Domain\Printing\Models;

use App\Domain\Catalog\Models\ProductGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class LabelTemplate extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    /** Placeholders available in a template (HTML is escaped except {datamatrix}). */
    public const PLACEHOLDERS = ['{datamatrix}', '{gtin}', '{serial}', '{name}', '{sku}', '{code}', '{batch}', '{date}'];

    /**
     * Built-in 60×40 mm template. Uses only CSS that dompdf renders reliably
     * (absolute positioning, no flex/grid).
     */
    public const DEFAULT_TEMPLATE = <<<'HTML'
<style>
  .label   { position: relative; width: 60mm; height: 40mm; font-family: "DejaVu Sans", sans-serif; color: #000; }
  .datamatrix { position: absolute; left: 3mm; top: 3mm; width: 27mm; height: 27mm; }
  .info    { position: absolute; left: 33mm; top: 3mm; width: 25mm; font-size: 6.5pt; line-height: 1.35; }
  .name    { font-weight: bold; font-size: 7pt; margin-bottom: 1.5mm; }
  .muted   { color: #444; font-size: 5.5pt; }
  .serial  { position: absolute; left: 3mm; bottom: 3mm; width: 54mm; font-size: 6.5pt; }
</style>
<div class="label">
  {datamatrix}
  <div class="info">
    <div class="name">{name}</div>
    <div class="muted">GTIN</div>
    <div><b>{gtin}</b></div>
    <div class="muted" style="margin-top: 1.5mm;">{date}</div>
  </div>
  <div class="serial"><span class="muted">SN</span> <b>{serial}</b></div>
</div>
HTML;

    /**
     * Built-in 60×40 mm layout for the visual editor. Positions and sizes are in mm,
     * font sizes in pt. Text may contain placeholders.
     */
    public const DEFAULT_LAYOUT = [
        ['type' => 'datamatrix', 'x' => 3, 'y' => 3, 'w' => 27, 'h' => 27],
        ['type' => 'text', 'text' => '{name}', 'x' => 33, 'y' => 3, 'w' => 24, 'h' => 9, 'font_size' => 7, 'bold' => true, 'align' => 'left'],
        ['type' => 'text', 'text' => 'GTIN', 'x' => 33, 'y' => 13, 'w' => 24, 'h' => 3, 'font_size' => 5.5, 'bold' => false, 'align' => 'left'],
        ['type' => 'text', 'text' => '{gtin}', 'x' => 33, 'y' => 16, 'w' => 24, 'h' => 4, 'font_size' => 6.5, 'bold' => true, 'align' => 'left'],
        ['type' => 'text', 'text' => '{date}', 'x' => 33, 'y' => 22, 'w' => 24, 'h' => 3.5, 'font_size' => 5.5, 'bold' => false, 'align' => 'left'],
        ['type' => 'text', 'text' => 'SN {serial}', 'x' => 3, 'y' => 33, 'w' => 54, 'h' => 4, 'font_size' => 6.5, 'bold' => true, 'align' => 'left'],
    ];

    protected $fillable = [
        'name', 'product_group_id', 'printer_type',
        'width_mm', 'height_mm', 'columns', 'gap_mm', 'labels_per_pdf', 'dpi',
        'template', 'layout', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'layout'     => 'array',
            'gap_mm'     => 'float',
        ];
    }

    /**
     * Legacy HTML form of a layout (stored in `template`). PDFs and previews of templates with a
     * layout are drawn by LayoutRenderer instead; this stays for older consumers.
     *
     * Turns an editor layout into the HTML that the PDF renderer consumes.
     * Only absolute positioning is used — dompdf renders that reliably.
     */
    public static function compileLayout(array $elements, int $widthMm, int $heightMm): string
    {
        $css  = '';
        $html = '';

        foreach (array_values($elements) as $i => $el) {
            [$innerW, $innerH] = self::innerSize($el);

            if ($el['type'] === 'datamatrix') {
                $css  .= sprintf(".dm%d img.datamatrix{display:block;width:%smm;height:%smm;}\n", $i, self::mm($innerW), self::mm($innerH));
                $html .= '  ' . self::elementBox($el, '', '{datamatrix}', "dm{$i}") . "\n";
                continue;
            }

            if ($el['type'] !== 'text') {
                continue; // barcodes and generated values exist only in the layout renderer
            }
            $text = filled($el['source'] ?? null) && !str_starts_with($el['source'], '@') ? '{' . $el['source'] . '}' : ($el['text'] ?? '');
            $html .= '  ' . self::elementBox($el, self::textCss($el), nl2br(e($text), false)) . "\n";
        }

        return "<style>\n{$css}</style>\n"
            . "<div style=\"position:relative;width:{$widthMm}mm;height:{$heightMm}mm;font-family:'DejaVu Sans',sans-serif;color:#000;\">\n"
            . $html
            . '</div>';
    }

    /** Content size before rotation: for 90°/270° the box on the label is the content turned on its side. */
    public static function innerSize(array $el): array
    {
        return ((int) ($el['rotation'] ?? 0)) % 180 === 0 ? [$el['w'], $el['h']] : [$el['h'], $el['w']];
    }

    public static function textCss(array $el): string
    {
        return sprintf(
            'overflow:hidden;font-size:%spt;line-height:1.2;font-weight:%s;text-align:%s;',
            self::mm($el['font_size'] ?? 7),
            !empty($el['bold']) ? 'bold' : 'normal',
            in_array($el['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($el['align'] ?? 'left') : 'left'
        );
    }

    /**
     * One absolutely positioned element. x/y/w/h are the box as it sits on the label;
     * rotation (0/90/180/270, clockwise) turns the content inside that box.
     */
    public static function elementBox(array $el, string $css, string $content, string $class = ''): string
    {
        $rotation = ((int) ($el['rotation'] ?? 0)) % 360;
        $box = sprintf(
            'position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;',
            self::mm($el['x']), self::mm($el['y']), self::mm($el['w']), self::mm($el['h'])
        );
        $classAttr = $class !== '' ? " class=\"{$class}\"" : '';

        if ($rotation === 0) {
            return "<div{$classAttr} style=\"{$box}{$css}\">{$content}</div>";
        }

        [$innerW, $innerH] = self::innerSize($el);
        $inner = sprintf(
            'position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;transform:rotate(%ddeg);transform-origin:50%% 50%%;',
            self::mm(($el['w'] - $innerW) / 2), self::mm(($el['h'] - $innerH) / 2), self::mm($innerW), self::mm($innerH), $rotation
        );

        return "<div{$classAttr} style=\"{$box}\"><div style=\"{$inner}{$css}\">{$content}</div></div>";
    }

    public static function mm(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** Labels per PDF part, rounded down to whole rows (never less than one row). */
    public function partSize(): int
    {
        $columns = max(1, (int) $this->columns);
        $wanted = max($columns, (int) ($this->labels_per_pdf ?: 2000));

        return intdiv($wanted, $columns) * $columns;
    }

    public static function default(): self
    {
        return static::where('is_default', true)->firstOrFail();
    }

    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class, 'template_id');
    }
}
