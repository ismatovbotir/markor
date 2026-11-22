{{-- One button look for the admin. variant: primary | secondary | ghost | danger; size: md | sm; icon: Heroicon outline name ("plus"). With href it is a link. --}}
@props(['variant' => 'secondary', 'size' => 'md', 'icon' => null, 'href' => null, 'type' => 'button'])
@php
    $look = [
        'primary' => 'bg-amber-700 text-white font-medium hover:bg-amber-800',
        'secondary' => 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-50',
        'ghost' => 'text-stone-700 hover:bg-stone-100',
        'danger' => 'bg-rose-600 text-white font-medium hover:bg-rose-700',
    ][$variant] ?? '';
    $sizing = $size === 'sm' ? 'gap-1.5 px-2.5 py-1.5 text-xs min-h-[34px]' : 'gap-2 px-4 py-2 text-sm min-h-10';
    $class = "admin-focus inline-flex items-center justify-center rounded-lg transition disabled:opacity-60 $sizing $look";
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->class($class) }}>@if($icon)@svg("heroicon-o-$icon", 'w-4 h-4 shrink-0')@endif<span>{{ $slot }}</span></a>
@else
    <button type="{{ $type }}" {{ $attributes->class($class) }}>@if($icon)@svg("heroicon-o-$icon", 'w-4 h-4 shrink-0')@endif<span>{{ $slot }}</span></button>
@endif
