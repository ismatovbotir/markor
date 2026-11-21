{{-- Order request modal for offer items and label sizes. Opened with $dispatch('order-item', {offer|template, title}). --}}
@once
@php
    $tg = config('contact.telegram');
    $oPhone = config('contact.phone');
    $oField = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white';
@endphp
<div x-data="orderModal(@js(['url' => route('offer-request'), 'name' => auth()->user()?->name, 'phone' => auth()->user()?->phone, 'error' => __('studio.generic_error'), 'tooMany' => __('studio.too_many')]))"
     x-on:order-item.window="open($event.detail)" x-on:keydown.escape.window="close()"
     x-show="visible" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" x-on:click.outside="close()">
        @include('partials.modal-promo')
        <p class="text-xs text-stone-500">{{ __('offers.action.order') }}</p>
        <h2 class="text-lg font-semibold text-stone-900" x-text="title"></h2>

        <form x-show="state !== 'done'" x-on:submit.prevent="submit()" class="space-y-3 mt-4">
            <input type="text" name="website" x-model="form.website" class="hidden" tabindex="-1" autocomplete="off">
            <label class="block text-xs font-medium text-stone-600">{{ __('auth.name') }}
                <input x-model="form.name" type="text" required maxlength="120" class="{{ $oField }} mt-1">
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('auth.phone') }}
                <input x-model="form.phone" type="tel" required placeholder="+998 90 123 45 67" class="{{ $oField }} mt-1">
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('requests.quantity') }} <span class="text-stone-400">({{ __('common.optional') }})</span>
                <input x-model="form.quantity" type="text" maxlength="40" class="{{ $oField }} mt-1">
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('requests.comment') }} <span class="text-stone-400">({{ __('common.optional') }})</span>
                <textarea x-model="form.comment" rows="2" maxlength="1000" class="{{ $oField }} mt-1"></textarea>
            </label>
            <p x-show="error" x-text="error" class="text-sm text-rose-600"></p>
            <div x-show="state === 'sending'" class="h-1.5 bg-stone-100 rounded-full overflow-hidden"><div class="h-full bg-amber-600 transition-all duration-200" :style="`width:${progress}%`"></div></div>
            <div class="flex items-center justify-between gap-3 pt-1">
                <span class="text-xs text-stone-500">
                    @if($tg)<a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener" class="hover:text-amber-700">Telegram</a>@endif
                    @if($tg && $oPhone) · @endif
                    @if($oPhone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $oPhone) }}" class="hover:text-amber-700">{{ $oPhone }}</a>@endif
                </span>
                <span class="flex gap-2">
                    <button type="button" x-on:click="close()" class="px-3 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                    <button type="submit" :disabled="state === 'sending'" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 disabled:opacity-50">{{ __('requests.send') }}</button>
                </span>
            </div>
        </form>
        <div x-show="state === 'done'" class="mt-4">
            <p class="text-sm text-emerald-700 flex items-center gap-2">@svg('heroicon-o-check-circle', 'w-5 h-5') {{ __('requests.sent') }}</p>
            <div class="flex justify-end pt-4"><button type="button" x-on:click="close()" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm">OK</button></div>
        </div>
    </div>
</div>
@endonce
