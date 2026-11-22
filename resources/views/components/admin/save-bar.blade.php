{{-- The only Save of an edit page, sticky at the bottom (rendered by x-admin.i18n-form). Left: state of the form
     (no changes / unsaved changes / saving / saved at / errors as links). Ctrl/⌘+S saves. Slot extra: more buttons. --}}
@props(['cancel' => null])
<div {{ $attributes->class('sticky bottom-0 z-30 mt-auto -mx-4 sm:-mx-6 lg:-mx-8 -mb-6 border-t border-stone-200 bg-white/95 backdrop-blur px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-x-4 gap-y-2 flex-wrap') }} data-save-bar>
    <div class="min-w-0 text-sm" aria-live="polite">
        <template x-if="!saving && allErrs().length">
            <p class="text-rose-700 flex items-center gap-1.5 flex-wrap">
                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 shrink-0')
                <span class="font-medium" x-text="@js(__('admin.save.errors')).replace(':n', allErrs().length) + ' —'"></span>
                <template x-for="(e, i) in allErrs().slice(0, 3)" :key="i">
                    <button type="button" x-on:click="goTo(e)" class="admin-focus rounded underline underline-offset-2 hover:text-rose-900 text-left" x-text="errLabel(e)"></button>
                </template>
            </p>
        </template>
        <template x-if="saving"><p class="flex items-center gap-2 text-stone-600"><span class="w-3.5 h-3.5 border-2 border-stone-300 border-t-amber-700 rounded-full animate-spin motion-reduce:animate-none"></span>{{ __('admin.save.saving') }}</p></template>
        <template x-if="!saving && !allErrs().length && dirty"><p class="flex items-center gap-2 text-amber-800 font-medium"><span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ __('admin.save.dirty') }}</p></template>
        <template x-if="!saving && !allErrs().length && !dirty && savedAt"><p class="flex items-center gap-1.5 text-emerald-700">@svg('heroicon-m-check', 'w-4 h-4')<span x-text="@js(__('admin.save.saved')).replace(':time', savedAt)"></span></p></template>
        <template x-if="!saving && !allErrs().length && !dirty && !savedAt"><p class="text-stone-500">{{ __('admin.save.clean') }}</p></template>
    </div>
    <div class="flex items-center gap-2 ml-auto">
        {{ $extra ?? '' }}
        @if($cancel)<x-admin.button variant="ghost" x-on:click="leave({{ Js::from($cancel) }})">{{ __('common.cancel') }}</x-admin.button>@endif
        <x-admin.button variant="primary" x-on:click="submit()" x-bind:disabled="saving" data-save>
            {{ __('common.save') }} <kbd class="hidden lg:inline text-[10px] font-sans font-normal bg-white/20 rounded px-1 py-0.5 ml-1">Ctrl S</kbd>
        </x-admin.button>
    </div>
</div>
