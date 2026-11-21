<span class="inline-flex items-center gap-1 text-xs">
    @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
        <a href="{{ route('locale', $code) }}" title="{{ $label }}"
           class="px-1.5 py-0.5 rounded {{ app()->getLocale() === $code ? 'bg-amber-100 text-amber-800 font-semibold' : 'text-stone-500 hover:text-amber-700' }}">{{ $code === 'zh' ? '中文' : strtoupper($code) }}</a>
    @endforeach
</span>
