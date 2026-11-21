<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    $url = \App\Http\Controllers\Guest\HomeController::localizedUrl('marketplace.item', app()->getLocale(), ['offer' => $offer->slug]);
    $price = \App\Http\Controllers\Guest\MarketplaceController::numericPrice($offer->price);
    $phone = config('contact.phone');
    $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $product = [
        '@context' => 'https://schema.org', '@type' => 'Product',
        'name' => $offer->title, 'description' => $offer->description ?: $category->description,
        'category' => $category->name, 'url' => $url,
    ] + ($offer->image_url ? ['image' => url($offer->image_url)] : [])
      + ($price ? ['offers' => ['@type' => 'Offer', 'price' => $price, 'priceCurrency' => 'UZS', 'availability' => 'https://schema.org/InStock', 'url' => $url]] : []);
    $jsonLd = [$product, [
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('nav.offers'), 'item' => route('home') . '#offers'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => route('home') . '#offers'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $offer->title, 'item' => $url],
        ],
    ]];
    if ($faq) {
        $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all()];
    }
@endphp
@include('partials.public-head', [
    // "Name — Category | Markor"; a long name drops the category instead of being cut mid-word
    'seoTitle' => (mb_strlen($offer->title . ' — ' . $category->name) <= 50 ? $offer->title . ' — ' . $category->name : \Illuminate\Support\Str::limit($offer->title, 50)) . ' | ' . config('app.name'),
    'seoDescription' => \Illuminate\Support\Str::limit($offer->description ?: ($category->description ?: __('home.offers.subtitle')), 158),
    'seoRoute' => 'marketplace.item',
    'seoParams' => ['offer' => $offer->slug],
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main class="max-w-6xl mx-auto px-4 py-8">
    <nav class="text-xs text-stone-500 mb-5 flex flex-wrap items-center gap-1" aria-label="breadcrumb">
        <a href="{{ route('home') }}" class="hover:text-amber-700">{{ config('app.name') }}</a> ›
        <a href="{{ route('home') }}#offers" class="hover:text-amber-700">{{ __('nav.offers') }}</a> ›
        <span>{{ $category->name }}</span>
    </nav>

    <article class="grid md:grid-cols-2 gap-8 items-start">
        <div class="bg-white border border-stone-200 rounded-2xl p-6 flex items-center justify-center aspect-square">
            @if($offer->image_url)
                <img src="{{ $offer->image_url }}" alt="{{ $offer->title }}" class="max-h-full max-w-full object-contain">
            @else
                <span class="text-amber-700">@svg($offer->icon ?: $category->icon ?: 'heroicon-o-cube', 'w-24 h-24')</span>
            @endif
        </div>

        <div>
            <p class="text-sm text-amber-700 font-medium">{{ $category->name }}</p>
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight mt-1">{{ $offer->title }}</h1>
            @if($offer->description)<p class="text-stone-600 mt-4 leading-relaxed">{{ $offer->description }}</p>@endif

            <p class="mt-6 text-xl font-semibold text-stone-900">{{ $offer->price ?: __('offers.price_on_request') }}</p>

            <div class="flex flex-wrap gap-3 mt-6">
                @if($offer->action === 'call' && $tel)
                    <a href="{{ $tel }}" class="inline-flex items-center gap-2 px-5 py-3 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">@svg('heroicon-o-phone', 'w-5 h-5') {{ __('offers.action.call') }}</a>
                @else
                    <button type="button" x-data x-on:click="$dispatch('order-item', { offer: {{ $offer->id }}, title: @js($offer->title) })"
                            class="inline-flex items-center gap-2 px-5 py-3 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">@svg('heroicon-o-shopping-cart', 'w-5 h-5') {{ __('offers.action.order') }}</button>
                    @if($tel)<a href="{{ $tel }}" class="inline-flex items-center gap-2 px-5 py-3 border border-stone-300 rounded-xl font-medium hover:bg-stone-100">@svg('heroicon-o-phone', 'w-5 h-5') {{ $phone }}</a>@endif
                @endif
                @if($offer->url)
                    <a href="{{ $offer->url }}" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-2 px-5 py-3 border border-stone-300 rounded-xl font-medium hover:bg-stone-100">@svg('heroicon-o-arrow-top-right-on-square', 'w-5 h-5') {{ __('marketplace.in_shop') }}</a>
                @endif
            </div>
            @if($category->market_url)
                <p class="text-sm mt-4"><a href="{{ $category->market_url }}" target="_blank" rel="noopener nofollow" class="text-amber-700 hover:underline">{{ __('offers.more_items') }} →</a></p>
            @endif
        </div>
    </article>

    @if($faq)
        <section class="mt-12 max-w-3xl">
            <h2 class="font-display font-bold text-xl text-stone-900 mb-4">{{ __('marketplace.faq') }}</h2>
            <div class="space-y-3">
                @foreach($faq as $f)
                    <details class="bg-white border border-stone-200 rounded-xl p-4 group" @if($loop->first) open @endif>
                        <summary class="font-medium cursor-pointer list-none flex justify-between gap-4">{{ $f['q'] }} <span class="text-amber-700 group-open:rotate-45 transition">+</span></summary>
                        <p class="text-stone-600 mt-2 leading-relaxed">{{ $f['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    @if($related->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-display font-bold text-xl text-stone-900 mb-4">{{ __('marketplace.related', ['category' => $category->name]) }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($related as $item)
                    <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('marketplace.item', app()->getLocale(), ['offer' => $item->slug]) }}" class="bg-white border border-stone-200 rounded-xl p-3 hover:border-amber-400 transition">
                        @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy" class="h-28 w-full object-contain">@else<div class="h-28 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($item->icon ?: $category->icon ?: 'heroicon-o-cube', 'w-9 h-9')</div>@endif
                        <p class="font-semibold text-sm text-stone-900 mt-3 leading-snug">{{ $item->title }}</p>
                        <p class="text-xs text-stone-500 mt-1">{{ $item->price ?: __('offers.price_on_request') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>

<footer class="border-t border-stone-200 py-6 text-center text-xs text-stone-500">{{ config('app.name') }} · {{ __('app.tagline') }}</footer>
@include('partials.order-modal')
@livewireScripts
</body>
</html>
