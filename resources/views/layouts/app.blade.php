<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>
    @include('partials.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-sans antialiased">

<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="w-64 bg-stone-100 border-r border-stone-200 flex flex-col">
        <div class="px-6 py-6 border-b border-stone-200">
            <h1><img src="/images/logo.png" alt="{{ config('app.name') }}" width="128" height="32" class="h-8 w-auto"></h1>
            <p class="text-xs text-stone-500 mt-1">{{ __('app.tagline') }}</p>
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
                <a href="{{ route('company.users') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('company.users') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-users', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.company_users') }}</span>
                </a>
                @endif
                <a href="{{ route('profile') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('profile') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-user-circle', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.profile') }}</span>
                </a>
                <a href="{{ route('help') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('help') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-question-mark-circle', 'w-5 h-5 mr-3')
                    <span>{{ __('help.title') }}</span>
                </a>
                @endcannot

                @can('admin')
                <p class="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-stone-400">{{ __('nav.admin') }}</p>
                <a href="{{ route('admin.clients') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('admin.clients', 'admin.companies.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-building-office-2', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.companies') }}</span>
                </a>
                <a href="{{ route('products.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('products.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-tag', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.products') }}</span>
                </a>
                <a href="{{ route('marking-codes.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('marking-codes.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-qr-code', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.codes') }}</span>
                </a>
                <a href="{{ route('product-groups.index') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('product-groups.*') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-rectangle-group', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.product_groups') }}</span>
                </a>
                @php $pendingPayments = \App\Domain\Billing\Payment::where('status', 'pending')->count(); @endphp
                <a href="{{ route('admin.payments') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('admin.payments') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-banknotes', 'w-5 h-5 mr-3')
                    <span class="flex-1">{{ __('nav.payments') }}</span>
                    @if($pendingPayments)<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-semibold">{{ $pendingPayments }}</span>@endif
                </a>
                @php $tgUnmatched = \App\Domain\Telegram\TelegramLink::where('status', 'phone_mismatch')->where('updated_at', '>=', now()->subDays(7))->count(); @endphp
                <a href="{{ route('admin.telegram') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('admin.telegram') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-paper-airplane', 'w-5 h-5 mr-3')
                    <span class="flex-1">Telegram</span>
                    @if($tgUnmatched)<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-semibold" title="{{ __('tg_admin.tab_unmatched') }}">{{ $tgUnmatched }}</span>@endif
                </a>
                <a href="{{ route('admin.settings') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('admin.settings') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-cog-6-tooth', 'w-5 h-5 mr-3')
                    <span>{{ __('nav.settings') }}</span>
                </a>
                <a href="{{ route('admin.seo') }}" class="flex items-center px-4 py-3 rounded-lg font-medium transition {{ request()->routeIs('admin.seo') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-magnifying-glass', 'w-5 h-5 mr-3')
                    <span class="flex-1">SEO</span>
                    @if(\App\Support\Seo::noindex())<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-semibold">noindex</span>@endif
                </a>

                <p class="px-4 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-teal-700 flex items-center gap-1.5"><span class="offer-nav-icon inline-flex">@svg('heroicon-o-shopping-bag', 'w-3.5 h-3.5')</span>{{ __('nav.offers') }}</p>
                @php $newRequests = \App\Domain\Offers\Models\OfferRequest::where('status', 'new')->count(); @endphp
                <a href="{{ route('admin.offer-requests') }}" class="flex items-center px-4 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('admin.offer-requests') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-inbox-arrow-down', 'w-5 h-5 mr-3')
                    <span class="flex-1">{{ __('nav.offer_requests') }}</span>
                    @if($newRequests)<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-semibold">{{ $newRequests }}</span>@endif
                </a>
                @php $newTemplateRequests = \App\Domain\Printing\Models\TemplateRequest::where('status', 'new')->count(); @endphp
                <a href="{{ route('admin.template-requests') }}" class="flex items-center px-4 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('admin.template-requests') ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                    @svg('heroicon-o-document-plus', 'w-5 h-5 mr-3')
                    <span class="flex-1">{{ __('nav.template_requests') }}</span>
                    @if($newTemplateRequests)<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-semibold">{{ $newTemplateRequests }}</span>@endif
                </a>
                @foreach(\App\Domain\Offers\Models\OfferCategory::orderBy('sort_order')->orderBy('id')->get() as $offerCategory)
                    @php $route = $offerCategory->isTemplates() ? route('label-templates.index') : route('admin.offers', $offerCategory); @endphp
                    @php $active = $offerCategory->isTemplates() ? request()->routeIs('label-templates.*') || request()->is('admin/offers/' . $offerCategory->slug) : request()->is('admin/offers/' . $offerCategory->slug); @endphp
                    <a href="{{ $route }}" class="flex items-center px-4 py-2.5 rounded-lg font-medium transition {{ $active ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }} {{ $offerCategory->is_active ? '' : 'opacity-50' }}">
                        @svg($offerCategory->icon ?: 'heroicon-o-cube', 'w-5 h-5 mr-3')
                        <span>{{ $offerCategory->name }}</span>
                    </a>
                @endforeach
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
    <main class="flex-1 flex flex-col">
        <div class="px-8 py-6 flex-1">
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
    x-on:notify.window="const d = Array.isArray($event.detail) ? $event.detail[0] : $event.detail; show = true; type = d.type; message = d.message; clearTimeout(window.__toastTimer); window.__toastTimer = setTimeout(() => show = false, 3000)"
    x-show="show"
    x-transition
    class="fixed bottom-5 right-5 z-50 px-4 py-3 rounded-lg shadow-lg text-sm text-white"
    :class="type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'"
>
    <span x-text="message"></span>
</div>

@livewireScripts
@include('partials.dev-role-switch')
</body>
</html>
