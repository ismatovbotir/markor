<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    $phone = config('contact.phone');
    $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $loginUrl = auth()->check() ? route('import-batches.index') : route('login');

    $jsonLd = [
        [
            // the site name Google shows above the result ("Markor" instead of the bare address)
            '@context' => 'https://schema.org', '@type' => 'WebSite',
            'name' => config('app.name'), 'alternateName' => ['markor.uz', 'Markor etiketka'],
            'url' => route('home'), 'inLanguage' => ['uz', 'ru', 'zh-Hans'],
        ],
        [
            '@context' => 'https://schema.org', '@type' => 'Organization',
            'name' => config('app.name'), 'url' => route('home'), 'logo' => url('/icon-512.png'),
        ] + ($phone ? ['telephone' => $phone] : []),
        [
            '@context' => 'https://schema.org', '@type' => 'WebApplication',
            'name' => config('app.name') . ' — ' . __('nav.studio'),
            'url' => route('studio'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => \App\Support\Seo::text('studio', 'description'),
            'inLanguage' => ['uz', 'ru', 'zh-Hans'],
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'UZS'],
        ],
    ];
    // one FAQPage for the page: the home questions + questions of the Marketplace items shown on it
    $itemFaq = $catalog->flatMap(fn ($c) => $c->isTemplates() ? [] : $c->offers)->flatMap(fn ($o) => $o->localizedFaq())->take(30);
    $allFaq = $faq->concat($itemFaq);
    if ($allFaq->isNotEmpty()) {
        $jsonLd[] = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => $allFaq->map(fn ($item) => [
                '@type' => 'Question', 'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ])->all(),
        ];
    }

    $features = [
        'excel' => 'heroicon-o-table-cells', 'designer' => 'heroicon-o-cursor-arrow-rays',
        'codes' => 'heroicon-o-qr-code', 'rows' => 'heroicon-o-squares-2x2',
        'rotate' => 'heroicon-o-arrow-path', 'marks' => 'heroicon-o-check-badge',
        'generated' => 'heroicon-o-hashtag',
        'privacy' => 'heroicon-o-shield-check', 'autosave' => 'heroicon-o-bookmark-square',
        'print' => 'heroicon-o-printer', 'trial' => 'heroicon-o-gift', 'guide' => 'heroicon-o-book-open',
    ];
@endphp
@include('partials.public-head', [
    'seoTitle' => \App\Support\Seo::text('home', 'title'),
    'seoDescription' => \App\Support\Seo::text('home', 'description'),
    // social previews: the admin's override if there is one, otherwise the longer og_* texts
    'ogTitle' => \App\Support\Seo::get('home.title.' . app()->getLocale()) ?? __('seo.home.og_title'),
    'ogDescription' => \App\Support\Seo::get('home.description.' . app()->getLocale()) ?? __('seo.home.og_description'),
    'seoRoute' => 'home',
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">

@include('partials.public-header')

<main>
    {{-- Hero --}}
    @php $banners = \App\Domain\Content\Banner::forHome(); $extra = $banners->where('is_default', false)->values(); @endphp
    {{-- Banners: the built-in hero is the default and first one; brand / reseller banners follow (Admin → Bannerlar) --}}
    {{-- all slides share one grid cell: the slider keeps the height of the tallest (the default hero), nothing below jumps --}}
    <div class="relative grid" @if($extra->isNotEmpty()) x-data="{ i: 0, n: {{ $extra->count() + 1 }}, t: null, go(k) { this.i = (k + this.n) % this.n }, start() { this.t = setInterval(() => this.go(this.i + 1), 7000) }, stop() { clearInterval(this.t) } }" x-init="start()" x-on:mouseenter="stop()" x-on:mouseleave="start()" @endif>
        <div style="grid-area: 1 / 1" class="transition-opacity duration-500" @if($extra->isNotEmpty()) :class="i === 0 ? 'opacity-100' : 'opacity-0 invisible pointer-events-none'" @endif>
    <section class="relative overflow-hidden">
            <div class="absolute inset-0 mat-grid" aria-hidden="true"></div>
            <div class="relative max-w-6xl mx-auto px-4 pt-10 pb-16 sm:pt-14 sm:pb-20 lg:pt-20 lg:pb-28 grid lg:grid-cols-[1.05fr_0.95fr] gap-12 items-center">
                <div>
                    <p class="rise inline-flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-amber-800 bg-amber-100/70 border border-amber-200 rounded-full px-3 py-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>{{ __('home.hero.badge') }}
                    </p>
                    <h1 class="rise font-display font-bold text-stone-900 text-[2rem] leading-[1.15] sm:text-5xl sm:leading-[1.1] tracking-tight mt-5" style="animation-delay:.06s">
                        {{ __('home.h1') }}
                    </h1>
                    <p class="rise text-lg text-stone-600 mt-5 max-w-xl leading-relaxed" style="animation-delay:.12s">{{ __('home.hero.subtitle') }}</p>
                    <div class="rise flex flex-wrap items-center gap-3 mt-8" style="animation-delay:.18s">
                        <a href="{{ route('studio') }}" class="inline-flex items-center gap-2 px-6 py-3.5 bg-amber-700 text-white rounded-xl font-semibold shadow-[0_6px_0_0_#78350f] hover:translate-y-[2px] hover:shadow-[0_4px_0_0_#78350f] active:translate-y-[6px] active:shadow-none transition">
                            {{ __('home.hero.cta_primary') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')
                        </a>
                        <a href="{{ $loginUrl }}" class="inline-flex items-center gap-2 px-5 py-3.5 text-stone-800 font-medium hover:text-amber-700 transition">
                            @svg('heroicon-o-qr-code', 'w-5 h-5') {{ __('home.hero.cta_secondary') }}
                        </a>
                    </div>
                    <p class="rise font-mono text-xs text-stone-500 mt-6" style="animation-delay:.24s">{{ __('home.hero.note') }}</p>
                </div>
    
                {{-- Labels coming off the roll (decoration: hidden on phones so the slider fits one screen) --}}
                <div class="relative mx-auto w-full max-w-md lg:max-w-none hidden sm:block" aria-hidden="true">
                    <div class="liner p-5 pb-0 rotate-[-3deg] overflow-hidden h-[420px]">
                        <div class="feed label-stock p-4 grid grid-cols-[112px_1fr] gap-4 items-start">
                            <img src="{{ route('studio.barcode', ['type' => 'datamatrix', 'data' => '010478001234567821MARKOR-0001']) }}" width="112" height="112" alt="" class="w-28 h-28" style="image-rendering:pixelated">
                            <div class="min-w-0">
                                <p class="font-semibold text-stone-900 leading-tight">{{ __('studio.sample_title') }}</p>
                                <p class="font-mono text-[10px] text-stone-500 mt-2">GTIN</p>
                                <p class="font-mono text-sm font-medium text-stone-900">04780012345678</p>
                                <p class="font-mono text-[10px] text-stone-500 mt-2">SN</p>
                                <p class="font-mono text-xs text-stone-900 truncate">AF9-&eDmMbl?6CkOyAEB</p>
                            </div>
                        </div>
                        <div class="feed label-stock p-4 mt-4 grid grid-cols-[1fr_84px] gap-4 items-center" style="animation-delay:.15s">
                            <div class="min-w-0">
                                <p class="font-semibold text-stone-900 leading-tight">{{ __('studio.sample_title') }}</p>
                                <p class="font-display font-bold text-2xl text-stone-900 mt-1">25 000</p>
                                <img src="{{ route('studio.barcode', ['type' => 'ean13', 'data' => '4780000000014']) }}" alt="" class="h-9 w-40 mt-2" style="image-rendering:pixelated">
                            </div>
                            <img src="{{ route('studio.barcode', ['type' => 'qr', 'data' => route('studio')]) }}" width="84" height="84" alt="" class="w-[84px] h-[84px]" style="image-rendering:pixelated">
                        </div>
                        <div class="feed label-stock p-4 mt-4 h-40 opacity-80" style="animation-delay:.3s">
                            <div class="h-3 w-2/3 bg-stone-200 rounded"></div>
                            <div class="h-3 w-1/3 bg-stone-200 rounded mt-3"></div>
                        </div>
                    </div>
                    {{-- dimension callouts --}}
                    <div class="absolute -top-3 left-8 right-10 rotate-[-3deg] flex items-center gap-2 font-mono text-[11px] text-stone-500">
                        <span class="h-px flex-1 bg-stone-400"></span>60 mm<span class="h-px flex-1 bg-stone-400"></span>
                    </div>
                    <div class="absolute -left-4 top-10 font-mono text-[11px] text-stone-500 [writing-mode:vertical-rl] rotate-180">40 mm</div>
                    <div class="rise absolute -bottom-4 right-2 sm:right-6 bg-stone-900 text-stone-50 rounded-xl px-4 py-3 shadow-xl flex items-center gap-3" style="animation-delay:.5s">
                        @svg('heroicon-o-arrow-down-tray', 'w-5 h-5 text-amber-400')
                        <span class="font-mono text-xs">labels.pdf</span>
                    </div>
                </div>
            </div>
        </section>
        </div>
        @foreach($extra as $k => $b)
            <section style="grid-area: 1 / 1" class="{{ $b->theme()[0] }} flex items-center transition-opacity duration-500 opacity-0 invisible pointer-events-none" :class="i === {{ $k + 1 }} ? '!opacity-100 !visible !pointer-events-auto' : ''" aria-hidden="true" :aria-hidden="i !== {{ $k + 1 }}">
                @include('partials.banner-slide', ['b' => $b])
            </section>
        @endforeach
        @if($extra->isNotEmpty())
            <div class="absolute bottom-4 inset-x-0 flex items-center justify-center gap-3 z-10">
                <button type="button" x-on:click="go(i - 1)" class="w-8 h-8 rounded-full bg-white/80 border border-stone-200 text-stone-700 hover:bg-white" aria-label="‹">‹</button>
                <template x-for="k in n" :key="k"><button type="button" x-on:click="go(k - 1)" class="w-2.5 h-2.5 rounded-full transition" :class="i === k - 1 ? 'bg-stone-900 ring-2 ring-white w-6' : 'bg-white/80 ring-1 ring-stone-400/60'" :aria-label="k"></button></template>
                <button type="button" x-on:click="go(i + 1)" class="w-8 h-8 rounded-full bg-white/80 border border-stone-200 text-stone-700 hover:bg-white" aria-label="›">›</button>
            </div>
        @endif
    </div>

    {{-- Two products --}}
    <section class="max-w-6xl mx-auto px-4 pb-20">
        <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight max-w-2xl">{{ __('home.products.title') }}</h2>
        <div class="grid md:grid-cols-2 gap-5 mt-8">
            <article class="bg-white border border-stone-200 rounded-2xl p-7 flex flex-col">
                <p class="font-mono text-xs uppercase tracking-widest text-amber-700">/label-design</p>
                <h3 class="font-display font-bold text-xl text-stone-900 mt-3">{{ __('home.products.design.title') }}</h3>
                <p class="text-stone-600 mt-3 leading-relaxed">{{ __('home.products.design.text') }}</p>
                <ul class="mt-5 space-y-2.5 text-sm text-stone-700 flex-1">
                    @foreach([1, 2, 3] as $i)
                        <li class="flex gap-2.5">@svg('heroicon-o-check', 'w-4 h-4 mt-0.5 text-amber-700 shrink-0') {{ __("home.products.design.point$i") }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('studio') }}" class="inline-flex items-center gap-2 font-semibold text-amber-700 hover:text-amber-900 mt-7">{{ __('home.products.design.cta') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
            </article>
            <article class="bg-stone-900 text-stone-100 rounded-2xl p-7 flex flex-col">
                <p class="font-mono text-xs uppercase tracking-widest text-amber-400">/aslbelgi</p>
                <h3 class="font-display font-bold text-xl text-white mt-3">{{ __('home.products.aslbelgi.title') }}</h3>
                <p class="text-stone-300 mt-3 leading-relaxed">{{ __('home.products.aslbelgi.text') }}</p>
                <ul class="mt-5 space-y-2.5 text-sm text-stone-200 flex-1">
                    @foreach([1, 2, 3] as $i)
                        <li class="flex gap-2.5">@svg('heroicon-o-check', 'w-4 h-4 mt-0.5 text-amber-400 shrink-0') {{ __("home.products.aslbelgi.point$i") }}</li>
                    @endforeach
                </ul>
                <a href="{{ $loginUrl }}" class="inline-flex items-center gap-2 font-semibold text-amber-400 hover:text-amber-300 mt-7">{{ __('home.products.aslbelgi.cta') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
            </article>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white border-y border-stone-200">
        <div class="max-w-6xl mx-auto px-4 py-20">
            <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.steps.title') }}</h2>
            <ol class="grid md:grid-cols-3 gap-8 mt-10">
                @foreach([1, 2, 3] as $i)
                    <li class="relative pt-6 border-t-2 border-dashed border-stone-300">
                        <span class="absolute -top-[15px] left-0 font-mono text-xs font-medium bg-amber-700 text-white rounded-md px-2 py-1">0{{ $i }}</span>
                        <h3 class="font-semibold text-lg text-stone-900">{{ __("home.steps.$i.title") }}</h3>
                        <p class="text-stone-600 mt-2 leading-relaxed">{{ __("home.steps.$i.text") }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Features --}}
    <section class="max-w-6xl mx-auto px-4 py-20">
        <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.features.title') }}</h2>
        <p class="text-stone-600 mt-3 max-w-2xl">{{ __('home.features.subtitle') }}</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-px bg-stone-200 border border-stone-200 rounded-2xl overflow-hidden mt-10">
            @foreach($features as $key => $icon)
                <div class="bg-stone-50 p-6 hover:bg-white transition">
                    @svg($icon, 'w-6 h-6 text-amber-700')
                    <h3 class="font-semibold text-stone-900 mt-4">{{ __("home.features.$key.title") }}</h3>
                    <p class="text-sm text-stone-600 mt-2 leading-relaxed">{{ __("home.features.$key.text") }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-8">
            <a href="{{ route('studio') }}" class="inline-flex items-center gap-2 font-semibold text-amber-700 hover:text-amber-900">{{ __('home.hero.cta_primary') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
        </div>
    </section>

    {{-- Asl Belgisi --}}
    @include('partials.section-aslbelgi')

    {{-- Equipment and consumables --}}
    @include('partials.section-offers')

    {{-- FAQ --}}
    @include('partials.section-faq')

    {{-- Final call --}}
    <section class="max-w-6xl mx-auto px-4 py-20">
        <div class="liner p-2">
            <div class="label-stock px-6 py-12 sm:px-12 text-center">
                <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.cta.title') }}</h2>
                <p class="text-stone-600 mt-3 max-w-xl mx-auto">{{ __('home.cta.text') }}</p>
                <a href="{{ route('studio') }}" class="inline-flex items-center gap-2 px-6 py-3.5 bg-amber-700 text-white rounded-xl font-semibold shadow-[0_6px_0_0_#78350f] hover:translate-y-[2px] hover:shadow-[0_4px_0_0_#78350f] transition mt-8">
                    {{ __('home.cta.button') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </a>
            </div>
        </div>
    </section>
</main>

@include('partials.public-footer')

@livewireScripts
</body>
</html>
