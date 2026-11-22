@php
    use App\Domain\Content\Banner;
    use App\Domain\Offers\Models\Offer;
    $moreDefaults = collect(\App\Support\AdminI18n::locales())->mapWithKeys(fn ($l) => [$l => __('banners.more', [], $l)])->all();
    $choice = 'rounded-lg border p-3 transition';
    $choiceOn = 'border-amber-600 ring-2 ring-amber-200 bg-amber-50/50';
    $choiceOff = 'border-stone-300 hover:border-stone-400';
    $radio = fn (bool $on) => 'w-4 h-4 shrink-0 rounded-full border-2 ' . ($on ? 'border-amber-700 bg-amber-700 ring-2 ring-inset ring-white' : 'border-stone-400');
@endphp
<div>
    <x-admin.i18n-form submit="save" :cancel="route('admin.banners')" wide-aside>
        <x-slot:header>
            <x-admin.page-header :back="route('admin.banners')" :back-label="__('nav.group.content') . ' · ' . __('nav.banners')"
                                 :title="$banner?->title_uz ?: __('banners.new')" />
        </x-slot:header>

        {{-- the preview is rendered on the server: it follows the one language switch of the page --}}
        <x-admin.lang-tabs x-init="$watch('lang', (l) => $wire.set('locale', l))" />

        <x-admin.section :title="__('banners.sec_content')" :hint="__('banners.sec_content_hint')">
            <x-admin.i18n-field name="item.title" :label="__('banners.title')" :max="$limits['title']" required live />
            <x-admin.i18n-field name="item.text" :label="__('banners.text')" type="textarea" rows="3" :max="$limits['text']" live />
            <x-admin.i18n-field name="item.button" :label="__('banners.button')" :max="$limits['button']" fallback="uz-default" :defaults="$moreDefaults" live />
        </x-admin.section>

        <x-admin.section :title="__('banners.sec_product')" :hint="__('banners.product_hint')" shared>
            <x-admin.field name="item.offer_id" :label="__('banners.product')" id="f-item-offer">
                <select id="f-item-offer" wire:model.live="item.offer_id" data-key="item.offer_id" data-label="{{ __('banners.product') }}" class="admin-input">
                    <option value="">{{ __('banners.product_none') }}</option>
                    @foreach($offers->groupBy(fn ($o) => $o->offerCategory?->name_uz ?? $o->category) as $cat => $list)
                        <optgroup label="{{ $cat }}">
                            @foreach($list as $o)<option value="{{ $o->id }}">{{ $o->title_uz }}{{ $o->is_active ? '' : ' (' . __('banners.hidden') . ')' }}{{ $o->image_path ? '' : ' · ' . __('banners.no_image') }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-admin.field>
            @if($offer)
                <div class="flex items-center gap-3 rounded-lg border border-stone-200 bg-stone-50 p-3">
                    <div class="w-14 h-14 rounded-lg bg-white ring-1 ring-stone-200 flex items-center justify-center overflow-hidden shrink-0 text-stone-400">
                        @if($offer->image_path)<img src="{{ Offer::publicUrl($offer->image_path) }}" alt="" class="w-full h-full object-contain">
                        @else @svg('heroicon-o-photo', 'w-6 h-6') @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-stone-900 truncate">{{ $offer->title_uz }}</p>
                        <p class="text-xs text-stone-600">{{ $offer->offerCategory?->name_uz }} ·
                            @if(!$offer->is_active)<span class="text-rose-700">{{ __('banners.product_hidden') }}</span>
                            @else<a href="/marketplace/{{ $offer->slug }}" target="_blank" rel="noopener" class="admin-focus rounded text-amber-800 hover:underline">{{ __('banners.open_product') }} ↗</a>@endif</p>
                    </div>
                    <x-admin.button size="sm" variant="ghost" wire:click="$set('item.offer_id', null)">{{ __('banners.clear_product') }}</x-admin.button>
                </div>
            @endif
        </x-admin.section>

        <x-admin.section :title="__('banners.sec_link')" shared>
            <x-admin.field name="item.url" :label="__('banners.url')" placeholder="/marketplace/zebra-zd421t · https://…" live />
            <div>
                <p class="text-[13px] font-medium text-stone-700 mb-1">{{ __('banners.style') }}</p>
                <x-admin.radio-cards model="item.style" :legend="__('banners.style')" live cols="grid-cols-3"
                                     :options="collect(Banner::THEMES)->map(fn ($t, $s) => ['label' => __('banners.styles.' . $s), 'swatch' => $t[0]])->all()" />
            </div>
            <x-admin.switch model="item.is_sponsored" :label="__('banners.sponsored')" :hint="__('banners.sponsored_hint')" live />
        </x-admin.section>

        <x-admin.section :title="__('banners.sec_image')" shared>
            @if($offer)
                {{-- product banner: the product photo, or another picture uploaded for this banner --}}
                @php $useOwn = (bool) $ownImage; @endphp
                <div class="grid sm:grid-cols-2 gap-3">
                    <div @if($useOwn) role="button" tabindex="0" wire:click="dropImage" x-on:keydown.enter="$wire.dropImage()" @endif
                         class="admin-focus text-left {{ $choice }} {{ !$useOwn ? $choiceOn : $choiceOff . ' cursor-pointer' }}">
                        <span class="flex items-center justify-between gap-2 text-sm font-medium text-stone-900">{{ __('banners.use_product_image') }}<span class="{{ $radio(!$useOwn) }}"></span></span>
                        <span class="mt-2 h-28 rounded-md bg-white ring-1 ring-stone-200 flex items-center justify-center overflow-hidden">
                            @if($productImage)<img src="{{ $productImage }}" alt="" class="w-full h-full object-contain p-2">
                            @else<span class="text-xs text-stone-600 text-center px-3">@svg('heroicon-o-photo', 'w-6 h-6 mx-auto mb-1 text-stone-400'){{ __('banners.product_no_image') }}</span>@endif
                        </span>
                        @unless($productImage)
                            <a href="{{ route('admin.offers.edit', [$offer->offerCategory ?? $offer->category, $offer]) }}" target="_blank" rel="noopener" x-on:click.stop class="admin-focus rounded inline-block text-xs text-amber-800 hover:underline mt-2">{{ __('banners.add_product_image') }} ↗</a>
                        @endunless
                    </div>
                    <div class="{{ $choice }} {{ $useOwn ? $choiceOn : 'border-stone-300' }}">
                        <span class="flex items-center justify-between gap-2 text-sm font-medium text-stone-900">{{ __('banners.use_own_image') }}<span class="{{ $radio($useOwn) }}"></span></span>
                        <label class="mt-2 h-28 rounded-md flex items-center justify-center overflow-hidden cursor-pointer relative has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-amber-700 {{ $ownImage ? 'bg-white ring-1 ring-stone-200' : 'border-2 border-dashed border-stone-300 hover:border-amber-500 hover:bg-white' }}">
                            @if($ownImage)<img src="{{ $ownImage }}" alt="" class="w-full h-full object-contain p-2">
                            @else<span class="text-xs text-stone-600 text-center px-3">@svg('heroicon-o-arrow-up-tray', 'w-6 h-6 mx-auto mb-1 text-stone-500'){{ __('banners.upload') }}</span>@endif
                            <input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" data-key="photo" data-label="{{ __('banners.sec_image') }}" class="sr-only" aria-label="{{ $ownImage ? __('banners.replace_image') : __('banners.upload') }}">
                            <span wire:loading.flex wire:target="photo" class="absolute inset-0 bg-white/80 items-center justify-center"><span class="w-5 h-5 border-2 border-amber-200 border-t-amber-700 rounded-full animate-spin motion-reduce:animate-none"></span></span>
                        </label>
                        @if($ownImage)<p class="text-xs text-stone-500 mt-2">{{ __('banners.replace_hint') }}</p>@endif
                    </div>
                </div>
                @error('photo')<p role="alert" class="text-xs text-rose-700 flex items-start gap-1"><span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span>{{ $message }}</span></p>@enderror
                <p class="text-xs text-stone-500">{{ __('banners.image_hint') }}</p>
            @else
                <x-admin.image-upload model="photo" :image="$previewImage" :note="$imageSource ? __('banners.source_' . $imageSource) : null"
                                      :remove="in_array($imageSource, ['upload', 'own'], true) ? 'dropImage' : null" :hint="__('banners.image_hint')" />
            @endif
            <x-admin.prompt-box :title="__('banners.prompt_title')" :hint="__('banners.prompt_hint')" :text="Banner::imagePrompt($offer?->title_uz)" />
        </x-admin.section>

        <x-admin.section :title="__('banners.sec_dates')" :hint="__('banners.dates_hint')" shared>
            <div class="grid sm:grid-cols-2 gap-4 max-w-md">
                <x-admin.field name="item.starts_at" :label="__('banners.starts')" type="date" />
                <x-admin.field name="item.ends_at" :label="__('banners.ends')" type="date" />
            </div>
        </x-admin.section>

        <x-slot:aside>
            {{-- live preview: the real home slide, scaled to the column, in the language of the switch --}}
            <div class="bg-white border border-stone-200 rounded-xl overflow-hidden" data-preview>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-stone-200">
                    <h2 class="text-sm font-semibold text-stone-900">{{ __('banners.preview') }}</h2>
                    <span class="text-xs font-semibold text-stone-600" x-text="lang.toUpperCase()">{{ strtoupper($locale) }}</span>
                </div>
                <div class="p-4 bg-stone-50">
                    <div x-data="{ w: 1152, s: 1, h: 0, fit() { this.s = Math.min(1, this.$el.clientWidth / this.w); this.h = this.$refs.slide.offsetHeight * this.s } }"
                         x-init="fit(); const ro = new ResizeObserver(() => fit()); ro.observe($el); ro.observe($refs.slide)"
                         class="relative overflow-hidden rounded-lg ring-1 ring-stone-200 shadow-sm" :style="`height: ${h}px`">
                        <div x-ref="slide" class="{{ $preview->theme()[0] }} absolute top-0 left-0 pointer-events-none" :style="`width: ${w}px; transform: scale(${s}); transform-origin: top left`">
                            @if(filled($preview->localized('title', $locale)))
                                @include('partials.banner-slide', ['b' => $preview, 'locale' => $locale, 'image' => $previewImage])
                            @else
                                <div class="h-96 flex items-center justify-center text-2xl text-stone-500">{{ __('banners.preview_empty') }}</div>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-stone-500 mt-3">{{ __('banners.preview_hint') }}</p>
                </div>
            </div>
            <x-admin.publish-card model="item.is_active" date="item.starts_at">
                @if($banner)<p class="text-xs text-stone-500">{{ __('admin.list.updated') }}: {{ $banner->updated_at?->format('d.m.Y H:i') }}</p>@endif
            </x-admin.publish-card>
        </x-slot:aside>
    </x-admin.i18n-form>

    <x-admin.confirm-fill :fields="$fillConflicts" :title="__('admin.fill.product_title')" apply="applyFill" cancel="cancelFill" />
</div>
