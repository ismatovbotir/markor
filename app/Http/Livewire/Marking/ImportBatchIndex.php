<?php

namespace App\Http\Livewire\Marking;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ImportBatchIndex extends Component
{
    use WithPagination, WithFileUploads;

    public bool $showForm    = false;
    public $csvFile          = null;
    public ?int $errorsFor   = null;

    /** product being named (not found in the classifier) */
    public ?int $namingId = null;
    public ?int $namingBatchId = null;
    public array $naming = ['name_uz' => '', 'name_ru' => '', 'sku' => ''];

    protected function rules(): array
    {
        return [
            // size, file name extension and real content (a binary renamed to .csv is rejected)
            'csvFile' => ['required', 'file', 'max:' . config('uploads.codes.max_kb'),
                'extensions:' . implode(',', config('uploads.codes.extensions')), new \App\Rules\PlainTextFile()],
        ];
    }

    /**
     * Called from the modal; the modal closes itself after the promo time when this returns true.
     * (Not named `upload`: $wire.upload() is Livewire's own file-upload function.)
     */
    /** Step 1 of the upload dialog: file to the server + batch record. Returns the batch id (0 on error). */
    public function queueUpload(ImportBatchService $service): int
    {
        $this->authorize('create', ImportBatch::class);
        $this->validate();

        try {
            $batch = $service->createFromUpload($this->csvFile, null, 'uploaded');
            $this->reset(['csvFile']);

            return $batch->id;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: __('common.error') . ': ' . $e->getMessage());
        }

        return 0;
    }

    /** Step 2: the dialog has closed — only now the batch is queued for loading into the database. */
    public function releaseUpload(int $batchId, ImportBatchService $service): void
    {
        $service->release($batchId);
        $this->showForm = false;
        $this->resetPage();
        $this->dispatch('notify', type: 'success', message: __('import.queued'));
    }

    /** Products of a batch that still need a name and that this user may name. */
    private function unnamedOf(int $batchId)
    {
        return collect($this->itemsOf([$batchId])[$batchId] ?? [])
            ->filter(fn ($p) => $p->needsName() && auth()->user()->can('name', $p))->values();
    }

    /** Products of each batch: [batch_id => [Product, ...]]. */
    private function itemsOf($batchIds): array
    {
        $pairs = \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')
            ->whereIn('import_batch_id', $batchIds)->whereNotNull('product_id')
            ->select('import_batch_id', 'product_id')->distinct()->get();
        $products = \App\Domain\Catalog\Models\Product::withoutGlobalScope('owner')->whereIn('id', $pairs->pluck('product_id')->unique())->get()->keyBy('id');

        return $pairs->groupBy('import_batch_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $products[$r->product_id] ?? null)->filter()->values())
            ->all();
    }

    public function openNaming(int $productId, ?int $batchId = null): void
    {
        $this->namingBatchId = $batchId;
        $product = \App\Domain\Catalog\Models\Product::findOrFail($productId);
        $this->authorize('name', $product);
        $this->resetValidation();
        $this->namingId = $product->id;
        $this->naming = ['name_uz' => '', 'name_ru' => '', 'sku' => ''];
    }

    public function saveNaming(): void
    {
        $product = \App\Domain\Catalog\Models\Product::findOrFail($this->namingId);
        $this->authorize('name', $product);

        $data = $this->validate([
            'naming.name_uz' => 'required_without:naming.name_ru|nullable|string|max:255',
            'naming.name_ru' => 'required_without:naming.name_uz|nullable|string|max:255',
            'naming.sku'     => 'nullable|string|max:100',
        ], [], ['naming.name_uz' => __('product_groups.name_uz'), 'naming.name_ru' => __('product_groups.name_ru')])['naming'];

        $product->update(array_map(fn ($v) => filled($v) ? trim($v) : null, $data));
        $this->namingId = null;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));

        // several unknown items in one batch: go straight to the next one
        if ($this->namingBatchId && ($next = $this->unnamedOf($this->namingBatchId)->first())) {
            $this->openNaming($next->id, $this->namingBatchId);
        }
    }

    public function toggleErrors(int $id): void
    {
        $this->errorsFor = $this->errorsFor === $id ? null : $id;
    }

    public function render(ImportBatchService $service)
    {
        $batches = $service->paginate();

        $monthStart = now()->startOfMonth();
        $month = \App\Domain\Marking\Models\ImportBatch::where('status', 'completed')->where('processed_at', '>=', $monthStart)
            ->selectRaw('coalesce(sum(processed_rows), 0) as codes, coalesce(sum(amount), 0) as amount, coalesce(sum(processed_rows * unit_price), 0) as gross')
            ->first();

        return view('livewire.marking.import-batch-index', [
            'batches'   => $batches,
            'month'     => $month,
            'lockedCount' => \App\Domain\Marking\Models\ImportBatch::where('is_locked', true)->count(),
            'shortfall' => auth()->user()->company ? \App\Domain\Billing\BalanceService::shortfall(auth()->user()->company->fresh()) : 0,
            'price'     => \App\Domain\Billing\Billing::unitPrice(),
            'discount'  => \App\Domain\Billing\Billing::discountFor(auth()->user()->company),
            'discountUntil' => \App\Domain\Billing\Billing::discountUntil(auth()->user()->company),
            // Refresh the list while something is still queued, loading or being checked.
            'polling'   => $batches->contains(fn ($b) => in_array($b->status, ['uploaded', 'pending', 'processing', 'checking'], true)),
            'items'     => $this->itemsOf($batches->pluck('id')),
            'offers'    => \App\Domain\Offers\Models\Offer::active()->orderBy('sort_order')->get(),
            'templates' => $templates = LabelTemplate::orderByDesc('is_default')->orderBy('name')->get(),
            'previews'  => $templates->mapWithKeys(fn ($t) => [$t->id => app(\App\Domain\Printing\Services\LabelPdfService::class)->previewDocument($t)]),
        ]);
    }
}
