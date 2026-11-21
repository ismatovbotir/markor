<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    $faq = collect(range(1, 8))->map(fn ($i) => ['q' => __("china.faq.$i.q"), 'a' => __("china.faq.$i.a")])->reject(fn ($f) => str_starts_with($f['q'], 'china.faq.'))->values();
    $registerUrl = auth()->check() ? route('import-batches.index') : route('register');
    $phone = config('contact.phone'); $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('china.nav'), 'item' => route('china')],
        ]],
    ];
    if ($faq->isNotEmpty()) {
        $jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all()];
    }
@endphp
@include('partials.public-head', [
    'seoTitle' => __('seo.china.title'),
    'seoDescription' => __('seo.china.description'),
    'seoRoute' => 'china',
    'jsonLd' => $jsonLd,
])
{{-- Baidu: page fits PC and mobile --}}
<meta name="applicable-device" content="pc,mobile">
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main>
    <section class="bg-stone-900 text-white">
        <div class="max-w-6xl mx-auto px-4 py-14 lg:py-20">
            <p class="inline-flex items-center gap-2 font-mono text-xs uppercase tracking-widest text-amber-300 bg-white/5 border border-white/10 rounded-full px-3 py-1">{{ __('china.hero.badge') }}</p>
            <h1 class="font-display font-bold text-3xl sm:text-5xl tracking-tight mt-5 max-w-4xl leading-tight">{{ __('china.h1') }}</h1>
            <p class="text-lg text-stone-300 mt-5 max-w-3xl leading-relaxed">{{ __('china.hero.subtitle') }}</p>
            <div class="flex flex-wrap gap-3 mt-8">
                <a href="{{ $registerUrl }}" class="inline-flex items-center gap-2 px-6 py-3.5 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-500">{{ __('china.hero.cta_primary') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
                <a href="#contact" class="inline-flex items-center gap-2 px-5 py-3.5 border border-white/20 rounded-xl font-medium hover:bg-white/10">@svg('heroicon-o-chat-bubble-left-right', 'w-5 h-5') {{ __('china.hero.cta_secondary') }}</a>
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-14 grid lg:grid-cols-2 gap-10">
        <div>
            <h2 class="font-display font-bold text-2xl text-stone-900">{{ __('china.what.title') }}</h2>
            <p class="text-stone-600 mt-4 leading-relaxed">{{ __('china.what.text') }}</p>
            <p class="text-sm text-stone-700 mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">{{ __('china.what.groups') }}</p>
        </div>
        <div>
            <h2 class="font-display font-bold text-2xl text-stone-900">{{ __('china.code.title') }}</h2>
            <p class="text-stone-600 mt-4 leading-relaxed">{{ __('china.code.text') }}</p>
            <ul class="mt-4 space-y-2 text-sm text-stone-700">
                @foreach(range(1, 4) as $i)
                    <li class="flex gap-2">@svg('heroicon-o-check-circle', 'w-5 h-5 text-emerald-600 shrink-0') {{ __("china.code.points.$i") }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="bg-white border-y border-stone-200">
        <div class="max-w-6xl mx-auto px-4 py-14">
            <h2 class="font-display font-bold text-2xl text-stone-900">{{ __('china.where.title') }}</h2>
            <div class="grid md:grid-cols-3 gap-4 mt-6">
                @foreach(range(1, 3) as $i)
                    <div class="rounded-xl border {{ $i === 1 ? 'border-amber-300 bg-amber-50' : 'border-stone-200' }} p-5">
                        <span class="font-mono text-xs text-amber-700">0{{ $i }}</span>
                        <h3 class="font-semibold text-stone-900 mt-2">{{ __("china.where.$i.title") }}</h3>
                        <p class="text-sm text-stone-600 mt-2 leading-relaxed">{{ __("china.where.$i.text") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-14">
        <h2 class="font-display font-bold text-2xl text-stone-900">{{ __('china.how.title') }}</h2>
        <ol class="grid md:grid-cols-5 gap-4 mt-6">
            @foreach(range(1, 5) as $i)
                @continue(str_starts_with(__("china.how.$i.title"), 'china.how.'))
                <li class="bg-white border border-stone-200 rounded-xl p-5">
                    <span class="w-8 h-8 rounded-full bg-amber-700 text-white text-sm font-semibold flex items-center justify-center">{{ $i }}</span>
                    <h3 class="font-semibold text-stone-900 mt-3">{{ __("china.how.$i.title") }}</h3>
                    <p class="text-sm text-stone-600 mt-2 leading-relaxed">{{ __("china.how.$i.text") }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="max-w-6xl mx-auto px-4 pb-14 grid md:grid-cols-2 gap-6">
        <div class="bg-white border border-stone-200 rounded-xl p-6">
            <h2 class="font-semibold text-lg text-stone-900">{{ __('china.equipment.title') }}</h2>
            <p class="text-stone-600 mt-3 leading-relaxed">{{ __('china.equipment.text') }}</p>
            <a href="{{ route('home') }}#offers" class="inline-block text-sm text-amber-700 font-medium mt-4 hover:underline">{{ __('nav.offers') }} →</a>
        </div>
        <div class="bg-white border border-stone-200 rounded-xl p-6">
            <h2 class="font-semibold text-lg text-stone-900">{{ __('china.price.title') }}</h2>
            <p class="text-stone-600 mt-3 leading-relaxed">{{ __('china.price.text') }}</p>
        </div>
    </section>

    @if($faq->isNotEmpty())
        <section class="bg-white border-y border-stone-200">
            <div class="max-w-3xl mx-auto px-4 py-14">
                <h2 class="font-display font-bold text-2xl text-stone-900 mb-6">{{ __('china.faq.title') }}</h2>
                <div class="space-y-3">
                    @foreach($faq as $f)
                        <details class="border border-stone-200 rounded-xl p-4 group" @if($loop->first) open @endif>
                            <summary class="font-medium cursor-pointer list-none flex justify-between gap-4">{{ $f['q'] }} <span class="text-amber-700 group-open:rotate-45 transition">+</span></summary>
                            <p class="text-stone-600 mt-2 leading-relaxed">{{ $f['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="contact" class="max-w-6xl mx-auto px-4 py-14 grid lg:grid-cols-2 gap-8 items-start">
        <div>
            <h2 class="font-display font-bold text-2xl text-stone-900">{{ __('china.cta.title') }}</h2>
            <p class="text-stone-600 mt-3 leading-relaxed">{{ __('china.cta.text') }}</p>
            <a href="{{ route('studio') }}" class="inline-flex items-center gap-2 mt-6 px-6 py-3.5 bg-amber-700 text-white rounded-xl font-semibold hover:bg-amber-800">{{ __('china.cta.button') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')</a>
        </div>
        <div class="bg-white border border-stone-200 rounded-2xl p-6">
            <h2 class="font-semibold text-lg text-stone-900">{{ __('china.contact.title') }}</h2>
            <p class="text-sm text-stone-600 mt-2">{{ __('china.contact.text') }}</p>
            <ul class="mt-4 space-y-3 text-sm">
                @if(app()->getLocale() === 'zh' && (($wechat = config('contact.wechat')) || config('contact.wechat_link'))) {{-- WeChat: Chinese version only --}}
                    @php $wechatLink = config('contact.wechat_link'); @endphp
                    @php $qrSrc = is_file(public_path('images/wechat-qr.jpg')) ? '/images/wechat-qr.jpg' : ($wechatLink ? route('studio.barcode', ['type' => 'qr', 'data' => $wechatLink]) : null); @endphp
                    <li class="p-4 rounded-xl bg-emerald-50 border border-emerald-200" x-data="{ copied: false, qr: false }" x-on:keydown.escape.window="qr = false">
                        <div class="flex items-start gap-4">
                            @if($qrSrc)
                                {{-- click: the QR opens large, easy to scan with a phone --}}
                                <button type="button" x-on:click="qr = true" class="shrink-0" title="{{ __('china.wechat_show_qr') }}">
                                    <img src="{{ $qrSrc }}" width="112" height="112" alt="WeChat QR" class="w-28 h-28 object-contain rounded-lg bg-white p-1 hover:ring-2 hover:ring-emerald-400 transition">
                                </button>
                            @else
                                <span class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0">微</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-emerald-800">WeChat · {{ __('china.wechat') }}</p>
                                @if($qrSrc)
                                    <button type="button" x-on:click="qr = true" class="inline-block mt-2 text-sm text-emerald-700 font-medium hover:underline">{{ __('china.wechat_open') }} →</button>
                                    <p class="text-[11px] text-stone-500 mt-1">{{ __('china.wechat_scan') }}</p>
                                @endif
                            </div>
                        </div>
                        @if($qrSrc)
                            <div x-show="qr" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center px-4" x-on:click.self="qr = false">
                                <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm text-center">
                                    <p class="font-semibold text-stone-900">WeChat · {{ __('china.wechat') }}</p>
                                    <img src="{{ $qrSrc }}" alt="WeChat QR" class="w-full max-w-xs mx-auto mt-4">
                                    <p class="text-sm text-stone-500 mt-3">{{ __('china.wechat_scan') }}</p>
                                    <div class="flex justify-center gap-3 mt-4">
                                        @if($wechatLink)<a href="{{ $wechatLink }}" target="_blank" rel="noopener" class="px-4 py-2 border border-emerald-300 text-emerald-800 rounded-lg text-sm hover:bg-emerald-50">{{ __('china.wechat_on_phone') }}</a>@endif
                                        <button type="button" x-on:click="qr = false" class="px-4 py-2 bg-stone-900 text-white rounded-lg text-sm">{{ __('common.close') }}</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </li>
                @endif
                @if($tel)<li class="flex items-center gap-3">@svg('heroicon-o-phone', 'w-5 h-5 text-amber-700') <a href="{{ $tel }}" class="hover:text-amber-700">{{ $phone }}</a></li>@endif
                @if($tg = config('contact.telegram'))<li class="flex items-center gap-3">@svg('heroicon-o-paper-airplane', 'w-5 h-5 text-amber-700') <a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener" class="hover:text-amber-700">t.me/{{ $tg }}</a></li>@endif
                @if($email = config('contact.email'))<li class="flex items-center gap-3">@svg('heroicon-o-envelope', 'w-5 h-5 text-amber-700') <a href="mailto:{{ $email }}" class="hover:text-amber-700">{{ $email }}</a></li>@endif
            </ul>
        </div>
    </section>
</main>

<footer class="border-t border-stone-200 py-6 text-center text-xs text-stone-500">{{ config('app.name') }} · {{ __('app.tagline') }}</footer>
@livewireScripts {{-- Alpine: copy button and the QR window --}}
</body>
</html>
