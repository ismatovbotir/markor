{{-- Where a record is: published (on the site) | scheduled | hidden. Dot + text, never colour alone. --}}
@props(['status' => 'hidden'])
@php
    [$pill, $dot] = [
        'published' => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'bg-emerald-600'],
        'scheduled' => ['bg-amber-50 text-amber-800 ring-amber-300', 'bg-amber-500'],
        'hidden' => ['bg-stone-100 text-stone-700 ring-stone-300', 'bg-stone-500'],
    ][$status] ?? ['bg-stone-100 text-stone-700 ring-stone-300', 'bg-stone-500'];
@endphp
<span {{ $attributes->class("inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 whitespace-nowrap $pill") }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>{{ __("admin.status.$status") }}
</span>
