{{-- The single multilingual form shell: one card with the language switch, an optional side column,
     the sticky save bar and the dialogs. submit: the Livewire save method; cancel: where "Cancel" leads;
     wide-aside: the side column takes half of the page (a wide live preview).
     Behaviour: resources/js/admin/i18n-form.js. Put x-admin.lang-tabs first inside, then x-admin.section blocks. --}}
@props(['submit' => 'save', 'cancel' => null, 'wideAside' => false])
@php
    $config = [
        'submit' => $submit,
        'source' => \App\Support\AdminI18n::SOURCE,
        'locales' => \App\Support\AdminI18n::locales(),
        't' => [
            'filled' => __('admin.i18n.filled'), 'errors' => __('admin.i18n.errors_n'), 'too_long' => __('admin.i18n.too_long'),
            'pasted' => __('admin.i18n.pasted'), 'paste_none' => __('admin.i18n.paste_none'),
        ],
    ];
@endphp
<div x-data="adminI18nForm(@js($config))" x-on:input="onInput($event)" x-on:change="onInput($event)" x-on:click.window="guardLink($event)" x-on:form-changed.window="touch()"
     x-on:keydown.window.ctrl.s.prevent="submit()" x-on:keydown.window.meta.s.prevent="submit()"
     {{ $attributes->class('flex flex-col min-h-[calc(100dvh-5.5rem)] lg:min-h-[calc(100dvh-1.5rem)]') }}>
    {{ $header ?? '' }}
    <div @class(['grid gap-5 items-start', 'xl:grid-cols-[minmax(0,1fr)_300px]' => isset($aside) && !$wideAside, 'xl:grid-cols-2' => isset($aside) && $wideAside])>
        <form x-on:submit.prevent="submit()" novalidate class="bg-white border border-stone-200 rounded-xl min-w-0">
            {{ $slot }}
        </form>
        @isset($aside)<aside class="space-y-4 xl:sticky xl:top-4 min-w-0">{{ $aside }}</aside>@endisset
    </div>
    <div class="h-6 shrink-0"></div>
    <x-admin.save-bar :cancel="$cancel" />

    {{-- leave without saving --}}
    <div x-show="leaveOpen" x-cloak x-on:keydown.escape.window="leaveOpen = false" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4">
        <div role="alertdialog" aria-modal="true" aria-labelledby="admin-leave-title" x-on:click.outside="leaveOpen = false" x-trap.noscroll="leaveOpen"
             class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5">
            <h2 id="admin-leave-title" class="font-semibold text-stone-900">{{ __('admin.leave.title') }}</h2>
            <p class="text-sm text-stone-600 mt-1">{{ __('admin.leave.text') }}</p>
            <div class="flex justify-end gap-2 mt-5 flex-wrap">
                <x-admin.button x-on:click="leaveOpen = false">{{ __('admin.leave.stay') }}</x-admin.button>
                <x-admin.button variant="danger" x-on:click="confirmLeave()">{{ __('admin.leave.leave') }}</x-admin.button>
            </div>
        </div>
    </div>

    {{-- paste a translation: blocks "### field" go to the fields of the current language --}}
    <div x-show="pasteOpen" x-cloak data-no-dirty x-on:keydown.escape.window="pasteOpen = false" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="admin-paste-title" x-on:click.outside="pasteOpen = false" x-trap.noscroll="pasteOpen"
             class="w-full sm:max-w-xl bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5 space-y-3">
            <h2 id="admin-paste-title" class="font-semibold text-stone-900">{{ __('admin.i18n.paste') }} — <span x-text="lang.toUpperCase()"></span></h2>
            <p class="text-sm text-stone-600">{{ __('admin.i18n.paste_hint') }}</p>
            <textarea x-model="pasteText" rows="9" aria-label="{{ __('admin.i18n.paste') }}" placeholder="### title&#10;…&#10;&#10;### body&#10;…" class="admin-input font-mono text-xs"></textarea>
            <p x-show="pasteMsg" class="text-sm text-emerald-700" role="status" x-text="pasteMsg"></p>
            <div class="flex justify-end gap-2">
                <x-admin.button variant="ghost" x-on:click="pasteOpen = false">{{ __('common.close') }}</x-admin.button>
                <x-admin.button variant="primary" x-on:click="applyPaste()" x-bind:disabled="!pasteText.trim()">{{ __('admin.i18n.paste_apply') }}</x-admin.button>
            </div>
        </div>
    </div>
</div>
