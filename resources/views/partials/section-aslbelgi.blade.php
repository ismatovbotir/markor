{{-- Asl Belgisi block: home page (h2) and /asl-belgisi (h1). --}}
@php $hTag ??= 'h2'; @endphp
<section id="aslbelgi" class="bg-stone-900 text-stone-100 scroll-mt-16">
    <div class="max-w-6xl mx-auto px-4 py-20 grid lg:grid-cols-2 gap-12">
        <div>
            <p class="font-mono text-xs uppercase tracking-widest text-amber-400">Asl Belgisi · GS1 DataMatrix</p>
            <{{ $hTag }} class="font-display font-bold text-2xl sm:text-3xl text-white tracking-tight mt-3">{{ __('home.aslbelgi.title') }}</{{ $hTag }}>
            <p class="text-stone-300 mt-4 leading-relaxed">{{ __('home.aslbelgi.text') }}</p>
            <div class="flex flex-wrap items-center gap-4 mt-8">
                <a href="{{ auth()->check() ? route('import-batches.index') : route('register') }}" class="inline-flex items-center gap-2 px-5 py-3 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-500 transition">
                    {{ auth()->check() ? __('home.aslbelgi.cta') : __('home.aslbelgi.register') }} @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </a>
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-stone-300 hover:text-white">{{ __('auth.have_account') }} {{ __('auth.login') }}</a>
                @endguest
            </div>
            <p class="text-sm text-stone-400 mt-4">{{ __('home.aslbelgi.private') }}</p>
            <p class="text-sm text-amber-300 mt-1">{{ __('home.aslbelgi.price') }}</p>
            <p class="text-sm text-stone-400 mt-1">{{ __('home.aslbelgi.topup') }}</p>
            <a href="{{ \App\Http\Controllers\Guest\HomeController::localizedUrl('china', app()->getLocale()) }}" class="inline-block text-sm text-amber-300 hover:text-amber-200 mt-4">{{ __('china.home_link') }}</a>
            <p class="flex items-start gap-2 text-sm text-stone-300 mt-6">@svg('heroicon-o-paper-airplane', 'w-4 h-4 mt-0.5 shrink-0') {{ __('home.aslbelgi.telegram') }}</p>
        </div>
        <ol class="grid sm:grid-cols-2 auto-rows-fr gap-px bg-stone-700 rounded-2xl overflow-hidden self-stretch">
            @foreach([1, 2, 3, 4] as $i)
                <li class="bg-stone-900 p-6">
                    <span class="font-mono text-xs text-amber-400">0{{ $i }}</span>
                    <h3 class="font-semibold text-white mt-2">{{ __("home.aslbelgi.point$i.title") }}</h3>
                    <p class="text-sm text-stone-400 mt-2 leading-relaxed">{{ __("home.aslbelgi.point$i.text") }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
