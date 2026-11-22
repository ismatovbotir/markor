{{-- Shown instead of the rows: nothing yet / nothing found. icon: Heroicon outline name. Slot action: a button. --}}
@props(['icon' => 'inbox', 'title', 'text' => null])
<li {{ $attributes->class('px-6 py-12 text-center') }}>
    <span class="inline-flex w-12 h-12 rounded-full bg-stone-100 text-stone-500 items-center justify-center">@svg("heroicon-o-$icon", 'w-6 h-6')</span>
    <p class="mt-3 font-medium text-stone-900">{{ $title }}</p>
    @if($text)<p class="text-sm text-stone-600 mt-1">{{ $text }}</p>@endif
    @isset($action)<div class="mt-4">{{ $action }}</div>@endisset
</li>
