{{-- The one language switch of a page (inside x-admin.i18n-form). It switches every translatable field client-side.
     Each tab: state mark (check / dot / ring / triangle), filled count, error or warning badge.
     bulk: show "copy UZ texts" and "paste translation". Slot actions: extra buttons (e.g. AI fill). --}}
@props(['bulk' => true])
@php $tabs = \App\Support\AdminI18n::tabs(); $codes = array_keys($tabs); @endphp
<div {{ $attributes->class('sticky top-0 z-20 -mb-px bg-white/95 backdrop-blur border-b border-stone-200 rounded-t-xl px-3 sm:px-6 py-2.5 flex items-center justify-between gap-x-3 gap-y-2 flex-wrap') }}>
    <div role="tablist" aria-label="{{ __('admin.i18n.content_lang') }}" class="grid grid-cols-3 sm:inline-flex gap-1 rounded-lg bg-stone-100 p-1 w-full sm:w-auto">
        @foreach($tabs as $l => $tab)
            @php $next = $codes[($loop->index + 1) % count($codes)]; $prev = $codes[($loop->index + count($codes) - 1) % count($codes)]; @endphp
            <button type="button" role="tab" id="admin-tab-{{ $l }}" data-lang-tab="{{ $l }}" x-on:click="lang = '{{ $l }}'"
                    x-on:keydown.arrow-right.prevent="focusTab('{{ $next }}')" x-on:keydown.arrow-left.prevent="focusTab('{{ $prev }}')"
                    :aria-selected="lang === '{{ $l }}'" :tabindex="lang === '{{ $l }}' ? 0 : -1" :aria-label="tabLabel('{{ $l }}', @js($tab['name']))"
                    class="admin-focus inline-flex items-center justify-center gap-1.5 rounded-md px-2.5 sm:px-3 py-1.5 text-sm min-h-9 transition"
                    :class="lang === '{{ $l }}' ? 'bg-white text-stone-900 font-semibold shadow-sm ring-1 ring-stone-300' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-200/60'">
                <span x-show="state('{{ $l }}') === 'complete'" x-cloak class="text-emerald-600">@svg('heroicon-m-check', 'w-4 h-4')</span>
                <span x-show="state('{{ $l }}') === 'partial'" x-cloak class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span x-show="state('{{ $l }}') === 'empty'" class="w-2.5 h-2.5 rounded-full border-2 border-stone-400"></span>
                <span x-show="state('{{ $l }}') === 'error'" x-cloak class="text-rose-600">@svg('heroicon-o-exclamation-triangle', 'w-4 h-4')</span>
                <span>{{ $tab['code'] }}</span>
                <span class="hidden md:inline font-normal text-stone-500">{{ $tab['name'] }}</span>
                <span class="text-xs tabular-nums font-normal" :class="state('{{ $l }}') === 'complete' ? 'text-emerald-700' : 'text-stone-500'" x-text="count('{{ $l }}') + '/' + total()" data-lang-count="{{ $l }}"></span>
                <span x-show="errs('{{ $l }}').length" x-cloak x-text="errs('{{ $l }}').length" data-lang-errors="{{ $l }}"
                      class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[11px] font-semibold leading-[18px] text-center"></span>
                <span x-show="!errs('{{ $l }}').length && warns('{{ $l }}').length" x-cloak x-text="warns('{{ $l }}').length"
                      class="min-w-[18px] h-[18px] px-1 rounded-full bg-amber-100 text-amber-800 ring-1 ring-amber-300 text-[11px] font-semibold leading-[18px] text-center"></span>
            </button>
        @endforeach
    </div>
    @if($bulk || isset($actions))
        <div class="flex items-center gap-1.5 ml-auto flex-wrap">
            @if($bulk)
                <x-admin.button size="sm" x-on:click="copyAll()" title="{{ __('admin.i18n.copy_uz_hint') }}">
                    <span x-text="copied === 'all' ? @js(__('admin.i18n.copied')) : @js(__('admin.i18n.copy_uz'))">{{ __('admin.i18n.copy_uz') }}</span>
                </x-admin.button>
                <x-admin.button size="sm" icon="language" x-show="lang !== source" x-cloak x-on:click="pasteMsg = ''; pasteOpen = true">
                    {{ __('admin.i18n.paste') }} · <span x-text="lang.toUpperCase()"></span>
                </x-admin.button>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
