{{-- Bot profile at Telegram: name, descriptions, "/" commands per language (Default + uz/ru/zh).
     Form values live in $wire.form (draft saved in settings on every change); counters and the preview read them client-side. --}}
@php
    $partKeys = ['name', 'description', 'short'];
    $rowsMax = ['name' => 1, 'description' => 5, 'short' => 2];
@endphp
<div class="bg-white border border-stone-200 rounded-xl" wire:init="init" data-bot-profile
     x-data="{
        lang: 'default', sendOpen: false, scope: 'all', previewOpen: false, previewLang: 'default', previewView: 'chat',
        limits: @js($limits),
        val(part, l) { return (($wire.form[part] || {})[l] || '') },
        len(part, l) { return [...this.val(part, l)].length },
        counter(n, max) { return n > max ? 'text-rose-600 font-semibold' : 'text-stone-500' },
        rows(l) { return (($wire.form.commands || {})[l] || []).filter(r => (r.command || '').trim() || (r.description || '').trim()) },
        filled(l) { return ['name', 'description', 'short'].filter(p => this.val(p, l).trim()).length + (this.rows(l).length ? 1 : 0) },
        shown(part, l) { const v = this.val(part, l).trim(); return v || this.val(part, 'default').trim() },
        cmds(l) { const r = this.rows(l); return (r.length ? r : this.rows('default')).map(c => ({ command: (c.command || '').replace(/^\/+/, '').toLowerCase(), description: c.description || '' })) },
        tg: {
            default: { what: 'What can this bot do?', start: 'START', bot: 'bot', info: 'Info', username: 'Username', message: 'Message', menu: 'Menu' },
            uz: { what: 'Bu bot nima qila oladi?', start: 'BOSHLASH', bot: 'bot', info: 'Ma\'lumot', username: 'Foydalanuvchi nomi', message: 'Xabar', menu: 'Menyu' },
            ru: { what: 'Что может делать этот бот?', start: 'ЗАПУСТИТЬ', bot: 'бот', info: 'Информация', username: 'Имя пользователя', message: 'Сообщение', menu: 'Меню' },
            zh: { what: '这个机器人能做什么？', start: '开始', bot: '机器人', info: '信息', username: '用户名', message: '消息', menu: '菜单' },
        },
        t(key) { return (this.tg[this.previewLang] || this.tg.default)[key] },
        openPreview() { this.previewLang = this.lang; this.previewOpen = true },
     }"
     x-on:bot-profile-errors.window="lang = $event.detail.lang">

    {{-- header --}}
    <div class="px-4 sm:px-5 pt-5 pb-3 flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
            <h2 class="font-semibold text-sm text-stone-900">{{ __('tg_profile.title') }}</h2>
            <p class="text-xs text-stone-500 mt-0.5 max-w-xl">{{ __('tg_profile.hint') }}</p>
        </div>
        @if($enabled)
            <div class="flex items-center gap-2 flex-wrap">
                <x-admin.button size="sm" icon="arrow-down-tray" wire:click="loadFromTelegram" wire:loading.attr="disabled" wire:target="loadFromTelegram" data-load>
                    <span wire:loading.remove wire:target="loadFromTelegram">{{ __('tg_profile.load') }}</span>
                    <span wire:loading wire:target="loadFromTelegram">{{ __('tg_profile.loading') }}</span>
                </x-admin.button>
                <x-admin.button size="sm" icon="eye" x-on:click="openPreview()" data-preview>{{ __('tg_profile.preview') }}</x-admin.button>
                <div class="relative" x-on:click.outside="sendOpen = false" x-on:keydown.escape="sendOpen = false">
                    <x-admin.button size="sm" variant="primary" icon="paper-airplane" x-on:click="sendOpen = !sendOpen" ::aria-expanded="sendOpen" aria-haspopup="menu" data-send-menu>
                        {{ __('tg_profile.send') }} ▾
                    </x-admin.button>
                    <div x-show="sendOpen" x-cloak x-transition.opacity role="menu"
                         class="absolute right-0 z-30 mt-1 w-72 rounded-xl border border-stone-200 bg-white shadow-lg p-2 text-sm">
                        <fieldset class="px-2 pt-1 pb-2 border-b border-stone-100">
                            <legend class="text-xs font-medium text-stone-500 mb-1">{{ __('tg_profile.scope') }}</legend>
                            <label class="flex items-center gap-2 py-1 cursor-pointer"><input type="radio" value="all" x-model="scope" class="accent-amber-700"> {{ __('tg_profile.scope_all') }}</label>
                            <label class="flex items-center gap-2 py-1 cursor-pointer"><input type="radio" value="current" x-model="scope" class="accent-amber-700">
                                <span>{{ __('tg_profile.scope_current') }}: <b x-text="lang === 'default' ? @js(__('tg_profile.lang_default')) : lang.toUpperCase()"></b></span></label>
                        </fieldset>
                        @foreach(['all' => 'tg_profile.send_all', 'name' => 'tg_profile.send_name', 'descriptions' => 'tg_profile.send_descriptions', 'commands' => 'tg_profile.send_commands'] as $opt => $label)
                            <button type="button" role="menuitem" x-on:click="sendOpen = false; $wire.prepareSend('{{ $opt }}', scope, lang)" data-send="{{ $opt }}"
                                    class="admin-focus w-full text-left rounded-lg px-2 py-2 hover:bg-stone-100 {{ $opt === 'all' ? 'font-semibold' : '' }}">{{ __($label) }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if(!$enabled)
        <p class="px-4 sm:px-5 pb-4 text-sm text-amber-800">{{ __('tg_profile.disabled') }}</p>
    @else
        {{-- state: unsent edits / draft saved --}}
        <div class="px-4 sm:px-5 pb-3 flex items-center gap-x-4 gap-y-1 flex-wrap text-xs" aria-live="polite">
            @if($unsentLangs)
                <span class="inline-flex items-center gap-1.5 text-amber-800" data-unsent>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    {{ __('tg_profile.unsent') }}: {{ implode(', ', array_map(fn ($l) => \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l), $unsentLangs)) }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-emerald-700">@svg('heroicon-m-check', 'w-4 h-4') {{ __('tg_profile.in_sync') }}</span>
            @endif
            @if($savedAt)<span class="text-stone-500">{{ __('tg_profile.draft_saved', ['time' => $savedAt]) }}</span>@endif
            @if($username)<a href="https://t.me/{{ $username }}" target="_blank" rel="noopener" class="text-amber-800 hover:underline">{{ '@' . $username }} ↗</a>@endif
        </div>

        {{-- results of the last load / send --}}
        @if($results)
            <div class="mx-4 sm:mx-5 mb-4 rounded-lg border border-stone-200 bg-stone-50 p-3" data-results>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <p class="text-xs font-semibold text-stone-700">{{ $resultsTitle }}</p>
                    <button type="button" wire:click="$set('results', [])" class="admin-focus rounded p-1 text-stone-500 hover:bg-stone-200" aria-label="{{ __('common.close') }}">@svg('heroicon-o-x-mark', 'w-4 h-4')</button>
                </div>
                <ul class="space-y-1 text-xs">
                    @foreach($results as $r)
                        <li class="flex items-start gap-1.5">
                            @if($r['ok'])<span class="text-emerald-600 shrink-0">✓</span>@else<span class="text-rose-600 shrink-0">{{ $r['skipped'] ? '–' : '✗' }}</span>@endif
                            <span class="font-medium text-stone-700 shrink-0">{{ $partLabels[$r['part']] }} · {{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($r['lang']) }}</span>
                            @if($r['error'])<span class="{{ $r['skipped'] ? 'text-stone-500' : 'text-rose-700' }} break-words min-w-0">— {{ $r['error'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($isEmpty)
            <div class="mx-4 sm:mx-5 mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 flex items-center justify-between gap-3 flex-wrap" data-suggest>
                <p class="text-sm text-amber-900">{{ __('tg_profile.suggest_text') }}</p>
                <x-admin.button size="sm" icon="sparkles" wire:click="suggest">{{ __('tg_profile.suggest_button') }}</x-admin.button>
            </div>
        @endif

        {{-- language tabs: Default + content languages --}}
        <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-y border-stone-200 px-3 sm:px-5 py-2.5">
            <div role="tablist" aria-label="{{ __('admin.i18n.content_lang') }}" class="grid grid-cols-2 sm:inline-flex gap-1 rounded-lg bg-stone-100 p-1 w-full sm:w-auto">
                @foreach($langs as $l)
                    @php $errs = $errorsByLang[$l] ?? 0; @endphp
                    <button type="button" role="tab" data-lang-tab="{{ $l }}" x-on:click="lang = '{{ $l }}'" :aria-selected="lang === '{{ $l }}'"
                            class="admin-focus inline-flex items-center justify-center gap-1.5 rounded-md px-2.5 sm:px-3 py-1.5 text-sm min-h-9 transition"
                            :class="lang === '{{ $l }}' ? 'bg-white text-stone-900 font-semibold shadow-sm ring-1 ring-stone-300' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-200/60'">
                        @if($errs)
                            <span class="text-rose-600">@svg('heroicon-o-exclamation-triangle', 'w-4 h-4')</span>
                        @else
                            <span x-show="filled('{{ $l }}') === 4" x-cloak class="text-emerald-600">@svg('heroicon-m-check', 'w-4 h-4')</span>
                            <span x-show="filled('{{ $l }}') > 0 && filled('{{ $l }}') < 4" x-cloak class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <span x-show="filled('{{ $l }}') === 0" class="w-2.5 h-2.5 rounded-full border-2 border-stone-400"></span>
                        @endif
                        <span>{{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l) }}</span>
                        <span class="text-xs tabular-nums font-normal text-stone-500" x-text="filled('{{ $l }}') + '/4'"></span>
                        @if($errs)<span class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[11px] font-semibold leading-[18px] text-center" data-lang-errors="{{ $l }}">{{ $errs }}</span>@endif
                        @if(in_array($l, $unsentLangs, true))<span class="text-amber-700" title="{{ __('tg_profile.unsent') }}" aria-label="{{ __('tg_profile.unsent') }}">@svg('heroicon-m-arrow-up-circle', 'w-4 h-4')</span>@endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- fields per language: all in the DOM, the current one visible --}}
        @foreach($langs as $l)
            <div x-show="lang === '{{ $l }}'" @if($l !== 'default') x-cloak @endif role="tabpanel" class="px-4 sm:px-5 py-5 space-y-5" data-panel="{{ $l }}">
                <p class="text-xs text-stone-500">{{ $l === 'default' ? __('tg_profile.default_hint') : __('tg_profile.lang_hint') }}</p>

                @foreach($partKeys as $part)
                    @php $id = "bp-$part-$l"; $path = "form.$part.$l"; @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3 mb-1">
                            <label for="{{ $id }}" class="text-[13px] font-medium text-stone-700 flex items-center gap-1.5">
                                <span>{{ $partLabels[$part] }}</span>
                                <span class="px-1.5 rounded bg-stone-100 text-stone-600 text-[10px] font-semibold tracking-wide leading-4">{{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l) }}</span>
                            </label>
                            <span class="text-xs tabular-nums shrink-0" :class="counter(len('{{ $part }}', '{{ $l }}'), {{ $limits[$part] }})"><span x-text="len('{{ $part }}', '{{ $l }}')">0</span> / {{ $limits[$part] }}</span>
                        </div>
                        @if($part === 'name')
                            <input type="text" id="{{ $id }}" wire:model.live.debounce.800ms="{{ $path }}" lang="{{ $l === 'default' ? 'uz' : $l }}" class="admin-input"
                                   @error($path) aria-invalid="true" @enderror aria-describedby="{{ $id }}-msg">
                        @else
                            <textarea id="{{ $id }}" wire:model.live.debounce.800ms="{{ $path }}" rows="{{ $rowsMax[$part] }}" lang="{{ $l === 'default' ? 'uz' : $l }}" class="admin-input"
                                      @error($path) aria-invalid="true" @enderror aria-describedby="{{ $id }}-msg"></textarea>
                        @endif
                        <div id="{{ $id }}-msg">
                            @error($path)<p role="alert" class="mt-1 text-xs text-rose-700 flex items-start gap-1"><span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span>{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-stone-500">{{ __("tg_profile.{$part}_hint") }}</p>
                            @if($l !== 'default')
                                <div x-show="!val('{{ $part }}', '{{ $l }}').trim()" class="mt-1.5 rounded-md bg-stone-50 border border-stone-200 px-2.5 py-1.5 text-xs text-stone-600">
                                    <p>{{ __('tg_profile.falls_back') }}</p>
                                    <p x-show="val('{{ $part }}', 'default').trim()" class="mt-0.5 line-clamp-2 whitespace-pre-line text-stone-700" x-text="val('{{ $part }}', 'default')"></p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- commands --}}
                @php $rows = $form['commands'][$l] ?? []; @endphp
                <div>
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <p class="text-[13px] font-medium text-stone-700 flex items-center gap-1.5">
                            <span>{{ __('tg_profile.commands') }}</span>
                            <span class="px-1.5 rounded bg-stone-100 text-stone-600 text-[10px] font-semibold tracking-wide leading-4">{{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l) }}</span>
                        </p>
                        <span class="text-xs tabular-nums text-stone-500">{{ count($rows) }} / {{ $limits['commands'] }}</span>
                    </div>
                    <p class="text-xs text-stone-500 mb-2">{{ __('tg_profile.commands_hint') }}</p>
                    @error("form.commands.$l")<p role="alert" class="mb-2 text-xs text-rose-700">{{ $message }}</p>@enderror

                    @if(!$rows && $l !== 'default')
                        <div class="rounded-md bg-stone-50 border border-stone-200 px-2.5 py-2 text-xs text-stone-600 flex items-center justify-between gap-2 flex-wrap mb-2">
                            <span>{{ __('tg_profile.commands_fall_back') }}</span>
                            @if($form['commands']['default'] ?? [])
                                <button type="button" wire:click="copyDefaultCommands('{{ $l }}')" class="admin-focus rounded text-amber-800 font-medium hover:underline">{{ __('tg_profile.copy_default_commands') }}</button>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-2">
                        @foreach($rows as $i => $row)
                            @php $cErr = $errors->first("form.commands.$l.$i.command"); $dErr = $errors->first("form.commands.$l.$i.description"); @endphp
                            <div class="rounded-lg border {{ $cErr || $dErr ? 'border-rose-300' : 'border-stone-200' }} p-2" wire:key="cmd-{{ $l }}-{{ $i }}" data-command-row>
                                <div class="flex items-start gap-2 flex-wrap sm:flex-nowrap">
                                    <div class="w-full sm:w-44 shrink-0">
                                        <label for="bp-cmd-{{ $l }}-{{ $i }}" class="sr-only">{{ __('tg_profile.command') }}</label>
                                        <div class="flex items-center">
                                            <span class="px-2 py-2 text-sm text-stone-500 bg-stone-50 border border-r-0 border-stone-300 rounded-l-lg font-mono">/</span>
                                            <input id="bp-cmd-{{ $l }}-{{ $i }}" type="text" wire:model.live.debounce.800ms="form.commands.{{ $l }}.{{ $i }}.command" placeholder="start"
                                                   class="admin-input rounded-l-none font-mono" autocapitalize="none" spellcheck="false" @if($cErr) aria-invalid="true" @endif>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <label for="bp-cmd-d-{{ $l }}-{{ $i }}" class="sr-only">{{ __('tg_profile.command_description') }}</label>
                                        <input id="bp-cmd-d-{{ $l }}-{{ $i }}" type="text" wire:model.live.debounce.800ms="form.commands.{{ $l }}.{{ $i }}.description"
                                               placeholder="{{ __('tg_profile.command_description') }}" lang="{{ $l === 'default' ? 'uz' : $l }}" class="admin-input" @if($dErr) aria-invalid="true" @endif>
                                    </div>
                                    <div class="flex items-center gap-0.5 shrink-0 ml-auto">
                                        <button type="button" wire:click="moveCommand('{{ $l }}', {{ $i }}, -1)" @disabled($i === 0) class="admin-focus rounded-lg p-2 text-stone-600 hover:bg-stone-100 disabled:opacity-30" aria-label="{{ __('tg_profile.move_up') }}">@svg('heroicon-o-arrow-up', 'w-4 h-4')</button>
                                        <button type="button" wire:click="moveCommand('{{ $l }}', {{ $i }}, 1)" @disabled($loop->last) class="admin-focus rounded-lg p-2 text-stone-600 hover:bg-stone-100 disabled:opacity-30" aria-label="{{ __('tg_profile.move_down') }}">@svg('heroicon-o-arrow-down', 'w-4 h-4')</button>
                                        <button type="button" wire:click="removeCommand('{{ $l }}', {{ $i }})" class="admin-focus rounded-lg p-2 text-rose-600 hover:bg-rose-50" aria-label="{{ __('tg_profile.remove') }}">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                                    </div>
                                </div>
                                <div class="flex justify-between gap-2 mt-1 text-xs">
                                    <span class="text-rose-700" role="alert">{{ $cErr ?: $dErr }}</span>
                                    <span class="tabular-nums shrink-0" :class="counter([...(($wire.form.commands['{{ $l }}'][{{ $i }}] || {}).description || '')].length, {{ $limits['command_description'] }})">
                                        <span x-text="[...(($wire.form.commands['{{ $l }}'][{{ $i }}] || {}).description || '')].length">0</span> / {{ $limits['command_description'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if(count($rows) < $limits['commands'])
                        <x-admin.button size="sm" icon="plus" class="mt-2" wire:click="addCommand('{{ $l }}')" data-add-command="{{ $l }}">{{ __('tg_profile.add_command') }}</x-admin.button>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- picture and username: only in @BotFather --}}
    <div class="px-4 sm:px-5 py-4 border-t border-stone-200 flex items-start gap-4 flex-wrap sm:flex-nowrap">
        <img src="{{ asset('images/bot-avatar-640.png') }}" alt="" class="w-16 h-16 rounded-full ring-1 ring-stone-200 shrink-0">
        <div class="min-w-0 text-sm">
            <p class="font-medium text-stone-800">{{ __('tg_profile.avatar_title') }}</p>
            <p class="text-xs text-stone-500 mt-0.5">{{ __('tg_profile.avatar_hint') }}</p>
            <p class="mt-1.5 font-mono text-xs text-stone-700 bg-stone-50 border border-stone-200 rounded-md px-2 py-1 inline-block">{{ __('tg_profile.avatar_steps') }}</p>
            <p class="mt-2"><a href="{{ asset('images/bot-avatar-640.png') }}" download="markor-bot-avatar-640.png" class="admin-focus rounded inline-flex items-center gap-1 text-amber-800 font-medium hover:underline">@svg('heroicon-o-arrow-down-tray', 'w-4 h-4') {{ __('tg_profile.avatar_download') }}</a></p>
        </div>
    </div>

    {{-- confirm: load from Telegram over unsent edits --}}
    @if($confirmLoad)
        <div x-data x-on:keydown.escape.window="$wire.cancelLoad()" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4" data-confirm-load>
            <div role="alertdialog" aria-modal="true" aria-labelledby="bp-load-title" x-trap.noscroll="true" class="w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5">
                <h2 id="bp-load-title" class="font-semibold text-stone-900">{{ __('tg_profile.load_confirm_title') }}</h2>
                <p class="text-sm text-stone-600 mt-1">{{ __('tg_profile.load_confirm_text') }}</p>
                <div class="flex justify-end gap-2 mt-5 flex-wrap">
                    <x-admin.button variant="ghost" wire:click="cancelLoad">{{ __('common.cancel') }}</x-admin.button>
                    <x-admin.button variant="danger" wire:click="loadFromTelegram(true)">{{ __('tg_profile.load_overwrite') }}</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- confirm: exactly what will be sent --}}
    @if($sendPlan)
        <div x-data x-on:keydown.escape.window="$wire.cancelSend()" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4" data-confirm-send>
            <div role="alertdialog" aria-modal="true" aria-labelledby="bp-send-title" x-trap.noscroll="true" class="w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-xl shadow-xl p-5 max-h-[90vh] overflow-y-auto">
                <h2 id="bp-send-title" class="font-semibold text-stone-900">{{ __('tg_profile.send_confirm_title') }}</h2>
                <p class="text-sm text-stone-600 mt-1">{{ __('tg_profile.send_confirm_text', ['n' => count($sendPlan['parts']) * count($sendPlan['langs'])]) }}</p>
                <ul class="mt-3 divide-y divide-stone-100 border border-stone-200 rounded-lg">
                    @foreach($sendPlan['langs'] as $l)
                        <li class="flex items-start gap-3 px-3 py-2 text-sm">
                            <span class="w-20 shrink-0 font-semibold text-stone-700">{{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l) }}</span>
                            <span class="flex flex-wrap gap-1.5">
                                @foreach($sendPlan['parts'] as $part)
                                    @php
                                        $empty = $part === 'commands' ? !($form['commands'][$l] ?? []) : trim($form[$part][$l] ?? '') === '';
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $empty ? 'bg-stone-100 text-stone-600 ring-1 ring-stone-300' : 'bg-amber-50 text-amber-900 ring-1 ring-amber-300' }}">
                                        {{ $partLabels[$part] }}{{ $empty ? ' · ' . __('tg_profile.will_clear') : '' }}
                                    </span>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if(in_array('name', $sendPlan['parts'], true))<p class="mt-3 text-xs text-amber-800">{{ __('tg_profile.name_rate_limit') }}</p>@endif
                <div class="flex justify-end gap-2 mt-5 flex-wrap">
                    <x-admin.button variant="ghost" wire:click="cancelSend">{{ __('common.cancel') }}</x-admin.button>
                    <x-admin.button variant="primary" icon="paper-airplane" wire:click="send" wire:loading.attr="disabled" wire:target="send" data-confirm-send-button>
                        <span wire:loading.remove wire:target="send">{{ __('tg_profile.send_now') }}</span>
                        <span wire:loading wire:target="send">{{ __('tg_profile.sending') }}</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- preview: a Telegram mock built from the current (unsaved) form values --}}
    @if($enabled)
        <div x-show="previewOpen" x-cloak x-on:keydown.escape.window="previewOpen = false" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4" data-preview-modal>
            <div role="dialog" aria-modal="true" aria-labelledby="bp-preview-title" x-trap.noscroll="previewOpen" x-on:click.outside="previewOpen = false"
                 class="w-full sm:max-w-5xl bg-white rounded-t-2xl sm:rounded-xl shadow-xl max-h-[92vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white border-b border-stone-200 px-4 sm:px-5 py-3 flex items-center justify-between gap-3 flex-wrap">
                    <h2 id="bp-preview-title" class="font-semibold text-stone-900">{{ __('tg_profile.preview_title') }}</h2>
                    <div class="flex items-center gap-2">
                        <div role="tablist" aria-label="{{ __('tg_profile.preview_lang') }}" class="inline-flex gap-1 rounded-lg bg-stone-100 p-1">
                            @foreach($langs as $l)
                                <button type="button" role="tab" x-on:click="previewLang = '{{ $l }}'" :aria-selected="previewLang === '{{ $l }}'"
                                        class="admin-focus rounded-md px-2.5 py-1 text-xs min-h-8"
                                        :class="previewLang === '{{ $l }}' ? 'bg-white text-stone-900 font-semibold shadow-sm ring-1 ring-stone-300' : 'text-stone-600 hover:bg-stone-200/60'">{{ \App\Http\Livewire\Admin\TelegramBotProfile::langLabel($l) }}</button>
                            @endforeach
                        </div>
                        <button type="button" x-on:click="previewOpen = false" class="admin-focus rounded-lg p-2 text-stone-500 hover:bg-stone-100" aria-label="{{ __('common.close') }}">@svg('heroicon-o-x-mark', 'w-5 h-5')</button>
                    </div>
                </div>

                {{-- small screens: one view at a time --}}
                <div class="lg:hidden px-4 pt-3">
                    <div role="tablist" class="grid grid-cols-3 gap-1 rounded-lg bg-stone-100 p-1">
                        @foreach(['chat' => 'tg_profile.view_chat', 'profile' => 'tg_profile.view_profile', 'menu' => 'tg_profile.view_menu'] as $v => $label)
                            <button type="button" role="tab" x-on:click="previewView = '{{ $v }}'" :aria-selected="previewView === '{{ $v }}'" class="admin-focus rounded-md py-1.5 text-xs"
                                    :class="previewView === '{{ $v }}' ? 'bg-white font-semibold shadow-sm ring-1 ring-stone-300' : 'text-stone-600'">{{ __($label) }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="p-4 sm:p-5 grid lg:grid-cols-3 gap-4">
                    {{-- (a) empty chat before Start --}}
                    <section :class="previewView === 'chat' ? '' : 'hidden lg:block'">
                        <h3 class="text-xs font-semibold text-stone-500 mb-2">{{ __('tg_profile.view_chat') }}</h3>
                        <div class="rounded-2xl overflow-hidden border border-stone-200 flex flex-col h-[480px] bg-[#cfe3c1]">
                            <div class="bg-white px-3 py-2 flex items-center gap-2.5 border-b border-stone-200">
                                <span class="text-sky-600 text-lg leading-none">‹</span>
                                <img src="{{ asset('images/bot-avatar-640.png') }}" alt="" class="w-9 h-9 rounded-full ring-1 ring-stone-200">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-stone-900 truncate" x-text="shown('name', previewLang) || @js($botName ?? 'Markor')"></p>
                                    <p class="text-[11px] text-stone-500" x-text="t('bot')"></p>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4 flex items-center justify-center">
                                <div class="bg-white/95 rounded-2xl shadow-sm px-4 py-3 max-w-[260px] text-center">
                                    <p class="text-sm font-semibold text-stone-900" x-text="t('what')"></p>
                                    <p class="text-[13px] text-stone-700 mt-1.5 whitespace-pre-line text-left break-words" x-show="shown('description', previewLang)" x-text="shown('description', previewLang)"></p>
                                    <p class="text-xs text-stone-500 mt-1.5 italic" x-show="!shown('description', previewLang)">{{ __('tg_profile.preview_no_description') }}</p>
                                </div>
                            </div>
                            <div class="bg-white border-t border-stone-200 p-2">
                                <div class="w-full rounded-lg py-2.5 text-center text-sm font-semibold text-sky-600 tracking-wide" x-text="t('start')"></div>
                            </div>
                        </div>
                    </section>

                    {{-- (b) profile card --}}
                    <section :class="previewView === 'profile' ? '' : 'hidden lg:block'">
                        <h3 class="text-xs font-semibold text-stone-500 mb-2">{{ __('tg_profile.view_profile') }}</h3>
                        <div class="rounded-2xl overflow-hidden border border-stone-200 h-[480px] bg-stone-100 flex flex-col">
                            <div class="bg-white px-4 pt-6 pb-4 text-center">
                                <img src="{{ asset('images/bot-avatar-640.png') }}" alt="" class="w-24 h-24 rounded-full ring-1 ring-stone-200 mx-auto">
                                <p class="mt-3 text-lg font-semibold text-stone-900 break-words" x-text="shown('name', previewLang) || @js($botName ?? 'Markor')"></p>
                                <p class="text-xs text-stone-500" x-text="t('bot')"></p>
                            </div>
                            <div class="mt-2 bg-white px-4 py-3 space-y-3">
                                <div x-show="shown('short', previewLang)">
                                    <p class="text-[13px] text-stone-900 whitespace-pre-line break-words" x-text="shown('short', previewLang)"></p>
                                    <p class="text-[11px] text-stone-500 mt-0.5" x-text="t('info')"></p>
                                </div>
                                <p x-show="!shown('short', previewLang)" class="text-xs text-stone-500 italic">{{ __('tg_profile.preview_no_short') }}</p>
                                <div class="border-t border-stone-100 pt-3">
                                    <p class="text-[13px] text-sky-700">{{ $username ? '@' . $username : '@…' }}</p>
                                    <p class="text-[11px] text-stone-500" x-text="t('username')"></p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- (c) "/" command menu --}}
                    <section :class="previewView === 'menu' ? '' : 'hidden lg:block'">
                        <h3 class="text-xs font-semibold text-stone-500 mb-2">{{ __('tg_profile.view_menu') }}</h3>
                        <div class="rounded-2xl overflow-hidden border border-stone-200 h-[480px] flex flex-col bg-[#cfe3c1]">
                            <div class="flex-1"></div>
                            <div class="bg-white border-t border-stone-200 max-h-[320px] overflow-y-auto">
                                <template x-for="c in cmds(previewLang)" :key="c.command">
                                    <div class="flex items-center gap-3 px-3 py-2 border-b border-stone-100">
                                        <img src="{{ asset('images/bot-avatar-640.png') }}" alt="" class="w-8 h-8 rounded-full ring-1 ring-stone-200">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-stone-900" x-text="'/' + c.command"></p>
                                            <p class="text-xs text-stone-500 truncate" x-text="c.description"></p>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="!cmds(previewLang).length" class="px-3 py-4 text-xs text-stone-500 italic">{{ __('tg_profile.preview_no_commands') }}</p>
                            </div>
                            <div class="bg-white border-t border-stone-200 px-3 py-2 flex items-center gap-2">
                                <span class="rounded-md bg-sky-600 text-white text-xs font-semibold px-2 py-1" x-text="t('menu')"></span>
                                <span class="flex-1 text-sm text-stone-900 font-mono">/</span>
                            </div>
                        </div>
                    </section>
                </div>
                <p class="px-4 sm:px-5 pb-4 text-xs text-stone-500">{{ __('tg_profile.preview_hint') }}</p>
            </div>
        </div>
    @endif
</div>
