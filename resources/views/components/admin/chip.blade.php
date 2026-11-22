{{-- A linked record shown as a chip with a remove button. remove: Livewire call. --}}
@props(['remove' => null])
<li {{ $attributes->class('inline-flex items-center gap-1 pl-3 pr-1 py-1 rounded-full bg-stone-100 text-sm text-stone-800 max-w-full') }}>
    <span class="truncate">{{ $slot }}</span>
    @if($remove)
        <button type="button" wire:click="{{ $remove }}" aria-label="{{ __('banners.remove_image') }}: {{ trim(strip_tags($slot)) }}"
                class="admin-focus w-7 h-7 shrink-0 rounded-full text-stone-500 hover:text-rose-700 hover:bg-white flex items-center justify-center">@svg('heroicon-o-x-mark', 'w-3.5 h-3.5')</button>
    @endif
</li>
