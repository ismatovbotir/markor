@php
    $headerPhone = config('contact.phone');
    $headerTel = $headerPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $headerPhone) : null;
@endphp
<header x-data="{ menu: false }" x-on:keydown.escape.window="menu = false" class="sticky top-0 z-30 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        {{-- logo and menu together on the left; language and login on the right --}}
        <div class="flex items-center gap-3 min-w-0">
        <a href="{{ route('home') }}" class="flex items-center shrink-0" aria-label="{{ config('app.name') }}">
            <img src="/images/logo.png" alt="{{ config('app.name') }}" width="112" height="28" class="h-6 sm:h-7 w-auto">
        </a>
        @php
            $loc = app()->getLocale();
            $u = fn ($route) => \App\Http\Controllers\Guest\HomeController::localizedUrl($route, $loc);
            // Markor's own icon (resources/svg/markor), or the Heroicon fallback while a file is missing
            $i = fn (string $name, string $fallback) => is_file(resource_path("svg/markor/$name.svg")) ? "markor-$name" : "heroicon-o-$fallback";
            // one list for the desktop icon bar and the phone menu: [url, label, active, icon]
            $menu = array_values(array_filter([
                [$u('home'), __('nav.home'), request()->routeIs('home'), $i('home', 'home')],
                [$u('studio'), __('nav.studio'), request()->routeIs('studio'), $i('label-studio', 'pencil-square')],
                [$u('aslbelgi'), 'Asl Belgisi', request()->routeIs('aslbelgi'), $i('asl-belgisi', 'qr-code')],
                [$u('marketplace'), __('nav.offers'), request()->routeIs('marketplace', 'marketplace.item'), $i('marketplace', 'shopping-bag')],
                [$u('faq'), __('home.nav.faq'), request()->routeIs('faq'), $i('faq', 'question-mark-circle')],
                $loc === 'zh' ? [$u('china'), __('china.nav'), request()->routeIs('china'), $i('china', 'globe-asia-australia')] : null,
                [route('guide'), __('guide.title'), request()->routeIs('guide'), $i('guide', 'book-open')],
                [$u('drivers'), __('nav.drivers'), request()->routeIs('drivers*'), $i('drivers', 'arrow-down-tray')],
                [$u('news'), __('nav.news'), request()->routeIs('news*'), $i('news', 'newspaper')],
                [$u('contact'), __('contact.title'), request()->routeIs('contact'), $i('contact', 'phone')],
            ]));
        @endphp
        {{-- desktop: icons only; the name shows as a tooltip and is read by screen readers --}}
        <nav class="hidden lg:flex items-center gap-1 text-stone-600 border-l border-stone-200 pl-3 ml-2" aria-label="{{ __('nav.menu') }}">
            @foreach($menu as [$href, $label, $active, $icon])
                <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" @if($active) aria-current="page" @endif
                   class="group relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition {{ $active ? 'text-amber-700 bg-amber-50' : 'hover:text-amber-700 hover:bg-stone-100' }}">
                    @svg($icon, 'w-5 h-5')
                    <span class="pointer-events-none absolute top-full mt-1.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-stone-900 text-white text-xs px-2 py-1 opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition z-40" aria-hidden="true">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
        </div>
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
            @foreach($menu as [$href, $label, $active, $icon])
                <a href="{{ $href }}" x-on:click="menu = false" @if($active) aria-current="page" @endif class="flex items-center gap-3 py-3 border-b border-stone-200/70 text-[15px] {{ $active ? 'text-amber-700 font-medium' : 'text-stone-700' }}">@svg($icon, 'w-5 h-5 shrink-0') {{ $label }}</a>
            @endforeach
            <div class="py-3 sm:hidden">@include('partials.locale-switcher')</div>
        </div>
    </nav>
</header>
