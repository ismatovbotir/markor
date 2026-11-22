{{-- A folded block with a ready text to copy (e.g. the brief for a product picture). --}}
@props(['title', 'hint' => null, 'text'])
<details {{ $attributes->class('rounded-lg border border-stone-200 bg-stone-50 text-sm') }} x-data="{ done: false }" data-no-dirty>
    <summary class="admin-focus cursor-pointer rounded-lg px-3 py-2.5 font-medium text-stone-700">{{ $title }}</summary>
    <div class="px-3 pb-3 space-y-2">
        @if($hint)<p class="text-xs text-stone-500">{{ $hint }}</p>@endif
        <pre x-ref="text" class="whitespace-pre-wrap text-xs bg-white border border-stone-200 rounded-lg p-3 text-stone-700 font-mono">{{ $text }}</pre>
        <x-admin.button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.text.innerText); done = true; setTimeout(() => done = false, 2000)">
            <span x-text="done ? {{ Js::from(__('admin.i18n.copied')) }} : {{ Js::from(__('admin.i18n.copy')) }}">{{ __('admin.i18n.copy') }}</span>
        </x-admin.button>
    </div>
</details>
