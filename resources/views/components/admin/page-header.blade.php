{{-- Top of every admin page. List page: crumb + title + hint + "view on site" + one primary action (slot actions).
     Edit page: back link (back + back-label) and the item name; no buttons. --}}
@props(['title', 'hint' => null, 'crumb' => null, 'back' => null, 'backLabel' => null, 'viewUrl' => null, 'viewLabel' => null])
<div {{ $attributes->class('flex items-start justify-between gap-4 flex-wrap mb-5') }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="admin-focus inline-flex items-center gap-1 rounded text-xs text-stone-600 hover:text-amber-800">@svg('heroicon-o-arrow-left', 'w-3.5 h-3.5') {{ $backLabel }}</a>
        @elseif($crumb)
            <p class="text-xs text-stone-500">{{ $crumb }}</p>
        @endif
        <h1 class="text-xl font-semibold text-stone-900 mt-0.5 break-words">{{ $title }}</h1>
        @if($hint)<p class="text-sm text-stone-600 mt-0.5 max-w-2xl">{{ $hint }}</p>@endif
    </div>
    @if($viewUrl || isset($actions))
        <div class="flex items-center gap-2 flex-wrap">
            @if($viewUrl)
                <a href="{{ $viewUrl }}" target="_blank" rel="noopener" title="{{ __('admin.view_on_site') }}"
                   class="admin-focus inline-flex items-center justify-center gap-1.5 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-700 hover:bg-stone-50 min-h-10">
                    <span>{{ $viewLabel ?? __('admin.view_on_site') }}</span>@svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                </a>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
