@php $field = 'w-full border border-stone-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div>
    <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
        <div>
            <p class="text-xs text-stone-500">{{ __('nav.offers') }}</p>
            <h1 class="text-xl font-semibold flex items-center gap-2">@svg($category->icon ?: 'heroicon-o-cube', 'w-5 h-5 text-amber-700') {{ $category->name_uz }}</h1>
        </div>
        @if($category->isTemplates())
            <a href="{{ route('label-templates.index') }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('nav.template_designer') }} →</a>
        @else
            <a href="{{ route('admin.offers.create', $category) }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">+ {{ __('offers.add_item') }}</a>
        @endif
    </div>

    {{-- Category settings --}}
    <form wire:submit="saveSettings" class="bg-white border border-stone-200 rounded-xl p-5 mb-6 space-y-4">
        <h2 class="font-semibold text-sm">{{ __('offers.category_settings') }}</h2>
        <div class="grid sm:grid-cols-3 gap-3">
            @foreach(['uz' => 'UZ', 'ru' => 'RU', 'zh' => '中文'] as $l => $lang)
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-stone-600">{{ __('common.name') }} ({{ $lang }})
                        <input wire:model="settings.name_{{ $l }}" type="text" class="{{ $field }} mt-1">
                    </label>
                    @error("settings.name_$l") <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                    <label class="block text-xs font-medium text-stone-600">{{ __('offers.description') }} ({{ $lang }})
                        <input wire:model="settings.description_{{ $l }}" type="text" class="{{ $field }} mt-1">
                    </label>
                </div>
            @endforeach
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600">{{ __('offers.market_url') }}
                <input wire:model="settings.market_url" type="url" placeholder="https://uzum.uz/uz/shop/…" class="{{ $field }} mt-1">
            </label>
            <p class="text-[11px] text-stone-500 mt-1">{{ __('offers.market_hint', ['n' => $shown]) }}</p>
            @error('settings.market_url') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm"><input wire:model="settings.is_active" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('offers.category_visible') }}</label>
            <button type="submit" class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">{{ __('common.save') }}</button>
        </div>
    </form>

    @if($category->isTemplates())
        {{-- Labels: every template is a size we sell --}}
        <p class="text-sm text-stone-500 mb-3">{{ __('offers.templates_are_items') }}</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($templates as $t)
                <article class="bg-white border border-stone-200 rounded-xl p-4">
                    <div class="bg-stone-100 rounded-lg p-4 flex items-center justify-center h-44">
                        @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 240, 'maxH' => 140])
                    </div>
                    <p class="font-medium mt-3">{{ $t->name }}</p>
                    <p class="text-xs text-stone-500">{{ $t->width_mm }}×{{ $t->height_mm }} mm</p>
                </article>
            @empty
                <p class="text-stone-400">{{ __('common.no_data') }}</p>
            @endforelse
        </div>
    @else
        {{-- Items --}}
        <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
            @forelse($items as $i => $offer)
                <div class="flex items-center gap-4 px-4 py-3 {{ $i ? 'border-t border-stone-100' : '' }} {{ $offer->is_active ? '' : 'opacity-50' }}" wire:key="offer-{{ $offer->id }}">
                    <span class="w-6 text-xs tabular-nums {{ $offer->is_active && $i < $shown ? 'text-amber-700 font-semibold' : 'text-stone-400' }}">{{ $i + 1 }}</span>
                    @if($offer->image_url)
                        <img src="{{ $offer->image_url }}" alt="" class="w-12 h-12 object-cover rounded-lg border border-stone-200">
                    @else
                        <span class="w-12 h-12 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($offer->icon ?: 'heroicon-o-cube', 'w-5 h-5')</span>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="font-medium truncate"><a href="{{ route('admin.offers.edit', [$category, $offer]) }}" class="hover:text-amber-700">{{ $offer->title_uz }}</a></p>
                        <p class="text-xs text-stone-500 truncate">{{ $offer->description_uz }}</p>
                    </div>
                    <span class="flex items-center gap-2 text-stone-400 whitespace-nowrap">
                        <button wire:click="move({{ $offer->id }}, -1)" class="hover:text-stone-700">@svg('heroicon-o-chevron-up', 'w-4 h-4')</button>
                        <button wire:click="move({{ $offer->id }}, 1)" class="hover:text-stone-700">@svg('heroicon-o-chevron-down', 'w-4 h-4')</button>
                        <button wire:click="toggle({{ $offer->id }})" class="text-xs hover:text-amber-700">{{ $offer->is_active ? __('offers.hide') : __('offers.show') }}</button>
                        <a href="{{ route('admin.offers.edit', [$category, $offer]) }}" class="text-xs hover:text-amber-700">{{ __('common.edit') }}</a>
                        <button wire:click="delete({{ $offer->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-xs hover:text-rose-600">{{ __('common.delete') }}</button>
                    </span>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</p>
            @endforelse
        </div>
        <p class="text-xs text-stone-500 mt-2">{{ __('offers.order_hint', ['n' => $shown]) }}</p>
    @endif

</div>
