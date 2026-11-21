@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div>
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.drivers') }}</h1>
            <p class="text-sm text-stone-500 mt-1 max-w-3xl">{{ __('drivers.admin_hint') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('drivers') }}" target="_blank" class="px-3 py-2 border border-stone-200 rounded-lg text-sm text-stone-600 hover:bg-stone-50">/drivers ↗</a>
            <a href="{{ route('admin.drivers.create') }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">+ {{ __('drivers.add') }}</a>
        </div>
    </div>

    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}…" class="{{ $field }} max-w-xs mb-4">

    <div class="bg-white border border-stone-200 rounded-xl divide-y divide-stone-100">
        @forelse($drivers as $d)
            <div class="flex items-center gap-4 px-4 py-3" wire:key="driver-{{ $d->id }}">
                <div class="flex-1 min-w-0">
                    <p class="font-medium"><a href="{{ route('admin.drivers.edit', $d) }}" class="hover:text-amber-700">{{ $d->name() }}</a> <span class="text-xs text-stone-400 font-normal">· {{ __('drivers.kinds.' . $d->kind) }}@if($d->language) · {{ $d->language }}@endif</span></p>
                    <p class="text-xs text-stone-500 truncate"><a href="{{ $d->driver_url }}" target="_blank" rel="noopener" class="hover:text-amber-700">{{ $d->driver_url }}</a></p>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        @forelse($d->offers as $o)
                            <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-200 text-[11px]">{{ $o->title_uz }}</span>
                        @empty
                            <span class="text-[11px] text-stone-400">{{ __('drivers.no_products') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0 text-xs">
                    <a href="{{ route('drivers.show', $d) }}" target="_blank" class="px-2 text-stone-500 hover:text-amber-700">↗</a>
                    <label class="inline-flex items-center gap-1.5 px-2 cursor-pointer select-none {{ $d->is_active ? 'text-emerald-700' : 'text-stone-500' }}">
                        <input type="checkbox" wire:click="toggle({{ $d->id }})" @checked($d->is_active) class="rounded border-stone-300 text-emerald-600"> {{ __('banners.publish') }}
                    </label>
                    <a href="{{ route('admin.drivers.edit', $d) }}" class="px-2 text-stone-500 hover:text-amber-700">{{ __('common.edit') }}</a>
                    <button wire:click="delete({{ $d->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="px-2 text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-stone-500">{{ __('drivers.empty') }}</p>
        @endforelse
    </div>

</div>
