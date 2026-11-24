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
            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">{{ __('telegram.howto_phone') }}</p>
            <img src="/images/telegram-connect-uz.gif" alt="{{ __('telegram.howto_alt') }}" width="720" height="417" loading="lazy" class="w-full max-w-3xl rounded-lg border border-stone-200">
        </div>
    @endunless

    @if($open && $link)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" @if(in_array($link->status, ['pending', 'started', 'phone_ok'])) wire:poll.2s @endif>
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 text-center">
                @include('partials.modal-promo')
                <h2 class="text-lg font-semibold">{{ __('telegram.title') }}</h2>

                @if(in_array($link->status, ['pending', 'started']))
                    <ol class="text-sm text-stone-600 text-left mt-3 space-y-1 list-decimal pl-5">
                        <li>{{ __('telegram.step_scan') }}</li>
                        <li>{{ __('telegram.step_phone') }}</li>
                        <li>{{ __('telegram.step_code') }}</li>
                    </ol>
                    <p class="text-xs text-stone-500 mt-2">{{ __('telegram.howto_phone') }}</p>
                    <img src="{{ $qr }}" alt="QR" class="w-52 h-52 mx-auto my-4" style="image-rendering: pixelated">
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-sky-700 hover:underline">t.me/{{ $bot }}</a>
                    <p class="text-xs text-stone-500 mt-2">
                        {{ $link->status === 'started' ? __('telegram.waiting_phone') : __('telegram.waiting_scan') }}
                        · {{ __('telegram.valid_until', ['time' => $link->expires_at->format('H:i')]) }}
                    </p>
                @elseif($link->status === 'phone_ok')
                    <p class="text-sm text-emerald-700 mt-3 flex items-center justify-center gap-1.5">@svg('heroicon-o-check-circle', 'w-5 h-5') {{ __('telegram.phone_ok') }}</p>
                    <p class="text-sm text-stone-600 mt-4">{{ __('telegram.type_code') }}</p>
                    <p class="font-mono text-4xl font-bold tracking-[0.35em] text-stone-900 my-4">{{ $link->code }}</p>
                @elseif(in_array($link->status, ['linked', 'admin_linked'], true))
                    <p class="text-emerald-700 mt-4 flex items-center justify-center gap-2 font-medium">@svg('heroicon-o-check-circle', 'w-6 h-6') {{ __('telegram.success') }}</p>
                    <p class="text-sm text-stone-600 mt-2">{{ __('telegram.success_hint') }}</p>
                @elseif($link->status === 'phone_mismatch')
                    <p class="text-rose-600 text-sm mt-4">{{ __('telegram.phone_mismatch') }}</p>
                @else
                    <p class="text-rose-600 text-sm mt-4">{{ __('telegram.expired') }}</p>
                @endif

                <div class="flex justify-center gap-3 mt-5">
                    @if(in_array($link->status, ['phone_mismatch', 'failed', 'expired']))
                        <button wire:click="start" class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">{{ __('import.retry') }}</button>
                    @endif
                    <button wire:click="close" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ in_array($link->status, ['linked', 'admin_linked'], true) ? 'OK' : __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
