<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    use App\Http\Controllers\Guest\HomeController;
    $loc = app()->getLocale();
    $title = $post->localized('title');
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('news.title'), 'item' => route('news')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => route('news.show', $post)],
        ]],
        ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => \Illuminate\Support\Str::limit($title, 110),
         'datePublished' => $post->published_at->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
         'description' => $post->summary(), 'mainEntityOfPage' => route('news.show', $post),
         'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => route('home')]]
         + ($post->source_url ? ['isBasedOn' => $post->source_url] : []),
    ];
@endphp
@include('partials.public-head', [
    'seoTitle' => \Illuminate\Support\Str::limit($title . ' | ' . config('app.name'), $loc === 'zh' ? 32 : 60),
    'seoDescription' => $post->summary($loc === 'zh' ? 84 : 158),
    'seoRoute' => 'news.show',
    'seoParams' => ['post' => $post->slug],
    'jsonLd' => $jsonLd,
])
<style>
    .news-body > :first-child { margin-top: 0 } .news-body > :last-child { margin-bottom: 0 }
    .news-body h2 { font-weight: 700; font-size: 1.25rem; color: #1c1917; margin: 1.5rem 0 .5rem }
    .news-body h3 { font-weight: 600; color: #1c1917; margin: 1.25rem 0 .5rem }
    .news-body p { margin: .75rem 0 }
    .news-body ul { list-style: disc; padding-left: 1.5rem; margin: .75rem 0 } .news-body ol { list-style: decimal; padding-left: 1.5rem; margin: .75rem 0 }
    .news-body li { margin: .25rem 0 } .news-body strong { color: #1c1917 }
    .news-body a { color: #b45309; text-decoration: underline }
    .news-body img { border-radius: .5rem; margin: 1rem 0; max-width: 100% }
    .news-body blockquote { border-left: 4px solid #fde68a; padding-left: 1rem; color: #57534e }
    .news-body code { background: #f5f5f4; padding: 0 .25rem; border-radius: .25rem }
</style>
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main class="max-w-3xl mx-auto px-4 py-12">
    <nav class="text-xs text-stone-500 mb-4">
        <a href="{{ route('home') }}" class="hover:text-amber-700">{{ config('app.name') }}</a> ›
        <a href="{{ HomeController::localizedUrl('news', $loc) }}" class="hover:text-amber-700">{{ __('news.title') }}</a>
    </nav>
    <article>
        <time datetime="{{ $post->published_at->toDateString() }}" class="text-sm text-stone-500">{{ $post->published_at->format('d.m.Y') }}</time>
        <h1 class="font-display font-bold text-3xl sm:text-4xl text-stone-900 tracking-tight mt-2 break-words">{{ $title }}</h1>
        @if($post->localized('excerpt'))<p class="text-lg text-stone-600 mt-4 leading-relaxed">{{ $post->localized('excerpt') }}</p>@endif
        <div class="news-body mt-8 bg-white border border-stone-200 rounded-2xl p-6 sm:p-8 text-stone-700 leading-relaxed break-words">
            {!! $post->bodyHtml() !!}
        </div>
        @if($post->source_url)
            <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-stone-600">
                @svg('heroicon-o-link', 'w-4 h-4 text-amber-700')
                <span>{{ __('news.source') }}: <span class="font-medium text-stone-800">{{ $post->source_name ?: $post->sourceHost() }}</span></span>
                <a href="{{ $post->source_url }}" target="_blank" rel="noopener nofollow" class="font-semibold text-amber-700 hover:text-amber-900">{{ __('news.read_original', ['host' => $post->sourceHost()]) }} ↗</a>
            </p>
        @endif
    </article>

    @if($others->isNotEmpty())
        <section class="mt-12">
            <h2 class="font-display font-bold text-lg text-stone-900">{{ __('news.others') }}</h2>
            <div class="mt-3 space-y-2">
                @foreach($others as $o)
                    <a href="{{ HomeController::localizedUrl('news.show', $loc, ['post' => $o->slug]) }}" class="flex items-baseline gap-3 px-4 py-3 bg-white border border-stone-200 rounded-lg hover:border-amber-400">
                        <time class="text-xs text-stone-500 shrink-0">{{ $o->published_at->format('d.m.Y') }}</time>
                        <span class="text-sm font-medium text-stone-900">{{ $o->localized('title') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
