{{-- Footer of all public pages: about, links, contacts (WeChat on the Chinese version only). --}}
@php
    use App\Http\Controllers\Guest\HomeController;
    $fPhone = config('contact.phone');
    $fTel = $fPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $fPhone) : null;
    $fLocale = app()->getLocale();
@endphp
<footer class="border-t border-stone-200 bg-white/50">
    <div class="max-w-6xl mx-auto px-4 py-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
        <div>
            <p><img src="/images/logo.png" alt="{{ config('app.name') }}" width="96" height="24" class="h-6 w-auto"></p>
            <p class="text-stone-500 mt-2 leading-relaxed">{{ __('home.footer.about') }}</p>
        </div>
        <nav class="flex flex-col gap-2 text-stone-600">
            <a href="{{ HomeController::localizedUrl('studio', $fLocale) }}" class="hover:text-amber-700">{{ __('nav.studio') }}</a>
            <a href="{{ route('home') }}#aslbelgi" class="hover:text-amber-700">Asl Belgisi</a>
            <a href="{{ route('home') }}#offers" class="hover:text-amber-700">{{ __('nav.offers') }}</a>
            <a href="{{ route('guide') }}" class="hover:text-amber-700">{{ __('guide.title') }}</a>
        </nav>
        <nav class="flex flex-col gap-2 text-stone-600">
            <a href="{{ route('home') }}#faq" class="hover:text-amber-700">{{ __('home.nav.faq') }}</a>
            <a href="{{ HomeController::localizedUrl('china', $fLocale) }}" class="hover:text-amber-700">{{ __('china.nav') }}</a>
            <a href="{{ HomeController::localizedUrl('contact', $fLocale) }}" class="hover:text-amber-700">{{ __('contact.title') }}</a>
        </nav>
        <div class="flex flex-col gap-2 text-stone-600">
            @if($fTel)<a href="{{ $fTel }}" class="inline-flex items-center gap-1.5 font-mono hover:text-amber-700">@svg('heroicon-o-phone', 'w-4 h-4') {{ $fPhone }}</a>@endif
            @if($tg = config('contact.telegram'))<a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-amber-700">@svg('heroicon-o-paper-airplane', 'w-4 h-4') Telegram</a>@endif
            @if($mail = config('contact.email'))<a href="mailto:{{ $mail }}" class="inline-flex items-center gap-1.5 hover:text-amber-700">@svg('heroicon-o-envelope', 'w-4 h-4') {{ $mail }}</a>@endif
            @include('partials.wechat-contact', ['compact' => true])
            <div class="mt-1">@include('partials.locale-switcher')</div>
        </div>
    </div>
    <p class="border-t border-stone-200 py-4 text-center text-xs text-stone-500">{{ config('app.name') }} · {{ __('app.tagline') }}</p>
</footer>
