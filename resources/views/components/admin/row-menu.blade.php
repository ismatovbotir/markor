{{-- "⋮" menu of a list row; put x-admin.menu-item inside. --}}
<div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false">
    <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('admin.list.more') }}"
            class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-ellipsis-vertical', 'w-4 h-4')</button>
    <div x-show="open" x-cloak x-on:click.outside="open = false" x-on:click="open = false" role="menu"
         class="absolute left-0 md:left-auto md:right-0 top-full z-10 mt-1 w-52 bg-white border border-stone-200 rounded-lg shadow-lg py-1 text-sm">
        {{ $slot }}
    </div>
</div>
