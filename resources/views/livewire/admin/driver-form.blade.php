@php
    $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-white';
    $label = 'block text-xs font-medium text-stone-700 mb-1';
@endphp
<div class="w-full max-w-3xl">
    <a href="{{ route('admin.drivers') }}" class="inline-flex items-center gap-1 text-xs text-stone-500 hover:text-amber-700">@svg('heroicon-o-arrow-left', 'w-3.5 h-3.5') {{ __('nav.drivers') }}</a>
    <h1 class="text-xl font-semibold mt-1 mb-6">{{ $driverId ? __('drivers.edit') : __('drivers.add') }}</h1>

    <form wire:submit="save" class="bg-white border border-stone-200 rounded-xl divide-y divide-stone-100">
        {{-- name and link --}}
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="d-brand" class="{{ $label }}">{{ __('drivers.brand') }}</label>
                    <input id="d-brand" wire:model="item.brand" type="text" maxlength="60" placeholder="Zebra" class="{{ $field }}">
                    @error('item.brand')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="d-model" class="{{ $label }}">{{ __('drivers.driver_name') }} <span class="font-normal text-stone-400">· {{ __('drivers.optional') }}</span></label>
                    <input id="d-model" wire:model="item.model" type="text" maxlength="80" placeholder="ZDesigner, GoLabel…" class="{{ $field }}">
                    @error('item.model')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="d-url" class="{{ $label }}">{{ __('drivers.url') }}</label>
                <input id="d-url" wire:model="item.driver_url" type="url" maxlength="500" placeholder="https://www.zebra.com/…" class="{{ $field }}">
                <p class="text-[11px] text-stone-500 mt-1">{{ __('drivers.url_hint') }}</p>
                @error('item.driver_url')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- products this driver fits --}}
        <div class="p-5 space-y-3">
            <div>
                <p class="text-sm font-semibold">{{ __('drivers.products') }}</p>
                <p class="text-xs text-stone-500 mt-0.5">{{ __('drivers.products_hint') }}</p>
            </div>
            @if($chosen->isNotEmpty())
                <ul class="border border-stone-200 rounded-lg divide-y divide-stone-100">
                    @foreach($chosen as $o)
                        <li class="flex items-center gap-3 px-3 py-2" wire:key="chosen-{{ $o->id }}">
                            <span class="w-9 h-9 rounded bg-stone-50 ring-1 ring-stone-200 flex items-center justify-center overflow-hidden shrink-0">
                                @if($o->image_path)<img src="{{ \App\Domain\Offers\Models\Offer::publicUrl($o->image_path) }}" alt="" class="w-full h-full object-contain">@else @svg('heroicon-o-printer', 'w-4 h-4 text-stone-300') @endif
                            </span>
                            <span class="flex-1 min-w-0 text-sm truncate">{{ $o->title_uz }}@unless($o->is_active)<span class="text-xs text-stone-400"> · {{ __('banners.hidden') }}</span>@endunless</span>
                            <button type="button" wire:click="removeOffer({{ $o->id }})" class="w-7 h-7 rounded-md text-stone-400 hover:text-rose-600 hover:bg-rose-50" aria-label="{{ __('common.delete') }}">@svg('heroicon-o-x-mark', 'w-4 h-4 mx-auto')</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <select wire:model.live="addOffer" class="{{ $field }}" aria-label="{{ __('drivers.add_product') }}">
                <option value="">+ {{ __('drivers.add_product') }}</option>
                @foreach($choices as $cat => $list)
                    <optgroup label="{{ $cat }}">
                        @foreach($list as $o)<option value="{{ $o->id }}">{{ $o->title_uz }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('offerIds.*')<p class="text-rose-600 text-xs">{{ $message }}</p>@enderror
        </div>

        {{-- optional details --}}
        <details class="group" @if($errors->hasAny(['item.kind', 'item.language', 'item.summary_uz', 'item.summary_ru', 'item.summary_zh', 'item.notes_uz', 'item.notes_ru', 'item.notes_zh'])) open @endif>
            <summary class="cursor-pointer px-5 py-3 text-sm text-stone-600 hover:text-stone-900 flex items-center gap-2">
                @svg('heroicon-o-chevron-right', 'w-4 h-4 transition group-open:rotate-90') {{ __('drivers.more') }}
            </summary>
            <div class="px-5 pb-5 space-y-4">
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $label }}">{{ __('drivers.kind') }}</label>
                        <select wire:model="item.kind" class="{{ $field }}">
                            @foreach(\App\Domain\Content\PrinterDriver::KINDS as $k)<option value="{{ $k }}">{{ __('drivers.kinds.' . $k) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('drivers.language') }}</label>
                        <input wire:model="item.language" type="text" maxlength="40" placeholder="ZPL" class="{{ $field }}">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span class="text-xs font-medium text-stone-700">{{ __('drivers.os') }}:</span>
                    @foreach(\App\Domain\Content\PrinterDriver::OS as $os)
                        <label class="flex items-center gap-1.5"><input wire:model="item.os" type="checkbox" value="{{ $os }}" class="rounded border-stone-300 text-amber-600"> {{ __('drivers.os_names.' . $os) }}</label>
                    @endforeach
                </div>
                @foreach(['uz' => 'UZ', 'ru' => 'RU', 'zh' => '中文'] as $l => $lang)
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">{{ __('drivers.summary') }} · {{ $lang }}</label>
                            <textarea wire:model="item.summary_{{ $l }}" rows="3" maxlength="300" class="{{ $field }}"></textarea>
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('drivers.notes') }} · {{ $lang }}</label>
                            <textarea wire:model="item.notes_{{ $l }}" rows="3" maxlength="3000" class="{{ $field }}"></textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        </details>

        <div class="p-5 flex items-center justify-between gap-4 flex-wrap">
            <label class="flex items-center gap-2 text-sm cursor-pointer"><input wire:model="item.is_active" type="checkbox" class="rounded border-stone-300 text-emerald-600"> {{ __('banners.active') }}</label>
            <div class="flex gap-2">
                <a href="{{ route('admin.drivers') }}" class="px-4 py-2 text-sm text-stone-600 rounded-lg hover:bg-stone-100">{{ __('common.cancel') }}</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-60">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
