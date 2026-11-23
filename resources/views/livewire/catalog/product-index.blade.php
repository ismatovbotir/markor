@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div>
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('nav.products') }}</h1>
        @can('create', \App\Domain\Catalog\Models\Product::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">+ {{ __('products.new') }}</button>
        @endcan
    </div>

    @if($admin)
        {{-- super admin: read-only list of every company's products, and GTIN analytics --}}
        <div class="flex gap-2 mb-4 text-sm" role="tablist">
            @foreach(['products' => __('products.tab_products'), 'gtin' => __('products.tab_gtin')] as $key => $label)
                <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        class="px-3 py-1.5 rounded-full border {{ $tab === $key ? 'bg-amber-100 border-amber-300 text-amber-900' : 'bg-white border-stone-200 text-stone-600 hover:bg-stone-100' }}">{{ $label }}</button>
            @endforeach
        </div>
    @endif

    <div class="mb-4 flex gap-3 flex-wrap">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}" aria-label="{{ __('common.search') }}"
               class="w-72 max-w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
        @if($admin && $tab === 'products')
            <select wire:model.live="filterCompany" aria-label="{{ __('products.company') }}" class="border border-stone-200 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">{{ __('products.all_companies') }}</option>
                @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        @endif
    </div>

    @if($analytics)
        <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 border-b border-stone-200 text-stone-600"><tr>
                    <th class="text-left px-4 py-3 font-medium">GTIN</th>
                    <th class="text-left px-4 py-3 font-medium">{{ __('common.name') }}</th>
                    <th class="text-right px-4 py-3 font-medium">{{ __('products.companies') }}</th>
                    <th class="text-right px-4 py-3 font-medium">{{ __('nav.codes') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse($analytics as $row)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->gtin }}</td>
                            <td class="px-4 py-3 text-stone-600">{{ $row->sample_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->companies }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row->codes, 0, '.', ' ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $analytics->links() }}</div>
    @else
        <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 border-b border-stone-200 text-stone-600"><tr>
                    <th class="text-left px-4 py-3 font-medium">GTIN / EAN</th>
                    <th class="text-left px-4 py-3 font-medium">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium">SKU</th>
                    @if($admin)<th class="text-left px-4 py-3 font-medium">{{ __('products.company') }}</th>@endif
                    <th class="text-right px-4 py-3 font-medium">{{ __('nav.codes') }}</th>
                    <th class="px-4 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-stone-50" wire:key="product-{{ $product->id }}">
                            <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                {{ $product->ean13() }}
                                @if($product->hasInternalGtin())<span class="ml-1 font-sans text-[10px] px-1.5 py-0.5 rounded bg-stone-100 text-stone-600" title="{{ __('products.gtin_internal_hint') }}">{{ __('products.gtin_internal') }}</span>@endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $product->name ?? '—' }}</div>
                                @if($product->description)<div class="text-xs text-stone-500 line-clamp-1">{{ $product->description }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-stone-500">{{ $product->sku ?? '—' }}</td>
                            @if($admin)<td class="px-4 py-3 text-stone-600">{{ $product->company?->name ?? '—' }}</td>@endif
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($product->marking_codes_count, 0, '.', ' ') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                @can('printLabel', $product)
                                    <button wire:click="openPrint({{ $product->id }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-700 text-white rounded-lg text-xs font-medium hover:bg-amber-800">
                                        @svg('heroicon-o-printer', 'w-4 h-4') {{ __('products.print_label') }}
                                    </button>
                                @endcan
                                @can('update', $product)
                                    <button wire:click="openEdit({{ $product->id }})" class="px-2 py-1.5 text-xs text-stone-600 hover:text-amber-700">{{ __('common.edit') }}</button>
                                @endcan
                                @can('delete', $product)
                                    <button wire:click="delete({{ $product->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="px-2 py-1.5 text-xs text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $admin ? 6 : 5 }}" class="px-4 py-8 text-center text-stone-400">{{ $admin ? __('common.no_data') : __('products.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    @endif

    {{-- product card --}}
    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4 overflow-y-auto py-6" wire:keydown.escape="$set('showForm', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('common.edit') : __('products.new') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label for="card-gtin" class="block text-xs font-medium text-stone-600 mb-1">GTIN / EAN</label>
                        <div class="flex gap-2 flex-wrap">
                            {{-- the GTIN is the product's identity: read-only once the product exists --}}
                            <input id="card-gtin" wire:model="card.gtin" type="text" inputmode="numeric" maxlength="14" @readonly($editingId)
                                   class="flex-1 min-w-40 {{ $field }} font-mono {{ $editingId ? 'bg-stone-100 text-stone-500 cursor-not-allowed' : '' }}">
                            @unless($editingId)
                                <button type="button" wire:click="generateGtin" class="px-3 py-2 border border-stone-300 rounded-lg text-xs font-medium hover:bg-stone-100 whitespace-nowrap">{{ __('products.gtin_generate') }}</button>
                            @endunless
                            <button type="button" wire:click="fetchFromTasnif" wire:loading.attr="disabled" wire:target="fetchFromTasnif" class="inline-flex items-center gap-1.5 px-3 py-2 border border-stone-300 rounded-lg text-xs font-medium hover:bg-stone-100 whitespace-nowrap disabled:opacity-50">
                                <span wire:loading.remove wire:target="fetchFromTasnif">@svg('heroicon-o-arrow-down-tray', 'w-4 h-4')</span>
                                <span wire:loading wire:target="fetchFromTasnif">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                                {{ __('products.tasnif_fetch') }}
                            </button>
                        </div>
                        @if($lookupMessage)<p class="text-xs mt-1 {{ str_starts_with($lookupMessage, '✓') ? 'text-emerald-700' : 'text-amber-700' }}">{{ $lookupMessage }}</p>@endif
                        @error('card.gtin') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="card-name" class="block text-xs font-medium text-stone-600 mb-1">{{ __('common.name') }}</label>
                        <input id="card-name" wire:model="card.name" type="text" maxlength="255" class="{{ $field }}">
                        @error('card.name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="card-description" class="block text-xs font-medium text-stone-600 mb-1">{{ __('products.field.description') }}</label>
                        <textarea id="card-description" wire:model="card.description" rows="3" maxlength="2000" class="{{ $field }}"></textarea>
                        @error('card.description') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid sm:grid-cols-3 gap-4">
                        @foreach(['sku' => 'SKU', 'weight' => __('products.field.weight'), 'shelf_life' => __('products.field.shelf_life'),
                                  'manufacturer' => __('products.field.manufacturer'), 'country' => __('products.field.country')] as $key => $label)
                            <div class="{{ $key === 'manufacturer' ? 'sm:col-span-2' : '' }}">
                                <label for="card-{{ $key }}" class="block text-xs font-medium text-stone-600 mb-1">{{ $label }}</label>
                                <input id="card-{{ $key }}" wire:model="card.{{ $key }}" type="text" class="{{ $field }}"
                                       placeholder="{{ ['weight' => '0.5 kg', 'shelf_life' => '24 oy', 'country' => "O'zbekiston"][$key] ?? '' }}">
                                @error("card.$key") <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="card.is_active" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('common.active') }}
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- one label of the product: pick a template, then print or PDF; copies are set in the print dialog --}}
    @if($printing)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" wire:keydown.escape="$set('printingId', null)"
             x-data="{ templateId: @js(($templates->firstWhere('is_default', true) ?? $templates->first())?->id) }">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ $printing->name }}</h2>
                    <p class="text-sm text-stone-500 font-mono">{{ $printing->ean13() }}</p>
                </div>
                <label class="block text-xs font-medium text-stone-600">{{ __('import.choose_design') }}
                    <select x-model.number="templateId" class="{{ $field }} mt-1">
                        @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->width_mm }}×{{ $t->height_mm }} mm)</option>@endforeach
                    </select>
                </label>
                <p class="text-xs text-stone-500">{{ __('products.print_hint') }}</p>
                <div class="flex justify-end gap-2 flex-wrap">
                    <button type="button" wire:click="$set('printingId', null)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                    <a :href="@js(route('products.label', $printing)) + '?template=' + templateId" target="_blank" rel="noopener"
                       class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">PDF</a>
                    <a :href="@js(route('products.label.print', $printing)) + '?template=' + templateId" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">@svg('heroicon-o-printer', 'w-4 h-4') {{ __('products.print_label') }}</a>
                </div>
            </div>
        </div>
    @endif
</div>
