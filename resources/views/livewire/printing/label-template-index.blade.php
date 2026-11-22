@php use App\Domain\Printing\Models\LabelTemplate; @endphp
<div>
    @php $labelsCategory = \App\Domain\Offers\Models\OfferCategory::where('kind', 'templates')->first(); @endphp
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.templates')" :hint="__('templates.admin_hint')">
        <x-slot:actions>
            @if($labelsCategory)
                {{-- short label; a missing shop link is an amber dot with a tooltip instead of extra text --}}
                <x-admin.button icon="cog-6-tooth" :href="route('admin.offers', [$labelsCategory, 'tab' => 'settings'])"
                                :title="$labelsCategory->market_url ? null : __('offers.market_missing')">
                    {{ __('offers.category_settings') }}
                    @unless($labelsCategory->market_url)
                        <span class="inline-block align-middle ml-1 w-2 h-2 rounded-full bg-amber-500" aria-hidden="true"></span><span class="sr-only">— {{ __('offers.market_missing') }}</span>
                    @endunless
                </x-admin.button>
            @endif
            @can('create', LabelTemplate::class)
                <x-admin.button variant="primary" icon="plus" wire:click="openCopy">{{ __('templates.new_from_default') }}</x-admin.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- names are in one language for now, so the languages column stays empty --}}
    <x-admin.list wide :head="[__('common.name'), __('templates.size'), '', __('admin.list.updated'), __('admin.list.actions')]">
        @forelse($templates as $t)
            <x-admin.list-row wire:key="tpl-{{ $t->id }}">
                {{ $t->name }}
                <x-slot:meta>
                    @if($t->is_default)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 px-1.5 py-0.5 text-[11px] font-medium" title="{{ __('templates.default_hint') }}">@svg('heroicon-o-star', 'w-3 h-3') {{ __('templates.default') }}</span>
                    @endif
                    @if(($t->columns ?: 1) > 1)<span>{{ $t->columns }}{{ __('templates.per_row_short') }}</span>@endif
                </x-slot:meta>
                <x-slot:status><span class="text-sm text-stone-700 tabular-nums whitespace-nowrap">{{ $t->width_mm }}×{{ $t->height_mm }} mm</span></x-slot:status>
                <x-slot:updated><time datetime="{{ $t->updated_at?->toIso8601String() }}" title="{{ $t->updated_at?->format('d.m.Y H:i') }}">{{ $t->updated_at?->isToday() ? $t->updated_at->format('H:i') : $t->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    <a href="{{ route('label-templates.preview', $t) }}" target="_blank" rel="noopener" aria-label="{{ __('templates.preview_pdf') }}: {{ $t->name }}" title="{{ __('templates.preview_pdf') }}"
                       class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-eye', 'w-4 h-4')</a>
                    @can('update', $t)
                        <button type="button" wire:click="openEdit({{ $t->id }})" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</button>
                    @endcan
                    @canany(['create', 'delete'], $t)
                        <x-admin.row-menu>
                            @can('create', LabelTemplate::class)
                                <x-admin.menu-item wire:click="openCopy({{ $t->id }})">{{ __('templates.copy') }}</x-admin.menu-item>
                            @endcan
                            @can('delete', $t)
                                <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('templates.delete_title')) }}, name: {{ Js::from($t->name) }}, text: {{ Js::from(__('templates.delete_text')) }}, run: () => $wire.delete({{ $t->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                            @endcan
                        </x-admin.row-menu>
                    @endcanany
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            <x-admin.empty-state icon="document-duplicate" :title="__('templates.empty_admin')" :text="__('templates.empty_admin_text')" />
        @endforelse
        @if($templates->total())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $templates->count(), 'total' => $templates->total()]) }}</x-slot:footer>
        @endif
    </x-admin.list>
    @if($templates->hasPages())<div class="mt-4">{{ $templates->links() }}</div>@endif

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
