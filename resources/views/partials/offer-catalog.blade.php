{{-- "Biz taklif qilamiz" catalogue: each category with up to N items and a "more items" link to the marketplace.
     Needs $catalog (OfferCategory::catalog()); optional $templatePreviews [template_id => html] for the labels category. --}}
@php
    $catPhone = config('contact.phone');
    $catTel = $catPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $catPhone) : null;
    $shown = \App\Domain\Offers\Models\OfferCategory::SHOWN_ITEMS;
    $labelTemplates = $catalog->contains(fn ($c) => $c->isTemplates())
        ? \App\Domain\Printing\Models\LabelTemplate::orderByDesc('is_default')->orderBy('name')->limit($shown)->get() : collect();
    $templatePreviews ??= [];
@endphp
<div class="space-y-10" x-data>
    @foreach($catalog as $category)
        @php $items = $category->isTemplates() ? $labelTemplates : $category->offers->take($shown); @endphp
        @continue($items->isEmpty() && !$category->market_url)
        <section>
            <div class="flex items-end justify-between gap-4 flex-wrap mb-4">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 text-amber-800 shrink-0">@svg($category->icon ?: 'heroicon-o-cube', 'w-5 h-5')</span>
                    <div>
                        <h3 class="font-semibold text-stone-900">{{ $category->name }}</h3>
                        @if($category->description)<p class="text-sm text-stone-500">{{ $category->description }}</p>@endif
                    </div>
                </div>

            </div>
            @php
                // rows of 4 (2 on tablets): a short row is completed by a "request" card so every section ends at the same edge
                $count = $items->count() + ($category->market_url ? 1 : 0);
                $lgSpan = (4 - $count % 4) % 4;
                $smSpan = $count % 2;
            @endphp
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($items as $item)
                    @if($category->isTemplates())
                        <article class="bg-white border border-stone-200 rounded-xl p-3 flex flex-col">
                            <img src="{{ route('label-image', ['w' => $item->width_mm, 'h' => $item->height_mm]) }}" alt="{{ $item->width_mm }}×{{ $item->height_mm }} mm" loading="lazy" class="h-28 w-full object-contain rounded-lg">
                            <p class="font-semibold text-sm text-stone-900 mt-3">{{ $item->width_mm }}×{{ $item->height_mm }} mm</p>
                            <p class="text-xs text-stone-500 flex-1">{{ __('offers.label_card_text') }}</p>
                            <button type="button" x-on:click="$dispatch('order-item', { template: {{ $item->id }}, title: @js($item->width_mm . '×' . $item->height_mm . ' mm — ' . __('offers.labels_title')) })"
                                    class="mt-3 text-xs font-semibold text-amber-700 hover:text-amber-900 text-left">{{ __('offers.action.order') }} →</button>
                        </article>
                    @else
                        <article class="bg-white border border-stone-200 rounded-xl p-3 flex flex-col">
                            @php $itemUrl = \App\Http\Controllers\Guest\HomeController::localizedUrl('marketplace.item', app()->getLocale(), ['offer' => $item->slug]); @endphp
                            <a href="{{ $itemUrl }}" class="block group">
                                @if($item->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy" class="h-28 w-full object-contain rounded-lg bg-white">
                                @else
                                    <div class="h-28 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($item->icon ?: $category->icon ?: 'heroicon-o-cube', 'w-9 h-9')</div>
                                @endif
                                <h4 class="font-semibold text-sm text-stone-900 mt-3 leading-snug group-hover:text-amber-700">{{ $item->title }}</h4>
                            </a>
                            <p class="text-xs text-stone-600 mt-1 leading-relaxed flex-1">{{ $item->description }}</p>
                            @if($faq = $item->localizedFaq())
                                <details class="mt-2 text-xs group">
                                    <summary class="cursor-pointer text-amber-700 hover:text-amber-900 list-none flex items-center gap-1">@svg('heroicon-o-question-mark-circle', 'w-3.5 h-3.5') {{ __('ai.faq_public', ['n' => count($faq)]) }}</summary>
                                    <dl class="mt-2 space-y-2">
                                        @foreach($faq as $f)
                                            <div><dt class="font-medium text-stone-800">{{ $f['q'] }}</dt><dd class="text-stone-600 mt-0.5">{{ $f['a'] }}</dd></div>
                                        @endforeach
                                    </dl>
                                </details>
                            @endif
                            <div class="flex items-center justify-between gap-2 mt-3">
                                <span class="text-xs text-stone-500">{{ $item->price ?: __('offers.price_on_request') }}</span>
                                @if($item->action === 'call' && $catTel)
                                    <a href="{{ $catTel }}" class="text-xs font-semibold text-amber-700 hover:text-amber-900">{{ __('offers.action.call') }}</a>
                                @else
                                    <button type="button" x-on:click="$dispatch('order-item', { offer: {{ $item->id }}, title: @js($item->title) })" class="text-xs font-semibold text-amber-700 hover:text-amber-900">{{ __('offers.action.order') }} →</button>
                                @endif
                            </div>
                        </article>
                    @endif
                @endforeach
                @if($lgSpan || $smSpan)
                    <div @class([
                        'hidden sm:flex border-2 border-dashed border-stone-300 rounded-xl p-5 flex-col items-center justify-center text-center gap-2',
                        'sm:col-span-1' => $smSpan, 'sm:col-span-2' => !$smSpan,
                        'lg:flex' => $lgSpan, 'lg:hidden' => !$lgSpan,
                        'lg:col-span-1' => $lgSpan === 1, 'lg:col-span-2' => $lgSpan === 2, 'lg:col-span-3' => $lgSpan === 3,
                    ])>
                        @svg('heroicon-o-chat-bubble-left-ellipsis', 'w-6 h-6 text-amber-700')
                        <p class="font-semibold text-sm text-stone-900">{{ __('offers.filler_title') }}</p>
                        <p class="text-xs text-stone-500 max-w-xs">{{ __('offers.filler_text') }}</p>
                        <div class="flex flex-wrap items-center justify-center gap-3 mt-1">
                            <button type="button" x-on:click="$dispatch('order-item', { category: {{ $category->id }}, title: @js($category->name) })" class="text-xs font-semibold text-amber-700 hover:text-amber-900">{{ __('offers.filler_button') }} →</button>
                            @if($catTel)<a href="{{ $catTel }}" class="text-xs text-stone-600 hover:text-amber-700">{{ __('offers.action.call') }}</a>@endif
                        </div>
                    </div>
                @endif
                @if($category->market_url)
                    <a href="{{ $category->market_url }}" target="_blank" rel="noopener"
                       class="border-2 border-dashed border-amber-300 rounded-xl p-3 flex flex-col items-center justify-center text-center text-amber-800 hover:bg-amber-50 transition min-h-40">
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-6 h-6')
                        <span class="font-semibold text-sm mt-2">{{ __('offers.more_items') }}</span>
                        <span class="text-xs text-stone-500 mt-1">{{ parse_url($category->market_url, PHP_URL_HOST) }}</span>
                    </a>
                @endif
            </div>
        </section>
    @endforeach
</div>
@include('partials.order-modal')
