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
        'rotate' => 'heroicon-o-arrow-path', 'overlap' => 'heroicon-o-exclamation-triangle',
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
    <div class="relative" @if($extra->isNotEmpty()) x-data="{ i: 0, n: {{ $extra->count() + 1 }}, t: null, go(k) { this.i = (k + this.n) % this.n }, start() { this.t = setInterval(() => this.go(this.i + 1), 7000) }, stop() { clearInterval(this.t) } }" x-init="start()" x-on:mouseenter="stop()" x-on:mouseleave="start()" @endif>
        <div @if($extra->isNotEmpty()) x-show="i === 0" x-transition.opacity.duration.500ms @endif>
    <section class="relative overflow-hidden">
            <div class="absolute inset-0 mat-grid" aria-hidden="true"></div>
            <div class="relative max-w-6xl mx-auto px-4 pt-14 pb-20 lg:pt-20 lg:pb-28 grid lg:grid-cols-[1.05fr_0.95fr] gap-12 items-center">
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
    
                {{-- Labels coming off the roll --}}
                <div class="relative mx-auto w-full max-w-md lg:max-w-none" aria-hidden="true">
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
            @php
                $theme = ['light' => ['bg-stone-100', 'text-stone-900', 'text-stone-600', 'bg-amber-700 text-white hover:bg-amber-800'],
                          'dark'  => ['bg-stone-900', 'text-white', 'text-stone-300', 'bg-amber-500 text-stone-900 hover:bg-amber-400'],
                          'amber' => ['bg-amber-700', 'text-white', 'text-amber-50', 'bg-white text-amber-800 hover:bg-amber-50']][$b->style] ?? ['bg-stone-100', 'text-stone-900', 'text-stone-600', 'bg-amber-700 text-white'];
                $link = $b->link();
                $external = $link && preg_match('#^https?://#i', $link);
                $rel = trim(($external ? 'noopener ' : '') . ($b->is_sponsored ? 'sponsored' : ($external ? 'nofollow' : '')));
            @endphp
            <section x-show="i === {{ $k + 1 }}" x-cloak x-transition.opacity.duration.500ms class="{{ $theme[0] }}">
                <div class="max-w-6xl mx-auto px-4 py-14 lg:py-20 grid lg:grid-cols-2 gap-10 items-center min-h-[460px]">
                    <div>
                        @if($b->is_sponsored)<span class="inline-block text-[11px] uppercase tracking-wider px-2 py-0.5 rounded border {{ $b->style === 'light' ? 'border-stone-300 text-stone-500' : 'border-white/40 text-white/80' }}">{{ __('banners.ad') }}</span>@endif
                        <h2 class="font-display font-bold text-3xl sm:text-4xl tracking-tight mt-3 {{ $theme[1] }}">{{ $b->localized('title') }}</h2>
                        @if($b->localized('text'))<p class="text-lg mt-4 leading-relaxed max-w-xl {{ $theme[2] }}">{{ $b->localized('text') }}</p>@endif
                        @if($link)
                            <a href="{{ $link }}" @if($external) target="_blank" @endif @if($rel) rel="{{ $rel }}" @endif class="inline-flex items-center gap-2 mt-8 px-6 py-3.5 rounded-xl font-semibold transition {{ $theme[3] }}">
                                {{ $b->localized('button') ?: __('banners.more') }} @svg($external ? 'heroicon-o-arrow-top-right-on-square' : 'heroicon-o-arrow-right', 'w-4 h-4')
                            </a>
                        @endif
                    </div>
                    @if($b->imageUrl())
                        <div class="flex items-center justify-center">
                            <img src="{{ $b->imageUrl() }}" alt="{{ $b->localized('title') }}" loading="lazy" class="max-h-80 w-auto object-contain drop-shadow-xl">
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
        @if($extra->isNotEmpty())
            <div class="absolute bottom-4 inset-x-0 flex items-center justify-center gap-3 z-10">
                <button type="button" x-on:click="go(i - 1)" class="w-8 h-8 rounded-full bg-white/80 border border-stone-200 text-stone-700 hover:bg-white" aria-label="‹">‹</button>
                <template x-for="k in n" :key="k"><button type="button" x-on:click="go(k - 1)" class="w-2.5 h-2.5 rounded-full transition" :class="i === k - 1 ? 'bg-amber-700 w-6' : 'bg-stone-400/70'" :aria-label="k"></button></template>
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
    <section id="aslbelgi" class="bg-stone-900 text-stone-100 scroll-mt-16">
        <div class="max-w-6xl mx-auto px-4 py-20 grid lg:grid-cols-[0.9fr_1.1fr] gap-12">
            <div>
                <p class="font-mono text-xs uppercase tracking-widest text-amber-400">Asl Belgisi · GS1 DataMatrix</p>
                <h2 class="font-display font-bold text-2xl sm:text-3xl text-white tracking-tight mt-3">{{ __('home.aslbelgi.title') }}</h2>
                <p class="text-stone-300 mt-4 leading-relaxed">{{ __('home.aslbelgi.text') }}</p>
                <div class="flex flex-wrap items-center gap-4 mt-8">
                    <a href="{{ auth()->check() ? $loginUrl : route('register') }}" class="inline-flex items-center gap-2 px-5 py-3 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-500 transition">
                        {{ auth()->check() ? __('home.aslbelgi.cta') : __('home.aslbelgi.register') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')
                    </a>
                    @guest
                        <a href="{{ route('login') }}" class="text-sm font-medium text-stone-300 hover:text-white">{{ __('auth.have_account') }} {{ __('auth.login') }}</a>
                    @endguest
                </div>
                <p class="text-sm text-stone-400 mt-4">{{ __('home.aslbelgi.private') }}</p>
                <p class="text-sm text-amber-300 mt-1">{{ __('home.aslbelgi.price') }}</p>
                <p class="text-sm text-stone-400 mt-1">{{ __('home.aslbelgi.topup') }}</p>
                <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('china', app()->getLocale()) }}" class="inline-block text-sm text-amber-300 hover:text-amber-200 mt-4">{{ __('china.home_link') }}</a>
                <p class="flex items-start gap-2 text-sm text-stone-300 mt-6">@svg('heroicon-o-paper-airplane', 'w-4 h-4 mt-0.5 shrink-0') {{ __('home.aslbelgi.telegram') }}</p>
            </div>
            <ol class="grid sm:grid-cols-2 gap-px bg-stone-700 rounded-2xl overflow-hidden self-start">
                @foreach([1, 2, 3, 4] as $i)
                    <li class="bg-stone-900 p-6">
                        <span class="font-mono text-xs text-amber-400">0{{ $i }}</span>
                        <h3 class="font-semibold text-white mt-2">{{ __("home.aslbelgi.point$i.title") }}</h3>
                        <p class="text-sm text-stone-400 mt-2 leading-relaxed">{{ __("home.aslbelgi.point$i.text") }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Equipment and consumables --}}
    @if($catalog->isNotEmpty())
        <section id="offers" class="max-w-6xl mx-auto px-4 py-20 scroll-mt-16">
            <div class="flex items-end justify-between gap-6 flex-wrap">
                <div>
                    <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.offers.title') }}</h2>
                    <p class="text-stone-600 mt-3 max-w-2xl">{{ __('home.offers.subtitle') }}</p>
                </div>
                @if($tel)
                    <a href="{{ $tel }}" class="inline-flex items-center gap-2 px-5 py-3 border border-stone-900 rounded-xl font-semibold hover:bg-stone-900 hover:text-white transition">@svg('heroicon-o-phone', 'w-4 h-4') {{ $phone }}</a>
                @endif
            </div>
            <div class="mt-10">@include('partials.offer-catalog', ['catalog' => $catalog, 'templatePreviews' => $templatePreviews])</div>
        </section>
    @endif

    {{-- FAQ --}}
    @if($faq->isNotEmpty())
        <section id="faq" class="bg-white border-y border-stone-200 scroll-mt-16">
            <div class="max-w-3xl mx-auto px-4 py-20">
                <h2 class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.faq.title') }}</h2>
                <div class="mt-8 divide-y divide-stone-200 border-y border-stone-200">
                    @foreach($faq as $item)
                        <details class="group py-5">
                            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-semibold text-stone-900">
                                <h3>{{ $item['q'] }}</h3>
                                <span class="shrink-0 w-7 h-7 rounded-full border border-stone-300 flex items-center justify-center text-stone-500 transition group-open:rotate-45 group-open:bg-amber-700 group-open:border-amber-700 group-open:text-white">+</span>
                            </summary>
                            <p class="text-stone-600 mt-3 leading-relaxed">{{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

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

<footer class="border-t border-stone-200">
    <div class="max-w-6xl mx-auto px-4 py-10 flex flex-wrap items-start justify-between gap-8 text-sm">
        <div class="max-w-sm">
            <p><img src="/images/logo.png" alt="{{ config('app.name') }}" width="96" height="24" class="h-6 w-auto"></p>
            <p class="text-stone-500 mt-2 leading-relaxed">{{ __('home.footer.about') }}</p>
        </div>
        <nav class="flex flex-col gap-2 text-stone-600">
            <a href="{{ route('studio') }}" class="hover:text-amber-700">{{ __('nav.studio') }}</a>
            <a href="{{ $loginUrl }}" class="hover:text-amber-700">Asl Belgisi</a>
            <a href="#offers" class="hover:text-amber-700">{{ __('nav.offers') }}</a>
            <a href="#faq" class="hover:text-amber-700">{{ __('home.nav.faq') }}</a>
        </nav>
        <div class="flex flex-col gap-2 text-stone-600">
            @if($tel)<a href="{{ $tel }}" class="font-mono hover:text-amber-700">{{ $phone }}</a>@endif
            @if(config('contact.telegram'))<a href="https://t.me/{{ config('contact.telegram') }}" rel="noopener" class="hover:text-amber-700">Telegram</a>@endif
            @include('partials.locale-switcher')
        </div>
    </div>
</footer>

@livewireScripts
</body>
</html>
