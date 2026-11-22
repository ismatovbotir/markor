<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Offers\Models\Offer;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Domain\Printing\Services\LabelPdfService;
use App\Domain\Studio\BarcodeImage;
use App\Domain\Studio\SpreadsheetReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Public label studio: upload a spreadsheet, design a label, download a PDF.
 * Nothing is stored — the file is parsed and dropped, the PDF is built from the request.
 * Asl Belgisi marking codes: recognised in uploaded files and printable as real GS1 DataMatrix
 * for up to MarkingTrial::LIMIT labels (free trial); the full workflow requires login.
 */
class LabelStudioController extends Controller
{
    public const MAX_LABELS = 300;

    /** Barcodes per request (labels × barcode elements) — keeps a public request light. */
    public const MAX_BARCODES = 1500;

    /** Labels per print job (HTML print): light for the server, the browser and the printer queue. */
    public const PRINT_BATCH = 300;

    public function index()
    {
        return view('studio.index', [
            'maxLabels' => self::MAX_LABELS,
            'offers'    => Offer::active()->orderBy('sort_order')->get(),
            'catalog'   => \App\Domain\Offers\Models\OfferCategory::catalog(),
            'templatePreviews' => \App\Domain\Offers\Models\OfferCategory::templatePreviews(),
        ]);
    }

    public function parse(Request $request, SpreadsheetReader $reader)
    {
        $request->validate(['file' => ['required', 'file', 'max:' . config('uploads.studio.max_kb'),
            'extensions:' . implode(',', config('uploads.studio.extensions')), new \App\Rules\PlainTextFile()]]);

        $file = $request->file('file');

        try {
            $data = $this->readCodeLines($file->getRealPath(), $file->getClientOriginalExtension())
                ?? $reader->read($file->getRealPath(), $file->getClientOriginalExtension(), self::MAX_LABELS);
        } catch (\Throwable) {
            return response()->json(['message' => __('studio.unreadable')], 422);
        } finally {
            @unlink($file->getRealPath());
        }

        if (!$data['headers'] || !$data['rows']) {
            return response()->json(['message' => __('studio.empty_file')], 422);
        }

        // Asl Belgisi codes in the file → offer the GS1 element (free trial, up to 10 labels)
        $data['marking'] ??= app(\App\Domain\Studio\MarkingTrial::class)->detect($data['headers'], $data['rows']);
        if ($data['marking']) {
            $data['marking']['limit'] = \App\Domain\Studio\MarkingTrial::LIMIT;
        }

        return response()->json($data);
    }

    /**
     * Operator code files are one code per line, and codes contain , ; " characters that a CSV
     * reader would take for column separators. If most lines are marking codes, read them as one
     * column "Kod" (no header row). Returns null for any other file.
     */
    private function readCodeLines(string $path, string $extension): ?array
    {
        if (!in_array(strtolower($extension), ['csv', 'txt'], true)) {
            return null;
        }
        $trial = app(\App\Domain\Studio\MarkingTrial::class);
        $lines = [];
        $handle = fopen($path, 'r');
        while (($line = fgets($handle)) !== false && count($lines) < 100000) {
            $line = trim($line, "\r\n\xEF\xBB\xBF");
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }
        fclose($handle);

        $probe = array_slice($lines, 0, 50);
        if (!$probe || count(array_filter($probe, fn ($l) => $trial->isCode($l))) / count($probe) < 0.8) {
            return null;
        }

        return [
            'headers' => ['Kod'],
            'rows'    => array_map(fn ($l) => ['Kod' => $l], array_slice($lines, 0, self::MAX_LABELS)),
            'total'   => count($lines),
            'marking' => ['column' => 'Kod', 'header_is_code' => false, 'count' => count($lines)],
        ];
    }

