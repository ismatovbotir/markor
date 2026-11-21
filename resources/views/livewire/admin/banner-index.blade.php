@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div>
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.banners') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('banners.hint') }}</p>
        </div>
        <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">+ {{ __('banners.add') }}</button>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl divide-y divide-stone-100">
        @foreach($banners as $b)
            <div class="flex items-center gap-4 px-4 py-3" wire:key="banner-{{ $b->id }}">
                <span class="w-6 text-xs text-stone-400 text-right">{{ $loop->iteration }}</span>
                <div class="w-28 h-14 rounded-lg overflow-hidden shrink-0 flex items-center justify-center {{ ['light' => 'bg-stone-100', 'dark' => 'bg-stone-900', 'amber' => 'bg-amber-700'][$b->style] ?? 'bg-stone-100' }}">
                    @if($b->is_default)<img src="/images/logo.png" alt="" class="h-5 w-auto">
                    @elseif($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="" class="h-full w-full object-contain">
                    @else<span class="text-xs {{ $b->style === 'light' ? 'text-stone-500' : 'text-white' }}">{{ __('banners.no_image') }}</span>@endif
                </div>
                <div class="flex-1 min-w-0">
                    @if($b->is_default)
                        <p class="font-medium flex items-center gap-2">{{ __('home.h1') }} <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-normal">@svg('heroicon-o-star', 'w-3 h-3 inline') {{ __('banners.default') }}</span></p>
                        <p class="text-xs text-stone-500">{{ __('banners.default_hint') }}</p>
                    @else
                        <p class="font-medium truncate">{{ $b->title_uz }} @if($b->is_sponsored)<span class="ml-1 px-1.5 py-0.5 rounded bg-stone-100 text-stone-500 text-[10px] font-normal">{{ __('banners.ad') }}</span>@endif</p>
                        <p class="text-xs text-stone-500 truncate">{{ $b->link() ?: '—' }}
                            @if($b->starts_at || $b->ends_at) · {{ $b->starts_at?->format('d.m.Y') ?? '…' }} – {{ $b->ends_at?->format('d.m.Y') ?? '…' }}@endif</p>
                    @endif
                </div>
                @unless($b->is_default)
                    <div class="flex items-center gap-1 shrink-0 text-xs">
                        <button wire:click="move({{ $b->id }}, -1)" class="px-1.5 text-stone-400 hover:text-stone-800" title="↑">↑</button>
                        <button wire:click="move({{ $b->id }}, 1)" class="px-1.5 text-stone-400 hover:text-stone-800" title="↓">↓</button>
                        <button wire:click="toggle({{ $b->id }})" class="px-2 {{ $b->is_active ? 'text-emerald-700' : 'text-stone-400' }}">{{ $b->is_active ? __('common.active') : __('banners.hidden') }}</button>
                        <button wire:click="openEdit({{ $b->id }})" class="px-2 text-stone-500 hover:text-amber-700">{{ __('common.edit') }}</button>
                        <button wire:click="delete({{ $b->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="px-2 text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-start justify-center overflow-y-auto py-8 px-4">
            <form wire:submit="save" class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ $editingId ? __('common.edit') : __('banners.add') }}</h2>
                <div class="grid sm:grid-cols-3 gap-3">
                    @foreach(['uz' => 'UZ', 'ru' => 'RU', 'zh' => '中文'] as $l => $lang)
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-stone-600">{{ __('banners.title') }} ({{ $lang }})
                                <input wire:model="item.title_{{ $l }}" type="text" maxlength="160" class="{{ $field }} mt-1">
                            </label>
                            @error("item.title_$l")<p class="text-rose-500 text-xs">{{ $message }}</p>@enderror
                            <label class="block text-xs font-medium text-stone-600">{{ __('banners.text') }} ({{ $lang }})
                                <textarea wire:model="item.text_{{ $l }}" rows="3" maxlength="400" class="{{ $field }} mt-1"></textarea>
                            </label>
                            <label class="block text-xs font-medium text-stone-600">{{ __('banners.button') }} ({{ $lang }})
                                <input wire:model="item.button_{{ $l }}" type="text" maxlength="60" placeholder="{{ __('banners.button_placeholder') }}" class="{{ $field }} mt-1">
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-xs font-medium text-stone-600">{{ __('banners.url') }}
                        <input wire:model="item.url" type="text" placeholder="/marketplace/zebra-zd230 · https://…" class="{{ $field }} mt-1">
                        @error('item.url')<span class="text-rose-500 text-xs">{{ __('banners.url_error') }}</span>@enderror
                    </label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('banners.style') }}
                        <select wire:model="item.style" class="{{ $field }} mt-1">
                            @foreach(\App\Domain\Content\Banner::STYLES as $s)<option value="{{ $s }}">{{ __('banners.styles.' . $s) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('banners.starts') }}
                        <input wire:model="item.starts_at" type="date" class="{{ $field }} mt-1">
                    </label>
                    <label class="block text-xs font-medium text-stone-600">{{ __('banners.ends') }}
                        <input wire:model="item.ends_at" type="date" class="{{ $field }} mt-1">
                        @error('item.ends_at')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="flex items-center gap-4 flex-wrap" x-data="{ preview: null }">
                    <img x-show="preview" x-cloak :src="preview" alt="" class="h-20 rounded-lg border border-amber-300">
                    @if(!$photo && !empty($item['image_path']))<img x-show="!preview" src="{{ \App\Domain\Offers\Models\Offer::publicUrl($item['image_path']) }}" alt="" class="h-20 rounded-lg border border-stone-200">@endif
                    <label class="text-xs font-medium text-stone-600">{{ __('banners.image') }}
                        <input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" class="block mt-1 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-700">
                    </label>
                    @error('photo')<p class="text-rose-500 text-xs">{{ $message }}</p>@enderror
                    <p class="text-[11px] text-stone-500 w-full">{{ __('banners.image_hint') }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm"><input wire:model="item.is_active" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('banners.active') }}</label>
                <label class="flex items-start gap-2 text-sm"><input wire:model="item.is_sponsored" type="checkbox" class="mt-0.5 rounded border-stone-300 text-amber-600">
                    <span>{{ __('banners.sponsored') }}<span class="block text-[11px] text-stone-500">{{ __('banners.sponsored_hint') }}</span></span></label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save, photo" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
