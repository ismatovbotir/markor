<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full bg-white border border-stone-200 rounded-xl p-6 text-center">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-rose-100 text-rose-700">@svg('heroicon-o-lock-closed', 'w-6 h-6')</span>
        <h1 class="text-lg font-semibold mt-3">{{ __('companies.blocked_title') }}</h1>
        <p class="text-sm text-stone-600 mt-2">{{ __('companies.blocked_text', ['company' => $company->name]) }}</p>
        @if($company->blocked_reason)<p class="text-sm text-stone-500 mt-2">{{ $company->blocked_reason }}</p>@endif
        @if(config('contact.phone'))<a href="tel:{{ preg_replace('/[^\d+]/', '', config('contact.phone')) }}" class="inline-block mt-4 text-amber-700 font-medium">{{ config('contact.phone') }}</a>@endif
        <form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<button class="text-sm text-stone-500 hover:text-stone-800">{{ __('auth.logout') }}</button></form>
    </div>
</body>
</html>
