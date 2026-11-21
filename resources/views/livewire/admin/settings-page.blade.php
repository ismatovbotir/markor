@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div class="w-full">
    <h1 class="text-xl font-semibold mb-1">{{ __('nav.settings') }}</h1>
    <p class="text-sm text-stone-500 mb-6">{{ __('settings.hint') }}</p>

    <form wire:submit="save" class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
        <h2 class="font-semibold text-sm">{{ __('settings.contacts') }}</h2>
        @foreach([
            'phone' => ['auth.phone', '+998 90 123 45 67', 'tel', 'settings.phone_hint'],
            'telegram' => ['Telegram', 'markor_uz', 'text', 'settings.telegram_hint'],
            'telegram_bot' => ['settings.telegram_bot', 'markor_bot', 'text', 'settings.bot_hint'],
            'email' => ['auth.email', 'sales@markor.uz', 'email', 'settings.email_hint'],
            'wechat' => ['WeChat', 'wechat_id', 'text', 'settings.wechat_hint'],
            'wechat_link' => ['settings.wechat_link', 'https://u.wechat.com/…', 'url', 'settings.wechat_link_hint'],
        ] as $key => [$label, $placeholder, $type, $hint])
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1" for="c-{{ $key }}">{{ __($label) }}</label>
                <div class="flex items-center">
                    @if(in_array($key, ['telegram', 'telegram_bot']))<span class="px-3 py-2 text-sm text-stone-500 bg-stone-50 border border-r-0 border-stone-200 rounded-l-lg">t.me/</span>@endif
                    <input id="c-{{ $key }}" wire:model="contact.{{ $key }}" type="{{ $type }}" placeholder="{{ $placeholder }}"
                           class="{{ $field }} {{ in_array($key, ['telegram', 'telegram_bot']) ? 'rounded-l-none' : '' }}">
                </div>
                <p class="text-[11px] text-stone-500 mt-1">{{ __($hint) }}</p>
                @error("contact.$key") <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div class="pt-4 border-t border-stone-100 grid sm:grid-cols-2 gap-4">
            <h2 class="font-semibold text-sm sm:col-span-2">{{ __('settings.payments') }}</h2>
            <label class="block text-xs font-medium text-stone-600">{{ __('balance.card') }}
                <input wire:model="cardNumber" type="text" inputmode="numeric" placeholder="8600 1234 5678 9012" class="{{ $field }} mt-1 font-mono">
                @error('cardNumber') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('settings.card_holder') }}
                <input wire:model="cardHolder" type="text" placeholder="ISMOILOV B." class="{{ $field }} mt-1">
            </label>
            <p class="text-[11px] text-stone-500 sm:col-span-2">{{ __('settings.card_hint') }}</p>
        </div>
        <div class="flex justify-end pt-2">
            <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('common.save') }}</button>
        </div>
    </form>

    <div class="mt-6">
        @livewire(\App\Http\Livewire\Admin\TelegramBotSettings::class)
    </div>

    <div class="mt-6">
        @livewire(\App\Http\Livewire\Admin\AiSettings::class)
    </div>
</div>
