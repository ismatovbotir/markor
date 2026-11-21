<div class="flex gap-8 items-start">
    <nav class="hidden md:block w-56 shrink-0 sticky top-6 text-sm space-y-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-stone-400 mb-2">{{ __('help.title') }}</p>
        @foreach($pages as $key => $title)
            <a href="{{ route('help', $key) }}" class="block px-3 py-2 rounded-lg {{ $key === $page ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-100' }}">{{ $title }}</a>
        @endforeach
    </nav>
    <article class="help-article flex-1 min-w-0 max-w-3xl bg-white border border-stone-200 rounded-xl p-6 sm:p-8">
        @if($html)
            {!! $html !!}
        @else
            <p class="text-stone-400">{{ __('help.not_ready') }}</p>
        @endif
    </article>
</div>
