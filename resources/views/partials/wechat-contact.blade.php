{{-- WeChat contact: Chinese version of the site only. QR opens large in a window.
     $compact = true: a small "WeChat" link (footer). --}}
@php
    $wechatLink = config('contact.wechat_link');
    $qrSrc = is_file(public_path('images/wechat-qr.jpg')) ? '/images/wechat-qr.jpg' : ($wechatLink ? route('studio.barcode', ['type' => 'qr', 'data' => $wechatLink]) : null);
    $compact ??= false;
@endphp
@if(app()->getLocale() === 'zh' && $qrSrc)
    <{{ $compact ? 'span' : 'li' }} x-data="{ qr: false }" x-on:keydown.escape.window="qr = false" @class(['p-4 rounded-xl bg-emerald-50 border border-emerald-200 block' => !$compact])>
        @if($compact)
            <button type="button" x-on:click="qr = true" class="inline-flex items-center gap-1.5 hover:text-emerald-700"><span class="w-4 h-4 rounded-full bg-emerald-600 text-white text-[9px] font-bold flex items-center justify-center">微</span> WeChat</button>
        @else
            <div class="flex items-start gap-4">
                <button type="button" x-on:click="qr = true" class="shrink-0" title="{{ __('china.wechat_show_qr') }}">
                    <img src="{{ $qrSrc }}" width="112" height="112" alt="WeChat QR" class="w-28 h-28 object-contain rounded-lg bg-white p-1 hover:ring-2 hover:ring-emerald-400 transition">
                </button>
                <div class="min-w-0">
                    <p class="font-semibold text-emerald-800">WeChat · {{ __('china.wechat') }}</p>
                    <button type="button" x-on:click="qr = true" class="inline-block mt-2 text-sm text-emerald-700 font-medium hover:underline">{{ __('china.wechat_open') }} →</button>
                    <p class="text-[11px] text-stone-500 mt-1">{{ __('china.wechat_scan') }}</p>
                </div>
            </div>
        @endif
        <div x-show="qr" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center px-4 text-left" x-on:click.self="qr = false">
            <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm text-center">
                <p class="font-semibold text-stone-900">WeChat · {{ __('china.wechat') }}</p>
                <img src="{{ $qrSrc }}" alt="WeChat QR" class="w-full max-w-xs mx-auto mt-4">
                <p class="text-sm text-stone-500 mt-3">{{ __('china.wechat_scan') }}</p>
                <div class="flex justify-center gap-3 mt-4">
                    @if($wechatLink)<a href="{{ $wechatLink }}" target="_blank" rel="noopener" class="px-4 py-2 border border-emerald-300 text-emerald-800 rounded-lg text-sm hover:bg-emerald-50">{{ __('china.wechat_on_phone') }}</a>@endif
                    <button type="button" x-on:click="qr = false" class="px-4 py-2 bg-stone-900 text-white rounded-lg text-sm">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </{{ $compact ? 'span' : 'li' }}>
@endif
