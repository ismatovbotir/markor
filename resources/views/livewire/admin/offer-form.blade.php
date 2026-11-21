@php
    $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-white';
    $card = 'bg-white border border-stone-200 rounded-xl';
    $locales = \App\Http\Livewire\Admin\OfferForm::LOCALES;
    $len = fn ($k) => mb_strlen((string) ($item[$k] ?? ''));
    $section = function ($n, $title, $hint = null) {
        return '<div class="flex items-start gap-3 px-5 pt-5"><span class="w-6 h-6 shrink-0 rounded-full bg-stone-100 text-stone-500 text-xs font-semibold flex items-center justify-center">' . $n . '</span><div><h2 class="font-semibold text-sm text-stone-900">' . e($title) . '</h2>'
            . ($hint ? '<p class="text-xs text-stone-500 mt-0.5">' . e($hint) . '</p>' : '') . '</div></div>';
    };
    $tabs = function () use ($locales, $locale, $item) {
        $html = '<div class="inline-flex rounded-lg border border-stone-200 overflow-hidden text-sm">';
        foreach ($locales as $code => $name) {
            $filled = filled($item["title_$code"] ?? null);
            $html .= '<button type="button" wire:click="$set(\'locale\', \'' . $code . '\')" class="px-3 py-1.5 inline-flex items-center gap-1.5 ' . ($locale === $code ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50') . '">'
                . '<span class="w-1.5 h-1.5 rounded-full ' . ($filled ? 'bg-emerald-500' : ($code === 'uz' ? 'bg-rose-500' : 'bg-stone-300')) . '"></span>' . strtoupper($code) . '</button>';
        }

        return $html . '</div>';
    };
    $title = $item["title_$locale"] ?: $item['title_uz'];
    $text = $item["description_$locale"] ?: $item['description_uz'];
    $n = 1;
@endphp
<div class="w-full">
    {{-- header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div class="min-w-0">
            <a href="{{ route('admin.offers', $category) }}" class="inline-flex items-center gap-1 text-xs text-stone-500 hover:text-amber-700">@svg('heroicon-o-arrow-left', 'w-3.5 h-3.5') {{ __('nav.offers') }} · {{ $category->name_uz }}</a>
            <h1 class="text-xl font-semibold mt-1 flex items-center gap-3 flex-wrap">
                <span class="truncate">{{ $offerId ? ($item['title_uz'] ?: __('offers.edit_item')) : __('offers.add_item') }}</span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $item['is_active'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-stone-100 text-stone-500 ring-1 ring-stone-200' }}">
                    {{ $item['is_active'] ? __('banners.publish') : __('banners.hidden') }}
                </span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.offers', $category) }}" class="px-4 py-2 text-sm text-stone-600 rounded-lg hover:bg-stone-100">{{ __('common.cancel') }}</a>
            <button type="submit" form="offer-form" wire:loading.attr="disabled" wire:target="save, photo" class="inline-flex items-center gap-2 px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-60">
                <span wire:loading wire:target="save" class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                {{ __('common.save') }}
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ __('banners.fix_errors') }}</div>
    @endif

    <div class="grid xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <form id="offer-form" wire:submit="save" class="space-y-5 min-w-0">
            {{-- 1. Name and description --}}
            <section class="{{ $card }}">
                {!! $section($n++, __('offers.sec_texts'), __('banners.sec_content_hint')) !!}
                <div class="px-5 pt-4 flex items-center justify-between gap-3 flex-wrap">
                    {!! $tabs() !!}
                    <details class="group relative" @if($aiMessage) open @endif>
                        <summary class="list-none cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-violet-200 bg-violet-50 text-violet-800 text-sm hover:bg-violet-100">@svg('heroicon-o-sparkles', 'w-4 h-4') {{ __('ai.generate') }}</summary>
                        <div class="absolute right-0 z-20 mt-2 w-80 max-w-[85vw] rounded-xl border border-violet-200 bg-white shadow-xl p-3 space-y-2">
                            <p class="text-xs font-medium text-violet-900">{{ __('ai.offer_title') }}</p>
                            <textarea wire:model="aiNotes" rows="3" maxlength="1500" placeholder="{{ __('ai.notes_placeholder') }}" class="{{ $field }} text-xs"></textarea>
                            <p class="text-[11px] text-stone-500">{{ $ai->available() ? __('ai.offer_hint') : __('ai.unavailable.' . $ai->unavailableReason()) }}</p>
                            @if($aiMessage)<p class="text-xs {{ str_starts_with($aiMessage, '✓') ? 'text-emerald-700' : 'text-rose-600' }}">{{ $aiMessage }}</p>@endif
                            <button type="button" wire:click="generateContent" wire:loading.attr="disabled" wire:target="generateContent" @disabled(!$ai->available())
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="generateContent">@svg('heroicon-o-sparkles', 'w-4 h-4')</span>
                                <span wire:loading wire:target="generateContent">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                                {{ __('ai.generate') }}
                            </button>
                        </div>
                    </details>
                </div>
                <div class="px-5 pb-5 pt-4 space-y-4" wire:key="texts-{{ $locale }}">
                    <div>
                        <div class="flex items-baseline justify-between mb-1">
                            <label for="o-title" class="text-xs font-medium text-stone-700">{{ __('common.name') }} @if($locale === 'uz')<span class="text-rose-500">*</span>@endif</label>
                            <span class="text-[11px] tabular-nums text-stone-400">{{ $len("title_$locale") }} / 160</span>
                        </div>
                        <input id="o-title" wire:model.live.debounce.400ms="item.title_{{ $locale }}" type="text" maxlength="160" class="{{ $field }}">
                        @error("item.title_$locale")<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
                        @if($locale !== 'uz' && $errors->has('item.title_uz'))<p class="text-rose-600 text-xs mt-1">{{ __('banners.title_uz_required') }}</p>@endif
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between mb-1">
                            <label for="o-desc" class="text-xs font-medium text-stone-700">{{ __('offers.description') }}</label>
                            <span class="text-[11px] tabular-nums text-stone-400">{{ $len("description_$locale") }} / 400</span>
                        </div>
                        <textarea id="o-desc" wire:model.live.debounce.400ms="item.description_{{ $locale }}" rows="4" maxlength="400" class="{{ $field }}"></textarea>
                        @if($locale !== 'uz' && blank($item["description_$locale"]) && filled($item['description_uz']))<p class="text-[11px] text-stone-500 mt-1">{{ __('banners.fallback_uz') }}</p>@endif
                    </div>
                </div>
            </section>

            {{-- 2. Photo --}}
            <section class="{{ $card }}">
                {!! $section($n++, __('offers.sec_photo'), __('banners.image_hint')) !!}
                <div class="p-5 space-y-4">
                    <div class="flex items-center gap-4 flex-wrap">
                        <div class="w-40 h-32 rounded-lg border border-stone-200 bg-white flex items-center justify-center overflow-hidden shrink-0 relative">
                            @if($image)<img src="{{ $image }}" alt="" class="w-full h-full object-contain p-2">@else<span class="text-stone-300">@svg($category->icon ?: 'heroicon-o-photo', 'w-8 h-8')</span>@endif
                            <div wire:loading.flex wire:target="photo" class="absolute inset-0 bg-white/80 items-center justify-center"><span class="w-5 h-5 border-2 border-amber-200 border-t-amber-700 rounded-full animate-spin"></span></div>
                        </div>
                        <div class="space-y-2">
                            @if($imageSource)<p class="text-xs text-stone-500">{{ __('banners.source_' . $imageSource) }}</p>@endif
                            <div class="flex items-center gap-2 flex-wrap">
                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-stone-300 text-sm cursor-pointer hover:bg-stone-50">
                                    @svg('heroicon-o-arrow-up-tray', 'w-4 h-4') {{ $image ? __('banners.replace_image') : __('banners.upload') }}
                                    <input wire:model="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only">
                                </label>
                                @if($image)<button type="button" wire:click="dropImage" class="px-3 py-1.5 rounded-lg text-sm text-stone-500 hover:text-rose-600 hover:bg-rose-50">{{ __('banners.remove_image') }}</button>@endif
                            </div>
                            @error('photo')<p class="text-rose-600 text-xs">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <details class="rounded-lg border border-stone-200 bg-stone-50 text-sm" x-data="{ copied: false }">
                        <summary class="cursor-pointer px-3 py-2 font-medium text-stone-700">{{ __('banners.prompt_title') }}</summary>
                        <div class="px-3 pb-3 space-y-2">
                            <p class="text-[11px] text-stone-500">{{ __('banners.prompt_hint') }}</p>
                            <pre x-ref="prompt" class="whitespace-pre-wrap text-xs bg-white border border-stone-200 rounded-lg p-3 text-stone-700">{{ \App\Domain\Content\Banner::imagePrompt($item['title_uz'] ?: null) }}</pre>
                            <button type="button" x-on:click="navigator.clipboard.writeText($refs.prompt.innerText); copied = true; setTimeout(() => copied = false, 2000)" class="px-3 py-1.5 rounded-lg border border-stone-300 bg-white text-xs hover:bg-stone-50">
                                <span x-show="!copied">{{ __('banners.copy') }}</span><span x-show="copied" x-cloak>{{ __('banners.copied') }}</span>
                            </button>
                        </div>
                    </details>
                </div>
            </section>

            {{-- 3. FAQ --}}
            <section class="{{ $card }}">
                <div class="flex items-start justify-between gap-3 pr-5">
                    {!! $section($n++, __('ai.faq'), __('offers.faq_hint')) !!}
                    <span class="mt-5 text-xs text-stone-400 tabular-nums whitespace-nowrap">{{ count($item['faq']) }} / 8</span>
                </div>
                <div class="px-5 pt-4">{!! $tabs() !!}</div>
                <div class="p-5 space-y-3">
                    @forelse($item['faq'] as $i => $f)
                        <div class="rounded-lg border border-stone-200 p-3 space-y-2 relative" wire:key="faq-{{ $i }}-{{ $locale }}">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-stone-400 w-5">{{ $i + 1 }}.</span>
                                <input wire:model="item.faq.{{ $i }}.q_{{ $locale }}" type="text" maxlength="200" placeholder="{{ __('ai.question') }}" class="{{ $field }} font-medium">
                                <button type="button" wire:click="removeFaq({{ $i }})" class="w-8 h-8 shrink-0 rounded-md text-stone-400 hover:text-rose-600 hover:bg-rose-50" aria-label="{{ __('common.delete') }}">@svg('heroicon-o-trash', 'w-4 h-4 mx-auto')</button>
                            </div>
                            <textarea wire:model="item.faq.{{ $i }}.a_{{ $locale }}" rows="2" maxlength="600" placeholder="{{ __('ai.answer') }}" class="{{ $field }} ml-7 !w-[calc(100%-4.25rem)]"></textarea>
                        </div>
                    @empty
                        <p class="text-sm text-stone-400">{{ __('offers.faq_empty') }}</p>
                    @endforelse
                    @if(count($item['faq']) < 8)
                        <button type="button" wire:click="addFaq" class="inline-flex items-center gap-1.5 text-sm text-amber-700 hover:text-amber-800">@svg('heroicon-o-plus', 'w-4 h-4') {{ __('ai.faq_add') }}</button>
                    @endif
                </div>
            </section>

            {{-- 4. Button and link --}}
            <section class="{{ $card }}">
                {!! $section($n++, __('offers.sec_button')) !!}
                <div class="p-5 space-y-4">
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach(['order' => 'heroicon-o-shopping-bag', 'call' => 'heroicon-o-phone'] as $a => $icon)
                            <label class="cursor-pointer rounded-lg border p-3 flex items-start gap-3 transition {{ $item['action'] === $a ? 'border-amber-500 ring-2 ring-amber-200 bg-amber-50/40' : 'border-stone-200 hover:border-stone-300' }}">
                                <input type="radio" wire:model.live="item.action" value="{{ $a }}" class="sr-only">
                                @svg($icon, 'w-5 h-5 mt-0.5 ' . ($item['action'] === $a ? 'text-amber-700' : 'text-stone-400'))
                                <span><span class="block text-sm font-medium">{{ __('offers.action.' . $a) }}</span><span class="block text-[11px] text-stone-500 mt-0.5">{{ __('offers.action_hint.' . $a) }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    <div>
                        <label for="o-url" class="block text-xs font-medium text-stone-700 mb-1">{{ __('offers.item_url') }}</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-stone-400">@svg('heroicon-o-link', 'w-4 h-4')</span>
                            <input id="o-url" wire:model="item.url" type="url" placeholder="https://uzum.uz/…" class="{{ $field }} pl-9">
                        </div>
                        <p class="text-[11px] text-stone-500 mt-1">{{ __('offers.item_url_hint') }}</p>
                        @error('item.url')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            {{-- 5. Drivers (printers) --}}
            @if($category->slug === 'printers' || $chosenDrivers->isNotEmpty())
                <section class="{{ $card }}">
                    {!! $section($n++, __('nav.drivers'), __('offers.drivers_hint')) !!}
                    <div class="p-5 space-y-3">
                        @if($chosenDrivers->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($chosenDrivers as $d)
                                    <span class="inline-flex items-center gap-1 pl-3 pr-1 py-1 rounded-full bg-stone-100 text-sm" wire:key="drv-{{ $d->id }}">
                                        {{ $d->name() }}
                                        <button type="button" wire:click="removeDriver({{ $d->id }})" class="w-5 h-5 rounded-full text-stone-400 hover:text-rose-600 hover:bg-white" aria-label="{{ __('common.delete') }}">@svg('heroicon-o-x-mark', 'w-3.5 h-3.5 mx-auto')</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <select wire:model.live="addDriver" class="{{ $field }}">
                            <option value="">+ {{ __('offers.add_driver') }}</option>
                            @foreach($driverChoices as $brand => $list)
                                <optgroup label="{{ $brand }}">@foreach($list as $d)<option value="{{ $d->id }}">{{ $d->name() }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                    </div>
                </section>
            @endif

            {{-- 6. Publishing and address --}}
            <section class="{{ $card }}">
                {!! $section($n++, __('banners.sec_publish')) !!}
                <div class="p-5 space-y-4">
                    <label class="flex items-center justify-between gap-4 cursor-pointer">
                        <span class="text-sm text-stone-800">{{ __('banners.active') }}</span>
                        <input type="checkbox" wire:model.live="item.is_active" class="sr-only peer">
                        <span class="relative shrink-0 w-10 h-6 rounded-full bg-stone-300 peer-checked:bg-emerald-600 transition after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4"></span>
                    </label>
                    <div>
                        <p class="text-xs font-medium text-stone-700 mb-1">{{ __('offers.slug') }}</p>
                        <p class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-600 font-mono break-all">
                            @svg('heroicon-o-lock-closed', 'w-4 h-4 text-stone-400 shrink-0')
                            /marketplace/{{ $item['slug'] ?: '…' }}
                        </p>
                        <p class="text-[11px] text-stone-500 mt-1">{{ $offerId ? __('offers.slug_hint_fixed') : __('offers.slug_hint') }}</p>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.offers', $category) }}" class="px-4 py-2 text-sm text-stone-600 rounded-lg hover:bg-stone-100">{{ __('common.cancel') }}</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save, photo" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-60">{{ __('common.save') }}</button>
            </div>
        </form>

        {{-- preview: the catalog card as visitors see it --}}
        <aside class="xl:sticky xl:top-20 space-y-4 min-w-0">
            <div class="{{ $card }} overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-stone-100">
                    <p class="text-sm font-semibold">{{ __('banners.preview') }}</p>
                    <span class="text-xs text-stone-400 uppercase">{{ $locale }}</span>
                </div>
                <div class="p-5 bg-stone-50">
                    <div class="bg-white border border-stone-200 rounded-2xl p-4 max-w-xs mx-auto">
                        <div class="h-40 rounded-xl bg-white flex items-center justify-center">
                            @if($image)<img src="{{ $image }}" alt="" class="max-h-40 w-full object-contain">
                            @else<div class="h-40 w-full rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">@svg($category->icon ?: 'heroicon-o-cube', 'w-10 h-10')</div>@endif
                        </div>
                        <p class="font-semibold text-stone-900 mt-4 leading-snug">{{ $title ?: __('banners.preview_empty') }}</p>
                        @if($text)<p class="text-sm text-stone-500 mt-1.5 leading-relaxed line-clamp-3">{{ $text }}</p>@endif
                        <span class="mt-4 inline-flex w-full items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold {{ $item['action'] === 'call' ? 'border border-stone-900 text-stone-900' : 'bg-amber-700 text-white' }}">
                            @svg($item['action'] === 'call' ? 'heroicon-o-phone' : 'heroicon-o-shopping-bag', 'w-4 h-4') {{ __('offers.action.' . $item['action'], [], $locale) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="{{ $card }} p-4 text-sm space-y-2">
                <div class="flex justify-between gap-3"><span class="text-stone-500">{{ __('offers.category') }}</span><span class="font-medium">{{ $category->name_uz }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-stone-500">{{ __('ai.faq') }}</span><span class="font-medium">{{ collect($item['faq'])->filter(fn ($f) => filled($f['q_uz'] ?? null) && filled($f['a_uz'] ?? null))->count() }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-stone-500">{{ __('offers.languages') }}</span>
                    <span class="font-medium">@foreach($locales as $code => $name)<span class="{{ filled($item["title_$code"]) ? 'text-emerald-700' : 'text-stone-300' }}">{{ strtoupper($code) }}</span>@if(!$loop->last) · @endif @endforeach</span></div>
                @if($offerId && $item['is_active'])
                    <a href="{{ route('marketplace.item', \App\Domain\Offers\Models\Offer::find($offerId)?->slug ?? $item['slug']) }}" target="_blank" class="inline-flex items-center gap-1 text-amber-700 hover:underline pt-1">{{ __('banners.open_product') }} ↗</a>
                @endif
            </div>
        </aside>
    </div>
</div>
