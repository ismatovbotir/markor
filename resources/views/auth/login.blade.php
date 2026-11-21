<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('auth.login') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-amber-700">{{ config('app.name') }}</h1>
            <p class="text-stone-500 text-sm mt-1">{{ __('app.tagline') }}</p>
            <div class="mt-3">@include('partials.locale-switcher')</div>
        </div>

        <div class="bg-white border border-stone-200 rounded-xl overflow-hidden shadow-sm"
             x-data="{ tab: 'qr' }">

            {{-- Tab header --}}
            <div class="flex bg-stone-50 border-b border-stone-200">
                <button
                    @click="tab = 'qr'"
                    :class="tab === 'qr' ? 'bg-white border-b-2 border-amber-600 text-amber-700 font-semibold' : 'text-stone-600 hover:text-stone-800'"
                    class="flex-1 py-3 px-4 text-sm transition border-b-2 border-transparent">
                    @svg('heroicon-o-qr-code', 'w-4 h-4 inline mr-2') {{ __('auth.qr_tab') }}
                </button>
                <button
                    @click="tab = 'login'"
                    :class="tab === 'login' ? 'bg-white border-b-2 border-amber-600 text-amber-700 font-semibold' : 'text-stone-600 hover:text-stone-800'"
                    class="flex-1 py-3 px-4 text-sm transition border-b-2 border-transparent">
                    @svg('heroicon-o-key', 'w-4 h-4 inline mr-2') {{ __('auth.login') }}
                </button>
            </div>

            {{-- Error message (global) --}}
            @if(session('error'))
                <div class="p-4 bg-rose-50 border-b border-rose-200 text-rose-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Tab content --}}
            <div class="p-6">
                {{-- Tab: QR Scan (default) --}}
                <div x-show="tab === 'qr'" x-transition class="space-y-4">
                    <p class="text-xs text-stone-500 text-center">{{ __('auth.qr_hint') }}</p>
                    <input
                        type="text"
                        id="qr-scan-field"
                        autofocus
                        class="w-full border-2 border-amber-400 rounded-lg px-4 py-4 text-center text-lg font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent bg-amber-50"
                        placeholder="{{ __('auth.qr_placeholder') }}">
                    <div class="flex items-center justify-center gap-2 text-sm text-amber-600">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        {{ __('auth.scanner_active') }}
                    </div>
                </div>

                {{-- Tab: Email + Password --}}
                <div x-show="tab === 'login'" x-transition class="space-y-4">
                    <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('auth.email') }}</label>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="w-full border border-stone-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400
                                @error('email') border-rose-300 @enderror"
                                placeholder="admin@example.com">
                            @error('email') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('auth.password') }}</label>
                            <input
                                type="password"
                                name="password"
                                class="w-full border border-stone-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400
                                @error('password') border-rose-300 @enderror"
                                placeholder="••••••••">
                            @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" name="remember" class="rounded border-stone-300 text-amber-600">
                            {{ __('auth.remember_me') }}
                        </label>

                        <button type="submit" class="w-full bg-amber-700 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-amber-800 transition">
                            {{ __('auth.login') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <p class="text-xs text-stone-400 text-center mt-6">
            Demo: <code class="bg-stone-100 px-2 py-1 rounded">admin@aslbelgi.local / admin123</code>
        </p>
        <p class="text-center text-sm text-stone-500 mt-5">
            {{ __('auth.no_account') }} <a href="{{ route('register') }}" class="text-amber-700 font-medium hover:underline">{{ __('auth.register') }}</a>
        </p>
    </div>

    <script>
        const qrField = document.getElementById('qr-scan-field');
        qrField.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("login.qr") }}';
                const csrf = document.createElement('input');
                csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
                const token = document.createElement('input');
                token.type = 'hidden'; token.name = 'qr_token'; token.value = this.value.trim();
                form.appendChild(csrf); form.appendChild(token);
                document.body.appendChild(form); form.submit();
            }
        });
    </script>
@include('partials.dev-role-switch')
</body>
</html>
