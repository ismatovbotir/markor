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

    /** Built-in default label size, mm: a 20×20 label carrying only the marking DataMatrix. */
    public const DEFAULT_WIDTH = 20;
    public const DEFAULT_HEIGHT = 20;

    /**
     * Built-in 20×20 mm template (HTML for dompdf): the DataMatrix only, 2 mm quiet margin
     * (16×16 mm symbol — Asl Belgisi asks for at least 14×14 mm of contrasting area).
     */
    public const DEFAULT_TEMPLATE = <<<'HTML'
<style>
  .label      { position: relative; width: 20mm; height: 20mm; }
  .datamatrix { position: absolute; left: 2mm; top: 2mm; width: 16mm; height: 16mm; }
</style>
<div class="label">
  {datamatrix}
</div>
HTML;

    /** Built-in 20×20 mm layout for the visual editor (mm): only the marking DataMatrix. */
    public const DEFAULT_LAYOUT = [
        ['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 16, 'h' => 16],
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
