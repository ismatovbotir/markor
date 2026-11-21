<div x-data="labelDownload({{ $templates->firstWhere('is_default', true)?->id ?? $templates->first()?->id ?? 'null' }}, {{ $offers->count() }})">
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('nav.import') }}</h1>
        <span class="flex items-center gap-2">
            <button type="button" wire:click="$refresh" class="inline-flex items-center gap-1.5 px-3 py-2 border border-stone-300 rounded-lg text-sm text-stone-700 hover:bg-stone-100" title="{{ __('import.refresh_hint') }}">
                <span wire:loading.remove wire:target="$refresh">@svg('heroicon-o-arrow-path', 'w-4 h-4')</span>
                <span wire:loading wire:target="$refresh">@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                {{ __('import.refresh') }}
            </button>
        @can('create', \App\Domain\Marking\Models\ImportBatch::class)
            <button wire:click="$set('showForm', true)" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('import.upload_csv') }}
            </button>
        @endcan
        </span>
    </div>

    @php $money = fn ($n) => \App\Domain\Billing\Billing::money($n); $pct = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'); @endphp
    <div class="bg-white border border-stone-200 rounded-xl px-4 py-3 mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
        <span class="text-stone-500">{{ __('billing.this_month') }}:</span>
        <span><span class="font-semibold tabular-nums">{{ number_format($month->codes, 0, '.', ' ') }}</span> {{ __('billing.codes') }} × {{ $money($price) }} = <span class="tabular-nums">{{ $money($month->gross) }}</span></span>
        @if($discount > 0)
            <span class="text-emerald-700">{{ __('billing.discount') }} {{ $pct($discount) }}%@if($discountUntil) <span class="text-stone-500">({{ __('billing.until', ['date' => $discountUntil->format('d.m.Y')]) }})</span>@endif</span>
        @endif
        <span class="font-semibold">{{ __('billing.to_pay') }}: {{ $money($month->amount) }}</span>
    </div>

    @if($lockedCount)
        <div class="mb-4 flex items-center justify-between gap-3 flex-wrap text-sm text-amber-900 bg-amber-50 border border-amber-300 rounded-lg px-4 py-3">
            <span class="flex items-center gap-2">@svg('heroicon-o-lock-closed', 'w-5 h-5 shrink-0') {{ __('balance.locked_banner', ['count' => $lockedCount, 'need' => \App\Domain\Billing\Billing::money($shortfall)]) }}</span>
            <a href="{{ route('balance') }}" class="px-3 py-1.5 bg-amber-700 text-white rounded-lg text-xs font-medium hover:bg-amber-800">{{ __('balance.top_up') }}</a>
        </div>
    @endif
    @php $unnamed = collect($items)->flatten()->unique('id')->filter(fn ($p) => $p->needsName()); @endphp
    @if($unnamed->isNotEmpty())
        <div class="mb-4 flex items-start gap-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 shrink-0')
            <span>{{ __('import.unnamed_banner', ['count' => $unnamed->count()]) }}</span>
        </div>
    @endif
    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden" @if($polling) wire:poll.2s @endif>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('import.batch') }}</th>
                    @can('admin')<th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('import.owner') }}</th>@endcan
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.products') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('import.quantity') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('billing.to_pay') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @php $colors = ['uploaded'=>'stone','pending'=>'stone','processing'=>'amber','checking'=>'sky','completed'=>'emerald','failed'=>'rose'] @endphp
                @forelse($batches as $batch)
                    <tr class="hover:bg-stone-50" wire:key="batch-{{ $batch->id }}">
                        <td class="px-4 py-3 font-medium">{{ $batch->original_filename }}</td>
                        @can('admin')<td class="px-4 py-3 text-stone-500" title="{{ $batch->uploader?->email }}">{{ $batch->uploader?->name ?? '—' }}</td>@endcan
                        <td class="px-4 py-3">
                            @php $batchItems = $items[$batch->id] ?? collect(); @endphp
                            @forelse($batchItems->take(2) as $item)
                                <div class="leading-tight {{ $loop->first ? '' : 'mt-1.5' }}">
                                    @if($item->name)
                                        <span class="font-medium text-stone-800">{{ $item->name }}</span>
                                        @if($item->lookupStatus() === 'not_found' && auth()->user()->can('name', $item))
                                            <button type="button" wire:click="openNaming({{ $item->id }}, {{ $batch->id }})" class="text-xs text-stone-400 hover:text-amber-700" title="{{ __('common.edit') }}">✎</button>
                                        @endif
                                    @elseif($item->needsName())
                                        <span class="text-amber-700 text-xs">{{ __('import.item_not_found') }}</span>
                                        @can('name', $item)
                                            <button type="button" wire:click="openNaming({{ $item->id }}, {{ $batch->id }})" class="block text-xs font-semibold text-amber-800 underline decoration-dotted hover:text-amber-900">{{ __('import.fill_item_name') }}</button>
                                        @endcan
                                    @elseif($batch->status === 'checking')
                                        <span class="text-stone-400 text-xs">{{ __('import.item_checking') }}</span>
                                    @else
                                        <span class="text-stone-400 text-xs">{{ __('import.item_no_name') }}</span>
                                    @endif
                                    <span class="block font-mono text-[11px] text-stone-500">{{ $item->gtin }}</span>
                                </div>
                            @empty
                                <span class="text-stone-400">—</span>
                            @endforelse
                            @if($batchItems->count() > 2)
                                <span class="text-xs text-stone-500">+{{ $batchItems->count() - 2 }}</span>
                            @endif
                            @php $hiddenUnnamed = $batchItems->slice(2)->filter(fn ($p) => $p->needsName()); @endphp
                            @if($hiddenUnnamed->isNotEmpty() && auth()->user()->can('name', $hiddenUnnamed->first()))
                                <button type="button" wire:click="openNaming({{ $hiddenUnnamed->first()->id }}, {{ $batch->id }})" class="block text-xs font-semibold text-amber-800 underline decoration-dotted hover:text-amber-900 mt-1">
                                    {{ __('import.more_unnamed', ['count' => $hiddenUnnamed->count()]) }}
                                </button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($batch->marking_codes_count, 0, '.', ' ') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">
                            @if($batch->status === 'completed')
                                {{ $money($batch->amount) }}
                                @if($batch->discount_percent > 0)<span class="block text-[11px] text-emerald-700">−{{ $pct($batch->discount_percent) }}%</span>@endif
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $colors[$batch->status] ?? 'stone' }}-100 text-{{ $colors[$batch->status] ?? 'stone' }}-700">
                                @if(in_array($batch->status, ['uploaded', 'pending', 'processing', 'checking']))
                                    <svg class="animate-spin h-3 w-3 mr-1 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                @endif
                                {{ __('import.status.'.$batch->status) }}
                            </span>
                            @php $unnamedCount = $batchItems->filter(fn ($p) => $p->needsName())->count(); @endphp
                            @if($unnamedCount)
                                <span class="inline-flex items-center gap-0.5 ml-1 text-amber-700 text-xs align-middle" title="{{ __('import.unnamed_title', ['count' => $unnamedCount]) }}">@svg('heroicon-s-exclamation-triangle', 'w-4 h-4') {{ $unnamedCount }}</span>
                            @endif
                            @if($batch->failed_rows > 0 || $batch->status === 'failed')
                                <button wire:click="toggleErrors({{ $batch->id }})" class="ml-1 text-xs text-rose-600 hover:underline">
                                    {{ $batch->failed_rows > 0 ? __('import.skipped', ['count' => $batch->failed_rows]) : __('common.error') }}
                                </button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-500">{{ $batch->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($batch->is_locked)
                                <a href="{{ route('balance') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-900 rounded-lg text-xs font-medium hover:bg-amber-200">
                                    @svg('heroicon-o-lock-closed', 'w-4 h-4') {{ __('balance.pay_to_open') }}
                                </a>
                            @elseif($batch->status === 'completed' && $batch->marking_codes_count > 0)
