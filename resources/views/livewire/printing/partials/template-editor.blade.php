@php
    $input = 'border border-stone-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white';
    $chip = 'px-2.5 py-1 border border-stone-300 rounded-md hover:bg-stone-100 bg-white';
@endphp
{{-- The visual template designer, shared by the super admin (/admin/templates) and clients (/aslbelgi/templates/mine). --}}
    @if($showForm)
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
                        <span class="inline-flex items-center gap-2 flex-wrap pb-1.5">@include('partials.label-watermark-toggle')</span>
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
                        <button type="button" x-on:click="add('logo')" class="{{ $chip }} inline-flex items-center gap-1">@svg('heroicon-o-photo', 'w-3.5 h-3.5') {{ __('templates.logo') }}</button>
                        @include('partials.label-marks-menu', ['chip' => $chip])
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

                @if($clientMode)
                    {{-- 3. May the super admin make it available to everyone? Only with this consent. --}}
                    <section class="bg-white border border-stone-200 rounded-xl p-5 mb-4">
                        <h2 class="font-semibold mb-2"><span class="text-amber-700">3.</span> {{ __('templates.share_title') }}</h2>
                        <p class="text-sm text-stone-600 mb-3">{{ __('templates.share_note') }}</p>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" class="mt-0.5 rounded border-stone-300 text-amber-600" @checked($shareConsent)
                                   x-on:change="$wire.set('shareConsent', $event.target.checked, false)">
                            <span>{{ __('templates.share_consent') }}</span>
                        </label>
                    </section>
                @endif

                <p class="text-xs text-stone-500">{{ __('templates.editor_footer') }}</p>
            </div>
        </div>
    @endif
