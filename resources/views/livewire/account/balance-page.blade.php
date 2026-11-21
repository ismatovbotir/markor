@php $money = fn ($n) => \App\Domain\Billing\Billing::money($n); $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div class="w-full space-y-6">
    <h1 class="text-xl font-semibold">{{ __('nav.balance') }}</h1>

    @if(\App\Domain\Billing\Billing::isFree($company))
        <div class="flex items-center gap-2 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
            🎁 {{ __('free.balance_note', ['date' => $company->free_until->format('d.m.Y'), 'paid_from' => \App\Domain\Billing\Billing::paidFrom($company)->format('d.m.Y')]) }}
            @if(($left = \App\Domain\Billing\Billing::freeCodesLeft($company)) !== null) {{ __('free.codes_left', ['left' => number_format($left, 0, '.', ' '), 'limit' => number_format(config('billing.free_codes_limit'), 0, '.', ' ')]) }}@endif
        </div>
    @endif
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="bg-white border border-stone-200 rounded-xl p-4">
            <p class="text-xs text-stone-500">{{ __('balance.current') }}</p>
            <p class="text-2xl font-semibold tabular-nums mt-1 {{ $company->balance < 0 ? 'text-rose-600' : '' }}">{{ $money($company->balance) }}</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-xl p-4">
            <p class="text-xs text-stone-500">{{ __('balance.price') }}</p>
            <p class="text-2xl font-semibold tabular-nums mt-1">{{ $money($price) }} <span class="text-sm font-normal text-stone-500">/ {{ __('balance.per_code') }}</span></p>
            @if($discount > 0)<p class="text-xs text-emerald-700 mt-1">{{ __('billing.discount') }} {{ \App\Domain\Billing\Billing::percent($discount) }}%@if($discountUntil) · {{ __('billing.until', ['date' => $discountUntil->format('d.m.Y')]) }}@endif</p>@endif
        </div>
        <div class="bg-white border border-stone-200 rounded-xl p-4 {{ $shortfall > 0 ? 'border-amber-300 bg-amber-50' : '' }}">
            <p class="text-xs text-stone-500">{{ __('balance.need') }}</p>
            <p class="text-2xl font-semibold tabular-nums mt-1 {{ $shortfall > 0 ? 'text-amber-800' : 'text-stone-400' }}">{{ $money($shortfall) }}</p>
            @if($locked->isNotEmpty())<p class="text-xs text-amber-800 mt-1">{{ __('balance.locked_count', ['count' => $locked->count()]) }}</p>@endif
        </div>
    </div>

    @if($locked->isNotEmpty())
        <div class="bg-white border border-amber-300 rounded-xl p-5">
            <h2 class="font-semibold text-sm flex items-center gap-2">@svg('heroicon-o-lock-closed', 'w-4 h-4 text-amber-700') {{ __('balance.locked_title') }}</h2>
            <ul class="mt-2 text-sm divide-y divide-stone-100">
                @foreach($locked as $b)
                    <li class="py-2 flex justify-between gap-4"><span>{{ $b->original_filename }} <span class="text-stone-400">· {{ number_format($b->processed_rows, 0, '.', ' ') }} {{ __('billing.codes') }}</span></span><span class="tabular-nums font-medium">{{ $money($b->amount) }}</span></li>
                @endforeach
            </ul>
            <p class="text-xs text-stone-500 mt-2">{{ __('balance.locked_hint') }}</p>
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white border border-stone-200 rounded-xl p-5 space-y-3">
            <h2 class="font-semibold text-sm">{{ __('balance.how_to_pay') }}</h2>
            <ol class="text-sm text-stone-600 list-decimal pl-5 space-y-1">
                <li>{{ __('balance.step_transfer') }}</li>
                <li>{{ __('balance.step_report') }}</li>
                <li>{{ __('balance.step_confirm') }}</li>
            </ol>
            @if($card)
                <div class="bg-stone-50 border border-stone-200 rounded-lg p-4" x-data="{ copied: false }">
                    <p class="text-xs text-stone-500">{{ __('balance.card') }}</p>
                    <p class="font-mono text-xl tracking-wider mt-1">{{ trim(chunk_split(preg_replace('/\D/', '', $card), 4, ' ')) }}</p>
                    @if($cardHolder)<p class="text-sm text-stone-600 mt-1">{{ $cardHolder }}</p>@endif
                    <button type="button" x-on:click="navigator.clipboard.writeText(@js(preg_replace('/\D/', '', $card))); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs text-amber-700 mt-2 hover:underline">
                        <span x-show="!copied">{{ __('balance.copy') }}</span><span x-show="copied" x-cloak>✓ {{ __('balance.copied') }}</span>
                    </button>
                </div>
            @else
                <p class="text-sm text-stone-500">{{ __('balance.no_card') }}</p>
            @endif
        </div>

        <form wire:submit="reportPayment" class="bg-white border border-stone-200 rounded-xl p-5 space-y-3">
            <h2 class="font-semibold text-sm">{{ __('balance.i_paid') }}</h2>
            <label class="block text-xs font-medium text-stone-600">{{ __('balance.amount') }}
                <input wire:model="amount" type="number" min="1000" step="1" class="{{ $field }} mt-1 tabular-nums">
                @error('amount') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('requests.comment') }} <span class="text-stone-400">({{ __('common.optional') }})</span>
                <input wire:model="comment" type="text" placeholder="{{ __('balance.comment_placeholder') }}" class="{{ $field }} mt-1">
            </label>
            <button type="submit" class="w-full px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('balance.i_paid_button') }}</button>
            @foreach($pending as $p)
                <p class="text-xs text-amber-800 bg-amber-50 rounded-lg px-3 py-2">⏳ {{ __('balance.pending_line', ['amount' => $money($p->amount), 'date' => $p->created_at->format('d.m.Y H:i')]) }}</p>
            @endforeach
        </form>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <h2 class="font-semibold text-sm px-5 pt-4 pb-2">{{ __('balance.history') }}</h2>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-y border-stone-200">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('balance.operation') }}</th>
                    <th class="text-right px-4 py-2 font-medium text-stone-600">{{ __('balance.amount') }}</th>
                    <th class="text-right px-4 py-2 font-medium text-stone-600">{{ __('balance.after') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($entries as $e)
                    <tr>
                        <td class="px-4 py-2 text-stone-500 whitespace-nowrap">{{ $e->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-2">{{ __('balance.type.' . $e->type) }}@if($e->batch) · {{ $e->batch->original_filename }}@endif @if($e->note)<span class="text-stone-500"> · {{ $e->note }}</span>@endif</td>
                        <td class="px-4 py-2 text-right tabular-nums {{ $e->amount >= 0 ? 'text-emerald-700' : 'text-stone-800' }}">{{ $e->amount >= 0 ? '+' : '' }}{{ $money($e->amount) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-stone-500">{{ $money($e->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $entries->links() }}</div>
</div>
