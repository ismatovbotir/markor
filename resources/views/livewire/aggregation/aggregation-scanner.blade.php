<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('aggregation.scanner') }}</h1>
    </div>

    <div class="grid grid-cols-3 gap-6">
        {{-- List of open aggregations --}}
        <div class="col-span-1 bg-white border border-stone-200 rounded-xl p-4 max-h-96 overflow-y-auto">
            <h3 class="font-semibold text-sm mb-3 text-stone-600">{{ __('aggregation.open_list') }}</h3>
            <div class="space-y-2">
                @forelse($openAggregations as $agg)
                    <button
                        wire:click="selectAggregation({{ $agg->id }})"
                        class="w-full text-left p-3 rounded-lg border transition
                            {{ $aggregationId === $agg->id
                                ? 'border-amber-400 bg-amber-50 text-amber-900'
                                : 'border-stone-200 hover:bg-stone-50' }}">
                        <div class="font-mono text-xs">{{ $agg->sscc }}</div>
                        <div class="text-xs text-stone-500 mt-0.5">{{ $agg->actual_quantity }} / {{ $agg->expected_quantity }}</div>
                    </button>
                @empty
                    <p class="text-xs text-stone-400 py-4 text-center">{{ __('aggregation.no_open') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Scanner input --}}
        <div class="col-span-2">
            @if($current)
                <div class="bg-white border border-stone-200 rounded-xl p-6 mb-6">
                    <div class="mb-6">
                        <p class="text-xs text-stone-500 mb-1">{{ __('aggregation.scanning_sscc') }}</p>
                        <p class="font-mono text-lg font-semibold text-amber-700">{{ $current->sscc }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-stone-50 rounded-lg">
                        <div>
                            <p class="text-xs text-stone-500">{{ __('aggregation.expected') }}</p>
                            <p class="text-2xl font-bold text-stone-800">{{ $current->expected_quantity }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">{{ __('aggregation.scanned') }}</p>
                            <p class="text-2xl font-bold {{ $current->isFull() ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $current->actual_quantity }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-medium text-stone-600 mb-2">{{ __('aggregation.scan_code') }}</label>
                        <input
                            wire:model="rawScan"
                            type="text"
                            autofocus
                            class="w-full border-2 border-amber-300 rounded-lg px-4 py-3 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent bg-white"
                            placeholder="[Поднесите сканер]">
                    </div>

                    @if($lastMessage)
                        <div class="p-3 rounded-lg mb-4
                            {{ $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : ($messageType === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-700' : 'bg-blue-50 border border-blue-200 text-blue-700') }}
                            text-sm">
                            {{ $lastMessage }}
                        </div>
                    @endif

                    @if($current->isFull())
                        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 text-center">
                            <p class="text-sm font-semibold">✓ {{ __('aggregation.complete') }}</p>
                        </div>
                    @endif
                </div>

                {{-- Scanned codes list --}}
                <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-stone-50 border-b border-stone-200">
                        <h3 class="font-semibold text-sm text-stone-600">{{ __('aggregation.scanned_codes') }} ({{ count($addedCodes) }})</h3>
                    </div>
                    <div class="divide-y divide-stone-100 max-h-80 overflow-y-auto">
                        @forelse($addedCodes as $c)
                            <div class="px-4 py-2 text-xs hover:bg-stone-50 flex justify-between items-center">
                                <div>
                                    <p class="font-mono text-stone-700">{{ $c['code'] }}</p>
                                    <p class="text-stone-400">{{ $c['gtin'] }} / {{ $c['serial'] }}</p>
                                </div>
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                    ✓
                                </span>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center text-stone-400 text-xs">{{ __('common.no_data') }}</div>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="bg-stone-50 border-2 border-dashed border-stone-200 rounded-xl p-8 text-center">
                    <p class="text-stone-400 text-sm">{{ __('aggregation.select_to_scan') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
