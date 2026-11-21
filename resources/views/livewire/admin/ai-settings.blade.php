<div class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <h2 class="font-semibold text-sm flex items-center gap-1.5">@svg('heroicon-o-sparkles', 'w-4 h-4 text-violet-600') Claude AI</h2>
        @if($reason === null)
            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">{{ __('ai.status_on') }}</span>
        @else
            <span class="px-2 py-0.5 rounded-full {{ in_array($reason, ['credit', 'auth', 'limit']) ? 'bg-rose-100 text-rose-700' : 'bg-stone-100 text-stone-600' }} text-xs font-medium">{{ __('ai.status.' . $reason) }}</span>
        @endif
    </div>

    @if($reason !== null)
        <div class="text-sm {{ in_array($reason, ['credit', 'auth', 'limit']) ? 'text-rose-700' : 'text-stone-600' }}">
            <p>{{ __('ai.unavailable.' . $reason) }}</p>
            @if($until)<p class="text-xs mt-1">{{ __('ai.paused_until', ['time' => \Illuminate\Support\Carbon::parse($until)->format('d.m.Y H:i')]) }}</p>@endif
            @if(in_array($reason, ['credit', 'auth']))
                <button wire:click="resume" class="mt-2 px-3 py-1.5 border border-stone-300 rounded-lg text-xs text-stone-700 hover:bg-stone-100">{{ __('ai.resume') }}</button>
            @endif
        </div>
    @endif

    <div class="grid sm:grid-cols-3 gap-3 text-sm">
        <div class="bg-stone-50 rounded-lg p-3"><p class="text-xs text-stone-500">{{ __('ai.month_cost') }}</p><p class="text-lg font-semibold tabular-nums">${{ number_format($cost, 4) }}</p>@if($limit > 0)<p class="text-[11px] text-stone-500">{{ __('ai.limit', ['usd' => $limit]) }}</p>@endif</div>
        <div class="bg-stone-50 rounded-lg p-3"><p class="text-xs text-stone-500">{{ __('ai.month_calls') }}</p><p class="text-lg font-semibold tabular-nums">{{ $calls }}</p></div>
        <div class="bg-stone-50 rounded-lg p-3"><p class="text-xs text-stone-500">{{ __('ai.month_failed') }}</p><p class="text-lg font-semibold tabular-nums {{ $failed ? 'text-amber-700' : '' }}">{{ $failed }}</p></div>
    </div>

    <table class="w-full text-sm">
        <thead><tr class="text-xs text-stone-500 text-left"><th class="py-1 font-medium">{{ __('ai.task') }}</th><th class="py-1 font-medium">{{ __('ai.level') }}</th><th class="py-1 font-medium text-right">{{ __('ai.month_calls') }}</th><th class="py-1 font-medium text-right">$</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
            @foreach($models as $task => $model)
                @php $row = $byFeature->firstWhere('feature', $task); @endphp
                <tr>
                    <td class="py-1.5">{{ __('ai.feature.' . $task) }}<span class="block text-[11px] text-stone-400">{{ __('ai.fallback.' . $task) }}</span></td>
                    <td class="py-1.5">
                        <select wire:model.live="levels.{{ $task }}" @disabled(isset($pinned[$task])) class="border border-stone-200 rounded-lg px-2 py-1 text-xs bg-white">
                            @foreach(config('ai.level_options') as $level)
                                <option value="{{ $level }}">{{ __('ai.levels.' . $level) }}</option>
                            @endforeach
                        </select>
                        <span class="block font-mono text-[11px] text-stone-400 mt-0.5">{{ $model }}@if(isset($pinned[$task])) · {{ __('ai.pinned') }}@endif</span>
                    </td>
                    <td class="py-1.5 text-right tabular-nums">{{ $row->calls ?? 0 }}</td>
                    <td class="py-1.5 text-right tabular-nums">{{ number_format((float) ($row->cost ?? 0), 4) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($lastError)
        <p class="text-[11px] text-stone-500">{{ __('ai.last_error') }}: {{ $lastError->created_at->format('d.m H:i') }} · {{ $lastError->status }} · {{ \Illuminate\Support\Str::limit($lastError->error, 140) }}</p>
    @endif
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-[11px] text-stone-500 flex-1">{{ __('ai.auto_hint') }}</p>
        <button wire:click="refreshModels" class="px-3 py-1.5 border border-stone-300 rounded-lg text-xs hover:bg-stone-100">{{ __('ai.refresh_models') }}</button>
    </div>
    <p class="text-[11px] text-stone-500">{{ __('ai.env_hint') }}</p>
</div>
