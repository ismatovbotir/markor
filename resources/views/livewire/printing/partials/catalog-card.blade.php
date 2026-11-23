{{-- A template card of the client catalog. $block: 'mine' (own design or starred global) / 'global'. --}}
@php
    $own = $t->company_id && $t->company_id === auth()->user()->company_id && !$t->client_deleted_at;
    $starred = in_array($t->id, $mineIds, true);
@endphp
<article wire:key="{{ $block }}-tpl-{{ $t->id }}" class="bg-white border rounded-xl p-4 flex flex-col {{ $block === 'mine' ? 'border-amber-600' : 'border-stone-200' }}">
    <div class="relative bg-stone-100 rounded-lg p-4 flex items-center justify-center h-52">
        @if(auth()->user()->company_id && !$own)
            {{-- ★ = in "Mine" (offered when printing) --}}
            <button type="button" wire:click="toggleMine({{ $t->id }})" aria-pressed="{{ $starred ? 'true' : 'false' }}"
                    title="{{ $starred ? __('templates.remove_mine') : __('templates.add_mine') }}" aria-label="{{ $starred ? __('templates.remove_mine') : __('templates.add_mine') }}"
                    class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white shadow-sm flex items-center justify-center transition {{ $starred ? 'text-amber-700' : 'text-stone-400 hover:text-amber-700' }}">
                @svg($starred ? 'heroicon-s-star' : 'heroicon-o-star', 'w-5 h-5')
            </button>
        @endif
        @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 260, 'maxH' => 170])
    </div>
    <div class="flex items-start justify-between gap-3 mt-3">
        <div class="min-w-0">
            <h3 class="font-medium break-words">{{ $t->name }}</h3>
            <p class="text-xs text-stone-500 mt-0.5">
                {{ $t->width_mm }}×{{ $t->height_mm }} mm
                @if(($t->columns ?? 1) > 1) · {{ $t->columns }} {{ __('templates.per_row') }}, {{ __('templates.gap') }} {{ rtrim(rtrim(number_format($t->gap_mm, 1, '.', ''), '0'), '.') }}@endif
            </p>
        </div>
        <span class="shrink-0 flex flex-col items-end gap-1">
            @if($own)
                <span class="px-2 py-0.5 rounded-full text-xs bg-sky-50 text-sky-800">{{ __('templates.tag_mine') }}</span>
            @elseif($block === 'mine')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-stone-100 text-stone-700"><img src="/images/mark.svg" alt="" class="w-3.5 h-3.5"> Markor</span>
            @endif
            @if($t->is_default)
                <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('templates.default') }}</span>
            @endif
        </span>
    </div>
    <div class="flex items-center gap-3 flex-wrap mt-2 text-xs">
        <a href="{{ route('templates.preview', $t) }}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">{{ __('templates.preview_pdf') }}</a>
        @if($own)
            <a href="{{ route('templates.mine', ['edit' => $t->id]) }}" class="text-amber-800 hover:underline">{{ __('common.edit') }}</a>
            <button type="button" wire:click="deleteOwn({{ $t->id }})" wire:confirm="{{ __('templates.client_delete_text') }}" class="text-rose-600 hover:underline">{{ __('common.delete') }}</button>
        @elseif(auth()->user()->company_id && $slotsLeft > 0)
            <a href="{{ route('templates.mine', ['copy' => $t->id]) }}" class="text-amber-800 hover:underline">{{ __('templates.copy_and_edit') }}</a>
        @endif
    </div>
</article>
