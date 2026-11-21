{{-- Marketplace catalog block: home page (h2) and /marketplace (h1). Needs $catalog, $templatePreviews. --}}
@php
    $hTag ??= 'h2';
    $phone = config('contact.phone');
    $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
@endphp
@if($catalog->isNotEmpty())
    <section id="offers" class="max-w-6xl mx-auto px-4 py-20 scroll-mt-16">
        <div class="flex items-end justify-between gap-6 flex-wrap">
            <div>
                <{{ $hTag }} class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.offers.title') }}</{{ $hTag }}>
                <p class="text-stone-600 mt-3 max-w-2xl">{{ __('home.offers.subtitle') }}</p>
            </div>
            @if($tel)
                <a href="{{ $tel }}" class="inline-flex items-center gap-2 px-5 py-3 border border-stone-900 rounded-xl font-semibold hover:bg-stone-900 hover:text-white transition">@svg('heroicon-o-phone', 'w-4 h-4') {{ $phone }}</a>
            @endif
        </div>
        <div class="mt-10">@include('partials.offer-catalog', ['catalog' => $catalog, 'templatePreviews' => $templatePreviews])</div>
    </section>
@endif
