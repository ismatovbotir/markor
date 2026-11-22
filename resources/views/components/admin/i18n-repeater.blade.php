{{-- A list of translatable rows (FAQ). Loop the rows yourself with x-admin.i18n-repeater-row inside.
     count / max: rows now and the limit; add: Livewire method that appends a row. --}}
@props(['title' => null, 'hint' => null, 'count' => 0, 'max' => null, 'add' => null, 'addLabel' => null])
<div {{ $attributes->class('space-y-3') }}>
    @if($title || $max)
        <div class="flex items-baseline justify-between gap-3">
            @if($title)<h3 class="text-[13px] font-medium text-stone-700">{{ $title }}</h3>@endif
            @if($max)<span class="text-xs text-stone-500 tabular-nums">{{ $count }} / {{ $max }}</span>@endif
        </div>
    @endif
    @if($hint)<p class="text-xs text-stone-500">{{ $hint }}</p>@endif
    {{ $slot }}
    @if($add && (!$max || $count < $max))
        <x-admin.button size="sm" icon="plus" wire:click="{{ $add }}">{{ $addLabel ?? __('common.add') }}</x-admin.button>
    @endif
</div>
