{{-- A field that is the same for every language. name: the Livewire path. Without a slot it renders an input of `type`;
     with a slot — your own control (give it id="{{ $id }}"). Short technical fields may keep maxlength (max).
     live: send the value while typing (only when a server-rendered preview needs it). --}}
@props(['name', 'label', 'hint' => null, 'type' => 'text', 'max' => null, 'required' => false, 'placeholder' => null, 'id' => null, 'live' => false])
@php $id ??= 'f-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'); $invalid = $errors->has($name); @endphp
<div {{ $attributes }}>
    <label for="{{ $id }}" class="text-[13px] font-medium text-stone-700 flex items-center gap-1.5 flex-wrap mb-1">
        <span>{{ $label }}</span>
        @if($required)<span class="text-rose-600 text-xs font-normal">{{ __('admin.i18n.required') }}</span>@endif
    </label>
    @if($slot->isEmpty())
        <input type="{{ $type }}" id="{{ $id }}" wire:model{{ $live ? '.live.debounce.400ms' : '' }}="{{ $name }}" @if($max) maxlength="{{ $max }}" @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif
               data-key="{{ $name }}" data-label="{{ $label }}" aria-invalid="{{ $invalid ? 'true' : 'false' }}" aria-describedby="{{ $id }}-msg" @if($required) aria-required="true" @endif
               class="admin-input">
    @else
        {{ $slot }}
    @endif
    <div id="{{ $id }}-msg">
        @error($name)
            <p role="alert" class="mt-1 text-xs text-rose-700 flex items-start gap-1"><span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span>{{ $message }}</span></p>
        @enderror
        @if($hint)<p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>@endif
    </div>
</div>
