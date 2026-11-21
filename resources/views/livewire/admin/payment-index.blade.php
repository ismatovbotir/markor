@php $money = fn ($n) => \App\Domain\Billing\Billing::money($n); $colors = ['pending' => 'amber', 'confirmed' => 'emerald', 'rejected' => 'stone']; @endphp
<div>
    <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.payments') }}</h1>
        <div class="flex rounded-lg border border-stone-200 bg-white overflow-hidden text-sm">
            @foreach(['pending' => __('payments.pending') . " ($pendingCount)", 'confirmed' => __('payments.status.confirmed'), 'rejected' => __('payments.status.rejected'), 'all' => __('common.all')] as $value => $label)
                <button wire:click="$set('status', '{{ $value }}')" class="px-3 py-1.5 {{ $status === $value ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.companies') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('payments.reported_by') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('balance.amount') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('requests.comment') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($payments as $p)
                    <tr wire:key="pay-{{ $p->id }}">
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.companies.show', $p->company) }}" class="font-medium hover:text-amber-700">{{ $p->company?->name }}</a><div class="text-xs text-stone-500">{{ __('balance.current') }}: {{ $money($p->company?->balance) }}</div></td>
                        <td class="px-4 py-3">{{ $p->user?->name ?? '—' }}<div class="text-xs text-stone-500">{{ $p->user?->phone }}</div></td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold whitespace-nowrap">{{ $money($p->amount) }}</td>
                        <td class="px-4 py-3 text-stone-600 max-w-xs">{{ $p->comment ?: '—' }}@if($p->admin_note)<div class="text-xs text-stone-400">{{ $p->admin_note }}</div>@endif</td>
                        <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $colors[$p->status] }}-100 text-{{ $colors[$p->status] }}-700">{{ __('payments.status.' . $p->status) }}</span></td>
                        <td class="px-4 py-3 text-right">@if($p->status === 'pending')<button wire:click="open({{ $p->id }})" class="text-xs font-semibold text-amber-700 hover:underline">{{ __('payments.decide') }}</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>

    @if($deciding)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ __('payments.decide') }}</h2>
                <p class="text-sm text-stone-500">{{ __('payments.check_hint') }}</p>
                <label class="block text-xs font-medium text-stone-600">{{ __('payments.received_amount') }}
                    <input wire:model="confirmAmount" type="number" min="1" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm mt-1 tabular-nums">
                    @error('confirmAmount') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </label>
                <label class="block text-xs font-medium text-stone-600">{{ __('payments.note') }}
                    <input wire:model="note" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm mt-1">
                </label>
                <div class="flex justify-between gap-3 pt-2">
                    <button wire:click="reject" wire:confirm="{{ __('payments.confirm_reject') }}" class="px-4 py-2 text-sm text-rose-600 hover:text-rose-800">{{ __('payments.reject') }}</button>
                    <span class="flex gap-2">
                        <button wire:click="$set('deciding', null)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                        <button wire:click="confirm" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">{{ __('payments.confirm') }}</button>
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
