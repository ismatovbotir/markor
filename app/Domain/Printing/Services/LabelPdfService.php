<?php

namespace App\Domain\Printing\Services;

use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use App\Domain\Printing\Renderers\LayoutRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class LabelPdfService
{
    public function __construct(private DataMatrixRenderer $renderer) {}

    /** dompdf slows down sharply on long documents, so big batches are rendered in chunks and merged. */
    private const CHUNK = 50;

    /**
     * @param iterable<MarkingCode> $codes
     * @param array|null $logo image for `logo` elements (LabelImage: the batch company's print logo, or the sample)
     */
    public function pdf(LabelTemplate $template, iterable $codes, ?string $batchName = null, ?array $logo = null): string
    {
        $columns = $template->columns ?: 1;
        $gap     = (float) $template->gap_mm;

        // Templates made in the editor are drawn straight from their layout.
        if ($template->layout) {
            $renderer = app(LayoutRenderer::class)->withLogo($logo);
            $label    = $template->labelDefinition();
            $captions = LayoutRenderer::markingFields();
            $now      = now();
            $pages    = collect($codes)->values()->map(fn (MarkingCode $code, int $i) => fn () => $renderer->render($label, LayoutRenderer::markingRow($code, $batchName), $i, $now, $code, $captions));

            return $this->pdfFromPages($template->width_mm, $template->height_mm, '', $pages, $columns, $gap);
        }

        // Older HTML-only templates.
        [$css, $body] = $this->splitStyles($template->template);
        $pages = collect($codes)->map(fn (MarkingCode $code) => fn () => $this->fill($body, $code, $batchName));

        return $this->pdfFromPages($template->width_mm, $template->height_mm, $css, $pages, $columns, $gap);
    }

    /**
     * The same labels as pdf(), as HTML for the browser print page: [css, [label html, ...]].
     * @param iterable<MarkingCode> $codes
     */
    public function labelsHtml(LabelTemplate $template, iterable $codes, ?string $batchName = null, ?array $logo = null): array
    {
        if ($template->layout) {
            $renderer = app(LayoutRenderer::class)->withLogo($logo);
            $label    = $template->labelDefinition();
            $captions = LayoutRenderer::markingFields();
            $now      = now();

            return ['', collect($codes)->values()->map(fn (MarkingCode $code, int $i) => $renderer->render($label, LayoutRenderer::markingRow($code, $batchName), $i, $now, $code, $captions))->all()];
        }

        [$css, $body] = $this->splitStyles($template->template);

        return [$css, collect($codes)->map(fn (MarkingCode $code) => $this->fill($body, $code, $batchName))->values()->all()];
    }

    /**
     * Generic entry point. Each PDF page is one row of the roll: `$columns` labels side by side,
     * separated by `$gapMm`.
     *
     * @param iterable<string|\Closure():string> $pages label bodies (HTML), or closures producing them lazily
     */
    public function pdfFromPages(int $widthMm, int $heightMm, string $css, iterable $pages, int $columns = 1, float $gapMm = 0): string
    {
        $columns = max(1, $columns);
        $pageW   = $columns * $widthMm + ($columns - 1) * $gapMm;

        $chunks = collect($pages)->values()->chunk($columns)->chunk(max(1, intdiv(self::CHUNK, $columns)));
        $render = fn ($rows) => $this->renderChunk($widthMm, $heightMm, $pageW, $gapMm, $css, $rows);

        if ($chunks->count() <= 1) {
            return $render($chunks->first() ?? []);
        }

        $orientation = $pageW >= $heightMm ? 'L' : 'P';
        $merged = new Fpdi($orientation, 'mm', [$pageW, $heightMm]);
        $merged->SetAutoPageBreak(false);

        foreach ($chunks as $rows) {
            $count = $merged->setSourceFile(StreamReader::createByString($render($rows)));
            for ($page = 1; $page <= $count; $page++) {
                $merged->AddPage($orientation, [$pageW, $heightMm]);
                $merged->useTemplate($merged->importPage($page), 0, 0, $pageW, $heightMm);
            }
        }

        return $merged->Output('S');
    }

    private function renderChunk(int $w, int $h, float $pageW, float $gap, string $css, iterable $rows): string
    {
        $body = '';
        foreach ($rows as $row) {
            $body .= '<div class="__page">';
            foreach (collect($row)->values() as $i => $label) {
                $body .= sprintf(
                    '<div class="__label" style="left:%.2Fmm;">%s</div>',
                    $i * ($w + $gap),
                    $label instanceof \Closure ? $label() : $label
                );
            }
            $body .= '</div>';
        }
        // same line spacing as the editor (see LabelTemplate::PDF_LINE_HEIGHT_CSS)
        $body = str_replace(\App\Domain\Printing\Models\LabelTemplate::LINE_HEIGHT_CSS, \App\Domain\Printing\Models\LabelTemplate::PDF_LINE_HEIGHT_CSS, $body);

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @page { size: {$pageW}mm {$h}mm; margin: 0; }
  html, body { margin: 0; padding: 0; }
  .__page { position: relative; width: {$pageW}mm; height: {$h}mm; overflow: hidden; page-break-after: always; }
  .__page:last-child { page-break-after: auto; }
  .__label { position: absolute; top: 0; width: {$w}mm; height: {$h}mm; overflow: hidden; }
{$css}
</style></head>
<body>{$body}</body></html>
HTML;

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, $this->mmToPt($pageW), $this->mmToPt($h)])
            ->setOption([
                'defaultFont'             => 'DejaVu Sans',
                'isRemoteEnabled'         => false,
                'isPhpEnabled'            => false,
                'isFontSubsettingEnabled' => true,
                'dpi'                     => 300,
            ])
            ->output();
    }

    /**
     * A standalone HTML page with one label of the template, drawn at real size (mm), for
     * showing in an <iframe srcdoc>. Always uses the demo code, never a client's real code.
     */
    public function previewDocument(LabelTemplate $template): string
    {
        if ($template->layout) {
            $css = '';
            // previews never show a client's logo: a neutral sample instead
            $label = app(LayoutRenderer::class)->withLogo(LabelImage::sample())->render(
                $template->labelDefinition(),
                LayoutRenderer::markingRow($this->demoCode(), 'partiya_01'), 0, now(), $this->demoCode(), LayoutRenderer::markingFields()
            );
        } else {
            [$css, $body] = $this->splitStyles($template->template);
            $label = $this->fill($body, $this->demoCode(), 'preview');
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'html,body{margin:0;padding:0;background:#fff;overflow:hidden}img{image-rendering:pixelated}'
            . $css
            . '</style></head><body><div style="position:relative;width:' . $template->width_mm . 'mm;height:' . $template->height_mm . 'mm;overflow:hidden">'
            . $label . '</div></body></html>';
    }

    public function demoCode(): MarkingCode
    {
        $code = new MarkingCode([
            'code'        => '010432345678817621AF9-&eDmMbl?6CkOyAEB91UZF092TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=',
            'gtin'        => '04323456788176',
            'serial'      => 'AF9-&eDmMbl?6CkOyAEB',
            'crypto_tail' => 'UZF0TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=',
        ]);
        $code->setRelation('product', new \App\Domain\Catalog\Models\Product(['name_uz' => __('templates.sample_name'), 'name_ru' => __('templates.sample_name'), 'sku' => 'SKU-001']));

        return $code;
    }

    /** One label rendered from a fake appliance code — for template previews. */
    public function sampleCode(): MarkingCode
    {
        $code = MarkingCode::with('product')->latest('id')->first();

        return $code ?? new MarkingCode([
            'code'        => '010432345678817621AF9-&eDmMbl?6CkOyAEB91UZF092TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=',
            'gtin'        => '04323456788176',
            'serial'      => 'AF9-&eDmMbl?6CkOyAEB',
            'crypto_tail' => 'UZF0TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=',
        ]);
    }

    private function fill(string $body, MarkingCode $code, ?string $batchName): string
    {
        $product = $code->product;

        $values = [
            '{datamatrix}' => '<img class="datamatrix" src="' . $this->renderer->renderAsBase64($code) . '">',
            '{gtin}'       => e($code->gtin),
            '{serial}'     => e($code->serial),
            '{name}'       => e($product?->name ?? ''),
            '{sku}'        => e($product?->sku ?? ''),
            '{code}'       => e($code->code),
            '{batch}'      => e($batchName ?? ''),
            '{date}'       => now()->format('d.m.Y'),
        ];

        return strtr($body, $values);
    }

    /** @return array{0:string,1:string} [css, body-without-style-tags] */
    private function splitStyles(string $template): array
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#is', $template, $m);

        return [implode("\n", $m[1]), preg_replace('#<style[^>]*>.*?</style>#is', '', $template)];
    }


    private function mmToPt(int|float $mm): float
    {
        return $mm * 72 / 25.4;
    }
}
