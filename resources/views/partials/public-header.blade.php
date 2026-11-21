@php
    $headerPhone = config('contact.phone');
    $headerTel = $headerPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $headerPhone) : null;
@endphp
<header class="sticky top-0 z-30 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="font-display font-bold text-lg tracking-tight text-stone-900 flex items-center gap-2">
            <span class="inline-block w-5 h-5 bg-amber-700 rounded-[5px] relative after:absolute after:inset-[5px] after:bg-stone-50 after:rounded-[1px]"></span>
            {{ config('app.name') }}
        </a>
        <nav class="hidden md:flex items-center gap-7 text-sm text-stone-600">
            <a href="{{ route('studio') }}" class="hover:text-amber-700 {{ request()->routeIs('studio') ? 'text-amber-700 font-medium' : '' }}">{{ __('nav.studio') }}</a>
            <a href="{{ route('home') }}#aslbelgi" class="hover:text-amber-700">Asl Belgisi</a>
            <a href="{{ route('home') }}#offers" class="hover:text-amber-700">{{ __('nav.offers') }}</a>
            <a href="{{ route('home') }}#faq" class="hover:text-amber-700">{{ __('home.nav.faq') }}</a>
        </nav>
        <div class="flex items-center gap-3 text-sm">
            @include('partials.locale-switcher')
            @if($headerTel)<a href="{{ $headerTel }}" class="text-stone-600 hover:text-amber-700 hidden lg:inline font-mono text-xs">{{ $headerPhone }}</a>@endif
            @guest
                <a href="{{ route('register') }}" class="hidden sm:inline text-stone-600 hover:text-amber-700">{{ __('auth.register') }}</a>
            @endguest
            <a href="{{ auth()->check() ? route('import-batches.index') : route('login') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-stone-900 text-stone-900 rounded-lg font-medium hover:bg-stone-900 hover:text-stone-50 transition">
                {{ auth()->check() ? 'Asl Belgisi' : __('auth.login') }}
            </a>
        </div>
    </div>
</header>
