<?php

namespace App\Http\Livewire\Catalog;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductService;
use App\Domain\Printing\Models\LabelTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Products of a company (never shared, GTIN unique per company — docs/spec/client-products.md).
 *  - a client: one product card (name, GTIN/EAN, SKU, description, weight/volume, manufacturer, country,
 *    shelf life), an internal EAN-13 generator, and one label per product (no marking code);
 *  - the super admin: every company's products with the owner, and GTIN analytics — read-only.
 */
#[Layout('layouts.app')]
class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    /** Super admin: 'products' list / 'gtin' analytics; company filter. */
    #[\Livewire\Attributes\Url(as: 'tab', except: 'products')]
    public string $tab = 'products';
    public ?int $filterCompany = null;

    /** The card being edited. */
    public array $card = [];
    public ?string $lookupMessage = null;

    /** Product whose label is being printed (template picker). */
    public ?int $printingId = null;

    private const EMPTY = ['gtin' => '', 'name' => '', 'description' => '', 'sku' => '', 'weight' => '', 'manufacturer' => '', 'country' => '', 'shelf_life' => '', 'is_active' => true];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterCompany(): void { $this->resetPage(); }
    public function updatingTab(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->authorize('create', Product::class);
        $this->resetValidation();
        $this->card = self::EMPTY;
        $this->editingId = null;
        $this->lookupMessage = null;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);
        $this->resetValidation();
        $this->card = ['gtin' => $product->ean13(), 'name' => $product->needsName() ? '' : (string) $product->name]
            + collect($product->only(['description', 'sku', 'weight', 'manufacturer', 'country', 'shelf_life']))->map(fn ($v) => (string) $v)->all()
            + ['is_active' => (bool) $product->is_active];
        $this->editingId = $id;
        $this->lookupMessage = null;
        $this->showForm = true;
    }

    /** No barcode? An internal EAN-13 of this company (20 + company + item number + check digit). */
    public function generateGtin(): void
    {
        $this->authorize('create', Product::class);
        abort_if($this->editingId, 403); // the GTIN of an existing product never changes
        $companyId = auth()->user()->company_id;
        try {
            $next = \Illuminate\Support\Facades\DB::transaction(function () use ($companyId) {
                \App\Domain\Companies\Company::lockForUpdate()->find($companyId);

                return Product::nextInternalGtin($companyId);
            });
        } catch (\DomainException $e) {
            $this->addError('card.gtin', __($e->getMessage()));
            return;
        }
        $this->card['gtin'] = substr($next['gtin'], 1); // shown as EAN-13
        $this->lookupMessage = __('products.gtin_internal_hint');
    }

    /** Fills the name from the national classifier (tasnif). */
    public function fetchFromTasnif(\App\Domain\Catalog\Services\GtinLookup $lookup, \App\Domain\Ai\ProductNamer $namer): void
    {
        $gtin = preg_replace('/\D/', '', (string) ($this->card['gtin'] ?? ''));
        if (!preg_match('/^\d{13,14}$/', $gtin)) {
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
        $this->card['name'] = mb_substr($namer->suggest($item['name'], $gtin)['name_uz'], 0, 255);
        $this->lookupMessage = '✓ ' . __('products.tasnif_filled');
    }

    public function save(ProductService $service): void
    {
        $product = $this->editingId ? Product::findOrFail($this->editingId) : null;
        $this->authorize($product ? 'update' : 'create', $product ?? Product::class);
        $companyId = auth()->user()->company_id;

        $this->card['gtin'] = preg_replace('/\D/', '', (string) ($this->card['gtin'] ?? ''));
        $gtin14 = str_pad($this->card['gtin'], 14, '0', STR_PAD_LEFT);
        $data = $this->validate([
            'card.gtin'         => $product ? ['nullable'] : ['required', 'regex:/^\d{8,14}$/', function ($attr, $value, $fail) use ($gtin14) {
                if (Product::checkDigit(substr($gtin14, 0, 13)) !== (int) substr($gtin14, -1)) {
                    $fail(__('products.gtin_bad_check'));
                }
            }],
            'card.name'         => 'required|string|max:255',
            'card.description'  => 'nullable|string|max:2000',
            'card.sku'          => 'nullable|string|max:100',
            'card.weight'       => 'nullable|string|max:50',
            'card.manufacturer' => 'nullable|string|max:255',
            'card.country'      => 'nullable|string|max:100',
            'card.shelf_life'   => 'nullable|string|max:50',
            'card.is_active'    => 'boolean',
        ], [], ['card.gtin' => 'GTIN / EAN', 'card.name' => __('common.name')])['card'];

        $values = collect($data)->except('gtin')->map(fn ($v) => is_string($v) ? (trim($v) !== '' ? trim($v) : null) : $v)->all();

        if ($product) {
            $service->update($product, $values); // the GTIN is the product's identity: never changed by editing
        } else {
            $existing = Product::withTrashed()->where('company_id', $companyId)->where('gtin', $gtin14)->first();
            if ($existing && !$existing->trashed()) {
                $this->addError('card.gtin', __('products.gtin_taken'));
                return;
            }
            if ($existing) { // deleted earlier: the GTIN comes back with the new card
                $existing->restore();
                $service->update($existing, $values);
                $this->showForm = false;
                $this->dispatch('notify', type: 'success', message: __('common.saved'));
                return;
            }
            $internal = str_starts_with($gtin14, '02') ? ['gtin_seq' => (int) substr($gtin14, 7, 6)] : [];
            $service->store($values + $internal + ['company_id' => $companyId, 'gtin' => $gtin14, 'lookup_status' => null]);
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

    public function openPrint(int $id): void
    {
        $this->authorize('printLabel', Product::findOrFail($id));
        $this->printingId = $id;
    }

    public function render(ProductService $service)
    {
        $admin = auth()->user()->isAdmin();

        return view('livewire.catalog.product-index', [
            'admin'     => $admin,
            'products'  => $this->tab === 'gtin' && $admin ? null : $service->paginate($this->search, $admin ? $this->filterCompany : null),
            'analytics' => $this->tab === 'gtin' && $admin ? $service->gtinAnalytics($this->search) : null,
            'companies' => $admin ? \App\Domain\Companies\Company::orderBy('name')->get(['id', 'name']) : collect(),
            'printing'  => $this->printingId ? Product::find($this->printingId) : null,
            'templates' => $this->printingId ? LabelTemplate::forCompany(auth()->user()->company)->get(['id', 'name', 'width_mm', 'height_mm', 'is_default']) : collect(),
        ]);
    }
}
