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
            $png = $data['type'] === \App\Domain\Studio\MarkingTrial::SYMBOLOGY
                ? base64_decode(substr(app(\App\Domain\Studio\MarkingTrial::class)->png($data['data']), strlen('data:image/png;base64,')))
                : $barcode->png($data['type'], $data['data'], 6, (bool) ($data['hrt'] ?? false));
        } catch (\Throwable) {
            abort(422);
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function generate(Request $request, LabelPdfService $pdf, BarcodeImage $barcode)
    {
        // errors always as JSON (422), never a redirect: the browser would save that HTML page as labels.pdf
        $request->headers->set('Accept', 'application/json');

        $data = $request->validate([
            'width_mm'             => 'required|integer|min:10|max:300',
            'height_mm'            => 'required|integer|min:10|max:300',
            'columns'              => 'nullable|integer|min:1|max:10',
            'gap_mm'               => 'nullable|numeric|min:0|max:30',
            'copies'               => 'nullable|integer|min:1|max:' . self::MAX_LABELS,
            'elements'             => 'required|array|min:1|max:40',
            'elements.*.type'      => 'required|in:text,barcode',
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

        $rows = $data['rows'] ?? [];
        if (!$rows) {
            $rows = array_fill(0, $data['copies'] ?? 1, []);
        }

        // real Asl Belgisi marks: free trial only, the full workflow is in /aslbelgi
        $trial = \App\Domain\Studio\MarkingTrial::class;
        if (collect($data['elements'])->contains(fn ($e) => ($e['symbology'] ?? null) === $trial::SYMBOLOGY)) {
            $rows = array_slice(array_values($rows), 0, $trial::LIMIT); // only the first 10 codes
        }

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
}
