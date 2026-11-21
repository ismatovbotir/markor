@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div>
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.drivers') }}</h1>
            <p class="text-sm text-stone-500 mt-1 max-w-3xl">{{ __('drivers.admin_hint') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('drivers') }}" target="_blank" class="px-3 py-2 border border-stone-200 rounded-lg text-sm text-stone-600 hover:bg-stone-50">/drivers ↗</a>
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">+ {{ __('drivers.add') }}</button>
        </div>
    </div>

    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}…" class="{{ $field }} max-w-xs mb-4">

    <div class="bg-white border border-stone-200 rounded-xl divide-y divide-stone-100">
        @forelse($drivers as $d)
            <div class="flex items-center gap-4 px-4 py-3" wire:key="driver-{{ $d->id }}">
                <div class="flex-1 min-w-0">
                    <p class="font-medium">{{ $d->name() }} <span class="text-xs text-stone-400 font-normal">· {{ __('drivers.kinds.' . $d->kind) }}@if($d->language) · {{ $d->language }}@endif</span></p>
                    <p class="text-xs text-stone-500 truncate"><a href="{{ $d->driver_url }}" target="_blank" rel="noopener" class="hover:text-amber-700">{{ $d->driver_url }}</a></p>
                </div>
                <div class="flex items-center gap-1 shrink-0 text-xs">
                    <a href="{{ route('drivers.show', $d) }}" target="_blank" class="px-2 text-stone-500 hover:text-amber-700">↗</a>
                    <label class="inline-flex items-center gap-1.5 px-2 cursor-pointer select-none {{ $d->is_active ? 'text-emerald-700' : 'text-stone-500' }}">
                        <input type="checkbox" wire:click="toggle({{ $d->id }})" @checked($d->is_active) class="rounded border-stone-300 text-emerald-600"> {{ __('banners.publish') }}
                    </label>
                    <button wire:click="openEdit({{ $d->id }})" class="px-2 text-stone-500 hover:text-amber-700">{{ __('common.edit') }}</button>
                    <button wire:click="delete({{ $d->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="px-2 text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-stone-500">{{ __('drivers.empty') }}</p>
        @endforelse
    </div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-start justify-center overflow-y-auto py-8 px-4">
            <form wire:submit="save" class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ $editingId ? __('common.edit') : __('drivers.add') }}</h2>
                <div class="grid sm:grid-cols-4 gap-3">
                    <label class="block text-xs font-medium text-stone-600">{{ __('drivers.brand') }}
                        <input wire:model="item.brand" type="text" maxlength="60" placeholder="Zebra" class="{{ $field }} mt-1">
                        @error('item.brand')<span class="text-rose-500">{{ $message }}</span>@enderror</label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('drivers.model') }}
                        <input wire:model="item.model" type="text" maxlength="80" placeholder="ZD421" class="{{ $field }} mt-1">
                        @error('item.model')<span class="text-rose-500">{{ $message }}</span>@enderror</label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('drivers.kind') }}
                        <select wire:model="item.kind" class="{{ $field }} mt-1">
                            @foreach(\App\Domain\Content\PrinterDriver::KINDS as $k)<option value="{{ $k }}">{{ __('drivers.kinds.' . $k) }}</option>@endforeach
                        </select></label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('drivers.language') }}
                        <input wire:model="item.language" type="text" maxlength="40" placeholder="ZPL" class="{{ $field }} mt-1"></label>
                </div>
                <label class="block text-xs font-medium text-stone-600">{{ __('drivers.url') }}
                    <input wire:model="item.driver_url" type="url" maxlength="500" placeholder="https://www.zebra.com/…" class="{{ $field }} mt-1">
                    <span class="block text-[11px] text-stone-400 mt-1">{{ __('drivers.url_hint') }}</span>
                    @error('item.driver_url')<span class="text-rose-500">{{ $message }}</span>@enderror</label>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="text-xs font-medium text-stone-600">{{ __('drivers.os') }}:</span>
                    @foreach(\App\Domain\Content\PrinterDriver::OS as $os)
                        <label class="flex items-center gap-1.5"><input wire:model="item.os" type="checkbox" value="{{ $os }}" class="rounded border-stone-300 text-amber-600"> {{ __('drivers.os_names.' . $os) }}</label>
                    @endforeach
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    @foreach(['uz' => 'UZ', 'ru' => 'RU', 'zh' => '中文'] as $l => $lang)
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-stone-600">{{ __('drivers.summary') }} ({{ $lang }})
                                <textarea wire:model="item.summary_{{ $l }}" rows="2" maxlength="300" class="{{ $field }} mt-1"></textarea></label>
                            <label class="block text-xs font-medium text-stone-600">{{ __('drivers.notes') }} ({{ $lang }})
                                <textarea wire:model="item.notes_{{ $l }}" rows="4" maxlength="3000" class="{{ $field }} mt-1"></textarea></label>
                        </div>
                    @endforeach
                </div>
                <label class="flex items-center gap-2 text-sm"><input wire:model="item.is_active" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('banners.active') }}</label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
