<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    use App\Http\Controllers\Guest\HomeController;
    $name = $driver->name();
    $loc = app()->getLocale();
    $steps = collect(range(1, 5))->map(fn ($i) => __("drivers.steps.$i", ['name' => $name, 'host' => $driver->host()]))->all();
    $faq = collect(range(1, 4))->map(fn ($i) => ['q' => __("drivers.faq.$i.q", ['name' => $name]), 'a' => __("drivers.faq.$i.a", ['name' => $name, 'language' => $driver->language ?: '—'])])->all();
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('drivers.title'), 'item' => route('drivers')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => route('drivers.show', $driver)],
        ]],
        ['@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => __('drivers.install_h2', ['name' => $name]),
         'step' => collect($steps)->values()->map(fn ($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'text' => $s])->all()],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all()],
    ];
@endphp
@include('partials.public-head', [
    // long model names fall back to the short title so it fits search results (Chinese is counted tighter)
    'seoTitle' => mb_strlen($t = __('drivers.seo_title', ['name' => $name])) <= ($loc === 'zh' ? 32 : 60) ? $t : __('drivers.seo_title_short', ['name' => $name]),
    'seoDescription' => \Illuminate\Support\Str::limit(__('drivers.seo_description', ['name' => $name]), $loc === 'zh' ? 84 : 158),
    'seoRoute' => 'drivers.show',
    'seoParams' => ['driver' => $driver->slug],
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main class="max-w-6xl mx-auto px-4 py-12">
    <nav class="text-xs text-stone-500 mb-4">
        <a href="{{ route('home') }}" class="hover:text-amber-700">{{ config('app.name') }}</a> ›
        <a href="{{ HomeController::localizedUrl('drivers', $loc) }}" class="hover:text-amber-700">{{ __('drivers.title') }}</a> › {{ $name }}
    </nav>

    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-8 items-start">
        <div>
            <p class="flex items-center gap-2 text-sm text-stone-500">@svg('heroicon-o-printer', 'w-5 h-5 text-amber-700') {{ __('drivers.kinds.' . $driver->kind) }}@if($driver->language) · {{ __('drivers.language') }}: {{ $driver->language }}@endif</p>
            <h1 class="font-display font-bold text-3xl sm:text-4xl text-stone-900 tracking-tight mt-2">{{ __('drivers.page_h1', ['name' => $name]) }}</h1>
            @if($driver->localized('summary'))<p class="text-lg text-stone-600 mt-3 leading-relaxed">{{ $driver->localized('summary') }}</p>@endif
            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($driver->os ?? [] as $os)<span class="px-2.5 py-1 rounded-lg bg-white border border-stone-200 text-sm text-stone-700">{{ __('drivers.os_names.' . $os) }}</span>@endforeach
            </div>
        </div>
        <aside class="bg-white border border-stone-200 rounded-2xl p-6">
            <a href="{{ $driver->driver_url }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 w-full px-5 py-3.5 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">
                @svg('heroicon-o-arrow-down-tray', 'w-5 h-5') {{ __('drivers.download') }}
            </a>
            <p class="text-xs text-stone-500 mt-3 text-center">{{ __('drivers.download_hint', ['host' => $driver->host()]) }}</p>
            <div class="border-t border-stone-100 mt-5 pt-5">
                <p class="font-semibold text-sm text-stone-900">{{ __('drivers.help_title') }}</p>
                <p class="text-sm text-stone-600 mt-1">{{ __('drivers.help_text') }}</p>
                <a href="{{ HomeController::localizedUrl('contact', $loc) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 hover:text-amber-900 mt-3">{{ __('contact.title') }} →</a>
            </div>
        </aside>
    </div>

    <div class="grid lg:grid-cols-2 gap-8 mt-12">
        <section class="bg-white border border-stone-200 rounded-2xl p-6">
            <h2 class="font-display font-bold text-xl text-stone-900">{{ __('drivers.install_h2', ['name' => $name]) }}</h2>
            <ol class="mt-4 space-y-3">
                @foreach($steps as $i => $step)
                    <li class="flex gap-3 text-sm text-stone-700 leading-relaxed"><span class="w-6 h-6 rounded-full bg-amber-700 text-white text-xs font-semibold flex items-center justify-center shrink-0">{{ $i + 1 }}</span> {{ $step }}</li>
                @endforeach
            </ol>
            @if($driver->localized('notes'))
                <div class="mt-5 text-sm text-stone-700 bg-amber-50 border border-amber-200 rounded-lg p-4 whitespace-pre-line">{{ $driver->localized('notes') }}</div>
            @endif
        </section>
        <section class="bg-white border border-stone-200 rounded-2xl p-6">
            <h2 class="font-display font-bold text-xl text-stone-900">{{ __('drivers.faq_h2') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach($faq as $f)
                    <details class="border border-stone-200 rounded-xl p-4 group" @if($loop->first) open @endif>
                        <summary class="font-medium text-sm cursor-pointer list-none flex justify-between gap-4">{{ $f['q'] }} <span class="text-amber-700 group-open:rotate-45 transition">+</span></summary>
                        <p class="text-sm text-stone-600 mt-2 leading-relaxed">{{ $f['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    </div>

    @if($products->isNotEmpty())
        {{-- the printers this driver fits, from our marketplace --}}
        <section class="mt-12">
            <h2 class="font-display font-bold text-xl text-stone-900">{{ __('drivers.products_h2', ['name' => $name]) }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                @foreach($products as $item)
                    <a href="{{ HomeController::localizedUrl('marketplace.item', $loc, ['offer' => $item->slug]) }}" class="bg-white border border-stone-200 rounded-xl p-3 hover:border-amber-400 transition">
                        @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy" class="h-24 w-full object-contain">@else<div class="h-24 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($item->icon ?: 'heroicon-o-printer', 'w-8 h-8')</div>@endif
                        <p class="font-semibold text-sm text-stone-900 mt-3 leading-snug">{{ $item->title }}</p>
                        <p class="text-xs text-stone-500 mt-1">{{ $item->price ?: __('offers.price_on_request') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($supplies->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-display font-bold text-xl text-stone-900">{{ __('drivers.supplies_h2', ['name' => $name]) }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                @foreach($supplies as $item)
                    <a href="{{ HomeController::localizedUrl('marketplace.item', $loc, ['offer' => $item->slug]) }}" class="bg-white border border-stone-200 rounded-xl p-3 hover:border-amber-400 transition">
                        @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy" class="h-24 w-full object-contain">@else<div class="h-24 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($item->icon ?: 'heroicon-o-cube', 'w-8 h-8')</div>@endif
                        <p class="font-semibold text-sm text-stone-900 mt-3 leading-snug">{{ $item->title }}</p>
                        <p class="text-xs text-stone-500 mt-1">{{ $item->price ?: __('offers.price_on_request') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($siblings->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-display font-bold text-lg text-stone-900">{{ __('drivers.siblings_h2', ['brand' => $driver->brand]) }}</h2>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($siblings as $s)
                    <a href="{{ HomeController::localizedUrl('drivers.show', $loc, ['driver' => $s->slug]) }}" class="px-3 py-2 bg-white border border-stone-200 rounded-lg text-sm hover:border-amber-400">{{ $s->name() }}</a>
                @endforeach
            </div>
        </section>
    @endif
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
