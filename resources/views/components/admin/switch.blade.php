{{-- On/off switch bound to a boolean Livewire property (role="switch"). live: re-render on change (server-rendered preview). --}}
@props(['model', 'label', 'hint' => null, 'live' => false])
@php $id = 'sw-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $model), '-'); @endphp
<div {{ $attributes }}>
    <label for="{{ $id }}" class="flex items-center justify-between gap-4 cursor-pointer min-h-10">
        <span class="text-sm text-stone-800">{{ $label }}</span>
        <span class="relative shrink-0 inline-flex">
            <input type="checkbox" role="switch" id="{{ $id }}" wire:model{{ $live ? '.live' : '' }}="{{ $model }}" data-key="{{ $model }}" data-label="{{ $label }}" class="peer sr-only">
            <span class="w-10 h-6 rounded-full bg-stone-400 transition peer-checked:bg-emerald-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-amber-700"></span>
            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
        </span>
    </label>
    @if($hint)<p class="text-xs text-stone-500 mt-1">{{ $hint }}</p>@endif
</div>
