<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('auth.register') }} — {{ config('app.name') }}</title>
    @include('partials.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 min-h-screen flex items-center justify-center py-10 px-4">
@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400'; @endphp
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="inline-flex"><img src="/images/logo.png" alt="{{ config('app.name') }}" width="160" height="40" class="h-10 w-auto"></a>
        <p class="text-stone-500 text-sm mt-1">{{ __('auth.register_title') }}</p>
        <div class="mt-3">@include('partials.locale-switcher')</div>
    </div>

    <form method="POST" action="{{ route('register.post') }}" class="bg-white border border-stone-200 rounded-xl shadow-sm p-6 space-y-4">
        @csrf
        @foreach([
            ['name', 'text', 'auth.name', 'name', true],
            ['company', 'text', 'auth.company', 'organization', false],
            ['email', 'email', 'auth.email', 'email', true],
            ['phone', 'tel', 'auth.phone', 'tel', true],
        ] as [$name, $type, $label, $autocomplete, $required])
            <div>
                <label for="{{ $name }}" class="block text-xs font-medium text-stone-600 mb-1">{{ __($label) }}@if(!$required) <span class="text-stone-400">({{ __('common.optional') }})</span>@endif</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @if($required) required @endif
                       @if($name === 'phone') placeholder="+998 90 123 45 67" @endif
                       class="{{ $field }} @error($name) border-rose-300 @enderror">
                @error($name) <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div>
            <label for="password" class="block text-xs font-medium text-stone-600 mb-1">{{ __('auth.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" class="{{ $field }} @error('password') border-rose-300 @enderror">
            @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-stone-600 mb-1">{{ __('auth.password_confirm') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="{{ $field }}">
        </div>
        <p class="text-xs text-stone-500 flex gap-1.5">@svg('heroicon-o-lock-closed', 'w-4 h-4 shrink-0') {{ __('auth.register_note') }}</p>
        <button type="submit" class="w-full py-2.5 bg-amber-700 text-white rounded-lg text-sm font-semibold hover:bg-amber-800 transition">{{ __('auth.register') }}</button>
    </form>

    <p class="text-center text-sm text-stone-500 mt-5">
        {{ __('auth.have_account') }} <a href="{{ route('login') }}" class="text-amber-700 font-medium hover:underline">{{ __('auth.login') }}</a>
    </p>
</div>
</body>
</html>
