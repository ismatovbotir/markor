@php use App\Domain\Marking\Services\BatchAdminService; use App\Domain\Billing\Billing; @endphp
<div>
    <x-admin.page-header crumb="Asl Belgisi" :title="__('batches.title')" :hint="__('batches.hint')" />

    <x-admin.toolbar>
        <x-admin.search model="search" :placeholder="__('batches.search')" />
        <x-admin.filter-tabs model="filter" :value="$activeFilter" :options="collect(\App\Http\Livewire\Admin\BatchIndex::FILTERS)->mapWithKeys(fn ($f) => [$f => ['label' => __('batches.filter.' . $f), 'count' => $counts[$f]]])->all()" />
        <select wire:model.live="origin" class="admin-input w-auto text-sm" aria-label="{{ __('batches.origin_label') }}">
            <option value="">{{ __('batches.origin.all') }}</option>
            <option value="csv">CSV</option>
            <option value="aslbelgi">Asl Belgisi API</option>
            <option value="pdf">PDF</option>
        </select>
        <select wire:model.live="company" class="admin-input w-auto max-w-56 text-sm" aria-label="{{ __('nav.companies') }}">
            <option value="">{{ __('batches.all_companies') }}</option>
            @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
    </x-admin.toolbar>

    <x-admin.list :head="[__('batches.col.batch'), __('common.status'), __('batches.col.money'), __('batches.col.date'), __('admin.list.actions')]" wide class="mt-4">
        @forelse($batches as $b)
            @php
                $printed = BatchAdminService::isPrinted($b);
                $refundable = BatchAdminService::refundable($b);
            @endphp
            <x-admin.list-row wire:key="batch-{{ $b->id }}">
                <span class="font-mono text-[13px]">{{ $b->original_filename }}</span>
                <x-slot:meta>
                    <a href="{{ route('admin.companies.show', $b->company_id) }}" class="hover:text-amber-800 underline-offset-2 hover:underline">{{ $b->company?->name ?? '—' }}</a>
                    · {{ number_format($b->processed_rows, 0, '.', ' ') }} {{ __('batches.codes') }}
                    @if($b->origin === 'aslbelgi')<span class="ml-1 px-1.5 py-0.5 rounded bg-teal-50 text-teal-800 text-[11px]">Asl Belgisi</span>@endif
                    @if($b->origin === 'pdf')<span class="ml-1 px-1.5 py-0.5 rounded bg-sky-50 text-sky-800 text-[11px]">PDF</span>@endif
                    @if($printed)<span class="ml-1 px-1.5 py-0.5 rounded bg-stone-100 text-stone-700 text-[11px]">{{ __('batches.printed') }}</span>@endif
                </x-slot:meta>
                <x-slot:status>
                    @if($b->charge_cancelled_at)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-stone-100 text-stone-700">{{ __('batches.state.cancelled') }}</span>
                    @elseif($b->is_locked)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-900">{{ __('batches.state.locked') }}</span>
                    @elseif($b->status === 'completed')
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-800">{{ __('batches.state.ready') }}</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-stone-100 text-stone-700">{{ __('import.status.' . $b->status) }}</span>
                    @endif
                </x-slot:status>
                <x-slot:langs>
                    <span class="text-sm tabular-nums {{ $b->charge_cancelled_at ? 'line-through text-stone-400' : 'text-stone-800' }}">{{ Billing::money($b->amount) }}</span>
                    @if($b->discount_percent > 0)<span class="text-xs text-emerald-700"> −{{ Billing::percent($b->discount_percent) }}%</span>@endif
                </x-slot:langs>
                <x-slot:updated>{{ $b->created_at->format('d.m.Y H:i') }}</x-slot:updated>
                <x-slot:actions>
                    @if($refundable > 0)
                        <x-admin.button size="sm" icon="receipt-refund" wire:click="open({{ $b->id }}, 'cancel')">{{ __('batches.cancel_charge') }}</x-admin.button>
                    @endif
                    <x-admin.button size="sm" variant="danger" icon="trash" wire:click="open({{ $b->id }}, 'delete')"
                                    :disabled="!($printed || $refundable <= 0)" :title="($printed || $refundable <= 0) ? null : __('batches.cancel_first')">{{ __('common.delete') }}</x-admin.button>
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            <x-admin.empty-state icon="inbox-stack" :title="__('batches.empty')" />
        @endforelse
        <x-slot:footer>{{ $batches->links() }}</x-slot:footer>
    </x-admin.list>

    {{-- cancel / delete dialog: a reason is required and goes to the client by Telegram and to the audit log --}}
    @if($acting)
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4" x-data x-on:keydown.escape.window="$wire.close()">
            <form wire:submit="confirm" role="alertdialog" aria-modal="true" aria-labelledby="batch-action-title" class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5 space-y-3">
                <h2 id="batch-action-title" class="font-semibold text-stone-900">{{ $action === 'cancel' ? __('batches.cancel_title') : __('batches.delete_title') }}</h2>
                <p class="text-sm text-stone-700"><span class="font-mono">{{ $acting->original_filename }}</span> · {{ $acting->company?->name }} · {{ number_format($acting->processed_rows, 0, '.', ' ') }} {{ __('batches.codes') }}</p>
                <p class="text-sm text-stone-600">
                    @if($action === 'cancel')
                        {{ __('batches.cancel_text', ['amount' => Billing::money($actingRefundable)]) }}
                    @elseif($actingPrinted)
                        {{ __('batches.delete_printed_text', ['amount' => Billing::money($actingRefundable)]) }}
                    @else
                        {{ __('batches.delete_text') }}
                    @endif
                </p>
                <label class="block text-sm font-medium text-stone-700" for="batch-reason">{{ __('batches.reason') }}</label>
                <textarea id="batch-reason" wire:model="reason" rows="3" class="admin-input" placeholder="{{ __('batches.reason_placeholder') }}" autofocus></textarea>
                @error('reason')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2 pt-1">
                    <x-admin.button type="button" wire:click="close">{{ __('common.cancel') }}</x-admin.button>
                    <x-admin.button type="submit" :variant="$action === 'cancel' ? 'primary' : 'danger'" wire:loading.attr="disabled" wire:target="confirm">
                        {{ $action === 'cancel' ? __('batches.cancel_charge') : __('batches.delete_confirm') }}
                    </x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
