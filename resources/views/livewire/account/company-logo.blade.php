<div class="bg-white border border-stone-200 rounded-xl p-5 space-y-3 mt-6">
    <div>
        <h2 class="font-semibold text-sm flex items-center gap-2">@svg('heroicon-o-photo', 'w-4 h-4 text-amber-700') {{ __('company_logo.title') }}</h2>
        <p class="text-xs text-stone-500 mt-1">{{ __('company_logo.hint') }}</p>
    </div>

    @if($original)
        <div class="flex items-start gap-4 flex-wrap">
            <figure class="text-center">
                <div class="w-36 h-24 border border-stone-200 rounded-lg bg-white flex items-center justify-center p-2">
                    <img src="{{ $original }}" alt="{{ __('company_logo.original') }}" class="max-w-full max-h-full object-contain">
                </div>
                <figcaption class="text-[11px] text-stone-500 mt-1">{{ __('company_logo.original') }}</figcaption>
            </figure>
            @if($print)
                <figure class="text-center">
                    <div class="w-36 h-24 border border-stone-200 rounded-lg bg-white flex items-center justify-center p-2">
                        <img src="{{ $print }}" alt="{{ __('company_logo.print_version') }}" class="max-w-full max-h-full object-contain" style="image-rendering: pixelated">
                    </div>
                    <figcaption class="text-[11px] text-stone-500 mt-1">{{ __('company_logo.print_version') }}</figcaption>
                </figure>
            @endif
            <button type="button" wire:click="remove" wire:confirm="{{ __('company_logo.remove_confirm') }}" class="text-xs text-stone-500 hover:text-rose-600 self-center">{{ __('common.delete') }}</button>
        </div>
        @if($printIsSvg)
            <p class="text-[11px] text-amber-700">{{ __('company_logo.svg_as_is') }}</p>
        @endif
    @endif

    <div x-data="{ fileError: '', sending: false, rule: @js($upload) }">
        {{-- checked here first (size, type); only a good file is sent --}}
        <label class="inline-flex items-center gap-2 px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 cursor-pointer" :class="sending && 'opacity-60 pointer-events-none'">
            @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
            <span x-show="!sending">{{ $original ? __('company_logo.replace') : __('company_logo.upload') }}</span>
            <span x-show="sending" x-cloak>{{ __('common.loading') }}</span>
            <input type="file" class="sr-only" accept="{{ collect($upload['extensions'])->map(fn ($e) => '.' . $e)->implode(',') }}"
                   x-on:change="const f = $event.target.files[0]; fileError = window.checkUpload(f, rule);
                       if (fileError || !f) { $event.target.value = ''; return; }
                       sending = true;
                       $wire.upload('logo', f, () => { sending = false; $event.target.value = '' }, () => { sending = false; fileError = rule.typeError })">
        </label>
        <p class="text-[11px] text-stone-400 mt-1">{{ __('company_logo.formats') }}</p>
        <p x-show="fileError" x-cloak x-text="fileError" class="text-rose-600 text-xs mt-1"></p>
        @error('logo')<p class="text-rose-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>
