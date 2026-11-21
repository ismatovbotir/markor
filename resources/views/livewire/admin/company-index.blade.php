@php $fmt = fn ($n) => number_format((int) $n, 0, '.', ' '); $money = fn ($n) => \App\Domain\Billing\Billing::money($n); @endphp
<div>
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('nav.companies') }}</h1>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }} (STIR, nomi)"
               class="w-72 border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['nav.companies', $fmt($totals['companies']), '+' . $fmt($totals['new_month']) . ' ' . __('clients.this_month')],
            ['clients.codes_month', $fmt($totals['codes_month']), __('clients.codes_total') . ': ' . $fmt($totals['codes'])],
            ['clients.to_pay_month', $money($totals['amount_month']), now()->translatedFormat('F Y')],
            ['companies.no_company', $fmt($totals['no_company']), __('companies.no_company_hint')],
        ] as $i => [$label, $value, $hint])
            <div class="bg-white border border-stone-200 rounded-xl p-4 {{ $i === 3 ? 'cursor-pointer hover:border-amber-300' : '' }}" @if($i === 3) wire:click="$set('view', '{{ $view === 'no_company' ? 'companies' : 'no_company' }}')" @endif>
                <p class="text-xs text-stone-500">{{ __($label) }}</p>
                <p class="text-2xl font-semibold tabular-nums mt-1">{{ $value }}</p>
                <p class="text-xs text-stone-400 mt-1">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    @if($view === 'no_company')
        <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto mb-6">
            <div class="flex items-center justify-between px-5 pt-4 pb-2">
                <h2 class="font-semibold text-sm">{{ __('companies.no_company') }}</h2>
                <button wire:click="$set('view', 'companies')" class="text-xs text-stone-500 hover:text-amber-700">← {{ __('nav.companies') }}</button>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-stone-100 border-t border-stone-200">
                    @forelse($loners as $u)
                        <tr><td class="px-4 py-2"><div class="font-medium">{{ $u->name }}</div><div class="text-xs text-stone-500">{{ $u->email }}</div></td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $u->phone ?: '—' }}</td>
                            <td class="px-4 py-2 text-stone-500 whitespace-nowrap">{{ $u->created_at->format('d.m.Y H:i') }}</td></tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('company.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">STIR</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('nav.company_users') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('clients.codes_month') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('clients.codes_total') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('clients.to_pay_month') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('free.payment') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('clients.last_upload') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($companies as $c)
                    <tr class="hover:bg-stone-50 cursor-pointer" wire:key="co-{{ $c->id }}" onclick="window.location='{{ route('admin.companies.show', $c) }}'">
                        <td class="px-4 py-3"><a href="{{ route('admin.companies.show', $c) }}" class="font-medium hover:text-amber-700">{{ $c->name }}</a>@unless($c->is_active) <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-semibold">{{ __('companies.status_blocked') }}</span>@endunless<div class="text-xs text-stone-500">{{ $c->director ?: '—' }}</div></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $c->tin }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $c->users }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ $fmt($c->codes_month) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fmt($c->codes) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $money($c->amount_month) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs">@if(\App\Domain\Billing\Billing::isFree($c))<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">{{ __('free.badge', ['date' => $c->free_until->format('d.m.Y')]) }}</span>@else<span class="text-stone-600">{{ __('free.paying') }}</span>@endif</td>
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap">{{ $c->last_upload ? \Illuminate\Support\Carbon::parse($c->last_upload)->format('d.m.Y H:i') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $companies->links() }}</div>
</div>
