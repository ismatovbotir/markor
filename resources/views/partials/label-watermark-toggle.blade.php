{{-- "markor.uz" mark on / off. Must be inside an x-data built on labelEditor(). --}}
<label class="inline-flex items-center gap-2 text-sm text-stone-700 whitespace-nowrap" title="{{ __('templates.watermark_hint') }}">
    <input x-model="watermark" type="checkbox" class="rounded border-stone-300 text-amber-600">
    {{ __('templates.watermark') }}
</label>
<span x-show="watermark && !watermarkFits" x-cloak class="text-[11px] text-amber-700">{{ __('templates.watermark_too_small') }}</span>
