{{-- Picture of a record: preview box, upload / replace, remove. model: Livewire property of the upload (WithFileUploads);
     image: url shown now; note: where the picture comes from ("new — uploaded on save"); remove: Livewire method (omit to hide the button);
     icon: Heroicon shown without a picture; hint: format and size. --}}
@props(['model' => 'photo', 'image' => null, 'note' => null, 'remove' => null, 'icon' => 'heroicon-o-photo', 'hint' => null, 'label' => null])
@php $id = 'up-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $model), '-'); @endphp
<div {{ $attributes->class('flex items-center gap-4 flex-wrap') }}>
    <div class="w-40 h-32 rounded-lg border border-stone-200 bg-stone-50 flex items-center justify-center overflow-hidden shrink-0 relative">
        @if($image)<img src="{{ $image }}" alt="" class="w-full h-full object-contain p-2">@else<span class="text-stone-400">@svg($icon, 'w-8 h-8')</span>@endif
        <div wire:loading.flex wire:target="{{ $model }}" class="absolute inset-0 bg-white/80 items-center justify-center"><span class="w-5 h-5 border-2 border-amber-200 border-t-amber-700 rounded-full animate-spin motion-reduce:animate-none"></span></div>
    </div>
    <div class="space-y-2 min-w-0">
        @if($note)<p class="text-sm text-stone-600">{{ $note }}</p>@endif
        <div class="flex items-center gap-2 flex-wrap">
            <label for="{{ $id }}" class="admin-focus inline-flex items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm text-stone-700 hover:bg-stone-50 min-h-10 cursor-pointer has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-amber-700">
                @svg('heroicon-o-arrow-up-tray', 'w-4 h-4 shrink-0') <span>{{ $image ? __('banners.replace_image') : __('banners.upload') }}</span>
                {{-- a file over the limit is stopped here: otherwise PHP drops it and Livewire only says "could not upload" --}}
                <input id="{{ $id }}" wire:model="{{ $model }}" type="file" accept=".jpg,.jpeg,.png,.webp" data-key="{{ $model }}" data-label="{{ $label ?? __('banners.sec_image') }}" class="sr-only"
                       x-data="{ maxKb: {{ (int) config('uploads.image.max_kb') }}, msg: @js(__('upload.size_error', ['mb' => round(config('uploads.image.max_kb') / 1024, 1)])) }"
                       x-on:change.capture="const f = $el.files[0]; $el.closest('div.space-y-2').querySelector('[data-size-error]').style.display = 'none';
                           if (f && f.size > maxKb * 1024) { $event.stopImmediatePropagation(); $el.value = ''; const e = $el.closest('div.space-y-2').querySelector('[data-size-error]'); e.querySelector('span:last-child').textContent = msg; e.style.display = 'flex'; }">
            </label>
            @if($remove && $image)<x-admin.button variant="ghost" wire:click="{{ $remove }}">{{ __('banners.remove_image') }}</x-admin.button>@endif
        </div>
        @error($model)<p role="alert" class="text-xs text-rose-700 flex items-start gap-1"><span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span>{{ $message }}</span></p>@enderror
        <p data-size-error style="display:none" role="alert" class="text-xs text-rose-700 flex items-start gap-1"><span class="shrink-0 mt-px">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')</span><span></span></p>
        @if($hint)<p class="text-xs text-stone-500 max-w-md">{{ $hint }}</p>@endif
    </div>
</div>
