{{-- List search bound to a Livewire property. --}}
@props(['model' => 'search', 'placeholder' => null])
<div class="relative w-full sm:w-72">
    <span class="absolute inset-y-0 left-3 flex items-center text-stone-500 pointer-events-none">@svg('heroicon-o-magnifying-glass', 'w-4 h-4')</span>
    <input type="search" wire:model.live.debounce.300ms="{{ $model }}" placeholder="{{ $placeholder ?? __('common.search') . '…' }}" aria-label="{{ __('common.search') }}" class="admin-input pl-9">
</div>
