<div @if(!$d) wire:poll.15s @endif>
    <x-admin.page-header :crumb="__('nav.group.system')" :title="__('system.title')" :hint="__('system.hint')" />

    <div class="grid md:grid-cols-2 gap-4">
        @foreach([
            'scheduler' => ['icon' => 'clock', 'cmd' => '* * * * * cd /path/to/markor && php artisan schedule:run >> /dev/null 2>&1'],
            'worker'    => ['icon' => 'cpu-chip', 'cmd' => 'php artisan queue:work --tries=3 --timeout=600'],
        ] as $key => $meta)
            @php $st = $s[$key]; @endphp
            <div class="bg-white border rounded-xl p-5 {{ $st['ok'] ? 'border-stone-200' : 'border-rose-300' }}">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold flex items-center gap-2">@svg('heroicon-o-' . $meta['icon'], 'w-5 h-5 text-stone-500') {{ __("system.$key.title") }}</p>
                    @if($st['ok'])
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>{{ __('system.running') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-800"><span class="w-2 h-2 rounded-full bg-rose-500"></span>{{ __('system.stopped') }}</span>
                    @endif
                </div>
                <p class="text-sm text-stone-600 mt-2">{{ __("system.$key.what") }}</p>
                <p class="text-xs text-stone-500 mt-2">{{ __('system.last_seen') }}: {{ $st['at'] ? $st['at']->diffForHumans() . ' · ' . $st['at']->format('d.m.Y H:i:s') : __('system.never') }}</p>
                @unless($st['ok'])
                    <div class="mt-3 text-xs bg-rose-50 border border-rose-200 rounded-lg p-3 text-rose-900">
                        <p class="font-medium">{{ __("system.$key.fix") }}</p>
                        <code class="block mt-1.5 font-mono text-[11px] text-stone-800 break-all">{{ $meta['cmd'] }}</code>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mt-4">
        <div class="bg-white border border-stone-200 rounded-xl p-4">
            <p class="text-xs text-stone-500">{{ __('system.driver') }}</p>
            <p class="text-lg font-semibold mt-1 font-mono">{{ $s['driver'] }}</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-xl p-4">
            <p class="text-xs text-stone-500">{{ __('system.pending') }}</p>
            <p class="text-lg font-semibold mt-1 tabular-nums">{{ $s['pending'] ?? '—' }}</p>
            @if($s['oldest'])<p class="text-xs {{ $s['oldest']->lt(now()->subMinutes(5)) ? 'text-rose-700' : 'text-stone-500' }}">{{ __('system.oldest') }}: {{ $s['oldest']->diffForHumans() }}</p>@endif
        </div>
        <div class="bg-white border rounded-xl p-4 {{ $s['failed_count'] ? 'border-rose-300' : 'border-stone-200' }}">
            <p class="text-xs text-stone-500">{{ __('system.failed') }}</p>
            <p class="text-lg font-semibold mt-1 tabular-nums {{ $s['failed_count'] ? 'text-rose-700' : '' }}">{{ $s['failed_count'] }}</p>
        </div>
    </div>

    @if($s['failed'])
        <div class="bg-white border border-stone-200 rounded-xl mt-4">
            <div class="flex items-center justify-between px-4 py-3 border-b border-stone-200">
                <p class="font-semibold text-sm">{{ __('system.failed_list') }}</p>
                <x-admin.button size="sm" icon="arrow-path" wire:click="retryAll">{{ __('system.retry_all') }}</x-admin.button>
            </div>
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach($s['failed'] as $f)
                    <li class="px-4 py-3 flex items-start gap-3" wire:key="fj-{{ $f['uuid'] }}">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium font-mono text-[13px]">{{ $f['job'] }} <span class="font-sans text-xs text-stone-500 font-normal">· {{ $f['failed_at']->format('d.m.Y H:i') }}</span></p>
                            <p class="text-xs text-stone-600 mt-0.5 break-words">{{ $f['error'] }}</p>
                        </div>
                        <x-admin.button size="sm" variant="ghost" icon="magnifying-glass" wire:click="showDetail('{{ $f['uuid'] }}')">{{ __('system.details') }}</x-admin.button>
                        <x-admin.button size="sm" wire:click="retry('{{ $f['uuid'] }}')">{{ __('system.retry') }}</x-admin.button>
                        <x-admin.button size="sm" variant="ghost" wire:click="forget('{{ $f['uuid'] }}')" wire:confirm="{{ __('system.forget_confirm') }}">{{ __('common.delete') }}</x-admin.button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($d)
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-stone-900/50 sm:p-4" x-data x-on:keydown.escape.window="$wire.closeDetail()">
            <div role="dialog" aria-modal="true" aria-labelledby="fj-title" class="w-full sm:max-w-3xl max-h-[90vh] flex flex-col bg-white rounded-t-2xl sm:rounded-xl shadow-xl">
                <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-stone-200">
                    <div class="min-w-0">
                        <h2 id="fj-title" class="font-semibold text-stone-900 font-mono text-sm break-all">{{ $d['job'] }}</h2>
                        <p class="text-xs text-stone-500 mt-1">{{ $d['failed_at']->format('d.m.Y H:i:s') }} · {{ $d['connection'] }}/{{ $d['queue'] }}
                            @if($d['max_tries']) · {{ __('system.tries') }}: {{ $d['max_tries'] }}@endif @if($d['timeout']) · timeout {{ $d['timeout'] }} s @endif</p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="admin-focus p-2 rounded-lg text-stone-500 hover:bg-stone-100" aria-label="{{ __('common.cancel') }}">@svg('heroicon-o-x-mark', 'w-5 h-5')</button>
                </div>
                <div class="px-5 py-4 overflow-auto" x-data="{ copied: false }">
                    <div class="flex justify-end mb-2">
                        <button type="button" class="text-xs text-amber-800 hover:underline" x-on:click="navigator.clipboard.writeText($refs.trace.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                            <span x-show="!copied">{{ __('system.copy') }}</span><span x-show="copied" x-cloak>✓</span>
                        </button>
                    </div>
                    <pre x-ref="trace" class="text-[11px] leading-relaxed font-mono whitespace-pre-wrap break-words bg-stone-950 text-stone-100 rounded-lg p-4">{{ $d['error'] }}</pre>
                    <p class="text-[11px] text-stone-500 mt-2">{{ __('system.masked') }}</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-3 border-t border-stone-200">
                    <x-admin.button wire:click="closeDetail">{{ __('common.cancel') }}</x-admin.button>
                    <x-admin.button variant="primary" icon="arrow-path" wire:click="retry('{{ $d['uuid'] }}'); $wire.closeDetail()">{{ __('system.retry') }}</x-admin.button>
                </div>
            </div>
        </div>
    @endif
</div>
