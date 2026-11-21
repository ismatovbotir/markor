@php
    $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-white';
    $card = 'bg-white border border-stone-200 rounded-xl';
    $limits = \App\Http\Livewire\Admin\BannerForm::LIMITS;
    $locales = \App\Http\Livewire\Admin\BannerForm::LOCALES;
    $count = fn ($f) => mb_strlen((string) ($item["{$f}_{$locale}"] ?? ''));
    $section = function ($n, $title, $hint = null) {
        return '<div class="flex items-start gap-3 px-5 pt-5"><span class="w-6 h-6 shrink-0 rounded-full bg-stone-100 text-stone-500 text-xs font-semibold flex items-center justify-center">' . $n . '</span><div><h2 class="font-semibold text-sm text-stone-900">' . e($title) . '</h2>'
            . ($hint ? '<p class="text-xs text-stone-500 mt-0.5">' . e($hint) . '</p>' : '') . '</div></div>';
    };
@endphp
<div class="w-full">
    {{-- header: breadcrumb, title, status, actions --}}
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <a href="{{ route('admin.banners') }}" class="inline-flex items-center gap-1 text-xs text-stone-500 hover:text-amber-700">@svg('heroicon-o-arrow-left', 'w-3.5 h-3.5') {{ __('nav.banners') }}</a>
            <h1 class="text-xl font-semibold mt-1 flex items-center gap-3">
                {{ $bannerId ? __('banners.edit') : __('banners.new') }}
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $item['is_active'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-stone-100 text-stone-500 ring-1 ring-stone-200' }}">
                    {{ $item['is_active'] ? __('banners.publish') : __('banners.hidden') }}
                </span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.banners') }}" class="px-4 py-2 text-sm text-stone-600 rounded-lg hover:bg-stone-100">{{ __('common.cancel') }}</a>
            <button type="submit" form="banner-form" wire:loading.attr="disabled" wire:target="save, photo" class="inline-flex items-center gap-2 px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-60">
                <span wire:loading wire:target="save" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                {{ __('common.save') }}
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ __('banners.fix_errors') }}</div>
    @endif

    <div class="grid xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] gap-6 items-start">
        <form id="banner-form" wire:submit="save" class="space-y-5 min-w-0">
            {{-- 1. Product --}}
            <section class="{{ $card }}">
                {!! $section(1, __('banners.sec_product'), __('banners.product_hint')) !!}
                <div class="p-5 space-y-3">
                    <select wire:model.live="item.offer_id" class="{{ $field }}" aria-label="{{ __('banners.product') }}">
                        <option value="">{{ __('banners.product_none') }}</option>
                        @foreach($offers->groupBy(fn ($o) => $o->offerCategory?->name_uz ?? $o->category) as $cat => $list)
                            <optgroup label="{{ $cat }}">
                                @foreach($list as $o)<option value="{{ $o->id }}">{{ $o->title_uz }}{{ $o->is_active ? '' : ' (' . __('banners.hidden') . ')' }}{{ $o->image_path ? '' : ' · ' . __('banners.no_image') }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if($offer)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 bg-stone-50 p-3">
                            <div class="w-14 h-14 rounded-lg bg-white ring-1 ring-stone-200 flex items-center justify-center overflow-hidden shrink-0">
                                @if($offer->image_path)<img src="{{ \App\Domain\Offers\Models\Offer::publicUrl($offer->image_path) }}" alt="" class="w-full h-full object-contain">
                                @else @svg('heroicon-o-photo', 'w-6 h-6 text-stone-300') @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate">{{ $offer->title_uz }}</p>
                                <p class="text-xs text-stone-500">{{ $offer->offerCategory?->name_uz }} · @if(!$offer->is_active)<span class="text-rose-600">{{ __('banners.product_hidden') }}</span>@else<a href="/marketplace/{{ $offer->slug }}" target="_blank" class="text-amber-700 hover:underline">{{ __('banners.open_product') }} ↗</a>@endif</p>
                            </div>
                            <button type="button" wire:click="$set('item.offer_id', null)" class="text-xs text-stone-500 hover:text-rose-600 px-2">{{ __('banners.clear_product') }}</button>
                        </div>
                    @endif
                </div>
            </section>

            {{-- 2. Texts per language --}}
            <section class="{{ $card }}">
                {!! $section(2, __('banners.sec_content'), __('banners.sec_content_hint')) !!}
                <div class="px-5 pt-4">
                    <div class="inline-flex rounded-lg border border-stone-200 overflow-hidden text-sm">
                        @foreach($locales as $code => $name)
                            @php $filled = filled($item["title_$code"]); @endphp
                            <button type="button" wire:click="$set('locale', '{{ $code }}')" class="px-3 py-1.5 inline-flex items-center gap-1.5 {{ $locale === $code ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $filled ? 'bg-emerald-500' : ($code === 'uz' ? 'bg-rose-500' : 'bg-stone-300') }}"></span>{{ strtoupper($code) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="px-5 pb-5 pt-4 space-y-4" wire:key="texts-{{ $locale }}">
                    @foreach(['title' => 'input', 'text' => 'textarea', 'button' => 'input'] as $f => $tag)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 mb-1">
                                <label for="f-{{ $f }}" class="text-xs font-medium text-stone-700">{{ __('banners.' . $f) }}
                                    @if($f === 'title' && $locale === 'uz')<span class="text-rose-500">*</span>@endif</label>
                                <span class="text-[11px] tabular-nums {{ $count($f) > $limits[$f] * 0.9 ? 'text-amber-700' : 'text-stone-400' }}">{{ $count($f) }} / {{ $limits[$f] }}</span>
                            </div>
                            @if($tag === 'textarea')
                                <textarea id="f-{{ $f }}" wire:model.live.debounce.400ms="item.{{ $f }}_{{ $locale }}" rows="3" maxlength="{{ $limits[$f] }}" class="{{ $field }}"></textarea>
                            @else
                                <input id="f-{{ $f }}" wire:model.live.debounce.400ms="item.{{ $f }}_{{ $locale }}" type="text" maxlength="{{ $limits[$f] }}" @if($f === 'button') placeholder="{{ __('banners.more', [], $locale) }}" @endif class="{{ $field }}">
                            @endif
                            @error("item.{$f}_{$locale}")<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
                            @if($locale !== 'uz' && blank($item["{$f}_{$locale}"]) && filled($item["{$f}_uz"]))
                                <p class="text-[11px] text-stone-500 mt-1">{{ __('banners.fallback_uz') }}</p>
                            @endif
                        </div>
                    @endforeach
                    @if($locale !== 'uz' && $errors->has('item.title_uz'))<p class="text-rose-600 text-xs">{{ __('banners.title_uz_required') }}</p>@endif
                </div>
            </section>

            {{-- 3. Link and look --}}
            <section class="{{ $card }}">
                {!! $section(3, __('banners.sec_link')) !!}
                <div class="p-5 space-y-5">
                    <div>
                        <label for="f-url" class="block text-xs font-medium text-stone-700 mb-1">{{ __('banners.url') }}</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-stone-400">@svg('heroicon-o-link', 'w-4 h-4')</span>
                            <input id="f-url" wire:model.live.debounce.400ms="item.url" type="text" placeholder="/marketplace/zebra-zd421t · https://…" class="{{ $field }} pl-9">
                        </div>
                        @error('item.url')<p class="text-rose-600 text-xs mt-1">{{ __('banners.url_error') }}</p>@enderror
                    </div>
                    <div>
                        <p class="text-xs font-medium text-stone-700 mb-2">{{ __('banners.style') }}</p>
                        <div class="grid grid-cols-3 gap-3">
                            @foreach(\App\Domain\Content\Banner::THEMES as $s => $t)
                                <label class="cursor-pointer rounded-lg border p-2 transition {{ $item['style'] === $s ? 'border-amber-500 ring-2 ring-amber-200' : 'border-stone-200 hover:border-stone-300' }}">
                                    <input type="radio" wire:model.live="item.style" value="{{ $s }}" class="sr-only">
                                    <span class="block h-10 rounded-md {{ $t[0] }} relative overflow-hidden">
                                        <span class="absolute left-2 top-2 h-1.5 w-10 rounded {{ $s === 'light' ? 'bg-stone-800' : 'bg-white' }}"></span>
                                        <span class="absolute left-2 top-5 h-1 w-14 rounded {{ $s === 'light' ? 'bg-stone-400' : 'bg-white/60' }}"></span>
                                        <span class="absolute right-2 top-2 h-6 w-6 rounded bg-white ring-1 ring-black/5"></span>
                                    </span>
                                    <span class="block text-xs text-center mt-1.5 {{ $item['style'] === $s ? 'text-amber-800 font-medium' : 'text-stone-600' }}">{{ __('banners.styles.' . $s) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <label class="flex items-start justify-between gap-4 cursor-pointer">
                        <span><span class="block text-sm text-stone-800">{{ __('banners.sponsored') }}</span><span class="block text-[11px] text-stone-500 mt-0.5">{{ __('banners.sponsored_hint') }}</span></span>
                        <input type="checkbox" wire:model.live="item.is_sponsored" class="sr-only peer">
                        <span class="relative shrink-0 w-10 h-6 rounded-full bg-stone-300 peer-checked:bg-amber-600 transition after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                    </label>
                </div>
            </section>

            {{-- 4. Picture --}}
            <section class="{{ $card }}">
                {!! $section(4, __('banners.sec_image'), __('banners.image_hint')) !!}
                <div class="p-5 space-y-4">
                    @if($offer)
                        {{-- product banner: the product photo, or another picture uploaded for this banner --}}
                        @php $useOwn = (bool) $ownImage; @endphp
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div role="button" tabindex="0" @if($useOwn) wire:click="dropImage" x-on:keydown.enter="$wire.dropImage()" @endif class="text-left rounded-lg border p-3 transition cursor-pointer {{ !$useOwn ? 'border-amber-500 ring-2 ring-amber-200 bg-amber-50/40' : 'border-stone-200 hover:border-stone-300' }}">
                                <span class="flex items-center justify-between gap-2 text-sm font-medium">
                                    {{ __('banners.use_product_image') }}
                                    <span class="w-4 h-4 rounded-full border-2 {{ !$useOwn ? 'border-amber-600 bg-amber-600 ring-2 ring-inset ring-white' : 'border-stone-300' }}"></span>
                                </span>
                                <span class="mt-2 h-28 rounded-md bg-white ring-1 ring-stone-200 flex items-center justify-center overflow-hidden">
                                    @if($productImage)<img src="{{ $productImage }}" alt="" class="w-full h-full object-contain p-2">
                                    @else<span class="text-xs text-stone-400 text-center px-3">@svg('heroicon-o-photo', 'w-6 h-6 mx-auto mb-1 text-stone-300'){{ __('banners.product_no_image') }}</span>@endif
                                </span>
                                @unless($productImage)
                                    <a href="{{ route('admin.offers.edit', [$offer->offerCategory ?? $offer->category, $offer]) }}" target="_blank" x-on:click.stop class="block text-[11px] text-amber-700 hover:underline mt-2">{{ __('banners.add_product_image') }} ↗</a>
                                @endunless
                            </div>
                            <div class="rounded-lg border p-3 transition {{ $useOwn ? 'border-amber-500 ring-2 ring-amber-200 bg-amber-50/40' : 'border-stone-200' }}">
                                <span class="flex items-center justify-between gap-2 text-sm font-medium">
                                    {{ __('banners.use_own_image') }}
                                    <span class="w-4 h-4 rounded-full border-2 {{ $useOwn ? 'border-amber-600 bg-amber-600 ring-2 ring-inset ring-white' : 'border-stone-300' }}"></span>
                                </span>
                                <label class="mt-2 h-28 rounded-md flex items-center justify-center overflow-hidden cursor-pointer relative {{ $ownImage ? 'bg-white ring-1 ring-stone-200' : 'border-2 border-dashed border-stone-300 hover:border-amber-400 hover:bg-white' }}">
                                    @if($ownImage)<img src="{{ $ownImage }}" alt="" class="w-full h-full object-contain p-2">
                                    @else<span class="text-xs text-stone-500 text-center px-3">@svg('heroicon-o-arrow-up-tray', 'w-6 h-6 mx-auto mb-1 text-stone-400'){{ __('banners.upload') }}</span>@endif
                                    <input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                                    <span wire:loading.flex wire:target="photo" class="absolute inset-0 bg-white/80 items-center justify-center"><span class="w-5 h-5 border-2 border-amber-200 border-t-amber-700 rounded-full animate-spin"></span></span>
                                </label>
                                @if($ownImage)
                                    <label class="inline-block text-[11px] text-amber-700 hover:underline mt-2 cursor-pointer">{{ __('banners.replace_image') }}<input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only"></label>
                                @endif
                            </div>
                        </div>
                        @error('photo')<p class="text-rose-600 text-xs">{{ $message }}</p>@enderror
                    @else
                    <div class="flex items-center gap-4 flex-wrap">
                        <div class="w-40 h-28 rounded-lg border border-stone-200 bg-white flex items-center justify-center overflow-hidden shrink-0 relative">
                            @if($previewImage)
                                <img src="{{ $previewImage }}" alt="" class="w-full h-full object-contain p-2">
                            @else
                                <span class="text-stone-300">@svg('heroicon-o-photo', 'w-8 h-8')</span>
                            @endif
                            <div wire:loading.flex wire:target="photo" class="absolute inset-0 bg-white/80 items-center justify-center"><span class="w-5 h-5 border-2 border-amber-200 border-t-amber-700 rounded-full animate-spin"></span></div>
                        </div>
                        <div class="space-y-2">
                            @if($imageSource)<p class="text-xs text-stone-500">{{ __('banners.source_' . $imageSource) }}</p>@endif
                            <div class="flex items-center gap-2 flex-wrap">
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 text-sm cursor-pointer hover:bg-stone-50">
                                    @svg('heroicon-o-arrow-up-tray', 'w-4 h-4') {{ $previewImage ? __('banners.replace_image') : __('banners.upload') }}
                                    <input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                                </label>
                                @if($imageSource === 'upload' || $imageSource === 'own')
                                    <button type="button" wire:click="dropImage" class="px-3 py-1.5 rounded-lg text-sm text-stone-500 hover:text-rose-600 hover:bg-rose-50">{{ __('banners.remove_image') }}</button>
                                @endif
                            </div>
                            @error('photo')<p class="text-rose-600 text-xs">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @endif
                    <details class="rounded-lg border border-stone-200 bg-stone-50 text-sm" x-data="{ copied: false }">
                        <summary class="cursor-pointer px-3 py-2 font-medium text-stone-700">{{ __('banners.prompt_title') }}</summary>
                        <div class="px-3 pb-3 space-y-2">
                            <p class="text-[11px] text-stone-500">{{ __('banners.prompt_hint') }}</p>
                            <pre x-ref="prompt" class="whitespace-pre-wrap text-xs bg-white border border-stone-200 rounded-lg p-3 text-stone-700">{{ \App\Domain\Content\Banner::imagePrompt($offer?->title_uz) }}</pre>
                            <button type="button" x-on:click="navigator.clipboard.writeText($refs.prompt.innerText); copied = true; setTimeout(() => copied = false, 2000)" class="px-3 py-1.5 rounded-lg border border-stone-300 bg-white text-xs hover:bg-stone-50">
                                <span x-show="!copied">{{ __('banners.copy') }}</span><span x-show="copied" x-cloak>{{ __('banners.copied') }}</span>
                            </button>
                        </div>
                    </details>
                </div>
            </section>

            {{-- 5. Publishing --}}
            <section class="{{ $card }}">
                {!! $section(5, __('banners.sec_publish'), __('banners.dates_hint')) !!}
                <div class="p-5 space-y-4">
                    <label class="flex items-center justify-between gap-4 cursor-pointer">
                        <span class="text-sm text-stone-800">{{ __('banners.active') }}</span>
                        <input type="checkbox" wire:model.live="item.is_active" class="sr-only peer">
                        <span class="relative shrink-0 w-10 h-6 rounded-full bg-stone-300 peer-checked:bg-emerald-600 transition after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                    </label>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block text-xs font-medium text-stone-700">{{ __('banners.starts') }}
                            <input wire:model.live="item.starts_at" type="date" class="{{ $field }} mt-1">
                        </label>
                        <label class="block text-xs font-medium text-stone-700">{{ __('banners.ends') }}
                            <input wire:model.live="item.ends_at" type="date" class="{{ $field }} mt-1">
                            @error('item.ends_at')<span class="text-rose-600 text-xs">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.banners') }}" class="px-4 py-2 text-sm text-stone-600 rounded-lg hover:bg-stone-100">{{ __('common.cancel') }}</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save, photo" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-60">{{ __('common.save') }}</button>
            </div>
        </form>

        {{-- live preview: the real home slide, scaled to the column --}}
        <aside class="xl:sticky xl:top-20 min-w-0">
            <div class="{{ $card }} overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-stone-100">
                    <p class="text-sm font-semibold">{{ __('banners.preview') }}</p>
                    <div class="flex rounded-md border border-stone-200 overflow-hidden text-xs">
                        @foreach($locales as $code => $name)
                            <button type="button" wire:click="$set('locale', '{{ $code }}')" class="px-2 py-1 {{ $locale === $code ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-50' }}">{{ strtoupper($code) }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="p-4 bg-stone-50">
                    <div x-data="{ w: 1152, s: 1, h: 0, fit() { this.s = Math.min(1, this.$el.clientWidth / this.w); this.h = this.$refs.slide.offsetHeight * this.s } }"
                         x-init="fit(); const ro = new ResizeObserver(() => fit()); ro.observe($el); ro.observe($refs.slide)"
                         class="relative overflow-hidden rounded-lg ring-1 ring-stone-200 shadow-sm" :style="`height: ${h}px`">
                        <div x-ref="slide" class="{{ $preview->theme()[0] }} absolute top-0 left-0 pointer-events-none" :style="`width: ${w}px; transform: scale(${s}); transform-origin: top left`">
                            @if(filled($preview->localized('title', $locale)))
                                @include('partials.banner-slide', ['b' => $preview, 'locale' => $locale, 'image' => $previewImage])
                            @else
                                <div class="h-96 flex items-center justify-center text-2xl text-stone-400">{{ __('banners.preview_empty') }}</div>
                            @endif
                        </div>
                    </div>
                    <p class="text-[11px] text-stone-500 mt-3">{{ __('banners.preview_hint') }}</p>
                </div>
            </div>
        </aside>
    </div>
</div>
