{{-- On/off list filter bound to a boolean Livewire property (e.g. "translation incomplete"). --}}
@props(['model', 'active' => false, 'count' => null, 'icon' => 'language'])
<button type="button" wire:click="$toggle('{{ $model }}')" aria-pressed="{{ $active ? 'true' : 'false' }}"
        {{ $attributes->class(['admin-focus inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm ring-1 min-h-10',
            'bg-amber-100 text-amber-900 ring-amber-400 font-medium' => $active, 'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => !$active]) }}>
    @svg("heroicon-o-$icon", 'w-4 h-4')<span>{{ $slot }}</span>
    @if($count !== null)<span class="text-xs tabular-nums">{{ $count }}</span>@endif
</button>
