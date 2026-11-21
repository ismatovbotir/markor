@php
    $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white';
    $money = fn ($n) => \App\Domain\Billing\Billing::money($n);
    $pct = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $colors = ['pending'=>'stone','processing'=>'amber','checking'=>'sky','completed'=>'emerald','failed'=>'rose'];
@endphp
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.clients') }}" class="text-xs text-stone-500 hover:text-amber-700">← {{ __('nav.companies') }}</a>
        <h1 class="text-xl font-semibold mt-1">{{ $company->name }}</h1>
        @unless($company->is_active)
            <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700">@svg('heroicon-o-lock-closed', 'w-3 h-3') {{ __('companies.status_blocked') }}@if($company->blocked_reason): {{ $company->blocked_reason }}@endif</span>
        @endunless
        <p class="text-sm text-stone-500 font-mono">STIR {{ $company->tin }} · {{ __('companies.registered') }} {{ $company->created_at->format('d.m.Y') }} · {{ $company->source === 'lookup' ? __('companies.from_lookup') : __('companies.manual') }}</p>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl p-5 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p class="font-semibold text-sm">{{ __('companies.status') }}</p>
            <p class="text-xs text-stone-500">{{ $company->is_active ? __('companies.status_active_hint') : __('companies.status_blocked_hint') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($company->is_active)
                <input wire:model="blockReason" type="text" placeholder="{{ __('companies.block_reason') }}" class="border border-stone-200 rounded-lg px-3 py-2 text-sm w-64">
                <button wire:click="toggleCompany" wire:confirm="{{ __('companies.confirm_block') }}" class="px-4 py-2 border border-rose-300 text-rose-700 rounded-lg text-sm hover:bg-rose-50">{{ __('companies.block') }}</button>
            @else
                <button wire:click="toggleCompany" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">{{ __('companies.unblock') }}</button>
            @endif
        </div>
    </div>

    @php $free = \App\Domain\Billing\Billing::isFree($company); $paidFrom = \App\Domain\Billing\Billing::paidFrom($company); @endphp
    <div class="bg-white border rounded-xl p-5 space-y-3 {{ $free ? 'border-emerald-300' : 'border-stone-200' }}">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="font-semibold text-sm">{{ __('free.title') }}</p>
                @if($free)
                    <p class="text-sm text-emerald-700 mt-0.5">{{ __('free.active', ['date' => $company->free_until->format('d.m.Y'), 'days' => (int) now()->startOfDay()->diffInDays($company->free_until) + 1]) }}</p>
                    <p class="text-xs text-stone-500">{{ __('free.paid_from', ['date' => $paidFrom->format('d.m.Y')]) }}</p>
                    @if(($left = \App\Domain\Billing\Billing::freeCodesLeft($company)) !== null)<p class="text-xs text-stone-500">{{ __('free.codes_left', ['left' => number_format($left, 0, '.', ' '), 'limit' => number_format(config('billing.free_codes_limit'), 0, '.', ' ')]) }}</p>@endif
                @elseif($company->free_until)
                    <p class="text-sm text-stone-600 mt-0.5">{{ __('free.ended', ['date' => $company->free_until->format('d.m.Y')]) }}</p>
                @else
                    <p class="text-sm text-stone-500 mt-0.5">{{ __('free.none') }}</p>
                    @if($company->source !== 'lookup')<p class="text-xs text-amber-700 mt-0.5">{{ __('free.unverified') }}</p>@endif
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs text-stone-500">{{ __('free.prolong') }}:</span>
                @foreach([1, 3, 6] as $m)
                    <button wire:click="prolongFree({{ $m }})" class="px-3 py-1.5 border border-emerald-300 text-emerald-800 rounded-lg text-sm hover:bg-emerald-50">+{{ $m }} {{ __('free.months') }}</button>
                @endforeach
                @if($free)
                    <button wire:click="endFree" wire:confirm="{{ __('free.end_confirm') }}" class="px-3 py-1.5 text-sm text-rose-600 hover:text-rose-800">{{ __('free.end') }}</button>
                @endif
            </div>
        </div>
        <form wire:submit="saveFreeUntil" class="flex items-center gap-2 flex-wrap border-t border-stone-100 pt-3">
            <span class="text-xs text-stone-500">{{ __('free.until_label') }}</span>
            <input wire:model="freeUntil" type="date" class="border border-stone-200 rounded-lg px-2 py-1.5 text-sm">
            <button type="submit" class="px-3 py-1.5 bg-amber-700 text-white rounded-lg text-sm">{{ __('common.save') }}</button>
            <span class="text-[11px] text-stone-400">{{ __('free.hint') }}</span>
        </form>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl p-5 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p class="font-semibold text-sm">{{ __('clients.discount') }}</p>
            <p class="text-xs text-stone-500">
                {{ __('companies.discount_now', ['percent' => \App\Domain\Billing\Billing::percent(\App\Domain\Billing\Billing::discountFor($company))]) }}
                @if($company->discount_percent !== null && $company->discount_until) · {{ __('billing.until', ['date' => $company->discount_until->format('d.m.Y')]) }}@endif
                @if($company->discount_percent === null) · {{ __('clients.default_discount') }}@endif
            </p>
        </div>
        @if($discountFor)
            <form wire:submit="saveDiscount" class="flex items-center gap-2 flex-wrap">
                <input wire:model="discountValue" type="number" min="0" max="100" step="0.01" placeholder="{{ $defaultDiscount }}" class="w-24 border border-stone-200 rounded-lg px-2 py-1.5 text-sm text-right"> %
                <span class="text-xs text-stone-500">{{ __('companies.discount_until_label') }}</span>
                <input wire:model="discountUntil" type="date" class="border border-stone-200 rounded-lg px-2 py-1.5 text-sm">
                <button type="submit" class="px-3 py-1.5 bg-amber-700 text-white rounded-lg text-sm">{{ __('common.save') }}</button>
                @error('discountValue') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
            </form>
        @else
            <button wire:click="editDiscount" class="px-3 py-1.5 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">{{ __('common.edit') }}</button>
        @endif
    </div>

    <div class="bg-white border border-stone-200 rounded-xl p-5 space-y-3">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="font-semibold text-sm">{{ __('nav.balance') }}</p>
                <p class="text-2xl font-semibold tabular-nums {{ $company->balance < 0 ? 'text-rose-600' : '' }}">{{ $money($company->balance) }}</p>
                @if($shortfall > 0)<p class="text-xs text-amber-800">{{ __('balance.need') }}: {{ $money($shortfall) }}</p>@endif
            </div>
            <button wire:click="$toggle('showMoney')" class="px-3 py-1.5 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">{{ __('payments.record') }}</button>
        </div>
        @if($showMoney)
            <form wire:submit="saveMoney" class="flex items-end gap-2 flex-wrap border-t border-stone-100 pt-3">
                <label class="text-xs font-medium text-stone-600">{{ __('balance.operation') }}
                    <select wire:model.live="moneyType" class="block border border-stone-200 rounded-lg px-2 py-1.5 text-sm mt-1"><option value="payment">{{ __('balance.type.payment') }}</option><option value="adjustment">{{ __('balance.type.adjustment') }}</option></select>
                </label>
                <label class="text-xs font-medium text-stone-600">{{ __('balance.amount') }}
                    <input wire:model="moneyAmount" type="number" step="1" class="block w-36 border border-stone-200 rounded-lg px-2 py-1.5 text-sm mt-1 tabular-nums" placeholder="{{ $moneyType === 'adjustment' ? '−5000 / 5000' : '' }}">
                </label>
                @if($moneyType === 'payment')
                    <label class="text-xs font-medium text-stone-600">{{ __('payments.method') }}
                        <select wire:model="moneyMethod" class="block border border-stone-200 rounded-lg px-2 py-1.5 text-sm mt-1"><option value="cash">{{ __('payments.methods.cash') }}</option><option value="card">{{ __('payments.methods.card') }}</option><option value="bank">{{ __('payments.methods.bank') }}</option></select>
                    </label>
                @endif
                <label class="text-xs font-medium text-stone-600 flex-1 min-w-48">{{ __('payments.note') }}
                    <input wire:model="moneyNote" type="text" class="block w-full border border-stone-200 rounded-lg px-2 py-1.5 text-sm mt-1">
                </label>
                <button type="submit" class="px-4 py-1.5 bg-amber-700 text-white rounded-lg text-sm">{{ __('common.save') }}</button>
                @error('moneyAmount') <p class="w-full text-rose-500 text-xs">{{ $message }}</p> @enderror
                @error('moneyNote') <p class="w-full text-rose-500 text-xs">{{ $message }}</p> @enderror
            </form>
        @endif
        @if($entries->isNotEmpty())
            <table class="w-full text-sm border-t border-stone-100">
                @foreach($entries as $e)
                    <tr class="border-b border-stone-50">
                        <td class="py-1.5 text-stone-500 whitespace-nowrap">{{ $e->created_at->format('d.m.Y H:i') }}</td>
                        <td class="py-1.5">{{ __('balance.type.' . $e->type) }}@if($e->batch) · {{ $e->batch->original_filename }}@endif @if($e->note)<span class="text-stone-500"> · {{ $e->note }}</span>@endif</td>
                        <td class="py-1.5 text-right tabular-nums {{ $e->amount >= 0 ? 'text-emerald-700' : '' }}">{{ $e->amount >= 0 ? '+' : '' }}{{ $money($e->amount) }}</td>
                        <td class="py-1.5 text-right tabular-nums text-stone-400">{{ $money($e->balance_after) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <form wire:submit="save" class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
        <h2 class="font-semibold text-sm">{{ __('company.title_edit') }}</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach(['name' => 'company.name', 'legal_address' => 'company.legal_address', 'director' => 'company.director', 'phone' => 'auth.phone', 'email' => 'auth.email', 'vat_code' => 'company.vat_code'] as $key => $label)
                <label class="block text-xs font-medium text-stone-600 {{ in_array($key, ['name', 'legal_address']) ? 'sm:col-span-2' : '' }}">{{ __($label) }}
                    <input wire:model="form.{{ $key }}" type="text" class="{{ $field }} mt-1">
                    @error("form.$key") <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </label>
            @endforeach
        </div>
        <div class="flex justify-end"><button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">{{ __('common.save') }}</button></div>
    </form>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <h2 class="font-semibold text-sm px-5 pt-4 pb-2">{{ __('nav.company_users') }} ({{ $users->count() }})</h2>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-y border-stone-200">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('auth.name') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('auth.phone') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('company_users.role') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">Telegram</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('common.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach($users as $u)
                    <tr wire:key="su-{{ $u->id }}">
                        <td class="px-4 py-2"><div class="font-medium">{{ $u->name }}</div><div class="text-xs text-stone-500">{{ $u->email }}</div></td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $u->phone ?: '—' }}</td>
                        <td class="px-4 py-2">{{ __('company_users.roles.' . ($u->company_role ?: 'member')) }}</td>
                        <td class="px-4 py-2 text-xs">{{ $u->telegram_chat_id ? __('telegram.connected') : '—' }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="toggleActive({{ $u->id }})" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600' }}">{{ $u->is_active ? __('common.active') : __('common.inactive') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <h2 class="font-semibold text-sm px-5 pt-4 pb-2">{{ __('companies.last_batches') }}</h2>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-y border-stone-200">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('import.batch') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('companies.uploaded_by') }}</th>
                    <th class="text-right px-4 py-2 font-medium text-stone-600">{{ __('import.quantity') }}</th>
                    <th class="text-right px-4 py-2 font-medium text-stone-600">{{ __('billing.to_pay') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="text-left px-4 py-2 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($batches as $b)
                    <tr wire:key="sb-{{ $b->id }}">
                        <td class="px-4 py-2 font-medium">{{ $b->original_filename }} @if($b->source === 'telegram')<span class="text-[10px] text-sky-700">TG</span>@endif</td>
                        <td class="px-4 py-2 text-stone-600">{{ $b->uploader?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($b->processed_rows, 0, '.', ' ') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($b->amount) }}</td>
                        <td class="px-4 py-2"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $colors[$b->status] ?? 'stone' }}-100 text-{{ $colors[$b->status] ?? 'stone' }}-700">{{ __('import.status.' . $b->status) }}</span>@if($b->is_locked) <span class="text-xs text-amber-700">🔒</span>@endif</td>
                        <td class="px-4 py-2 text-stone-500 whitespace-nowrap">{{ $b->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-2 text-right">@if($b->status === 'completed')<a href="{{ route('import-batches.labels', $b) }}" target="_blank" class="text-xs text-amber-700 hover:underline">PDF</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
