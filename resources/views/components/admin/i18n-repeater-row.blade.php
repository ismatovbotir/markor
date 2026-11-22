{{-- One row of x-admin.i18n-repeater. name: Livewire path of the row ("item.faq.0") — a validation error under that key
     (e.g. "the Uzbek question and answer are required") is shown on the row and counted on the UZ tab.
     label: "Savol 1"; remove: Livewire call that deletes the row. Put x-admin.i18n-field items inside. --}}
@props(['name', 'label', 'remove' => null])
@php $id = 'row-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'); @endphp
<div id="{{ $id }}" data-row data-key="{{ $name }}" data-label="{{ $label }}" tabindex="-1"
     {{ $attributes->class('rounded-lg border p-3 sm:p-4 space-y-4') }} :class="rowErr(@js($name)) ? 'border-rose-400 bg-rose-50/40' : 'border-stone-200'">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-semibold text-stone-600">{{ $label }}</p>
        @if($remove)
            <button type="button" wire:click="{{ $remove }}" aria-label="{{ __('common.delete') }}: {{ $label }}" class="admin-focus p-1.5 rounded-lg text-stone-500 hover:text-rose-700 hover:bg-rose-50">@svg('heroicon-o-trash', 'w-4 h-4')</button>
        @endif
    </div>
    <p x-show="rowErr(@js($name))" @unless($errors->has($name)) x-cloak @endunless role="alert" class="text-xs text-rose-700 flex items-start gap-1">
        <span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span x-text="rowErr(@js($name))">{{ $errors->first($name) }}</span>
    </p>
    {{ $slot }}
</div>
