<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.codes') }}</h1>
    </div>

    <div class="mb-4 flex gap-3 flex-wrap">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }} code/GTIN"
               class="w-72 border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
        <select wire:model.live="filterProduct" class="border border-stone-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
            <option value="">{{ __('nav.products') }}: {{ __('common.all') }}</option>
            @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->label }}</option>@endforeach
        </select>
        <select wire:model.live="filterStatus" class="border border-stone-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
            <option value="">{{ __('common.status') }}: {{ __('common.all') }}</option>
            @foreach($statuses as $s)<option value="{{ $s }}">{{ __("codes.status.$s") }}</option>@endforeach
        </select>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">GTIN</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">Serial</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.products') }}</th>
                    @can('admin')<th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('import.owner') }}</th>@endcan
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @php $statusColors = ['imported'=>'stone','assigned'=>'amber','printed'=>'sky','aggregated'=>'violet','shipped'=>'emerald','cancelled'=>'rose'] @endphp
                @forelse($codes as $code)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $code->gtin }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-stone-500">{{ $code->serial }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $code->product?->name ?? '—' }}</td>
                        @can('admin')<td class="px-4 py-3 text-stone-600 text-xs" title="{{ $code->owner?->email }}">{{ $code->owner?->name ?? '—' }}</td>@endcan
                        <td class="px-4 py-3">
                            @php $c = $statusColors[$code->status] ?? 'stone' @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                                {{ __("codes.status.$code->status") }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-stone-500 text-xs">{{ $code->created_at->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $codes->links() }}</div>
</div>