    /** Barcode preview for the editor canvas. */
    public function barcode(Request $request, BarcodeImage $barcode)
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', [...array_keys(BarcodeImage::TYPES), \App\Domain\Studio\MarkingTrial::SYMBOLOGY]),
            'data' => 'required|string|max:500',
            'hrt'  => 'nullable|boolean',
        ]);

        try {
            // the editor preview of an Asl Belgisi element is only a look-alike: a real GS1 mark is made
            // only by generate/print, within the daily trial (otherwise this would be a free mark printer)
            $png = $data['type'] === \App\Domain\Studio\MarkingTrial::SYMBOLOGY
                ? $barcode->png('datamatrix', 'MARKOR PREVIEW ' . substr(md5($data['data']), 0, 8))
                : $barcode->png($data['type'], $data['data'], 6, (bool) ($data['hrt'] ?? false));
        } catch (\Throwable) {
            abort(422);
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function generate(Request $request, LabelPdfService $pdf)
    {
        // errors always as JSON (422), never a redirect: the browser would save that HTML page as labels.pdf
        $request->headers->set('Accept', 'application/json');
        [$data, $rows] = $this->design($request);

        $renderer = app(LayoutRenderer::class);
        $pages = [];
        $now = now();
        foreach (array_values($rows) as $index => $row) {
            $pages[] = fn () => $renderer->render($data, $row, $index, $now);
        }

        set_time_limit(120);

        return response($pdf->pdfFromPages($data['width_mm'], $data['height_mm'], '', $pages, (int) ($data['columns'] ?? 1), (float) ($data['gap_mm'] ?? 0)), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="labels.pdf"',
        ]);
    }

    /**
     * Print without a PDF: the labels as an HTML page sized for the label printer (@page = label row),
     * opened by the browser in a new window that calls the system print dialog. At most PRINT_BATCH
     * labels per print (light for the server and the browser); `offset` prints the next batch.
     */
    public function print(Request $request)
    {
        $request->headers->set('Accept', 'application/json');
        [$data, $rows] = $this->design($request);
        $offset = max(0, (int) $request->integer('offset'));
        $total = count($rows);
        $batch = array_slice(array_values($rows), $offset, self::PRINT_BATCH);
        abort_if(!$batch, 422, __('studio.print_nothing'));

        $renderer = app(LayoutRenderer::class);
        $now = now();
        $labels = [];
        foreach ($batch as $i => $row) {
            $labels[] = $renderer->render($data, $row, $offset + $i, $now); // serial numbers continue across batches
        }

        $columns = max(1, (int) ($data['columns'] ?? 1));
        $gap = (float) ($data['gap_mm'] ?? 0);

        return response()->view('studio.print', [
            'pages'  => array_chunk($labels, $columns),
            'width'  => (float) $data['width_mm'],
            'height' => (float) $data['height_mm'],
            'gap'    => $gap,
            'pageW'  => $columns * $data['width_mm'] + ($columns - 1) * $gap,
            'from'   => $offset + 1,
            'to'     => $offset + count($batch),
            'total'  => $total,
        ]);
    }

    /** Validated design + the label rows (shared by the PDF download and printing). */
    private function design(Request $request): array
    {
        $data = $request->validate([
            'width_mm'             => 'required|integer|min:10|max:300',
            'height_mm'            => 'required|integer|min:10|max:300',
            'columns'              => 'nullable|integer|min:1|max:10',
            'gap_mm'               => 'nullable|numeric|min:0|max:30',
            'copies'               => 'nullable|integer|min:1|max:' . self::MAX_LABELS,
            'elements'             => 'required|array|min:1|max:40',
            'watermark'            => 'nullable|boolean',             // the small "markor.uz" mark (on unless unchecked)
            'elements.*.type'      => 'required|in:text,barcode,image,mark',
            'elements.*.mark'      => 'nullable|required_if:elements.*.type,mark|in:' . implode(',', \App\Domain\Printing\Renderers\LayoutRenderer::MARKS),
            // the visitor's own image: a PNG / JPEG data URI made in the browser (≤ 1 MB decoded, checked below)
            'elements.*.src'       => 'nullable|string|max:' . (int) ceil(\App\Domain\Printing\Services\LabelImage::MAX_KB * 1024 * 4 / 3 + 64),
            'elements.*.bw'        => 'nullable|boolean',             // image printed black-and-white (default) or grayscale
            'elements.*.x'         => 'required|numeric|min:0|max:300',
            'elements.*.y'         => 'required|numeric|min:0|max:300',
            'elements.*.w'         => 'required|numeric|min:1|max:300',
            'elements.*.h'         => 'required|numeric|min:1|max:300',
            'elements.*.text'      => 'nullable|string|max:2000',
            'elements.*.source'    => 'nullable|string|max:80',   // spreadsheet column bound through the dropdown
            'elements.*.caption'   => 'nullable|boolean',         // print "Column: value"
            // generated sources: "@date" (print date/time) and "@serial" (running number per label)
            'elements.*.fmt'         => 'nullable|string|max:40',
            'elements.*.offset_days' => 'nullable|integer|min:-3650|max:3650',
            'elements.*.prefix'      => 'nullable|string|max:30',
            'elements.*.suffix'      => 'nullable|string|max:30',
            'elements.*.start'       => 'nullable|integer|min:0|max:999999999',
            'elements.*.step'        => 'nullable|integer|min:-1000|max:1000',
            'elements.*.pad'         => 'nullable|integer|min:0|max:12',

            'elements.*.symbology' => 'nullable|in:' . implode(',', [...array_keys(BarcodeImage::TYPES), \App\Domain\Studio\MarkingTrial::SYMBOLOGY]),
            'elements.*.font_size' => 'nullable|numeric|min:3|max:72',
            'elements.*.bold'      => 'nullable|boolean',
            'elements.*.align'     => 'nullable|in:left,center,right',
            'elements.*.rotation'  => 'nullable|integer|in:0,90,180,270',
            'elements.*.hrt'       => 'nullable|boolean',             // EAN-13 / Code 128: digits under the bars
            'rows'                 => 'nullable|array|max:' . self::MAX_LABELS,
            'rows.*'               => 'array|max:50',
            'rows.*.*'             => 'nullable|string|max:500',
        ]);


        $data['watermark'] = $request->has('watermark') ? $request->boolean('watermark') : true;
        $data['elements'] = $this->studioImages($data['elements']);

        $rows = $data['rows'] ?? [];
        if (!$rows) {
            $rows = array_fill(0, $data['copies'] ?? 1, []);
        }

        // real Asl Belgisi marks: free trial only, the full workflow is in /aslbelgi
        $trial = \App\Domain\Studio\MarkingTrial::class;
        $markingElements = collect($data['elements'])->filter(fn ($e) => ($e['symbology'] ?? null) === $trial::SYMBOLOGY);
        // a real mark only from a table column (the renderer enforces the same): typed text / generators are refused
        abort_if($markingElements->contains(fn ($e) => blank($e['source'] ?? null) || str_starts_with((string) $e['source'], '@')), 422, __('studio.marking_column_only'));
        // a marking code put into a plain DataMatrix / QR / Code 128 would skip the trial: refused
        $parser = app($trial);
        foreach (collect($data['elements'])->where('type', 'barcode')->reject(fn ($e) => ($e['symbology'] ?? null) === $trial::SYMBOLOGY) as $e) {
            $values = filled($e['source'] ?? null) && !str_starts_with((string) $e['source'], '@')
                ? collect($rows)->pluck($e['source'])->filter()->take(50)->all()
                : [(string) ($e['text'] ?? '')];
            foreach ($values as $v) {
                abort_if($parser->isCode((string) $v), 422, __('studio.marking_use_asl'));
            }
        }
        if ($markingElements->isNotEmpty()) {
            $rows = array_slice(array_values($rows), 0, $trial::LIMIT); // only the first 10 codes
            $codes = collect($rows)->flatMap(fn ($row) => $markingElements->map(fn ($e) => (string) ($row[$e['source'] ?? ''] ?? '')))->filter()->unique()->values()->all();
            abort_unless(app($trial)->allowToday($trial::visitorKey($request->ip()), $codes), 422, __('studio.marking_daily', ['limit' => $trial::LIMIT]));
        }

        // heavy requests are refused before any work (public page: protect the server)
        $barcodes = collect($data['elements'])->where('type', 'barcode')->count();
        $columns = max(1, (int) ($data['columns'] ?? 1));
        $pageWidth = $columns * $data['width_mm'] + ($columns - 1) * (float) ($data['gap_mm'] ?? 0);
        abort_if(count($rows) * $barcodes > self::MAX_BARCODES || $pageWidth > 320, 422, __('studio.too_heavy'));


        return [$data, $rows];
    }

    /** At most this many own images per design (each up to 1 MB). */
    public const MAX_IMAGES = 3;

    /**
     * Own images: validated, re-encoded and (unless unchecked) made black-and-white in memory only —
     * never written to disk or logged. An image element without an image draws nothing.
     */
    private function studioImages(array $elements): array
    {
        $images = collect($elements)->where('type', 'image');
        if ($images->count() > self::MAX_IMAGES) {
            throw \Illuminate\Validation\ValidationException::withMessages(['elements' => __('studio.image_too_many', ['max' => self::MAX_IMAGES])]);
        }
        foreach ($elements as $i => $el) {
            unset($elements[$i]['_img']);
            if ($el['type'] !== 'image' || blank($el['src'] ?? null)) {
                continue;
            }
            $image = \App\Domain\Printing\Services\LabelImage::fromStudio($el['src'], array_key_exists('bw', $el) && $el['bw'] !== null && !filter_var($el['bw'], FILTER_VALIDATE_BOOLEAN));
            if (!$image) {
                throw \Illuminate\Validation\ValidationException::withMessages(["elements.$i.src" => __('studio.image_invalid')]);
            }
            unset($elements[$i]['src']);
            $elements[$i]['_img'] = $image;
        }

        return $elements;
    }
}
