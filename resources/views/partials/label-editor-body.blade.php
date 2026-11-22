{{-- Canvas + properties panel of the label editor. Must be inside an x-data built on labelEditor(). --}}
@php $input = 'w-full border border-stone-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<p x-show="overlapping.size" x-cloak class="mb-3 flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 shrink-0') {{ __('templates.overlap_warning') }}
</p>
<p x-show="watermarkOverlap" x-cloak class="mb-3 flex items-center gap-2 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 shrink-0') {{ __('templates.watermark_overlap') }}
</p>
<div class="flex gap-6 items-start flex-col lg:flex-row">
    <div class="flex-1 w-full bg-stone-100 rounded-lg p-6 flex items-center justify-center min-h-[460px] overflow-auto">
        <div class="flex items-start shrink-0" :style="`gap:${px(gapMm)}`">
        <div class="relative bg-white shadow outline outline-1 outline-stone-300 select-none touch-none focus:outline-amber-500 shrink-0"
             tabindex="0"
             :style="`width:${px(width)};height:${px(height)};font-family:'DejaVu Sans',Verdana,sans-serif;`"
             x-on:pointerdown.self="selectedId = null"
             x-on:keydown.left.prevent="nudge(-1, 0)" x-on:keydown.right.prevent="nudge(1, 0)"
             x-on:keydown.up.prevent="nudge(0, -1)" x-on:keydown.down.prevent="nudge(0, 1)"
             x-on:keydown.delete.prevent="remove()">
            <template x-for="el in elements" :key="el.id">
                <div class="absolute cursor-move"
                     :class="selectedId === el.id ? 'outline outline-2 outline-amber-500 z-10' : (overlapping.has(el.id) ? 'outline outline-2 outline-rose-500' : 'outline outline-1 outline-dashed outline-stone-300 hover:outline-amber-400')"
                     :style="boxStyle(el)"
                     x-on:pointerdown="start($event, el, 'move')">
                    <template x-if="el.type === 'datamatrix'">
                        <div :style="innerStyle(el)"><img :src="sampleDm" class="w-full h-full pointer-events-none" style="image-rendering: pixelated" draggable="false" alt=""></div>
                    </template>
                    <template x-if="el.type === 'logo' || el.type === 'image' || el.type === 'mark'">
                        <div class="pointer-events-none flex items-center justify-center overflow-hidden" :style="innerStyle(el)">
                            <img x-show="imageSrc(el)" :src="imageSrc(el)" class="w-full h-full object-contain" :style="imageFilter(el)" draggable="false" alt="">
                            <span x-show="!imageSrc(el)" class="text-[10px] text-stone-400 text-center leading-tight">{{ __('studio.image_readd') }}</span>
                        </div>
                    </template>
                    <template x-if="el.type === 'barcode'">
                        <div class="pointer-events-none flex items-center justify-center bg-stone-50 text-[10px] text-stone-400 overflow-hidden" :style="innerStyle(el)">
                            <img x-show="barcodeUrl(el)" :src="barcodeUrl(el)" class="w-full h-full" style="image-rendering: pixelated" draggable="false" alt=""
                                 x-on:error="$el.style.display = 'none'" x-on:load="$el.style.display = ''">
                            <span x-show="!barcodeUrl(el)" x-text="el.symbology"></span>
                        </div>
                    </template>
                    <template x-if="el.type === 'text'">
                        <div class="overflow-hidden whitespace-pre-line break-words text-black pointer-events-none" :style="innerStyle(el) + textStyle(el)" x-text="preview(el)"></div>
                    </template>
                    <span x-show="selectedId === el.id" x-on:pointerdown.stop="start($event, el, 'resize')"
                          class="absolute -right-1.5 -bottom-1.5 w-3 h-3 bg-amber-600 border border-white rounded-sm cursor-nwse-resize"></span>
                </div>
            </template>
            {{-- "markor.uz" mark at its real size and place (keep this corner free) --}}
            <div x-show="watermarkShown" x-cloak class="absolute z-20 pointer-events-none text-black text-right whitespace-nowrap" :style="watermarkStyle()">markor.uz</div>
        </div>

        {{-- The rest of the row: read-only copies of the label --}}
        <template x-for="n in cols - 1" :key="'ghost' + n">
            <div class="relative bg-white shadow outline outline-1 outline-stone-300 shrink-0 overflow-hidden pointer-events-none opacity-70"
                 :style="`width:${px(width)};height:${px(height)};font-family:'DejaVu Sans',Verdana,sans-serif;`">
                <template x-for="el in elements" :key="el.id">
                    <div class="absolute" :style="boxStyle(el)">
                        <template x-if="el.type === 'datamatrix'">
                            <div :style="innerStyle(el)"><img :src="sampleDm" class="w-full h-full" style="image-rendering: pixelated" alt=""></div>
                        </template>
                        <template x-if="(el.type === 'logo' || el.type === 'image' || el.type === 'mark') && imageSrc(el)">
                            <div :style="innerStyle(el)"><img :src="imageSrc(el)" class="w-full h-full object-contain" :style="imageFilter(el)" alt=""></div>
                        </template>
                        <template x-if="el.type === 'barcode'">
                            <div :style="innerStyle(el)"><img x-show="barcodeUrl(el, ghostSample(n), n)" :src="barcodeUrl(el, ghostSample(n), n)" class="w-full h-full" style="image-rendering: pixelated" alt=""></div>
                        </template>
                        <template x-if="el.type === 'text'">
                            <div class="overflow-hidden whitespace-pre-line break-words text-black" :style="innerStyle(el) + textStyle(el)" x-text="preview(el, ghostSample(n), n)"></div>
                        </template>
                    </div>
                </template>
                <div x-show="watermarkShown" class="absolute text-black text-right whitespace-nowrap" :style="watermarkStyle()">markor.uz</div>
            </div>
        </template>
        </div>
    </div>

    <div class="w-full lg:w-64 shrink-0 text-sm">
        <template x-if="!selected">
            <p class="text-stone-500 text-xs leading-relaxed">{{ __('templates.editor_hint') }}</p>
        </template>
        <template x-if="selected">
            <div class="space-y-3">
                <template x-if="selected.type === 'logo'">
                    <p class="text-xs text-stone-500 leading-relaxed">@svg('heroicon-o-photo', 'w-4 h-4 inline -mt-0.5 text-amber-700') {{ __('templates.logo_hint') }}</p>
                </template>
                <template x-if="selected.type === 'image'">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" :checked="selected.bw !== false" x-on:change="selected.bw = $event.target.checked" class="rounded border-stone-300 text-amber-600">
                            <span>{{ __('studio.image_bw') }}</span>
                        </label>
                        <p class="text-[11px] text-stone-500">{{ __('studio.image_bw_hint') }}</p>
                        <button type="button" x-on:click="pickImage(selected)" class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-stone-300 rounded-md text-xs hover:bg-stone-100">
                            @svg('heroicon-o-arrow-up-tray', 'w-3.5 h-3.5') {{ __('studio.image_replace') }}
                        </button>
                    </div>
                </template>
                <template x-if="useSources && selected.type !== 'logo' && selected.type !== 'image' && selected.type !== 'mark'">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.kind') }}</label>
                        <select x-on:change="setKind(selected, $event.target.value)" class="{{ $input }}">
                            <template x-if="allowMarking"><option value="marking" :selected="kindOf(selected) === 'marking'">{{ __('templates.marking_dm') }}</option></template>
                            <option value="text" :selected="kindOf(selected) === 'text'">{{ __('templates.text') }}</option>
                            <option value="qr" :selected="kindOf(selected) === 'qr'">QR</option>
                            <option value="datamatrix" :selected="kindOf(selected) === 'datamatrix'">DataMatrix</option>
                            <option value="code128" :selected="kindOf(selected) === 'code128'">Code 128</option>
                            <option value="ean13" :selected="kindOf(selected) === 'ean13'">EAN-13</option>
                            <template x-if="$data.marking || selected.symbology === 'asl'"><option value="asl" :selected="kindOf(selected) === 'asl'">{{ __('studio.asl_dm') }}</option></template>
                        </select>
                    </div>
                </template>
                <template x-if="selected.type === 'barcode'">
                    <div class="space-y-3">
                        <template x-if="useSources">
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.source') }}</label>
                                <select x-model="selected.source" x-on:change="sourceChanged(selected)" class="{{ $input }}">
                                    <option value="">{{ __('studio.fixed_value') }}</option>
                                    <option value="@date" :selected="selected.source === '@date'">{{ __('studio.source_date') }}</option>
                                    <option value="@serial" :selected="selected.source === '@serial'">{{ __('studio.source_serial') }}</option>
                                    <template x-for="o in sourceOptions" :key="o.value"><option :value="o.value" x-text="o.label" :selected="selected.source === o.value"></option></template>
                                </select>
                            </div>
                        </template>
                        <div x-show="['ean13', 'code128'].includes(selected.symbology)">
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.barcode_look') }}</label>
                            <div class="grid grid-cols-2 gap-1 text-xs">
                                <button type="button" x-on:click="selected.hrt = true" class="px-2 py-1.5 rounded-lg border" :class="selected.hrt ? 'border-amber-500 bg-amber-50 text-amber-900 font-medium' : 'border-stone-300 text-stone-600 hover:bg-stone-50'">{{ __('studio.barcode_with_digits') }}</button>
                                <button type="button" x-on:click="selected.hrt = false" class="px-2 py-1.5 rounded-lg border" :class="!selected.hrt ? 'border-amber-500 bg-amber-50 text-amber-900 font-medium' : 'border-stone-300 text-stone-600 hover:bg-stone-50'">{{ __('studio.barcode_bars_only') }}</button>
                            </div>
                        </div>
                        @include('partials.label-generated-settings')
                        <div x-show="!selected.source">
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.barcode_value') }}</label>
                            <textarea x-model.debounce.400ms="selected.text" maxlength="2000" rows="2" class="{{ $input }}"></textarea>
                        </div>
                    </div>
                </template>
                <template x-if="selected.type === 'text'">
                    <div class="space-y-3">
                        <template x-if="useSources">
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.source') }}</label>
                                <select x-model="selected.source" x-on:change="sourceChanged(selected)" class="{{ $input }}">
                                    <option value="">{{ __('studio.source_static') }}</option>
                                    <option value="@date" :selected="selected.source === '@date'">{{ __('studio.source_date') }}</option>
                                    <option value="@serial" :selected="selected.source === '@serial'">{{ __('studio.source_serial') }}</option>
                                    <template x-for="o in sourceOptions" :key="o.value"><option :value="o.value" x-text="o.label" :selected="selected.source === o.value"></option></template>
                                </select>
                            </div>
                        </template>
                        @include('partials.label-generated-settings')
                        <label x-show="selected.source && hasHeader && !isGenerated(selected)" class="flex items-center gap-2">
                            <input x-model="selected.caption" type="checkbox" class="rounded border-stone-300 text-amber-600">
                            <span>{{ __('studio.show_caption') }} <span class="text-stone-400" x-text="`(${sourceLabel(selected.source)}: …)`"></span></span>
                        </label>
                        <div x-show="!selected.source">
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('templates.text') }}</label>
                            <textarea x-model="selected.text" maxlength="2000" rows="2" class="{{ $input }}"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('templates.font_size') }}</label>
                                <input x-model.number="selected.font_size" type="number" min="3" max="72" step="0.5" class="{{ $input }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('templates.align') }}</label>
                                <select x-model="selected.align" class="{{ $input }}">
                                    <option value="left">{{ __('templates.align_left') }}</option>
                                    <option value="center">{{ __('templates.align_center') }}</option>
                                    <option value="right">{{ __('templates.align_right') }}</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2">
                            <input x-model="selected.bold" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('templates.bold') }}
                        </label>
                    </div>
                </template>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['x' => 'X (mm)', 'y' => 'Y (mm)', 'w' => __('templates.width'), 'h' => __('templates.height')] as $prop => $label)
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ $label }}</label>
                            <input x-model.number="selected.{{ $prop }}" x-on:change="clamp(selected)" type="number" min="0" step="0.5" class="{{ $input }}">
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between">
                    <button type="button" x-on:click="rotate(selected)" class="inline-flex items-center gap-1.5 px-2.5 py-1 border border-stone-300 rounded-md text-xs hover:bg-stone-100">
                        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5') {{ __('templates.rotate') }}
                    </button>
                    <span class="text-xs text-stone-500" x-text="`${selected.rotation || 0}°`"></span>
                </div>
                <p x-show="overlapping.has(selected.id)" class="text-xs text-rose-600">{{ __('templates.overlap_selected') }}</p>
                <button type="button" x-on:click="remove()" class="text-xs text-rose-600 hover:underline">{{ __('templates.remove_element') }}</button>
            </div>
        </template>
    </div>
</div>