<button type="button"
                                        x-on:click="open(@js(['url' => route('import-batches.labels', $batch), 'partsUrl' => route('import-batches.parts', $batch), 'name' => pathinfo($batch->original_filename, PATHINFO_FILENAME), 'count' => $batch->marking_codes_count]))"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-amber-700 text-amber-700 rounded-lg text-xs font-medium hover:bg-amber-50 transition">
                                    @svg('heroicon-o-printer', 'w-4 h-4') {{ __('import.labels_pdf') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if($errorsFor === $batch->id && $batch->error_log)
                        <tr wire:key="batch-errors-{{ $batch->id }}">
                            <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="px-4 py-3 bg-rose-50 text-xs text-rose-700">
                                @foreach(array_slice($batch->error_log, 0, 20) as $error)<div>{{ $error }}</div>@endforeach
                                @if(count($batch->error_log) > 20)<div>…</div>@endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-1">{{ __('import.upload_csv') }}</h2>
                <p class="text-xs text-stone-500 mb-4">{{ __('import.hint') }}</p>
                <form x-data="{ busy: false, progress: 0, timer: null }"
                      x-on:submit.prevent="busy = true; progress = 0; const started = Date.now();
                          timer = setInterval(() => progress = Math.min(100, (Date.now() - started) / 50), 200); // 5 s → 100%
                          const id = await $wire.queueUpload(); await promoWait(started);
                          clearInterval(timer); busy = false; if (id) $wire.releaseUpload(id)" class="space-y-4">
                    @php $rule = \App\Support\Uploads::forJs('codes'); @endphp
                    <div x-data="{ fileError: '', sending: false, rule: @js($rule) }">
                        {{-- checked here first (size, type); only a good file is sent to the server --}}
                        <input type="file" accept="{{ collect($rule['extensions'])->map(fn ($e) => '.' . $e)->implode(',') }}"
                               x-on:change="fileError = window.checkUpload($event.target.files[0], rule);
                                   if (fileError) { $event.target.value = ''; $wire.set('csvFile', null); return; }
                                   sending = true;
                                   $wire.upload('csvFile', $event.target.files[0], () => sending = false, () => { sending = false; fileError = rule.typeError })"
                               class="w-full text-sm text-stone-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200">
                        <p class="text-[11px] text-stone-400 mt-1">{{ $rule['hint'] }}</p>
                        <p x-show="fileError" x-cloak x-text="fileError" class="text-rose-500 text-xs mt-1"></p>
                        @error('csvFile') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div x-show="busy" x-cloak>
                        <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden"><div class="h-full bg-amber-600 transition-all duration-200" :style="`width:${progress}%`"></div></div>
                        <p class="text-xs text-stone-500 mt-1">{{ __('import.uploading_hint') }}</p>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="csvFile" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition disabled:opacity-50"
                                :disabled="busy" wire:loading.attr="disabled" wire:target="csvFile">
                            <span x-show="!busy">{{ __('import.upload') }}</span>
                            <span x-show="busy" x-cloak>{{ __('common.loading') }}</span>
                        </button>
                    </div>
                </form>
                @include('partials.modal-promo')
            </div>
        </div>
    @endif

    @if($namingId)
        @php $namingProduct = \App\Domain\Catalog\Models\Product::find($namingId); @endphp
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4">
            <form wire:submit="saveNaming" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('import.fill_item_title') }}</h2>
                    <p class="text-sm text-stone-500 mt-1">GTIN <span class="font-mono">{{ $namingProduct?->gtin }}</span> — {{ __('import.fill_item_hint') }}</p>
                </div>
                @foreach(['name_uz' => 'product_groups.name_uz', 'name_ru' => 'product_groups.name_ru', 'sku' => 'SKU'] as $key => $label)
                    <label class="block text-xs font-medium text-stone-600">{{ __($label) }}@if($key === 'sku') <span class="text-stone-400">({{ __('common.optional') }})</span>@endif
                        <input wire:model="naming.{{ $key }}" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @error("naming.$key") <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                @endforeach
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" wire:click="$set('namingId', null)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">{{ __('common.save') }}</button>
                </div>
                @include('partials.modal-promo')
            </form>
        </div>
    @endif

    {{-- Generate & download labels PDF --}}
    <div x-show="batch" x-cloak class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center" x-on:keydown.escape.window="close()">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6" x-on:click.outside="close()">
            <h2 class="text-lg font-semibold" x-text="batch?.name"></h2>
            <p class="text-sm text-stone-500 mb-4"><span x-text="batch?.count"></span> {{ __('import.labels_unit') }}</p>

            <div x-show="state === 'choose'">
                <p class="text-xs font-medium text-stone-600 mb-2">{{ __('import.choose_design') }}</p>
                <div class="bg-stone-100 rounded-lg p-4 mb-3 flex items-center justify-center h-44">
                    @foreach($templates as $t)
                        <div x-show="templateId == {{ $t->id }}">
                            @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 330, 'maxH' => 145])
                        </div>
                    @endforeach
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($templates as $t)
                        <label class="flex items-center gap-3 border rounded-lg px-3 py-2.5 cursor-pointer transition"
                               :class="templateId == {{ $t->id }} ? 'border-amber-600 bg-amber-50' : 'border-stone-200 hover:bg-stone-50'">
                            <input type="radio" x-model.number="templateId" x-on:change="loadParts()" value="{{ $t->id }}" class="text-amber-600">
                            <span class="flex-1 text-sm font-medium">{{ $t->name }}</span>
                            <span class="text-xs text-stone-500">{{ $t->width_mm }}×{{ $t->height_mm }} mm</span>
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" x-on:click="close()" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                    <button type="button" x-show="parts.length <= 1" x-on:click="generate(1)" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('import.generate_download') }}</button>
                </div>
                <div x-show="parts.length > 1" x-cloak class="mt-4 border-t border-stone-100 pt-4">
                    <p class="text-xs font-medium text-stone-600">{{ __('import.parts_title') }}</p>
                    <p class="text-[11px] text-stone-500 mb-2" x-text="@js(__('import.parts_hint', ['size' => '__SIZE__'])).replace('__SIZE__', parts[0]?.labels ?? '')"></p>
                    <div class="space-y-1.5 max-h-56 overflow-y-auto">
                        <template x-for="p in parts" :key="p.part">
                            <div class="flex items-center justify-between gap-3 border border-stone-200 rounded-lg px-3 py-2 text-sm">
                                <span>
                                    <span class="font-medium" x-text="@js(__('import.part_line', ['part' => '__P__', 'first' => '__F__', 'last' => '__L__'])).replace('__P__', p.part).replace('__F__', p.first.toLocaleString('ru-RU')).replace('__L__', p.last.toLocaleString('ru-RU'))"></span>
                                    <span x-show="p.ready" class="ml-1 text-[11px] text-emerald-700">✓ {{ __('import.part_ready') }}</span>
                                </span>
                                <button type="button" x-on:click="generate(p.part)" class="px-3 py-1 border border-amber-700 text-amber-700 rounded-md text-xs font-medium hover:bg-amber-50">{{ __('import.download_part') }}</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="state === 'working'">
                <p class="text-sm mb-2">{{ __('import.generating') }}</p>
                <div class="h-2 bg-stone-100 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-600 transition-all duration-300" :style="`width:${progress}%`"></div>
                </div>
                <p class="text-xs text-stone-500 mt-2">{{ __('import.estimate') }} <span x-text="remaining"></span> {{ __('import.seconds') }}</p>
                @include('partials.promo-offer')
            </div>

            <div x-show="state === 'done'">
                <p class="text-sm text-emerald-700 flex items-center gap-2">@svg('heroicon-o-check-circle', 'w-5 h-5') {{ __('import.ready') }}</p>
                @include('partials.promo-offer')
                <div class="flex justify-end pt-5">
                    <button type="button" x-on:click="close()" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">OK</button>
                </div>
            </div>

            <div x-show="state === 'error'">
                <p class="text-sm text-rose-600">{{ __('import.generate_failed') }}</p>
                <div class="flex justify-end gap-3 pt-5">
                    <button type="button" x-on:click="close()" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                    <button type="button" x-on:click="state = 'choose'" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('import.retry') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
