<?php

namespace App\Http\Livewire\Marking;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Marking\Support\MarkingSystem;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reads the DataMatrix codes from a client's existing PDF in the browser (the PDF is never
 * uploaded). The codes read go straight into a normal batch ("Import"), or into a CSV file.
 * Only the category structures are sent to the page, to recognise which strings are marking codes.
 */
#[Layout('layouts.app')]
class PdfCodeReader extends Component
{
    /** At most this many codes in one import (the CSV import's own size: 10 000 long codes). */
    public const MAX_CODES = 10000;

    public function mount(): void
    {
        $this->authorize('create', ImportBatch::class);
    }

    /**
     * The codes read become a batch exactly like an uploaded CSV (one code per line): same queue,
     * system detection, billing and duplicate checks. $name = the PDF file name(s), shown in the list.
     * Returns '' on success (the page goes to the import list) or the error to show.
     */
    public function import(array $codes, string $name, ImportBatchService $service): string
    {
        $this->authorize('create', ImportBatch::class);

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => is_string($c) ? trim(str_replace(["\r", "\n", "\x1D", "\x1E"], '', $c)) : '', $codes,
        ), fn ($c) => $c !== '' && mb_strlen($c) <= 200)));
        if (!$codes) {
            return __('pdfreader.import_empty');
        }
        if (count($codes) > self::MAX_CODES) {
            return __('pdfreader.import_too_many', ['max' => number_format(self::MAX_CODES, 0, '.', ' ')]);
        }

        $name = mb_substr(trim(basename(str_replace('\\', '/', $name))), 0, 200) ?: 'codes.pdf';
        // the same PDF is never loaded twice by one company (like a CSV file name)
        if ($duplicate = ImportBatch::loadedWithName(auth()->user()->company_id, $name)) {
            return $duplicate->duplicateMessage();
        }

        try {
            $batch = $service->createFromContents(auth()->id(), $name, implode("\n", $codes) . "\n", 'web');
            $batch->update(['origin' => 'pdf']);
        } catch (\Throwable $e) {
            report($e);

            return __('import.upload_failed');
        }

        session()->flash('notify', __('import.queued'));
        $this->redirectRoute('import-batches.index');

        return '';
    }

    public function render()
    {
        return view('livewire.marking.pdf-code-reader', [
            'groups'   => ProductGroup::forBrowser(),
            'systems'  => MarkingSystem::options(),
            'fallback' => auth()->user()->company?->marking_system, // null: no history yet (then the GTIN prefix)
            'max'      => self::MAX_CODES,
        ]);
    }
}
