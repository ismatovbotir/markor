{{-- Segmented filter with counts. options: [value => ['label' => …, 'count' => …]]; value: the current one. --}}
@props(['model', 'value', 'options' => [], 'label' => null])
<div class="inline-flex rounded-lg border border-stone-300 bg-white overflow-hidden text-sm max-w-full overflow-x-auto" role="group" aria-label="{{ $label ?? __('common.status') }}">
    @foreach($options as $key => $option)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" aria-pressed="{{ $value === $key ? 'true' : 'false' }}"
                class="admin-focus px-3 py-2 border-r last:border-r-0 border-stone-200 whitespace-nowrap {{ $value === $key ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-700 hover:bg-stone-50' }}">
            {{ $option['label'] }} <span class="text-xs text-stone-500 tabular-nums">{{ $option['count'] ?? '' }}</span>
        </button>
    @endforeach
</div>
