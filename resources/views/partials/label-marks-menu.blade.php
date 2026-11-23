{{-- "Знаки" menu of the label designer: icons managed in the admin (LabelMark), grouped.
     Also hands the designer preview every icon's url (hidden ones too: old templates keep showing them). --}}
@php($markGroups = \App\Domain\Printing\Models\LabelMark::menu())
<div class="relative" x-data="{ marksOpen: false }" x-init="window.labelMarks = @js(\App\Domain\Printing\Models\LabelMark::urls()); window.labelMarkRatios = @js(\App\Domain\Printing\Models\LabelMark::ratios())"
     x-on:click.outside="marksOpen = false" x-on:keydown.escape="marksOpen = false">
    <button type="button" x-on:click="marksOpen = !marksOpen" class="{{ $chip }} inline-flex items-center gap-1" :aria-expanded="marksOpen" aria-haspopup="true">
        @if($first = $markGroups['marking'][0] ?? null)<img src="{{ $first['url'] }}" alt="" class="w-3.5 h-3.5 object-contain">@endif
        {{ __('labels.marks') }} @svg('heroicon-o-chevron-down', 'w-3 h-3')
    </button>
    <div x-show="marksOpen" x-cloak x-transition.opacity class="absolute z-40 mt-1 left-0 w-80 max-w-[calc(100vw-2rem)] max-h-[70vh] overflow-y-auto bg-white border border-stone-200 rounded-lg shadow-lg p-2 text-xs">
        @forelse ($markGroups as $group => $marks)
            <div class="px-1 pt-1 pb-1 font-medium text-stone-500">{{ __("labels.marks_group.$group") }}</div>
            @if (in_array($group, \App\Domain\Printing\Models\LabelMark::CERTIFIED_GROUPS, true))
                <p class="px-1 pb-2 text-amber-800">{{ __('labels.marks_conformity_hint') }}</p>
            @endif
            <div class="grid grid-cols-2 gap-1 mb-1">
                @foreach ($marks as $m)
                    <button type="button" x-on:click="add('mark', @js($m['key'])); marksOpen = false" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-stone-100 text-left">
                        <img src="{{ $m['url'] }}" alt="" class="h-5 {{ ($m['width'] ?? 1) > 1.5 * ($m['height'] ?: 1) ? 'w-auto max-w-[4.5rem]' : 'w-5' }} object-contain shrink-0"> <span>{{ $m['label'] }}</span>
                    </button>
                @endforeach
            </div>
        @empty
            <p class="px-1 py-2 text-stone-500">{{ __('labels.marks_none') }}</p>
        @endforelse
        <p class="px-1 pt-1 text-stone-500">{{ __('labels.mark_hint') }}</p>
    </div>
</div>
