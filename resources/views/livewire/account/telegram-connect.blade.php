<div class="bg-white border border-stone-200 rounded-xl p-5 flex items-center justify-between gap-4 flex-wrap" x-data="{ howto: false }">
    <div class="flex items-center gap-3">
        <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-sky-100 text-sky-700">@svg('heroicon-o-paper-airplane', 'w-5 h-5')</span>
        <div>
            <p class="font-medium">{{ __('telegram.title') }}</p>
            <p class="text-xs text-stone-500">{{ $linked ? __('telegram.linked_hint') : __('telegram.hint') }}</p>
            @unless($linked)
                <button type="button" x-on:click="howto = !howto" class="text-xs text-sky-700 hover:underline mt-0.5" :aria-expanded="howto">{{ __('telegram.howto') }}</button>
            @endunless
        </div>
    </div>
    @if($linked)
        <span class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">@svg('heroicon-o-check', 'w-3 h-3') {{ __('telegram.connected') }}</span>
            <button wire:click="disconnect" wire:confirm="{{ __('telegram.confirm_disconnect') }}" class="text-xs text-stone-500 hover:text-rose-600">{{ __('telegram.disconnect') }}</button>
        </span>
    @elseif($ready)
        <button wire:click="start" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-medium hover:bg-sky-700">{{ __('telegram.connect') }}</button>
    @else
        <span class="text-xs text-stone-400">{{ __('telegram.not_configured') }}</span>
    @endif

    @unless($linked)
        <div x-show="howto" x-cloak x-collapse class="basis-full">
            <ol class="text-xs text-stone-600 list-decimal pl-5 space-y-1 bg-stone-50 border border-stone-200 rounded-lg px-3 py-2">
                <li>{{ __('telegram.step_open', ['bot' => '@' . $bot]) }}</li>
                <li>{{ __('telegram.step_phone') }}</li>
                <li>{{ __('telegram.step_code') }}</li>
            </ol>
        </div>
    @endunless

    @if($open && $link)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" @if($link->status === 'pending') wire:poll.1s="tick" @endif>
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 text-center">
                @include('partials.modal-promo')
                <h2 class="text-lg font-semibold">{{ __('telegram.title') }}</h2>

                @if($link->status === 'pending')
                    <ol class="text-sm text-stone-600 text-left mt-3 space-y-1 list-decimal pl-5">
                        <li>{{ __('telegram.step_open', ['bot' => '@' . $bot]) }}</li>
                        <li>{{ __('telegram.step_phone') }}</li>
                        <li>{{ __('telegram.step_code') }}</li>
                    </ol>
                    {{-- the code is made on the server; a new one every 30 s while this window is open --}}
                    <p class="font-mono text-4xl font-bold tracking-[0.35em] text-stone-900 mt-5 select-all" aria-live="polite">{{ $link->code }}</p>
                    <div class="mt-2 mx-auto w-48" x-data="{ left: {{ (int) $left }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)" wire:key="tg-code-{{ $link->code }}">
                        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden"><div class="h-full bg-sky-500 transition-all duration-1000 ease-linear" :style="`width:${left / {{ \App\Domain\Telegram\TelegramLink::CODE_TTL }} * 100}%`"></div></div>
                        <p class="text-xs text-stone-500 mt-1" x-text="@js(__('telegram.code_left')).replace(':s', left)"></p>
                    </div>
                    @if($url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-5 px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-medium hover:bg-sky-700">@svg('heroicon-o-paper-airplane', 'w-4 h-4') {{ __('telegram.open_bot', ['bot' => '@' . $bot]) }}</a>
                    @endif
                @elseif(in_array($link->status, ['linked', 'admin_linked'], true))
                    <p class="text-emerald-700 mt-4 flex items-center justify-center gap-2 font-medium">@svg('heroicon-o-check-circle', 'w-6 h-6') {{ __('telegram.success') }}</p>
                    <p class="text-sm text-stone-600 mt-2">{{ __('telegram.success_hint') }}</p>
                @else
                    <p class="text-stone-600 text-sm mt-4">{{ __('telegram.code_stopped') }}</p>
                @endif

                <div class="flex justify-center gap-3 mt-5">
                    @if(!in_array($link->status, ['pending', 'linked', 'admin_linked'], true))
                        <button wire:click="start" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">{{ __('telegram.new_code') }}</button>
                    @endif
                    <button wire:click="close" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ in_array($link->status, ['linked', 'admin_linked'], true) ? 'OK' : __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
