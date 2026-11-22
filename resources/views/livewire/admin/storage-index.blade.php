@php use App\Domain\Reporting\CodeStorage; $n = fn ($v) => number_format((int) $v, 0, '.', ' '); $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : '—'; @endphp
<div>
    <x-admin.page-header :crumb="__('nav.group.clients')" :title="__('storage.title')" :hint="__('storage.hint')" />

    {{-- whole system --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        @foreach([
            [__('storage.sum.db'), CodeStorage::bytes($summary['db_bytes'])],
            [__('storage.sum.codes_table'), CodeStorage::bytes($summary['codes_bytes'])],
            [__('storage.sum.codes'), $n($summary['codes'])],
            [__('storage.sum.month'), '+' . $n($summary['codes_month'])],
            [__('storage.sum.pdf'), CodeStorage::bytes($summary['pdf_bytes'])],
        ] as [$label, $value])
            <div class="bg-white border border-stone-200 rounded-xl p-4">
                <p class="text-xs text-stone-500">{{ $label }}</p>
                <p class="text-xl font-semibold tabular-nums text-stone-900 mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <x-admin.toolbar>
        <x-admin.search model="search" :placeholder="__('batches.search')" />
        <x-admin.filter-tabs model="sort" :value="$activeSort" :label="__('storage.sort')" :options="collect(\App\Http\Livewire\Admin\StorageIndex::SORTS)->mapWithKeys(fn ($s) => [$s => ['label' => __('storage.sort_by.' . $s)]])->all()" />
    </x-admin.toolbar>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto mt-4">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200 text-xs text-stone-600">
                <tr>
                    <th class="text-left font-medium px-4 py-2">{{ __('nav.companies') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.total') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.imported') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.printed') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.cancelled') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.problems') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.batches') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.db') }}</th>
                    <th class="text-right font-medium px-3 py-2">{{ __('storage.col.pdf') }}</th>
                    <th class="text-left font-medium px-3 py-2">{{ __('storage.col.activity') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($rows as $r)
                    <tr wire:key="st-{{ $r['company']->id }}" class="hover:bg-stone-50">
                        <td class="px-4 py-2.5"><a href="{{ route('admin.companies.show', $r['company']) }}" class="font-medium text-stone-900 hover:text-amber-800">{{ $r['company']->name }}</a><div class="text-xs text-stone-500 font-mono">{{ $r['company']->tin }}</div></td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $n($r['total']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($r['imported']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($r['printed']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($r['cancelled']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums {{ $r['problems'] ? 'text-rose-700' : '' }}">{{ $n($r['problems']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $n($r['batches']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums">≈ {{ CodeStorage::bytes($r['db_bytes']) }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums" title="{{ __('storage.pdf_title', ['cached' => $r['pdf_cached'], 'archived' => $r['pdf_archived']]) }}">{{ CodeStorage::bytes($r['pdf_bytes']) }}</td>
                        <td class="px-3 py-2.5 text-xs text-stone-600 whitespace-nowrap">{{ __('storage.upload') }}: {{ $d($r['last_upload']) }}<br>{{ __('storage.print') }}: {{ $d($r['last_print']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-stone-500">{{ __('storage.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-stone-500 mt-3">{{ __('storage.note') }}</p>
</div>
