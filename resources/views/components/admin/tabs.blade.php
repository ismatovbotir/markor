{{-- Tabs of one page (not the language switch). tabs: [key => ['label' => …, 'href' => …, 'count' => …, 'gap' => …]].
     With href the tabs are links and `value` is the current key (server side); without href they switch the Alpine
     variable named in `model` (all panels stay in the page: x-show="tab === 'key'"). --}}
@props(['tabs' => [], 'value' => null, 'model' => null, 'label' => null])
@php
    $base = 'admin-focus inline-flex items-center gap-1.5 px-3 py-2 -mb-px border-b-2 whitespace-nowrap rounded-t';
    $on = 'border-amber-700 text-amber-900 font-medium';
    $off = 'border-transparent text-stone-600 hover:text-stone-900';
    $gapBadge = 'inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 text-[11px] font-semibold tabular-nums';
@endphp
<div {{ $attributes->class('flex gap-1 border-b border-stone-200 mb-4 text-sm overflow-x-auto') }} role="tablist" @if($label) aria-label="{{ $label }}" @endif>
    @foreach($tabs as $key => $tab)
        @if($model)
            <button type="button" role="tab" data-tab="{{ $key }}" x-on:click="{{ $model }} = '{{ $key }}'" :aria-selected="{{ $model }} === '{{ $key }}'"
                    class="{{ $base }}" :class="{{ $model }} === '{{ $key }}' ? '{{ $on }}' : '{{ $off }}'">
                <span>{{ $tab['label'] }}</span>
                @isset($tab['count'])<span class="text-xs text-stone-500 tabular-nums font-normal">{{ $tab['count'] }}</span>@endisset
            </button>
        @else
            <a href="{{ $tab['href'] }}" role="tab" data-tab="{{ $key }}" aria-selected="{{ $value === $key ? 'true' : 'false' }}" class="{{ $base }} {{ $value === $key ? $on : $off }}">
                <span>{{ $tab['label'] }}</span>
                @isset($tab['count'])<span class="text-xs text-stone-500 tabular-nums font-normal">{{ $tab['count'] }}</span>@endisset
                @if(!empty($tab['gap']))<span class="{{ $gapBadge }}" title="{{ __('admin.nav.gap', ['n' => $tab['gap']]) }}">@svg('heroicon-o-language', 'w-3 h-3'){{ $tab['gap'] }}</span>@endif
            </a>
        @endif
    @endforeach
</div>
