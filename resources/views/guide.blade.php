<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@include('partials.public-head', [
    'seoTitle' => $title . ' — ' . __('guide.title') . ' | ' . config('app.name'),
    'seoDescription' => \Illuminate\Support\Str::limit(trim(strip_tags($html)), 155),
    'seoRoute' => 'guide',
    'seoParams' => ['page' => $page],
    'jsonLd' => [[
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('guide.title'), 'item' => route('guide')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => route('guide', $page)],
        ],
    ], [
        '@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => $title,
        'inLanguage' => \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz',
        'step' => collect(preg_match_all('#<li>(.*?)</li>#s', $html, $m) ? $m[1] : [])->take(12)
            ->map(fn ($s) => ['@type' => 'HowToStep', 'text' => trim(strip_tags($s))])->values()->all(),
    ]],
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')
<main class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="font-display font-bold text-2xl text-stone-900 tracking-tight">{{ __('guide.title') }}</h1>
    <p class="text-stone-500 mt-1 mb-6">{{ __('guide.subtitle') }}</p>
    <div class="flex gap-8 items-start flex-col md:flex-row">
        <nav class="w-full md:w-56 shrink-0 md:sticky md:top-6 text-sm space-y-1">
            @foreach($pages as $key => $pageTitle)
                <a href="{{ \App\Support\LlmsText::guideUrl($key, app()->getLocale()) }}" class="block px-3 py-2 rounded-lg {{ $key === $page ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-100' }}">{{ $pageTitle }}</a>
            @endforeach
        </nav>
        <article class="help-article flex-1 min-w-0 max-w-3xl bg-white border border-stone-200 rounded-xl p-6 sm:p-8">{!! $html !!}</article>
    </div>
</main>
@include('partials.public-footer')
@livewireScripts
</body>
</html>
