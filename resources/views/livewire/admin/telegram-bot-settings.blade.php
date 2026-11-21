<div class="bg-white border border-stone-200 rounded-xl p-5 space-y-4" wire:init="check">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h2 class="font-semibold text-sm">{{ __('tg_admin.bot_settings') }}</h2>
        @if($enabled)
            <button wire:click="check" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">
                <span wire:loading.remove wire:target="check">@svg('heroicon-o-arrow-path', 'w-4 h-4')</span>
                <span wire:loading wire:target="check">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                {{ __('tg_admin.check') }}
            </button>
        @endif
    </div>

    @if(!$enabled)
        <p class="text-sm text-amber-800">{{ __('tg_admin.no_token') }}</p>
    @else
        <div class="space-y-1 text-sm">
            <p><span class="text-stone-500">{{ __('tg_admin.bot') }}:</span>
                @if(!$checked) <span class="text-stone-400">…</span>
                @elseif($bot) <a href="https://t.me/{{ $bot['username'] }}" target="_blank" rel="noopener" class="font-medium text-sky-700 hover:underline">{{ '@' . $bot['username'] }}</a> · {{ $bot['first_name'] }}
                @else <span class="text-rose-600">{{ __('tg_admin.token_invalid') }}</span> @endif
            </p>
            <p><span class="text-stone-500">Webhook:</span>
                @if(!$checked) <span class="text-stone-400">…</span>
                @elseif(filled($webhook['url'] ?? null))
                    <span class="text-emerald-700 font-medium">{{ __('tg_admin.webhook_on') }}</span>
                    <span class="block font-mono text-xs text-stone-500 break-all">{{ $webhook['url'] }}</span>
                    @if(($webhook['pending_update_count'] ?? 0) > 0)<span class="text-xs text-stone-500">{{ __('tg_admin.pending', ['n' => $webhook['pending_update_count']]) }}</span>@endif
                    @if(filled($webhook['last_error_message'] ?? null))<span class="block text-xs text-rose-600">{{ __('tg_admin.last_error') }}: {{ $webhook['last_error_message'] }}</span>@endif
                @else
                    <span class="text-stone-500">{{ __('tg_admin.webhook_off') }}</span>
                @endif
            </p>
            @unless($publicUrl)
                <p class="text-xs text-amber-700">{{ __('tg_admin.needs_https', ['url' => config('app.url')]) }}</p>
            @endunless
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button wire:click="setWebhook" @disabled(!$publicUrl) class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-medium hover:bg-sky-700 disabled:opacity-40">{{ __('tg_admin.set_webhook') }}</button>
            @if(filled($webhook['url'] ?? null))
                <button wire:click="deleteWebhook" wire:confirm="{{ __('tg_admin.confirm_delete') }}" class="px-3 py-2 border border-rose-200 text-rose-700 rounded-lg text-sm hover:bg-rose-50">{{ __('tg_admin.delete_webhook') }}</button>
            @endif
        </div>

        {{-- the two chats, from .env (read-only) --}}
        <div class="border-t border-stone-100 pt-4 space-y-2">
            @foreach($chats as $key => [$label, $value, $used, $env])
                <div class="flex items-center justify-between gap-3 flex-wrap text-sm">
                    <div>
                        <p class="text-xs font-medium text-stone-600">{{ __($label) }} <span class="font-mono text-stone-400">{{ $env }}</span></p>
                        @if($value)
                            <p class="font-mono">{{ $value }}</p>
                        @elseif($used)
                            <p class="text-stone-500">{{ __('tg_admin.chat_fallback', ['chat' => $used]) }}</p>
                        @else
                            <p class="text-amber-700">{{ __('tg_admin.chat_missing') }}</p>
                        @endif
                    </div>
                    @if($used)
                        <button type="button" wire:click="testChat('{{ $key }}')" class="px-3 py-1.5 border border-stone-300 rounded-lg text-xs hover:bg-stone-100">{{ __('tg_admin.test') }}</button>
                    @endif
                </div>
            @endforeach
            <p class="text-[11px] text-stone-500">{{ __('tg_admin.chats_env_hint') }}</p>
        </div>

        @if($message)<p class="text-sm {{ $messageOk ? 'text-emerald-700' : 'text-rose-600' }}">{{ $message }}</p>@endif
    @endif
</div>
