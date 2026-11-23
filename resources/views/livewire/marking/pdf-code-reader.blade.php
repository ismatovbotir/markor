<div x-data="pdfCodeReader(@js($groups), @js($systems), @js($fallback))" class="max-w-4xl">
    <div class="flex items-center justify-between mb-2 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold flex items-center gap-2">
            {{ __('pdfreader.title') }}
            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('pdfreader.beta') }}</span>
        </h1>
        <a href="{{ route('import-batches.index') }}" class="text-sm text-stone-600 hover:text-amber-700">← {{ __('nav.import') }}</a>
    </div>
    <p class="text-sm text-stone-500 mb-6">{{ __('pdfreader.intro') }}</p>

    {{-- pick files --}}
    <div x-show="state !== 'reading'" class="bg-white border border-stone-200 rounded-xl p-6 mb-4">
        <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-stone-300 rounded-lg px-4 py-10 cursor-pointer hover:border-amber-500 hover:bg-amber-50/40 transition text-center"
               x-on:dragover.prevent x-on:drop.prevent="pick({ target: { files: $event.dataTransfer.files, set value(v) {} } })">
            @svg('heroicon-o-document-arrow-up', 'w-10 h-10 text-stone-400')
            <span class="font-medium text-stone-700">{{ __('pdfreader.choose') }}</span>
            <span class="text-xs text-stone-500">{{ __('pdfreader.choose_hint') }}</span>
            <input type="file" accept="application/pdf,.pdf" multiple class="sr-only" x-on:change="pick($event)">
        </label>
        <p class="mt-3 text-xs text-stone-500 flex items-start gap-1.5">@svg('heroicon-o-lock-closed', 'w-4 h-4 shrink-0') {{ __('pdfreader.private') }}</p>
    </div>

    {{-- progress --}}
    <div x-show="state === 'reading'" x-cloak class="bg-white border border-stone-200 rounded-xl p-6 mb-4">
        <div class="flex items-center justify-between gap-4 mb-2 text-sm">
            <span class="truncate" x-text="progress.file"></span>
            <span class="tabular-nums text-stone-500 shrink-0" x-text="progress.pages ? progress.page + ' / ' + progress.pages : '…'"></span>
        </div>
        <div class="h-2 bg-stone-100 rounded-full overflow-hidden">
            <div class="h-full bg-amber-600 transition-all" :style="'width:' + (progress.pages ? progress.page / progress.pages * 100 : 0) + '%'"></div>
        </div>
        <div class="mt-3 flex items-center justify-between text-sm">
            <span>{{ __('pdfreader.found') }}: <b class="tabular-nums" x-text="codes.length"></b></span>
            <button type="button" x-on:click="cancelled = true" class="px-3 py-1.5 text-stone-600 hover:text-rose-600">{{ __('common.cancel') }}</button>
        </div>
        <p class="mt-2 text-xs text-stone-500">{{ __('pdfreader.keep_open') }}</p>
    </div>

    {{-- result --}}
    <template x-if="state === 'done'">
        <div class="space-y-4">
            <div x-show="error" class="text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-4 py-3">
                {{ __('pdfreader.error') }}: <span x-text="error"></span>
            </div>

            <div class="bg-white border border-stone-200 rounded-xl p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                    <div><div class="text-xs text-stone-500">{{ __('pdfreader.codes') }}</div><div class="text-2xl font-semibold tabular-nums text-emerald-600" x-text="codes.length"></div></div>
                    <div><div class="text-xs text-stone-500">{{ __('pdfreader.pages') }}</div><div class="text-2xl font-semibold tabular-nums" x-text="pages.length"></div></div>
                    <div><div class="text-xs text-stone-500">{{ __('pdfreader.unreadable') }}</div><div class="text-2xl font-semibold tabular-nums" :class="unreadable ? 'text-rose-600' : ''" x-text="unreadable"></div></div>
                    <div><div class="text-xs text-stone-500">{{ __('pdfreader.time') }}</div><div class="text-2xl font-semibold tabular-nums" x-text="seconds + ' s'"></div></div>
                </div>

                <div x-show="codes.length" class="mb-3 text-sm flex flex-wrap items-center gap-2">
                    <span class="text-stone-500">{{ __('marking_system.field') }}:</span>
                    <span class="font-medium px-2 py-0.5 rounded-full bg-sky-50 text-sky-800" x-text="systemLabel"></span>
                    <span x-show="!systemDetected" class="text-xs text-stone-500">{{ __('pdfreader.system_guess') }}</span>
                </div>
                <div x-show="cancelled" class="mb-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('pdfreader.cancelled') }}</div>
                <div x-show="problemPages.length" class="mb-3 text-sm text-rose-800 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
                    {{ __('pdfreader.unreadable_hint') }}
                    <span x-text="problemPages.map(p => (files.length > 1 ? p.file + ' ' : '') + '{{ __('pdfreader.page') }} ' + p.page + ' (' + p.unreadable + ')').join(', ')"></span>
                </div>
                <div x-show="duplicates" class="mb-3 text-sm text-stone-700 bg-stone-50 border border-stone-200 rounded-lg px-3 py-2">
                    {{ __('pdfreader.duplicates') }}: <b x-text="duplicates"></b>
                </div>
                <div x-show="notGs1" class="mb-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    {{ __('pdfreader.not_gs1') }}: <b x-text="notGs1"></b>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button" x-on:click="download()" :disabled="!codes.length"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 disabled:opacity-40">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4') {{ __('pdfreader.download') }}
                    </button>
                    <button type="button" x-on:click="reset()" class="px-4 py-2 border border-stone-300 rounded-lg text-sm text-stone-700 hover:bg-stone-100">{{ __('pdfreader.again') }}</button>
                </div>
                <p class="mt-3 text-xs text-stone-500">{{ __('pdfreader.next_step') }}</p>
            </div>

            <div x-show="byGtin.length" class="bg-white border border-stone-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-stone-500 text-xs"><tr>
                        <th class="text-left px-4 py-2 font-medium">GTIN</th>
                        <th class="text-left px-4 py-2 font-medium">{{ __('pdfreader.category') }}</th>
                        <th class="text-right px-4 py-2 font-medium">{{ __('pdfreader.codes') }}</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="row in byGtin" :key="row.gtin + row.group">
                            <tr class="border-t border-stone-100">
                                <td class="px-4 py-2 font-mono" x-text="row.gtin"></td>
                                <td class="px-4 py-2" x-text="row.group"></td>
                                <td class="px-4 py-2 text-right tabular-nums" x-text="row.count"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="rejected.length" class="bg-white border border-stone-200 rounded-xl p-4">
                <h2 class="text-sm font-semibold mb-2">{{ __('pdfreader.rejected') }} (<span x-text="rejected.length"></span>)</h2>
                <p class="text-xs text-stone-500 mb-2">{{ __('pdfreader.rejected_hint') }}</p>
                <ul class="text-xs font-mono space-y-1 max-h-48 overflow-y-auto">
                    <template x-for="(r, i) in rejected.slice(0, 50)" :key="i">
                        <li class="break-all"><span class="text-stone-400" x-text="'{{ __('pdfreader.page') }} ' + r.page + ': '"></span><span x-text="r.text"></span></li>
                    </template>
                </ul>
            </div>
        </div>
    </template>
</div>
