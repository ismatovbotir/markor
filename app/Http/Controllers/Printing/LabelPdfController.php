<?php

namespace App\Http\Controllers\Printing;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class LabelPdfController extends Controller
{
    /** All labels of one import batch as a single PDF, one label per page. */
    public function batch(Request $request, ImportBatch $batch, LabelPdfService $service)
    {
        Gate::authorize('view', $batch);

        $template = $request->filled('template')
            ? LabelTemplate::findOrFail($request->integer('template'))
            : LabelTemplate::default();

        abort_unless($batch->status === 'completed' && $batch->processed_rows > 0, 404);
        abort_if($batch->is_locked, 402, __('balance.locked_batch'));

        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $store = app(\App\Domain\Printing\Services\LabelPdfStore::class);
        [$record, $pdf] = $store->get($batch, $template, $request->user(), max(1, $request->integer('part', 1)));
        $store->markDownloaded($record);
        $name = pathinfo($batch->original_filename, PATHINFO_FILENAME) . ($record->parts > 1 ? "_part{$record->part}of{$record->parts}" : '');

        return $this->inline($pdf, $name);
    }

    /**
     * One part of a batch on the browser print page (same template, parts and rules as the PDF):
     * the system print dialog opens by itself; set the label size as paper size in the printer driver.
     */
    public function print(Request $request, ImportBatch $batch, LabelPdfService $service)
    {
        Gate::authorize('view', $batch);
        abort_unless($batch->status === 'completed' && $batch->processed_rows > 0, 404);
        abort_if($batch->is_locked, 402, __('balance.locked_batch'));
        $template = $request->filled('template') ? LabelTemplate::findOrFail($request->integer('template')) : LabelTemplate::default();

        set_time_limit(0);
        ini_set('memory_limit', '1G');
        $store = app(\App\Domain\Printing\Services\LabelPdfStore::class);
        $parts = $store->parts($batch, $template);
        $part = $parts[max(1, min($request->integer('part', 1), count($parts))) - 1];
        $codes = $store->partCodes($batch, $part);
        [$css, $labels] = $service->labelsHtml($template, $codes, pathinfo($batch->original_filename, PATHINFO_FILENAME));
        $store->markPrinted($codes);

        $columns = max(1, (int) $template->columns);
        $gap = (float) $template->gap_mm;

        return response()->view('studio.print', [
            'pages'  => array_chunk($labels, $columns),
            'css'    => $css,
            'width'  => (float) $template->width_mm,
            'height' => (float) $template->height_mm,
            'gap'    => $gap,
            'pageW'  => $columns * $template->width_mm + ($columns - 1) * $gap,
            'from'   => $part['first'],
            'to'     => $part['last'],
            'total'  => array_sum(array_column($parts, 'labels')),
        ]);
    }

    /** How a batch is split into PDF parts for a design (for the download dialog). */
    public function parts(Request $request, ImportBatch $batch)
    {
        Gate::authorize('view', $batch);
        abort_if($batch->is_locked, 402, __('balance.locked_batch'));
        $template = $request->filled('template') ? LabelTemplate::findOrFail($request->integer('template')) : LabelTemplate::default();

        return response()->json(app(\App\Domain\Printing\Services\LabelPdfStore::class)->parts($batch, $template));
    }

    /** One sample label, to check a template before printing a whole batch. */
    public function preview(LabelTemplate $template, LabelPdfService $service)
    {
        return $this->inline($service->pdf($template, [$service->demoCode()], 'preview'), 'preview-' . $template->id);
    }

    private function inline(string $pdf, string $name)
    {
        $ascii = Str::slug($name, '_') ?: 'labels';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$ascii}.pdf\"; filename*=UTF-8''" . rawurlencode($name) . '.pdf',
        ]);
    }
}
