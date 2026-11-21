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
