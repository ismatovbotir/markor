@php
    $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $telegram = config('contact.telegram');
@endphp
<div>
    <div class="flex items-start justify-between gap-6 mb-8 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.offers') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('offers.subtitle') }}</p>
        </div>
        @if($tel)
            <a href="{{ $tel }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                @svg('heroicon-o-phone', 'w-4 h-4') {{ $phone }}
            </a>
        @endif
    </div>

    @include('partials.offer-catalog', ['catalog' => $catalog, 'templatePreviews' => $templatePreviews])
</div>
