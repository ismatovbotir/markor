<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@php
    $phone = config('contact.phone'); $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $tg = config('contact.telegram'); $bot = config('contact.telegram_bot'); $email = config('contact.email');
    $org = array_filter(['@type' => 'Organization', 'name' => config('app.name'), 'url' => route('home'),
        'contactPoint' => array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => $phone, 'email' => $email,
            'availableLanguage' => ['Uzbek', 'Russian', 'Chinese']])]);
    $jsonLd = [
        ['@context' => 'https://schema.org', '@type' => 'ContactPage', 'name' => __('contact.title'), 'url' => route('contact'), 'mainEntity' => $org],
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('contact.title'), 'item' => route('contact')],
        ]],
    ];
@endphp
@include('partials.public-head', [
    'seoTitle' => __('contact.seo_title'),
    'seoDescription' => __('contact.seo_description'),
    'seoRoute' => 'contact',
    'jsonLd' => $jsonLd,
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@include('partials.public-header')

<main class="max-w-4xl mx-auto px-4 py-14">
    <h1 class="font-display font-bold text-3xl sm:text-4xl text-stone-900 tracking-tight">{{ __('contact.title') }}</h1>
    <p class="text-stone-600 mt-3 max-w-2xl leading-relaxed">{{ __('contact.intro') }}</p>

    <ul class="grid sm:grid-cols-2 gap-4 mt-8">
        @include('partials.wechat-contact')
        @if($tel)
            <li><a href="{{ $tel }}" class="flex items-center gap-4 p-5 bg-white border border-stone-200 rounded-xl hover:border-amber-400 transition">
                <span class="w-11 h-11 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center">@svg('heroicon-o-phone', 'w-5 h-5')</span>
                <span><span class="block text-xs text-stone-500">{{ __('contact.phone') }}</span><span class="font-mono font-semibold text-stone-900">{{ $phone }}</span></span></a></li>
        @endif
        @if($tg)
            <li><a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener" class="flex items-center gap-4 p-5 bg-white border border-stone-200 rounded-xl hover:border-amber-400 transition">
                <span class="w-11 h-11 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">@svg('heroicon-o-paper-airplane', 'w-5 h-5')</span>
                <span><span class="block text-xs text-stone-500">Telegram</span><span class="font-semibold text-stone-900">{{ "@" . $tg }}</span></span></a></li>
        @endif
        @if($email)
            <li><a href="mailto:{{ $email }}" class="flex items-center gap-4 p-5 bg-white border border-stone-200 rounded-xl hover:border-amber-400 transition">
                <span class="w-11 h-11 rounded-full bg-stone-100 text-stone-700 flex items-center justify-center">@svg('heroicon-o-envelope', 'w-5 h-5')</span>
                <span><span class="block text-xs text-stone-500">{{ __('contact.email') }}</span><span class="font-semibold text-stone-900">{{ $email }}</span></span></a></li>
        @endif
        @if($bot)
            <li><a href="https://t.me/{{ $bot }}" target="_blank" rel="noopener" class="flex items-center gap-4 p-5 bg-white border border-stone-200 rounded-xl hover:border-amber-400 transition">
                <span class="w-11 h-11 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center">@svg('heroicon-o-chat-bubble-left-right', 'w-5 h-5')</span>
                <span><span class="block text-xs text-stone-500">{{ __('contact.bot') }}</span><span class="font-semibold text-stone-900">{{ "@" . $bot }}</span></span></a></li>
        @endif
    </ul>

    <div class="grid sm:grid-cols-3 gap-4 mt-10 text-sm">
        <a href="{{ route('home') }}#offers" class="p-4 rounded-xl border border-stone-200 bg-white hover:border-amber-400">@svg('heroicon-o-shopping-bag', 'w-5 h-5 text-amber-700') <span class="block font-semibold mt-2">{{ __('nav.offers') }}</span><span class="text-stone-500">{{ __('contact.offers_hint') }}</span></a>
        <a href="{{ route('register') }}" class="p-4 rounded-xl border border-stone-200 bg-white hover:border-amber-400">@svg('heroicon-o-user-plus', 'w-5 h-5 text-amber-700') <span class="block font-semibold mt-2">Asl Belgisi</span><span class="text-stone-500">{{ __('contact.register_hint') }}</span></a>
        <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('studio', app()->getLocale()) }}" class="p-4 rounded-xl border border-stone-200 bg-white hover:border-amber-400">@svg('heroicon-o-pencil-square', 'w-5 h-5 text-amber-700') <span class="block font-semibold mt-2">{{ __('nav.studio') }}</span><span class="text-stone-500">{{ __('contact.studio_hint') }}</span></a>
    </div>
</main>

@include('partials.public-footer')
@livewireScripts
</body>
</html>
