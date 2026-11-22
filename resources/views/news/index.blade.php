<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    use App\Http\Controllers\Guest\HomeController;
    $loc = app()->getLocale();
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('news.title'), 'item' => route('news')],
        ]],
    ];
    if ($posts->isNotEmpty()) {
        $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('news.title'),
            'itemListElement' => $posts->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p->localized('title'), 'url' => route('news.show', $p)])->all()];
    }
@endphp
@include('partials.public-head', [
    'seoTitle' => \App\Support\Seo::text('news', 'title'),
    'seoDescription' => \App\Support\Seo::text('news', 'description'),
    'seoRoute' => 'news',
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main class="max-w-6xl mx-auto px-4 py-12">
    <nav class="text-xs text-stone-500 mb-4"><a href="{{ route('home') }}" class="hover:text-amber-700">{{ config('app.name') }}</a> › {{ __('news.title') }}</nav>
    <h1 class="font-display font-bold text-3xl sm:text-4xl text-stone-900 tracking-tight">{{ __('news.title') }}</h1>
    <p class="text-stone-600 mt-3 max-w-3xl leading-relaxed">{{ __('news.intro') }}</p>

    @if($posts->isEmpty())
        <div class="mt-10 bg-white border border-stone-200 rounded-2xl p-8 text-center text-stone-500">
            @svg('heroicon-o-newspaper', 'w-8 h-8 mx-auto text-stone-300')
            <p class="mt-3">{{ __('news.empty') }}</p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-10">
            @foreach($posts as $p)
                <a href="{{ HomeController::localizedUrl('news.show', $loc, ['post' => $p->slug]) }}" class="bg-white border border-stone-200 rounded-xl p-5 hover:border-amber-400 transition flex flex-col">
                    <span class="text-xs text-stone-500"><time datetime="{{ $p->published_at->toDateString() }}">{{ $p->published_at->format('d.m.Y') }}</time>@if($p->source_url) · {{ __('news.source') }}: {{ $p->source_name ?: $p->sourceHost() }}@endif</span>
                    <span class="font-semibold text-stone-900 mt-2 leading-snug">{{ $p->localized('title') }}</span>
                    <span class="text-sm text-stone-600 mt-2 flex-1">{{ $p->summary(180) }}</span>
                    <span class="text-sm font-semibold text-amber-700 mt-4">{{ __('news.read_more') }} →</span>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $posts->withQueryString()->links() }}</div>
    @endif
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
