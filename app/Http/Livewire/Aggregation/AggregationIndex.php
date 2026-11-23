<?php

namespace App\Http\Livewire\Aggregation;

use App\Domain\Aggregation\Models\Aggregation;
use App\Domain\Aggregation\Services\AggregationService;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AggregationIndex extends Component
{
    use WithPagination;

    public bool $showForm      = false;
    public ?int $editingId     = null;
    public ?int $product_id    = null;
    public int $expected_quantity = 0;
    public int $level          = 1;
    public string $sscc_source = 'generated';

    protected function rules(): array
    {
        return [
            'product_id'        => 'nullable|exists:products,id',
            'expected_quantity' => 'required|integer|min:1',
            'level'             => 'required|in:1,2,3',
            'sscc_source'       => 'required|in:imported_pool,generated',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['product_id', 'expected_quantity', 'editingId']);
        $this->level       = 1;
        $this->sscc_source = 'generated';
        $this->showForm    = true;
    }

    public function save(AggregationService $service): void
    {
        $this->authorize('create', Aggregation::class);
        $data = $this->validate();

        $data['sscc'] = match ($data['sscc_source']) {
            'generated' => '00' . str_pad((string) random_int(0, 9999999999999999999), 18, '0', STR_PAD_LEFT),
            default     => '00' . Str::random(16),
        };

        $service->open($data);

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('aggregation.opened'));
    }

    public function close(int $id, AggregationService $service): void
    {
        $aggregation = Aggregation::findOrFail($id);
        $this->authorize('update', $aggregation);
        $service->close($aggregation);
        $this->dispatch('notify', type: 'success', message: __('aggregation.closed'));
    }

    public function render(AggregationService $service)
    {
        return view('livewire.aggregation.aggregation-index', [
            'aggregations' => $service->paginate(),
            'products'     => Product::active()->get(['id', 'gtin', 'name', 'name_ru', 'name_uz']),
        ]);
    }
}
