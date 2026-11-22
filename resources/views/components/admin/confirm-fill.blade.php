{{-- "Overwrite what is already typed?" — asked before AI fill or "choose product" replaces filled fields.
     Server-driven: render it with the labels of the fields that would be replaced (empty list = no dialog).
     apply: Livewire method called with false (fill the empty fields only) or true (overwrite); cancel: method that drops the pending texts. --}}
@props(['fields' => [], 'title' => null, 'text' => null, 'apply' => 'applyFill', 'cancel' => 'cancelFill'])
@if($fields)
    <div x-data x-init="$nextTick(() => $refs.keep?.focus())" x-on:keydown.escape.window="$wire.{{ $cancel }}()" data-no-dirty data-confirm-fill
         class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4">
        <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-fill-title" x-trap.noscroll="true"
             class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5">
            <h2 id="confirm-fill-title" class="font-semibold text-stone-900">{{ $title ?? __('admin.fill.title') }}</h2>
            <p class="text-sm text-stone-600 mt-1">{{ $text ?? __('admin.fill.text') }}</p>
            <ul class="mt-3 flex flex-wrap gap-1.5">
                @foreach($fields as $label)
                    <li class="inline-flex items-center rounded-md bg-amber-50 text-amber-900 ring-1 ring-amber-300 px-2 py-0.5 text-xs font-medium">{{ $label }}</li>
                @endforeach
            </ul>
            <div class="flex justify-end gap-2 mt-5 flex-wrap">
                <x-admin.button variant="ghost" wire:click="{{ $cancel }}">{{ __('common.cancel') }}</x-admin.button>
                <x-admin.button x-ref="keep" wire:click="{{ $apply }}(false)" data-fill-empty>{{ __('admin.fill.empty_only') }}</x-admin.button>
                <x-admin.button variant="danger" wire:click="{{ $apply }}(true)" data-fill-overwrite>{{ __('admin.fill.overwrite') }}</x-admin.button>
            </div>
        </div>
    </div>
@endif
