<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.templates') }}</h1>
        <p class="text-sm text-stone-500 mt-1">{{ __('templates.catalog_hint') }}</p>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($templates as $t)
            <article class="bg-white border border-stone-200 rounded-xl p-4 flex flex-col">
                <div class="bg-stone-100 rounded-lg p-4 flex items-center justify-center h-52">
                    @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 260, 'maxH' => 170])
                </div>
                <div class="flex items-start justify-between gap-3 mt-3">
                    <div>
                        <h2 class="font-medium">{{ $t->name }}</h2>
                        <p class="text-xs text-stone-500 mt-0.5">
                            {{ $t->width_mm }}×{{ $t->height_mm }} mm
                            @if(($t->columns ?? 1) > 1) · {{ $t->columns }} {{ __('templates.per_row') }}, {{ __('templates.gap') }} {{ rtrim(rtrim(number_format($t->gap_mm, 1, '.', ''), '0'), '.') }}@endif
                        </p>
                    </div>
                    @if($t->is_default)
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('templates.default') }}</span>
                    @endif
                </div>
                <a href="{{ route('templates.preview', $t) }}" target="_blank" class="text-xs text-amber-700 hover:underline mt-2">{{ __('templates.preview_pdf') }}</a>
            </article>
        @empty
            <p class="text-stone-400">{{ __('common.no_data') }}</p>
        @endforelse
    </div>
</div>
