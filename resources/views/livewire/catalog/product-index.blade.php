<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.products') }}</h1>
        @can('create', \App\Domain\Catalog\Models\Product::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('common.add') }}
            </button>
        @endcan
    </div>

    <div class="mb-4 flex gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}"
               class="w-72 border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
        <select wire:model.live="filterGroup" class="border border-stone-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
            <option value="">{{ __('product_groups.all') }}</option>
            @foreach($groups as $g)
                <option value="{{ $g->id }}">{{ $g->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">GTIN</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.product_groups') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">SKU</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('nav.codes') }}</th>
                    @can('admin')<th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('products.owners') }}</th>@endcan
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($products as $product)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $product->gtin }}</td>
                        <td class="px-4 py-3 font-medium">{{ $product->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $product->productGroup?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $product->sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($product->marking_codes_count, 0, '.', ' ') }}</td>
                        @can('admin')
                            <td class="px-4 py-3 text-xs text-stone-600">
                                @forelse($owners[$product->id] ?? [] as $owner)
                                    <div title="{{ $owner['user']->email }}">{{ $owner['user']->name }} <span class="text-stone-400 tabular-nums">· {{ number_format($owner['codes'], 0, '.', ' ') }}</span></div>
                                @empty — @endforelse
                            </td>
                        @endcan
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $product->is_active ? __('common.active') : __('common.inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('editOwn', $product)
                                <button wire:click="openEdit({{ $product->id }})" class="text-stone-400 hover:text-amber-700 text-xs">{{ __('common.edit') }}</button>
                            @endcan
                            @can('delete', $product)
                                <button wire:click="delete({{ $product->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-stone-400 hover:text-rose-600 text-xs">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('common.edit') : __('common.add') }} {{ __('nav.products') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('nav.product_groups') }}</label>
                        <select wire:model="product_group_id" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            <option value="">—</option>
                            @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                        </select>
                        @error('product_group_id') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">GTIN (14)</label>
                        <div class="flex gap-2">
                            {{-- the GTIN is the product's identity: read-only once the product exists --}}
                            <input wire:model="gtin" type="text" maxlength="14" @readonly($editingId) class="flex-1 border border-stone-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 {{ $editingId ? 'bg-stone-100 text-stone-500 cursor-not-allowed' : '' }}">
                            <button type="button" wire:click="fetchFromTasnif" wire:loading.attr="disabled" wire:target="fetchFromTasnif" class="inline-flex items-center gap-1.5 px-3 py-2 border border-stone-300 rounded-lg text-xs font-medium hover:bg-stone-100 whitespace-nowrap disabled:opacity-50">
                                <span wire:loading.remove wire:target="fetchFromTasnif">@svg('heroicon-o-arrow-down-tray', 'w-4 h-4')</span>
                                <span wire:loading wire:target="fetchFromTasnif">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                                {{ __('products.tasnif_fetch') }}
                            </button>
                        </div>
                        @if($lookupMessage)<p class="text-xs mt-1 {{ str_starts_with($lookupMessage, '✓') ? 'text-emerald-700' : 'text-amber-700' }}">{{ $lookupMessage }}</p>@endif
                        @error('gtin') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @cannot('admin')<p class="text-[11px] text-stone-500 mt-1">{{ __('products.own_names_hint') }}</p>@endcannot
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('product_groups.name_ru') }}</label>
                            <input wire:model="name_ru" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            @error('name_ru') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('product_groups.name_uz') }}</label>
                            <input wire:model="name_uz" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">SKU</label>
                            <input wire:model="sku" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">EAN</label>
                            <input wire:model="barcode_ean" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="is_active" type="checkbox" class="rounded border-stone-300 text-amber-600">
                        {{ __('common.active') }}
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
