@props(['active' => false])
<a {{ $attributes }}
   class="flex items-center px-3 py-2 rounded-md transition-colors
          {{ $active
            ? 'bg-amber-100 text-amber-800 font-medium'
            : 'text-stone-600 hover:bg-stone-200 hover:text-stone-800' }}">
    {{ $slot }}
</a>
