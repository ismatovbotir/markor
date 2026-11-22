{{-- One translatable field in all languages (only the current one is visible; all stay in the DOM for wire:model and validation).
     name: Livewire path without the language ("item.title" → item.title_uz / _ru / _zh); other key shapes: key="texts.home.title.:locale".
     type: input | textarea | markdown. max: hard limit of the server rule (counter turns rose, never cuts the text);
     soft: recommended length — a number, or per language: :soft="['uz' => 60, 'ru' => 60, 'zh' => 32]".
     fallback: uz (empty translation shows the Uzbek text) | default (a standard text, pass :defaults per language)
     | uz-default (the Uzbek text when it is typed, otherwise the standard text) | none. --}}
@props(['name', 'label', 'type' => 'input', 'rows' => 3, 'max' => null, 'soft' => null, 'required' => false, 'hint' => null,
    'fallback' => 'uz', 'defaults' => [], 'key' => null, 'field' => null, 'live' => false])
@php
    $source = \App\Support\AdminI18n::SOURCE;
    $base = 'f-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-');
    $fieldId = $field ?? preg_replace('/^item\./', '', $name);
    $model = $live ? 'wire:model.live.debounce.400ms' : 'wire:model';
@endphp
<div {{ $attributes }}>
    @foreach(\App\Support\AdminI18n::tabs() as $l => $tab)
        @php
            $id = "$base-$l";
            $path = $key ? str_replace(':locale', $l, $key) : "{$name}_$l";
            $default = $defaults[$l] ?? '';
            $softL = is_array($soft) ? ($soft[$l] ?? null) : $soft;
        @endphp
        <div x-show="lang === '{{ $l }}'" @if($l !== $source) x-cloak @endif data-i18n-wrap="{{ $l }}">
            <div class="flex items-baseline justify-between gap-3 mb-1">
                <label for="{{ $id }}" class="text-[13px] font-medium text-stone-700 flex items-center gap-1.5 flex-wrap">
                    <span>{{ $label }}</span>
                    <span class="px-1.5 rounded bg-stone-100 text-stone-600 text-[10px] font-semibold tracking-wide leading-4">{{ $tab['code'] }}</span>
                    @if($required && $l === $source)<span class="text-rose-600 text-xs font-normal">{{ __('admin.i18n.required') }}</span>@endif
                </label>
                @if($max || $softL)
                    <span id="{{ $id }}-count" class="text-xs tabular-nums shrink-0" :class="counterClass('{{ $id }}')"><span x-text="len('{{ $id }}')">0</span> / {{ $softL ?: $max }}</span>
                @endif
            </div>
            @if($type === 'input')
                <input type="text" id="{{ $id }}" {{ $model }}="{{ $path }}" lang="{{ $tab['html'] }}" placeholder="{{ $default }}"
                       data-i18n="{{ $l }}" data-key="{{ $path }}" data-field="{{ $fieldId }}" data-label="{{ $label }}" data-max="{{ $max }}" data-soft="{{ $softL }}" data-default="{{ $default }}"
                       :aria-invalid="err('{{ $id }}') ? 'true' : 'false'" aria-describedby="{{ $id }}-msg" @if($required && $l === $source) aria-required="true" @endif
                       class="admin-input">
            @else
                <textarea id="{{ $id }}" {{ $model }}="{{ $path }}" rows="{{ $rows }}" lang="{{ $tab['html'] }}" placeholder="{{ $default }}"
                          data-i18n="{{ $l }}" data-key="{{ $path }}" data-field="{{ $fieldId }}" data-label="{{ $label }}" data-max="{{ $max }}" data-soft="{{ $softL }}" data-default="{{ $default }}"
                          :aria-invalid="err('{{ $id }}') ? 'true' : 'false'" aria-describedby="{{ $id }}-msg" @if($required && $l === $source) aria-required="true" @endif
                          class="admin-input {{ $type === 'markdown' ? 'font-mono text-[13px] leading-relaxed' : '' }}"></textarea>
            @endif
            <div id="{{ $id }}-msg">
                <p x-show="err('{{ $id }}')" @unless($errors->has($path)) x-cloak @endunless role="alert" class="mt-1 text-xs text-rose-700 flex items-start gap-1">
                    <span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span x-text="err('{{ $id }}')">{{ $errors->first($path) }}</span>
                </p>
                @if($softL)<p x-show="!err('{{ $id }}') && warn('{{ $id }}')" x-cloak class="mt-1 text-xs text-amber-800">{{ __('admin.i18n.over_soft') }}</p>@endif
                @if($hint)<p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>@endif
            </div>
            @if($l !== $source)
                {{-- the Uzbek original stays in sight while translating --}}
                @if($fallback === 'default')
                    <p x-show="empty('{{ $id }}')" class="mt-1 text-xs text-stone-500">{{ __('admin.i18n.fallback_default') }}</p>
                @else
                    @php $has = $fallback === 'uz-default' ? "changed('$base', '$source')" : "original('$base')"; @endphp
                    @if($fallback === 'uz-default')<p x-show="empty('{{ $id }}') && !{{ $has }}" class="mt-1 text-xs text-stone-500">{{ __('admin.i18n.fallback_default') }}</p>@endif
                    <div x-show="{{ $has }}" x-cloak class="mt-1.5 rounded-md bg-stone-50 border border-stone-200 px-2.5 py-1.5 text-xs text-stone-600" data-uz-original>
                        <div class="flex items-start gap-2">
                            <span class="font-semibold text-stone-500 shrink-0">{{ strtoupper($source) }}</span>
                            <span class="flex-1 min-w-0 line-clamp-2 whitespace-pre-line" lang="{{ $source }}" x-text="original('{{ $base }}')"></span>
                            <button type="button" x-on:click="copy(original('{{ $base }}'), '{{ $id }}')" class="admin-focus rounded shrink-0 text-amber-800 hover:underline font-medium"
                                    x-text="copied === '{{ $id }}' ? @js(__('admin.i18n.copied')) : @js(__('admin.i18n.copy'))">{{ __('admin.i18n.copy') }}</button>
                        </div>
                        @if($fallback !== 'none')<p x-show="empty('{{ $id }}')" class="mt-1 text-stone-500">{{ __('admin.i18n.fallback_uz') }}</p>@endif
                    </div>
                @endif
            @elseif(in_array($fallback, ['default', 'uz-default'], true))
                <p x-show="empty('{{ $id }}')" class="mt-1 text-xs text-stone-500">{{ __('admin.i18n.fallback_default') }}</p>
            @endif
        </div>
    @endforeach
</div>
