@php
    $headerPhone = config('contact.phone');
    $headerTel = $headerPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $headerPhone) : null;
@endphp
<header x-data="{ menu: false }" x-on:keydown.escape.window="menu = false" class="sticky top-0 z-30 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center shrink-0" aria-label="{{ config('app.name') }}">
            <img src="/images/logo.png" alt="{{ config('app.name') }}" width="112" height="28" class="h-6 sm:h-7 w-auto">
        </a>
        @php
            $loc = app()->getLocale();
            $u = fn ($route) => \App\Http\Controllers\Guest\HomeController::localizedUrl($route, $loc);
            // one list for the desktop menu and the phone menu: [url, label, active, desktop visibility]
            $menu = array_values(array_filter([
                [$u('studio'), __('nav.studio'), request()->routeIs('studio'), 'lg:inline'],
                [$u('aslbelgi'), 'Asl Belgisi', request()->routeIs('aslbelgi'), 'lg:inline'],
                [$u('marketplace'), __('nav.offers'), request()->routeIs('marketplace', 'marketplace.item'), 'lg:inline'],
                [$u('faq'), __('home.nav.faq'), request()->routeIs('faq'), 'xl:inline'],
                $loc === 'zh' ? [$u('china'), __('china.nav'), request()->routeIs('china'), 'lg:inline'] : null,
                [route('guide'), __('guide.title'), request()->routeIs('guide'), 'xl:inline'],
                [$u('drivers'), __('nav.drivers'), request()->routeIs('drivers*'), 'lg:inline'],
                [$u('contact'), __('contact.title'), request()->routeIs('contact'), 'lg:inline'],
            ]));
        @endphp
        <nav class="hidden lg:flex items-center gap-4 xl:gap-6 text-sm text-stone-600 whitespace-nowrap">
            @foreach($menu as [$href, $label, $active, $show])
                <a href="{{ $href }}" class="hidden {{ $show }} hover:text-amber-700 {{ $active ? 'text-amber-700 font-medium' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2 sm:gap-3 text-sm">
            <span class="hidden sm:inline-flex">@include('partials.locale-switcher')</span>
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
               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-stone-900 text-stone-900 rounded-lg font-medium whitespace-nowrap hover:bg-stone-900 hover:text-stone-50 transition">
                {{ auth()->check() ? 'Asl Belgisi' : __('auth.login') }}
            </a>
            {{-- phone / tablet: the menu opens as a list --}}
            <button type="button" x-on:click="menu = !menu" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-stone-300 text-stone-700" :aria-expanded="menu" aria-label="{{ __('nav.menu') }}">
                <span x-show="!menu">@svg('heroicon-o-bars-3', 'w-5 h-5')</span>
                <span x-show="menu" x-cloak>@svg('heroicon-o-x-mark', 'w-5 h-5')</span>
            </button>
        </div>
    </div>
    <nav x-show="menu" x-cloak x-transition.origin.top class="lg:hidden border-t border-stone-200 bg-stone-50">
        <div class="max-w-6xl mx-auto px-4 py-2 grid sm:grid-cols-2 gap-x-6">
            @foreach($menu as [$href, $label, $active])
                <a href="{{ $href }}" x-on:click="menu = false" class="py-3 border-b border-stone-200/70 text-[15px] {{ $active ? 'text-amber-700 font-medium' : 'text-stone-700' }}">{{ $label }}</a>
            @endforeach
            <div class="py-3 sm:hidden">@include('partials.locale-switcher')</div>
        </div>
    </nav>
</header>
