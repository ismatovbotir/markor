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
        'template', 'layout', 'is_default', 'watermark',
        'company_id', 'created_by', 'share_consent', 'shared_at', 'client_deleted_at',
    ];

    /**
     * Who sees a template: the super admin — all; a client — the global ones (made by the super admin
     * or published with the owner's consent) and its own company's. Guests, queue jobs and the Telegram
     * bot have no logged-in client: they must use public() / visibleTo() explicitly.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('visible', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where(fn ($q) => self::visibleWhere($q, $user->company_id));
            }
        });
    }

    private static function visibleWhere($q, ?int $companyId)
    {
        $q->whereNull($q->qualifyColumn('company_id'))->orWhereNotNull($q->qualifyColumn('shared_at'));
        if ($companyId) {
            // the company's own designs, unless it deleted them
            $q->orWhere(fn ($own) => $own->where($own->qualifyColumn('company_id'), $companyId)->whereNull($own->qualifyColumn('client_deleted_at')));
        }

        return $q;
    }

    /** Only templates everyone may see (public pages, marketplace). */
    public function scopePublic($query)
    {
        return $query->where(fn ($q) => self::visibleWhere($q, null));
    }

    /** Templates a company may use (for code without a logged-in client, e.g. the Telegram bot). */
    public function scopeVisibleTo($query, ?\App\Domain\Companies\Company $company)
    {
        return $query->where(fn ($q) => self::visibleWhere($q, $company?->id));
    }

    /** A client's own design it still keeps (counts toward the company's template_limit). */
    public function scopeOwnedBy($query, int $companyId)
    {
        return $query->where('company_id', $companyId)->whereNull('client_deleted_at');
    }

    /** Super admin status of a client design: private / consent / global / deleted. */
    public function clientStatus(): ?string
    {
        return match (true) {
            $this->company_id === null => null,
            $this->shared_at !== null => 'global',
            $this->client_deleted_at !== null => 'deleted',
            $this->share_consent => 'consent',
            default => 'private',
        };
    }

    public function isGlobal(): bool
    {
        return $this->company_id === null || $this->shared_at !== null;
    }

    public function company()
    {
        return $this->belongsTo(\App\Domain\Companies\Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * The "markor.uz" mark: black 4 pt DejaVu Sans in the bottom-right corner, 0.5 mm inside the
     * edge, drawn on top. Never on labels narrower than 20 mm or lower than 10 mm. The box (mm) is
     * what the editor keeps free and checks barcodes against: 4 pt "markor.uz" ≈ 7.2 mm wide.
     */
    public const WATERMARK_TEXT = 'markor.uz';
    public const WATERMARK_PT = 4;
    public const WATERMARK_MARGIN = 0.5;
    public const WATERMARK_W = 7.4;
    public const WATERMARK_H = 1.5;
    public const WATERMARK_MIN_W = 20;
    public const WATERMARK_MIN_H = 10;

    public static function watermarkFits(int|float $widthMm, int|float $heightMm): bool
    {
        return $widthMm >= self::WATERMARK_MIN_W && $heightMm >= self::WATERMARK_MIN_H;
    }

    /** The template has a company logo element (its PDFs depend on the batch company's logo). */
    public function usesLogo(): bool
    {
        return collect($this->layout ?: [])->contains('type', 'logo');
    }

    /** Label definition for LayoutRenderer::render(). */
    public function labelDefinition(): array
    {
        return ['width_mm' => $this->width_mm, 'height_mm' => $this->height_mm, 'elements' => $this->layout ?: [], 'watermark' => (bool) $this->watermark];
    }

    /**
     * Templates offered to a company for PDF / printing: "Mine" — its own designs and the global
     * templates it starred — or every template it may see while "Mine" is empty. Default first, then by name.
     */
    public static function forCompany(?\App\Domain\Companies\Company $company)
    {
        $query = static::query()->visibleTo($company)->orderByDesc('is_default')->orderBy('name');
        if ($company && ($company->labelTemplates()->exists() || static::query()->ownedBy($company->id)->exists())) {
            // "Mine": the company's own designs and the global templates it starred
            $query->where(fn ($q) => $q->whereIn('id', $company->labelTemplates()->select('label_templates.id'))
                ->orWhere(fn ($own) => $own->where('company_id', $company->id)->whereNull('client_deleted_at')));
        }

        return $query;
    }

    /** "58×40" — label size, used by the size filter. */
    public function sizeKey(): string
    {
        return $this->width_mm . '×' . $this->height_mm;
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'share_consent' => 'boolean',
            'shared_at'  => 'datetime',
            'client_deleted_at' => 'datetime',
            'watermark'  => 'boolean',
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

    /**
     * Text line spacing: 1.2 in the editor and on the browser print page. dompdf adds the font's own height
     * (DejaVu ≈ 1.16 em) on top of it, so PDFs use PDF_LINE_HEIGHT instead (measured against a 1.2 grid);
     * otherwise a box sized for two lines in the editor shows only the first line in the PDF.
     */
    public const LINE_HEIGHT_CSS = 'line-height:1.2;';
    public const PDF_LINE_HEIGHT_CSS = 'line-height:0.93;';

    public static function textCss(array $el): string
    {
        // word-wrap: long words without spaces break inside the box, like in the editor (break-words)
        return sprintf(
            'overflow:hidden;font-size:%spt;' . self::LINE_HEIGHT_CSS . 'word-wrap:break-word;overflow-wrap:break-word;font-weight:%s;text-align:%s;',
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
