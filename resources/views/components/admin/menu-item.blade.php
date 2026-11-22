{{-- One action inside x-admin.row-menu. --}}
@props(['danger' => false])
<button type="button" role="menuitem" {{ $attributes->class(['admin-focus w-full text-left px-3 py-2', 'text-rose-700 hover:bg-rose-50' => $danger, 'text-stone-800 hover:bg-stone-100' => !$danger]) }}>{{ $slot }}</button>
