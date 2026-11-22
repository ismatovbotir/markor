{{-- Destructive confirmation, once per layout. Open it with
     $dispatch('confirm-delete', { title, name, text, run: () => $wire.delete(id) }) — it names the item and the consequence. --}}
<div x-data="{ open: false, d: {} }" x-on:confirm-delete.window="d = $event.detail; open = true; $nextTick(() => $refs.cancel.focus())"
     x-show="open" x-cloak x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4">
    <div role="alertdialog" aria-modal="true" aria-labelledby="confirm-delete-title" x-on:click.outside="open = false" x-trap.noscroll="open"
         class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5">
        <h2 id="confirm-delete-title" class="font-semibold text-stone-900" x-text="d.title"></h2>
        <p class="text-sm text-stone-700 mt-2" x-show="d.name">«<span class="font-medium" x-text="d.name"></span>»</p>
        <p class="text-sm text-stone-600 mt-2" x-show="d.text" x-text="d.text"></p>
        <div class="flex justify-end gap-2 mt-5">
            <x-admin.button x-ref="cancel" x-on:click="open = false">{{ __('common.cancel') }}</x-admin.button>
            <x-admin.button variant="danger" x-on:click="open = false; d.run && d.run()">{{ __('common.delete') }}</x-admin.button>
        </div>
    </div>
</div>
