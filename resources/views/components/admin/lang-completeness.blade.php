{{-- Per language: how many translatable fields of a record are filled (all fields count, not only the title). --}}
@props(['item', 'fields' => []])
@php
    $counts = \App\Support\AdminI18n::completeness($item, $fields);
    $total = count($fields);
    $tabs = \App\Support\AdminI18n::tabs();
@endphp
<div {{ $attributes->class('flex items-center gap-1.5') }}>
    @foreach($counts as $l => $n)
        <span title="{{ $tabs[$l]['name'] }}: {{ $n }} / {{ $total }} {{ __('admin.i18n.filled') }}"
              @class(['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold ring-1 tabular-nums',
                'bg-emerald-50 text-emerald-800 ring-emerald-200' => $n === $total, 'bg-amber-50 text-amber-800 ring-amber-300' => $n > 0 && $n < $total,
                'bg-white text-stone-600 ring-stone-300' => $n === 0])>
            {{ $tabs[$l]['code'] }}
            @if($n === $total)@svg('heroicon-m-check', 'w-3 h-3')@else<span class="font-normal">{{ $n ? "$n/$total" : '—' }}</span>@endif
            <span class="sr-only">{{ $n }} / {{ $total }}</span>
        </span>
    @endforeach
</div>
