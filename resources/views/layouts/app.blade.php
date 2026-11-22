<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>
    @include('partials.icons')
    {{-- the super admin uses the public site's type (Golos Text body, JetBrains Mono for Markdown) --}}
    @can('admin')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=golos-text:400,500,600|jetbrains-mono:400,500&display=swap" rel="stylesheet">
    @endcan
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 {{ auth()->user()?->isAdmin() ? 'font-body' : 'font-sans' }} antialiased">

<div class="flex min-h-screen" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
    {{-- Sidebar: a column from lg up, a drawer opened by the menu button below lg --}}
    <div x-show="drawer" x-cloak x-transition.opacity x-on:click="drawer = false" class="fixed inset-0 z-40 bg-stone-900/40 lg:hidden"></div>
    <aside id="app-sidebar" :data-open="drawer" aria-label="{{ __('nav.menu') }}"
           class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-stone-100 border-r border-stone-200 flex flex-col transition-[transform,visibility] duration-200 motion-reduce:transition-none
                  max-lg:invisible max-lg:-translate-x-full max-lg:data-[open=true]:visible max-lg:data-[open=true]:translate-x-0 max-lg:data-[open=true]:shadow-xl lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:w-64 lg:shrink-0">
        <div class="px-6 py-4 lg:py-6 border-b border-stone-200 flex items-start justify-between gap-2">
            <div>
                <h1><img src="/images/logo.png" alt="{{ config('app.name') }}" width="128" height="32" class="h-8 w-auto"></h1>
                <p class="text-xs text-stone-500 mt-1">{{ __('app.tagline') }}</p>
            </div>
            <button type="button" x-on:click="drawer = false" aria-label="{{ __('admin.nav.close') }}" class="lg:hidden p-2 -mr-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-x-mark', 'w-5 h-5')</button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            @auth
                @cannot('admin')
                @if(auth()->user()->company_id)
                {{-- "We offer": own colour + light animation so it stands out from the work menu --}}
                <a href="{{ route('offers.index') }}" class="offer-nav group relative flex items-center px-4 py-3 mb-3 rounded-lg font-semibold overflow-hidden transition {{ request()->routeIs('offers.*') ? 'bg-teal-700 text-white shadow-md' : 'bg-teal-50 text-teal-800 ring-1 ring-teal-200 hover:bg-teal-100' }}">
                    <span class="offer-nav-icon inline-flex mr-3">@svg('heroicon-o-shopping-bag', 'w-5 h-5')</span>
                    <span class="flex-1">{{ __('nav.offers') }}</span>
                    <span class="relative flex w-2 h-2"><span class="offer-nav-ping absolute inline-flex w-full h-full rounded-full bg-teal-400 opacity-75"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-teal-500"></span></span>
                </a>
                <a href="{{ route('import-batches.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('import-batches.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-arrow-up-tray', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.import') }}</span>
                </a>
                <a href="{{ route('products.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('products.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-tag', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.products') }}</span>
                </a>
                <a href="{{ route('marking-codes.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('marking-codes.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-qr-code', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.codes') }}</span>
                </a>
                <a href="{{ route('templates.catalog') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('templates.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-document-duplicate', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.templates') }}</span>
                </a>
                @else
                <a href="{{ route('company.setup') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('company.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-building-office-2', 'w-5 h-5 mr-3')
                    <span>{{ __('company.menu') }}</span>
                </a>
                @endif
                @if(auth()->user()->company_id)
                <a href="{{ route('balance') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('balance') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-wallet', 'w-5 h-5 mr-3')
                    <span class="flex-1">{{ __('nav.balance') }}</span>
                    <span class="text-xs tabular-nums {{ (auth()->user()->company?->balance ?? 0) < 0 ? 'text-rose-600' : 'text-stone-500' }}">{{ number_format((float) auth()->user()->company?->balance, 0, '.', ' ') }}</span>
                </a>
                @endif
                <a href="{{ route('profile') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('profile') || (auth()->user()->company_id && request()->routeIs('company.*')) ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-user-circle', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.profile') }}</span>
                </a>
                <a href="{{ route('help') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('help') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-question-mark-circle', 'w-5 h-5 mr-3')
                    <span>{{ __('help.title') }}</span>
                </a>
                @endcannot

                @can('admin')
                    @include('partials.admin-nav')
                @endcan
            @endauth
        </nav>

        <div class="px-4 pt-3">@include('partials.locale-switcher')</div>
        <div class="px-4 py-3 text-xs text-stone-500 flex items-center justify-between">
            <span class="truncate">{{ auth()->user()?->name }} <span class="block text-[10px] text-stone-400">{{ __('users.roles.' . (auth()->user()?->role ?? 'user')) }}</span></span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-stone-400 hover:text-rose-600">{{ __('auth.logout') }}</button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="flex-1 min-w-0 flex flex-col">
        {{-- top bar with the menu button (below lg only) --}}
        <div class="lg:hidden bg-stone-100 border-b border-stone-200 px-3 py-2 flex items-center gap-2">
            <button type="button" x-on:click="drawer = true" :aria-expanded="drawer" aria-controls="app-sidebar" aria-label="{{ __('admin.nav.open') }}" data-menu-button
                    class="p-2 -ml-1 rounded-lg text-stone-700 hover:bg-stone-200">@svg('heroicon-o-bars-3', 'w-6 h-6')</button>
            <img src="/images/logo.png" alt="{{ config('app.name') }}" width="96" height="24" class="h-6 w-auto">
        </div>
        <div class="px-4 sm:px-6 lg:px-8 py-6 flex-1">
            @php $helpPage = \App\Http\Controllers\HelpController::FOR_ROUTE[request()->route()?->getName()] ?? null; @endphp
            @if($helpPage && !auth()->user()?->isAdmin())
                <a href="{{ route('help', $helpPage) }}" class="float-right ml-4 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-stone-200 bg-white text-xs text-stone-600 hover:text-amber-700 hover:border-amber-300" title="{{ __('help.title') }}">
                    @svg('heroicon-o-question-mark-circle', 'w-4 h-4') {{ __('help.button') }}
                </a>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>

{{-- Toast notifications --}}
<div
    x-data="{ show: false, type: 'success', message: '' }"
    @if(session('notify')) x-init="message = @js(session('notify')); show = true; window.__toastTimer = setTimeout(() => show = false, 3000)" @endif
    x-on:notify.window="const d = Array.isArray($event.detail) ? $event.detail[0] : $event.detail; show = true; type = d.type; message = d.message; clearTimeout(window.__toastTimer); window.__toastTimer = setTimeout(() => show = false, 3000)"
    x-show="show"
    x-transition
    class="fixed {{ auth()->user()?->isAdmin() ? 'bottom-20' : 'bottom-5' }} right-5 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white"
    :class="type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'"
>
    <span x-text="message"></span>
</div>

@can('admin')<x-admin.confirm-delete />@endcan

@livewireScripts
@include('partials.dev-role-switch')
</body>
</html>
