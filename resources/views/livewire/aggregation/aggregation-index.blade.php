<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.aggregation') }}</h1>
        @can('create', \App\Domain\Aggregation\Models\Aggregation::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('aggregation.open') }}
            </button>
        @endcan
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">SSCC</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.products') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('aggregation.level') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('aggregation.quantity') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($aggregations as $agg)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $agg->sscc }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $agg->product?->name_ru ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $agg->level }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $agg->isFull() ? 'text-emerald-600 font-medium' : 'text-stone-600' }}">
                                {{ $agg->actual_quantity }} / {{ $agg->expected_quantity }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php $c = match($agg->status) { 'open'=>'amber','closed'=>'emerald', default=>'stone' } @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                                {{ __("aggregation.status.$agg->status") }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($agg->status === 'open')
                                @can('update', $agg)
                                    <button wire:click="close({{ $agg->id }})" wire:confirm="{{ __('aggregation.confirm_close') }}"
                                            class="text-xs text-stone-400 hover:text-emerald-600">{{ __('aggregation.close') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $aggregations->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ __('aggregation.open') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('nav.products') }}</label>
                        <select wire:model="product_id" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            <option value="">—</option>
                            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name_ru }}</option>@endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('aggregation.expected') }}</label>
                            <input wire:model="expected_quantity" type="number" min="1" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                            @error('expected_quantity') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('aggregation.level') }}</label>
                            <select wire:model="level" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="1">1 — КМ</option>
                                <option value="2">2 — SSCC группа</option>
                                <option value="3">3 — Паллета</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('aggregation.open') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
