{{-- Settings of generated values (date/time, serial number) for the selected element. --}}
<template x-if="selected.source === '@date'">
    <div class="space-y-2 border border-stone-200 rounded-lg p-2.5 bg-stone-50">
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.date_format') }}</label>
            <input x-model="selected.fmt" type="text" list="markor-date-formats" maxlength="40" class="{{ $input }} font-mono">
            <datalist id="markor-date-formats">
                <option value="DD.MM.YYYY"></option>
                <option value="DD.MM.YYYY HH:mm"></option>
                <option value="DD.MM.YY"></option>
                <option value="YYYY-MM-DD"></option>
                <option value="DD/MM/YYYY"></option>
                <option value="MM.YYYY"></option>
                <option value="HH:mm"></option>
                <option value="YYYYMMDD"></option>
                <option value="YYYY-MM-DD HH:mm:ss"></option>
            </datalist>
            <p class="text-[11px] text-stone-500 mt-1 font-mono">DD MM YYYY YY HH mm ss</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.date_offset') }}</label>
            <input x-model.number="selected.offset_days" type="number" min="-3650" max="3650" class="{{ $input }}">
        </div>
        <p class="text-[11px] text-stone-500">{{ __('studio.date_hint') }}</p>
    </div>
</template>
<template x-if="selected.source === '@serial'">
    <div class="space-y-2 border border-stone-200 rounded-lg p-2.5 bg-stone-50">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.serial_prefix') }}</label>
                <input x-model="selected.prefix" type="text" maxlength="30" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.serial_suffix') }}</label>
                <input x-model="selected.suffix" type="text" maxlength="30" class="{{ $input }}">
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.serial_start') }}</label>
                <input x-model.number="selected.start" type="number" min="0" max="999999999" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.serial_step') }}</label>
                <input x-model.number="selected.step" type="number" min="-1000" max="1000" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('studio.serial_pad') }}</label>
                <input x-model.number="selected.pad" type="number" min="0" max="12" class="{{ $input }}">
            </div>
        </div>
        <p class="text-[11px] text-stone-500">{{ __('studio.serial_hint') }} <span class="font-mono text-stone-700" x-text="[0, 1, 2].map((i) => serialValue(selected, i)).join(', ') + ' …'"></span></p>
    </div>
</template>
