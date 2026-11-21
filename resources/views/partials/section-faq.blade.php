{{-- FAQ block: home page (h2) and /faq (h1). Needs $faq (collection of [q, a]). --}}
@php $hTag ??= 'h2'; @endphp
@if($faq->isNotEmpty())
    <section id="faq" class="bg-white border-y border-stone-200 scroll-mt-16">
        <div class="max-w-3xl mx-auto px-4 py-20">
            <{{ $hTag }} class="font-display font-bold text-2xl sm:text-3xl text-stone-900 tracking-tight">{{ __('home.faq.title') }}</{{ $hTag }}>
            <div class="mt-8 divide-y divide-stone-200 border-y border-stone-200">
                @foreach($faq as $item)
                    <details class="group py-5">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-semibold text-stone-900">
                            <h3>{{ $item['q'] }}</h3>
                            <span class="shrink-0 w-7 h-7 rounded-full border border-stone-300 flex items-center justify-center text-stone-500 transition group-open:rotate-45 group-open:bg-amber-700 group-open:border-amber-700 group-open:text-white">+</span>
                        </summary>
                        <p class="text-stone-600 mt-3 leading-relaxed">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
