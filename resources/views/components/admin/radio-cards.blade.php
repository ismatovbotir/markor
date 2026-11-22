{{-- A choice shown as cards (one of a few options, the same for every language). model: Livewire property;
     options: [value => ['label' => …, 'hint' => …, 'icon' => 'heroicon-o-…', 'swatch' => 'bg-… (colour sample)']]; legend: group name.
     live: re-render on change (when something on the page depends on the choice). --}}
@props(['model', 'options' => [], 'legend' => null, 'live' => false, 'cols' => 'sm:grid-cols-2'])
@php $name = 'rc-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $model), '-'); @endphp
<fieldset {{ $attributes->class("grid $cols gap-3") }}>
    @if($legend)<legend class="sr-only">{{ $legend }}</legend>@endif
    @foreach($options as $value => $option)
        <label class="cursor-pointer rounded-lg border border-stone-300 p-3 flex items-start gap-3 transition hover:border-stone-400
                      has-[:checked]:border-amber-600 has-[:checked]:ring-2 has-[:checked]:ring-amber-200 has-[:checked]:bg-amber-50/50
                      has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-amber-700">
            <input type="radio" name="{{ $name }}" value="{{ $value }}" wire:model{{ $live ? '.live' : '' }}="{{ $model }}" data-key="{{ $model }}" data-label="{{ $legend }}" class="peer sr-only">
            @if(!empty($option['icon']))<span class="text-stone-500 peer-checked:text-amber-700 shrink-0">@svg($option['icon'], 'w-5 h-5')</span>@endif
            @if(!empty($option['swatch']))<span class="w-9 h-9 shrink-0 rounded-md ring-1 ring-black/10 {{ $option['swatch'] }}"></span>@endif
            <span class="min-w-0"><span class="block text-sm font-medium text-stone-900">{{ $option['label'] }}</span>
                @if(!empty($option['hint']))<span class="block text-xs text-stone-600 mt-0.5">{{ $option['hint'] }}</span>@endif</span>
        </label>
    @endforeach
</fieldset>
