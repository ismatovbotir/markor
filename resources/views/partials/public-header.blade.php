@php
    $headerPhone = config('contact.phone');
    $headerTel = $headerPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $headerPhone) : null;
@endphp
<header class="sticky top-0 z-30 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center shrink-0" aria-label="{{ config('app.name') }}">
            <img src="/images/logo.png" alt="{{ config('app.name') }}" width="112" height="28" class="h-7 w-auto">
        </a>
        <nav class="hidden md:flex items-center gap-4 lg:gap-6 text-sm text-stone-600 whitespace-nowrap">
            <a href="{{ route('studio') }}" class="hover:text-amber-700 {{ request()->routeIs('studio') ? 'text-amber-700 font-medium' : '' }}">{{ __('nav.studio') }}</a>
            <a href="{{ route('home') }}#aslbelgi" class="hover:text-amber-700">Asl Belgisi</a>
            <a href="{{ route('home') }}#offers" class="hover:text-amber-700">{{ __('nav.offers') }}</a>
            <a href="{{ route('home') }}#faq" class="hidden lg:inline hover:text-amber-700">{{ __('home.nav.faq') }}</a>
            @if(app()->getLocale() === 'zh')<a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('china', 'zh') }}" class="hover:text-amber-700 {{ request()->routeIs('china') ? 'text-amber-700 font-medium' : '' }}">{{ __('china.nav') }}</a>@endif
            <a href="{{ route('guide') }}" class="hidden xl:inline hover:text-amber-700 {{ request()->routeIs('guide') ? 'text-amber-700 font-medium' : '' }}">{{ __('guide.title') }}</a>
            <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('contact', app()->getLocale()) }}" class="hover:text-amber-700 {{ request()->routeIs('contact') ? 'text-amber-700 font-medium' : '' }}">{{ __('contact.title') }}</a>
        </nav>
        <div class="flex items-center gap-3 text-sm">
            @include('partials.locale-switcher')
            @guest
                {{-- Register: a new person with a "+" badge, label shows on hover --}}
                <a href="{{ route('register') }}" title="{{ __('auth.register') }}" aria-label="{{ __('auth.register') }}"
                   class="group relative inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-700 text-white shadow-sm hover:bg-amber-800 transition">
                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor" aria-hidden="true">
                        <circle cx="12" cy="8" r="4.5"/>
                        <path d="M3.5 21c0-4.6 3.8-7.5 8.5-7.5s8.5 2.9 8.5 7.5a.6.6 0 0 1-.6.6H4.1a.6.6 0 0 1-.6-.6Z"/>
                    </svg>
                    <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 ring-2 ring-white flex items-center justify-center">
                        <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" aria-hidden="true"><path d="M6 2.5v7M2.5 6h7" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <span class="pointer-events-none absolute top-full mt-2 right-0 whitespace-nowrap rounded-md bg-stone-900 text-white text-xs px-2 py-1 opacity-0 group-hover:opacity-100 transition">{{ __('auth.register') }}</span>
                </a>
            @endguest
            <a href="{{ auth()->check() ? route('import-batches.index') : route('login') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-stone-900 text-stone-900 rounded-lg font-medium hover:bg-stone-900 hover:text-stone-50 transition">
                {{ auth()->check() ? 'Asl Belgisi' : __('auth.login') }}
            </a>
        </div>
    </div>
</header>
