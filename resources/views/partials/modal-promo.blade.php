{{-- A random product of ours, for client and visitor modals. Re-rolled every time the modal is rendered/opened. --}}
@php
    $promoItems = once(fn () => \App\Domain\Offers\Models\Offer::active()->orderBy('sort_order')->get());
    $promo = $promoItems->shuffle()->first();
    $promoPhone = config('contact.phone');
@endphp
@if($promo && !auth()->user()?->isAdmin())
    <div class="mt-5 border border-amber-200 bg-amber-50 rounded-xl p-3 flex items-center gap-3 text-left">
        @if($promo->image_url)
            <img src="{{ $promo->image_url }}" alt="" class="w-14 h-14 object-contain rounded-lg bg-white shrink-0">
        @else
            <span class="flex items-center justify-center w-14 h-14 rounded-lg bg-white text-amber-700 shrink-0">@svg($promo->icon ?: 'heroicon-o-cube', 'w-6 h-6')</span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">{{ __('nav.offers') }}</p>
            <p class="font-semibold text-sm text-stone-900 leading-tight">{{ $promo->title }}</p>
            <p class="text-xs text-stone-600 mt-0.5 line-clamp-2">{{ $promo->description }}</p>
        </div>
        @if($promo->url)
            <a href="{{ $promo->url }}" target="_blank" rel="noopener" class="shrink-0 text-xs font-semibold text-amber-700 hover:text-amber-900">{{ __("offers.action.$promo->action") }} →</a>
        @elseif($promoPhone)
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $promoPhone) }}" class="shrink-0 text-xs font-semibold text-amber-700 hover:text-amber-900">{{ __("offers.action.$promo->action") }}</a>
        @endif
    </div>
@endif
