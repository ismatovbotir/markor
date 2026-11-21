<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    // One home page block on its own page (menu item with its own address): /asl-belgisi, /marketplace, /faq.
    // Expects: $page (Seo page key = route name), $label (breadcrumb / menu name), $jsonLd (extra schema.org objects).
    use App\Http\Controllers\Guest\HomeController;
    $url = HomeController::localizedUrl($page, app()->getLocale());
    $jsonLd = array_merge([
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => $url],
        ]],
    ], $jsonLd ?? []);
@endphp
@include('partials.public-head', [
    'seoTitle' => \App\Support\Seo::text($page, 'title'),
    'seoDescription' => \App\Support\Seo::text($page, 'description'),
    'seoRoute' => $page,
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main>
    <nav class="max-w-6xl mx-auto px-4 pt-6 text-sm text-stone-500" aria-label="breadcrumb">
        <a href="{{ HomeController::localizedUrl('home', app()->getLocale()) }}" class="hover:text-amber-700">{{ config('app.name') }}</a> › <span class="text-stone-700">{{ $label }}</span>
    </nav>
    @if($page === 'aslbelgi')
        <div class="mt-6">@include('partials.section-aslbelgi', ['hTag' => 'h1'])</div>
        {{-- the questions about codes, then the way to start --}}
        @include('partials.section-faq', ['hTag' => 'h2'])
    @elseif($page === 'marketplace')
        @include('partials.section-offers', ['hTag' => 'h1'])
    @else
        <div class="mt-6">@include('partials.section-faq', ['hTag' => 'h1'])</div>
    @endif

    <section class="max-w-6xl mx-auto px-4 py-16">
        <div class="bg-white border border-stone-200 rounded-2xl p-6 sm:p-8 flex items-center justify-between gap-6 flex-wrap">
            <div>
                <p class="font-display font-bold text-xl text-stone-900">{{ __('home.cta.title') }}</p>
                <p class="text-stone-600 mt-2 max-w-xl">{{ __('home.cta.text') }}</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ HomeController::localizedUrl('studio', app()->getLocale()) }}" class="inline-flex items-center gap-2 px-5 py-3 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">{{ __('home.cta.button') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
                <a href="{{ HomeController::localizedUrl('contact', app()->getLocale()) }}" class="inline-flex items-center gap-2 px-5 py-3 border border-stone-300 rounded-xl font-medium hover:bg-stone-100">{{ __('contact.title') }}</a>
            </div>
        </div>
    </section>
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
