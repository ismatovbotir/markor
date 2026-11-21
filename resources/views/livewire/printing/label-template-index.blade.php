<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs text-stone-500">{{ __('nav.offers') }}</p>
            <h1 class="text-xl font-semibold">{{ __('offers.labels_title') }}</h1>
            @if($labelsCategory = \App\Domain\Offers\Models\OfferCategory::where('kind', 'templates')->first())
                <a href="{{ route('admin.offers', $labelsCategory) }}" class="text-xs text-amber-700 hover:underline">{{ __('offers.category_settings') }}{{ $labelsCategory->market_url ? '' : ' — ' . __('offers.market_missing') }}</a>
            @endif
        </div>
        @can('create', \App\Domain\Printing\Models\LabelTemplate::class)
            <button wire:click="openCopy" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('templates.new_from_default') }}
            </button>
        @endcan
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('templates.size') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($templates as $t)
                    <tr class="hover:bg-stone-50" wire:key="tpl-{{ $t->id }}">
                        <td class="px-4 py-3 font-medium">
                            {{ $t->name }}
                            @if($t->is_default)
                                <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-normal bg-amber-100 text-amber-700" title="{{ __('templates.default_hint') }}">
                                    @svg('heroicon-o-star', 'w-3 h-3') {{ __('templates.default') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-500">{{ $t->width_mm }}×{{ $t->height_mm }} mm</td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <a href="{{ route('label-templates.preview', $t) }}" target="_blank" class="text-stone-500 hover:text-amber-700 text-xs">{{ __('templates.preview') }}</a>
                            @can('create', \App\Domain\Printing\Models\LabelTemplate::class)
                                <button wire:click="openCopy({{ $t->id }})" class="text-stone-500 hover:text-amber-700 text-xs">{{ __('templates.copy') }}</button>
                            @endcan
                            @can('update', $t)
                                <button wire:click="openEdit({{ $t->id }})" class="text-stone-500 hover:text-amber-700 text-xs">{{ __('common.edit') }}</button>
                            @endcan
                            @can('delete', $t)
                                <button wire:click="delete({{ $t->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-stone-500 hover:text-rose-600 text-xs">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>

    @if($showForm)
        @php
            $input = 'border border-stone-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white';
            $chip = 'px-2.5 py-1 border border-stone-300 rounded-md hover:bg-stone-100 bg-white';
        @endphp
        <div class="fixed inset-0 bg-stone-50 z-40 overflow-y-auto" wire:key="editor-{{ $editorKey }}">
            <div class="max-w-6xl mx-auto px-4 py-6" wire:ignore
                 x-data="labelEditor(@js($editor), @js($sampleDm), @js($sample))"
                 x-on:pointermove.window="move($event)" x-on:pointerup.window="stop()">

                <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
                    <div>
                        <p class="text-xs text-stone-500">{{ __('nav.template_designer') }}</p>
                        <h1 class="text-xl font-semibold">{{ $editingId ? __('common.edit') : __('templates.new') }}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" x-on:click="$wire.set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="button" x-on:click="save()" :disabled="saving" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 transition disabled:opacity-50">{{ __('common.save') }}</button>
                    </div>
                </div>

                {{-- 1. Template --}}
                <section class="bg-white border border-stone-200 rounded-xl p-5 mb-4">
                    <h2 class="font-semibold mb-3"><span class="text-amber-700">1.</span> {{ __('templates.step_template') }}</h2>
                    <div class="flex items-end gap-3 flex-wrap text-sm text-stone-600">
                        <label class="flex-1 min-w-60">
                            <span class="block text-xs font-medium mb-1">{{ __('common.name') }}</span>
                            <input x-model="name" type="text" class="{{ $input }} w-full">
                        </label>
                        <label><span class="block text-xs font-medium mb-1">{{ __('templates.width') }}</span><input x-model.number="width" x-on:change="clampAll()" type="number" min="10" max="300" class="{{ $input }} w-24"></label>
                        <label><span class="block text-xs font-medium mb-1">{{ __('templates.height') }}</span><input x-model.number="height" x-on:change="clampAll()" type="number" min="10" max="300" class="{{ $input }} w-24"></label>
                        <label><span class="block text-xs font-medium mb-1">{{ __('templates.columns') }}</span><input x-model.number="columns" type="number" min="1" max="10" class="{{ $input }} w-20"></label>
                        <label><span class="block text-xs font-medium mb-1">{{ __('templates.gap') }}</span><input x-model.number="gap" type="number" min="0" max="30" step="0.5" class="{{ $input }} w-20"></label>
                        <label title="{{ __('templates.labels_per_pdf_hint') }}"><span class="block text-xs font-medium mb-1">{{ __('templates.labels_per_pdf') }}</span><input x-model.number="labelsPerPdf" type="number" min="100" max="10000" step="100" class="{{ $input }} w-24"></label>
                        <button type="button" x-on:click="rotateLabel()" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border border-stone-300 rounded-lg text-xs hover:bg-stone-100 whitespace-nowrap">
                            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5') {{ __('templates.rotate_label') }}
                        </button>
                    </div>
                </section>

                {{-- 2. Design --}}
                <section class="bg-white border border-stone-200 rounded-xl p-5 mb-4">
                    <h2 class="font-semibold mb-3"><span class="text-amber-700">2.</span> {{ __('studio.step_design') }}</h2>
                    <div class="flex items-center gap-2 mb-2 flex-wrap text-xs">
                        <span class="text-stone-500">{{ __('templates.add') }}:</span>
                        <button type="button" x-on:click="add('datamatrix')" class="{{ $chip }} border-amber-400 text-amber-800 font-medium">{{ __('templates.marking_dm') }}</button>
                        <button type="button" x-on:click="add('text', @js(__('templates.text')))" class="{{ $chip }}">{{ __('templates.text') }}</button>
                        <button type="button" x-on:click="add('barcode', '', 'qr', 'code')" class="{{ $chip }}">QR</button>
                        <button type="button" x-on:click="add('barcode', '', 'code128', 'gtin')" class="{{ $chip }}">Code 128</button>
                        <button type="button" x-on:click="add('barcode', '', 'ean13', 'gtin')" class="{{ $chip }}">EAN-13</button>
                        <span class="w-px h-4 bg-stone-300 mx-1"></span>
                        <button type="button" x-on:click="add('text', '', 'qr', '@date')" class="{{ $chip }}">{{ __('studio.source_date') }}</button>
                        <button type="button" x-on:click="add('text', '', 'qr', '@serial')" class="{{ $chip }}">{{ __('studio.source_serial') }}</button>
                    </div>
                    <div class="flex items-center gap-2 mb-3 flex-wrap text-xs">
                        <span class="text-stone-500">{{ __('templates.fields') }}:</span>
                        <template x-for="f in fields" :key="f.value">
                            <button type="button" x-on:click="add('text', '', 'qr', f.value)" class="{{ $chip }} border-amber-300 text-amber-800" x-text="f.label"></button>
                        </template>
                    </div>

                    @include('partials.label-editor-body')
                </section>

                <p class="text-xs text-stone-500">{{ __('templates.editor_footer') }}</p>
            </div>
        </div>
    @endif
</div>
