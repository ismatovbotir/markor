{{-- One brand / product banner slide (home slider and the admin preview). $b = Banner, $locale = optional preview language, $image = optional picture url --}}
@php
    $locale ??= null;
    $theme = $b->theme();
    $link = $b->link();
    $external = $link && preg_match('#^https?://#i', $link);
    $rel = trim(($external ? 'noopener ' : '') . ($b->is_sponsored ? 'sponsored' : ($external ? 'nofollow' : '')));
    $image ??= $b->imageUrl();
    $text = $b->localized('text', $locale);
@endphp
<div class="w-full max-w-6xl mx-auto px-4 py-14 lg:py-20 grid lg:grid-cols-2 gap-10 items-center">
    <div>
        @if($b->is_sponsored)<span class="inline-block text-[11px] uppercase tracking-wider px-2 py-0.5 rounded border {{ $b->style === 'light' ? 'border-stone-300 text-stone-500' : 'border-white/40 text-white/80' }}">{{ __('banners.ad', [], $locale) }}</span>@endif
        <h2 class="font-display font-bold text-3xl sm:text-4xl tracking-tight mt-3 {{ $theme[1] }}">{{ $b->localized('title', $locale) }}</h2>
        @if($text)<p class="text-lg mt-4 leading-relaxed max-w-xl {{ $theme[2] }}">{{ $text }}</p>@endif
        @if($link)
            <a href="{{ $link }}" @if($external) target="_blank" @endif @if($rel) rel="{{ $rel }}" @endif class="inline-flex items-center gap-2 mt-8 px-6 py-3.5 rounded-xl font-semibold transition {{ $theme[3] }}">
                {{ $b->localized('button', $locale) ?: __('banners.more', [], $locale) }} @svg($external ? 'heroicon-o-arrow-top-right-on-square' : 'heroicon-o-arrow-right', 'w-4 h-4')
            </a>
        @endif
    </div>
    @if($image)
        <div class="flex items-center justify-center">
            <div class="bg-white rounded-3xl shadow-2xl ring-1 ring-black/5 p-6 w-full max-w-md">
                <img src="{{ $image }}" alt="{{ $b->localized('title', $locale) }}" loading="lazy" class="w-full h-72 object-contain">
            </div>
        </div>
    @endif
</div>
