<?php

namespace App\Http\Livewire\Marking;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Services\MarkingCodeService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MarkingCodeIndex extends Component
{
    use WithPagination;

    public string $search      = '';
    public ?int $filterProduct = null;
    public string $filterStatus = '';

    public function updatingSearch(): void      { $this->resetPage(); }
    public function updatingFilterProduct(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }

    public function render(MarkingCodeService $service)
    {
        return view('livewire.marking.marking-code-index', [
            'codes'    => $service->paginate([
                'search'     => $this->search,
                'product_id' => $this->filterProduct,
                'status'     => $this->filterStatus,
            ]),
            'products' => Product::active()->get(['id', 'gtin', 'name_ru', 'name_uz']),
            'statuses' => ['imported', 'assigned', 'printed', 'aggregated', 'shipped', 'cancelled'],
        ]);
    }
}
