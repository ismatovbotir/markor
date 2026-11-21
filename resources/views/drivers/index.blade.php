<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    $all = $brands->flatten();
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('drivers.title'), 'item' => route('drivers')],
        ]],
        ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('drivers.title'),
         'itemListElement' => $all->values()->map(fn ($d, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => __('drivers.page_h1', ['name' => $d->name()]), 'url' => route('drivers.show', $d)])->all()],
    ];
@endphp
@include('partials.public-head', [
    'seoTitle' => \App\Support\Seo::text('drivers', 'title'),
    'seoDescription' => \App\Support\Seo::text('drivers', 'description'),
    'seoRoute' => 'drivers',
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

@php
    // search text per model: name + command language, without spaces/hyphens ("xp365" finds "XP-365B")
    $norm = fn ($s) => preg_replace('/[\s\-_\/]+/u', '', mb_strtolower($s));
    $hay = $all->mapWithKeys(fn ($d) => [$d->id => $norm($d->name() . ' ' . $d->language . ' ' . $d->brand)]);
@endphp
<main class="max-w-6xl mx-auto px-4 py-12" x-data="{ q: '', hay: @js($hay->values()), norm(s) { return (s || '').toLowerCase().replace(/[\s\-_\/]+/g, '') }, has(h) { return !this.norm(this.q) || h.includes(this.norm(this.q)) }, get none() { return !this.hay.some(h => this.has(h)) } }">
    <nav class="text-xs text-stone-500 mb-4"><a href="{{ route('home') }}" class="hover:text-amber-700">{{ config('app.name') }}</a> › {{ __('drivers.title') }}</nav>
    <h1 class="font-display font-bold text-3xl sm:text-4xl text-stone-900 tracking-tight">{{ __('drivers.h1') }}</h1>
    <p class="text-stone-600 mt-3 max-w-3xl leading-relaxed">{{ __('drivers.intro') }}</p>

    <div class="mt-6 max-w-md relative">
        @svg('heroicon-o-magnifying-glass', 'w-5 h-5 text-stone-400 absolute left-3 top-1/2 -translate-y-1/2')
        <input x-model="q" type="search" placeholder="{{ __('drivers.search') }}" class="w-full pl-10 pr-3 py-3 border border-stone-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
    </div>

    <div class="mt-10 space-y-10">
        @foreach($brands as $brand => $drivers)
            <section x-show="@js($drivers->map(fn ($d) => $hay[$d->id])->values()).some(h => has(h))">
                <h2 class="font-display font-bold text-xl text-stone-900">{{ __('drivers.brand_h2', ['brand' => $brand]) }}</h2>
                <div @class(['grid sm:grid-cols-2 gap-4 mt-4', 'lg:grid-cols-3' => $drivers->count() >= 3])>
                    @foreach($drivers as $d)
                        <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('drivers.show', app()->getLocale(), ['driver' => $d->slug]) }}"
                           x-show="has(@js($hay[$d->id]))"
                           class="bg-white border border-stone-200 rounded-xl p-5 hover:border-amber-400 transition flex flex-col">
                            <span class="flex items-center gap-2 text-xs text-stone-500">@svg('heroicon-o-printer', 'w-4 h-4 text-amber-700') {{ __('drivers.kinds.' . $d->kind) }}@if($d->language) · {{ $d->language }}@endif</span>
                            <span class="font-semibold text-stone-900 mt-2">{{ __('drivers.card_title', ['name' => $d->name()]) }}</span>
                            <span class="text-sm text-stone-600 mt-1 flex-1">{{ $d->localized('summary') }}</span>
                            <span class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($d->os ?? [] as $os)<span class="px-2 py-0.5 rounded bg-stone-100 text-stone-600 text-[11px]">{{ __('drivers.os_names.' . $os) }}</span>@endforeach
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <p x-show="none" x-cloak class="mt-10 text-stone-500">{{ __('drivers.nothing_found') }}</p>

    <div class="mt-14 bg-white border border-stone-200 rounded-2xl p-6 grid md:grid-cols-[1fr_auto] gap-4 items-center">
        <div>
            <h2 class="font-semibold text-lg text-stone-900">{{ __('drivers.missing_title') }}</h2>
            <p class="text-sm text-stone-600 mt-1">{{ __('drivers.missing_text') }}</p>
        </div>
        <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('contact', app()->getLocale()) }}" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">@svg('heroicon-o-chat-bubble-left-right', 'w-5 h-5') {{ __('contact.title') }}</a>
    </div>
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
