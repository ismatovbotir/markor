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
    public ?string $lookupMessage = null;

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
        $this->authorize('editOwn', $product);
        // a client sees and edits its own names (company copy); the super admin the shared product
        $shown = auth()->user()->isAdmin() ? $product : $product->forCompany(auth()->user()->company_id);
        $this->fill($shown->only(['product_group_id', 'gtin', 'name_uz', 'name_ru', 'sku', 'barcode_ean', 'is_active']));
        $this->editingId = $id;
        $this->lookupMessage = null;
        $this->showForm  = true;
    }

    /** Fills the name from the national classifier (tasnif) — clean Latin Uzbek + Russian when AI is available. */
    public function fetchFromTasnif(\App\Domain\Catalog\Services\GtinLookup $lookup, \App\Domain\Ai\ProductNamer $namer): void
    {
        $gtin = $this->editingId ? Product::findOrFail($this->editingId)->gtin : preg_replace('/\D/', '', $this->gtin);
        if (!preg_match('/^\d{13,14}$/', (string) $gtin)) {
            $this->lookupMessage = __('products.tasnif_bad_gtin');
            return;
        }
        if (!\Illuminate\Support\Facades\RateLimiter::attempt('tasnif:' . auth()->id(), 20, fn () => true, 60)) {
            $this->lookupMessage = __('auth.throttle', ['seconds' => 60]);
            return;
        }
        $item = $lookup->find(str_pad($gtin, 14, '0', STR_PAD_LEFT));
        if ($item === false) {
            $this->lookupMessage = __('products.tasnif_error');
            return;
        }
        if (!$item || blank($item['name'] ?? null)) {
            $this->lookupMessage = __('products.tasnif_not_found');
            return;
        }
        $name = $namer->suggest($item['name'], $gtin);
        $this->name_uz = mb_substr($name['name_uz'], 0, 255);
        if ($name['name_ru']) {
            $this->name_ru = mb_substr($name['name_ru'], 0, 255);
        }
        $this->lookupMessage = '✓ ' . __('products.tasnif_filled');
    }

    public function save(ProductService $service): void
    {
        $product = $this->editingId ? Product::findOrFail($this->editingId) : null;

        // a client: only its own name / SKU for this product; the shared product (and the GTIN) never change
        if ($product && !auth()->user()->isAdmin()) {
            $this->authorize('editOwn', $product);
            $data = $this->validate(['name_uz' => 'nullable|string|max:255', 'name_ru' => 'nullable|string|max:255', 'sku' => 'nullable|string|max:100']);
            \App\Domain\Catalog\Models\CompanyProductName::updateOrCreate(
                ['company_id' => auth()->user()->company_id, 'product_id' => $product->id],
                array_map(fn ($v) => filled($v) ? trim($v) : null, $data) + ['named_by' => auth()->id()]
            );
            \Illuminate\Support\Once::flush();
            $this->showForm = false;
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            return;
        }

        $this->authorize($product ? 'update' : 'create', $product ?? Product::class);
        if ($product) {
            $this->gtin = $product->gtin; // the GTIN is the product's identity: never changed by editing
        }

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

        // a client sees its own names over the shared ones
        if (!auth()->user()->isAdmin()) {
            $products->setCollection($products->getCollection()->map(fn ($p) => $p->forCompany(auth()->user()->company_id)));
        }

        return view('livewire.catalog.product-index', [
            'products' => $products,
            'owners'   => auth()->user()->isAdmin() ? Product::ownersOf($products->pluck('id')) : [],
            'groups'   => ProductGroup::active()->get(['id', 'name_ru', 'name_uz']),
        ]);
    }
}
