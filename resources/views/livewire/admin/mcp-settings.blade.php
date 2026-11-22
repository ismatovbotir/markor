<div class="bg-white border border-stone-200 rounded-xl p-5 space-y-3">
    <div>
        <h2 class="font-semibold text-sm">{{ __('mcp.title') }}</h2>
        <p class="text-xs text-stone-500 mt-1">{{ __('mcp.hint') }}</p>
    </div>
    <p class="text-sm">{{ __('mcp.endpoint') }}: <code class="font-mono text-xs bg-stone-100 px-1.5 py-0.5 rounded">{{ $endpoint }}</code></p>

    @if($newToken)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2" x-data="{ copied: false }">
            <p class="text-sm font-medium text-amber-900">{{ __('mcp.copy_now') }}</p>
            <div class="flex gap-2">
                <input type="text" readonly value="{{ $newToken }}" class="flex-1 font-mono text-xs border border-amber-200 rounded-lg px-3 py-2 bg-white" x-on:focus="$event.target.select()">
                <button type="button" x-on:click="navigator.clipboard.writeText(@js($newToken)); copied = true" class="px-3 py-2 bg-amber-700 text-white rounded-lg text-xs">
                    <span x-show="!copied">{{ __('mcp.copy') }}</span><span x-show="copied" x-cloak>✓</span>
                </button>
            </div>
            <p class="text-[11px] text-stone-600">{{ __('mcp.claude_code') }}</p>
            <pre class="text-[11px] bg-stone-900 text-stone-100 rounded-lg p-3 overflow-x-auto">claude mcp add --transport http markor {{ $endpoint }} --header "Authorization: Bearer {{ $newToken }}"</pre>
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-xs {{ $exists ? 'text-emerald-700' : 'text-stone-500' }}">
            {{ $exists ? __('mcp.active', ['date' => $createdAt]) : __('mcp.off') }}
        </p>
        <div class="flex gap-2">
            @if($exists)
                <button type="button" wire:click="revoke" wire:confirm="{{ __('mcp.revoke_confirm') }}" class="px-3 py-2 text-xs text-stone-600 hover:text-rose-600">{{ __('mcp.revoke') }}</button>
            @endif
            <button type="button" wire:click="generate" @if($exists) wire:confirm="{{ __('mcp.replace_confirm') }}" @endif class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">
                {{ $exists ? __('mcp.replace') : __('mcp.create') }}
            </button>
        </div>
    </div>
</div>
