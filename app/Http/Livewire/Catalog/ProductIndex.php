<?php

namespace App\Http\Livewire\Catalog;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Catalog\Services\ProductService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ProductIndex extends Component
{
    use WithPagination;

    public string $search    = '';
    public ?int $filterGroup = null;
    public bool $showForm    = false;
    public ?int $editingId   = null;

    public ?int $product_group_id = null;
    public string $gtin     = '';
    public ?string $name_uz  = '';
    public ?string $name_ru  = '';
    public ?string $sku      = '';
    public ?string $barcode_ean = '';
    public bool $is_active  = true;

    protected function rules(): array
    {
        $unique = \Illuminate\Validation\Rule::unique('products', 'gtin')->ignore($this->editingId);
        return [
            'product_group_id' => 'nullable|exists:product_groups,id',
            'gtin'             => ['required', 'digits:14', $unique],
            'name_uz'          => 'nullable|string|max:255',
            'name_ru'          => 'nullable|string|max:255',
            'sku'              => 'nullable|string|max:100',
            'barcode_ean'      => 'nullable|digits_between:8,13',
            'is_active'        => 'boolean',
        ];
    }

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingFilterGroup(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['gtin', 'name_uz', 'name_ru', 'sku', 'barcode_ean', 'editingId']);
        $this->is_active       = true;
        $this->product_group_id = null;
        $this->showForm        = true;
    }

    public function openEdit(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->fill($product->only(['product_group_id', 'gtin', 'name_uz', 'name_ru', 'sku', 'barcode_ean', 'is_active']));
        $this->editingId = $id;
        $this->showForm  = true;
    }

    public function save(ProductService $service): void
    {
        $product = $this->editingId ? Product::findOrFail($this->editingId) : null;
        $this->authorize($product ? 'update' : 'create', $product ?? Product::class);

        $this->product_group_id = $this->product_group_id ?: null;
        $data = $this->validate();

        if ($product) {
            $service->update($product, $data);
        } else {
            $service->store($data);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function delete(int $id, ProductService $service): void
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $service->destroy($product);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(ProductService $service)
    {
        $products = $service->paginate($this->search, $this->filterGroup);

        return view('livewire.catalog.product-index', [
            'products' => $products,
            'owners'   => auth()->user()->isAdmin() ? Product::ownersOf($products->pluck('id')) : [],
            'groups'   => ProductGroup::active()->get(['id', 'name_ru', 'name_uz']),
        ]);
    }
}
