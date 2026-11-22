{{-- One of our products, shown while a PDF is being generated. Needs `promoIndex` in the Alpine scope.
     Fixed height (h-44) for every product: long texts are clamped, so the dialog never jumps. --}}
@php
    $promoPhone = config('contact.phone');
    $promoTel = $promoPhone ? 'tel:' . preg_replace('/[^\d+]/', '', $promoPhone) : null;
@endphp
@foreach($offers->values() as $i => $offer)
    <div x-show="promoIndex === {{ $i }}" x-cloak class="mb-5 h-44 overflow-hidden flex flex-col border border-amber-200 bg-amber-50 rounded-xl p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700 mb-2">{{ __('nav.offers') }}</p>
        <div class="flex items-start gap-3 min-h-0 flex-1">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-white text-amber-700 shrink-0">@svg($offer->icon ?: 'heroicon-o-cube', 'w-5 h-5')</span>
            <div class="flex-1 min-w-0 min-h-0 flex flex-col h-full">
                <h3 class="font-semibold text-sm line-clamp-2" title="{{ $offer->title }}">{{ $offer->title }}</h3>
                <p class="text-sm text-stone-600 mt-1 line-clamp-3">{{ $offer->description }}</p>
                @if($promoTel)
                    <a href="{{ $promoTel }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-700 hover:text-amber-900 mt-auto pt-2 truncate">
                        @svg('heroicon-o-phone', 'w-4 h-4') {{ __("offers.action.$offer->action") }} · {{ $promoPhone }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endforeach
