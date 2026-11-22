<div class="bg-white border border-stone-200 rounded-xl p-5 space-y-3 mt-6">
    <div>
        <h2 class="font-semibold text-sm flex items-center gap-2">@svg('heroicon-o-key', 'w-4 h-4 text-amber-700') {{ __('aslbelgi_key.title') }}</h2>
        <p class="text-xs text-stone-500 mt-1">{{ __('aslbelgi_key.hint') }}</p>
    </div>

    @if($hasKey)
        <div class="flex items-center justify-between gap-3 flex-wrap text-sm">
            <p>
                <span class="font-mono text-stone-700">••••••••{{ $last4 }}</span>
                @if($expiresAt)
                    <span @class(['ml-2 text-xs', 'text-rose-600' => $expiresAt->isPast(), 'text-amber-700' => !$expiresAt->isPast() && $expiresAt->diffInDays(now(), true) <= 14, 'text-emerald-700' => $expiresAt->diffInDays(now(), true) > 14 && !$expiresAt->isPast()])>
                        {{ $expiresAt->isPast() ? __('aslbelgi_key.expired') : __('aslbelgi_key.expires', ['date' => $expiresAt->format('d.m.Y')]) }}
                    </span>
                @endif
            </p>
            <button type="button" wire:click="remove" wire:confirm="{{ __('aslbelgi_key.remove_confirm') }}" class="text-xs text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
        </div>
    @endif

    <form wire:submit="save" class="flex gap-2 items-start flex-wrap">
        <div class="flex-1 min-w-[240px]">
            <input wire:model="apiKey" type="password" autocomplete="off" spellcheck="false" placeholder="{{ $hasKey ? __('aslbelgi_key.replace_placeholder') : 'c09d906f-…' }}"
                   class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
            @error('apiKey')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 disabled:opacity-60">
            <span wire:loading.remove wire:target="save">{{ __('aslbelgi_key.save') }}</span><span wire:loading wire:target="save">{{ __('aslbelgi_key.checking') }}</span>
        </button>
    </form>
    <p class="text-[11px] text-stone-500">{{ __('aslbelgi_key.where') }}</p>
</div>
