{{-- One record in a list. Column order is fixed: title + meta · status · languages · updated · actions. --}}
@props(['href' => null, 'muted' => false])
<li {{ $attributes->class('admin-list-grid px-4 py-3 hover:bg-stone-50') }}>
    <div class="min-w-0 flex items-center gap-3">
        @isset($thumb)<div class="shrink-0">{{ $thumb }}</div>@endisset
        <div class="min-w-0 {{ $muted ? 'opacity-60' : '' }}">
            @if($href)
                <a href="{{ $href }}" class="admin-focus rounded font-medium text-stone-900 hover:text-amber-800 line-clamp-2 md:truncate md:block">{{ $slot }}</a>
            @else
                <p class="font-medium text-stone-900 line-clamp-2 md:truncate">{{ $slot }}</p>
            @endif
            @isset($meta)<p class="text-xs text-stone-500 mt-0.5">{{ $meta }}</p>@endisset
        </div>
    </div>
    <div>{{ $status ?? '' }}</div>
    <div>{{ $langs ?? '' }}</div>
    <p class="text-xs text-stone-600">@isset($updated)<span class="md:hidden">{{ __('admin.list.updated') }}: </span>{{ $updated }}@endisset</p>
    <div class="flex items-center md:justify-end gap-1">{{ $actions ?? '' }}</div>
</li>
