{{-- A block of the form card. shared: the fields are the same for every language (tagged, placed after the translatable blocks).
     collapsed: rarely used fields, folded until opened (never put translatable text there). --}}
@props(['title', 'hint' => null, 'shared' => false, 'collapsed' => false])
@if($collapsed)
    <section {{ $attributes->class('border-t border-stone-200') }} x-data="{ open: false }" data-collapsible x-on:open-section="open = true">
        <button type="button" x-on:click="open = !open" :aria-expanded="open"
                class="admin-focus w-full text-left px-4 sm:px-6 py-3.5 text-sm font-medium text-stone-700 hover:text-stone-900 flex items-center gap-2 flex-wrap">
            <span class="transition" :class="open && 'rotate-90'">@svg('heroicon-m-chevron-right', 'w-4 h-4')</span>
            <span>{{ $title }}</span>
            @if($shared)<span class="inline-flex items-center rounded-full bg-stone-100 text-stone-600 text-[11px] font-normal px-2 py-0.5">{{ __('admin.i18n.shared') }}</span>@endif
        </button>
        <div x-show="open" x-cloak class="px-4 sm:px-6 pb-5">
            @if($hint)<p class="text-xs text-stone-500 mb-4">{{ $hint }}</p>@endif
            <div class="space-y-5">{{ $slot }}</div>
        </div>
    </section>
@else
    <section {{ $attributes->class('px-4 sm:px-6 py-5 border-t border-stone-200') }} @unless($shared) role="tabpanel" :aria-labelledby="'admin-tab-' + lang" @endunless>
        <div class="mb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-sm font-semibold text-stone-900">{{ $title }}</h2>
                @if($shared)<span class="inline-flex items-center rounded-full bg-stone-100 text-stone-600 text-[11px] px-2 py-0.5">{{ __('admin.i18n.shared') }}</span>@endif
            </div>
            @if($hint)<p class="text-xs text-stone-500 mt-0.5">{{ $hint }}</p>@endif
        </div>
        <div class="space-y-5">{{ $slot }}</div>
    </section>
@endif
