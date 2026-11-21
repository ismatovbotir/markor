@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div class="max-w-3xl">
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-xl font-semibold">SEO</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('seo_admin.hint') }}</p>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('robots') }}" target="_blank" class="px-3 py-1.5 border border-stone-300 rounded-lg hover:bg-stone-100">robots.txt ↗</a>
            <a href="{{ route('sitemap') }}" target="_blank" class="px-3 py-1.5 border border-stone-300 rounded-lg hover:bg-stone-100">sitemap.xml ↗</a>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Titles and descriptions per page and language --}}
        <div class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <h2 class="font-semibold text-sm">{{ __('seo_admin.texts') }}</h2>
                <div class="flex rounded-lg border border-stone-200 overflow-hidden text-sm">
                    @foreach($locales as $code => $name)
                        <button type="button" wire:click="$set('locale', '{{ $code }}')" class="px-3 py-1.5 {{ $locale === $code ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">{{ $name }}</button>
                    @endforeach
                </div>
            </div>
            <p class="text-[11px] text-stone-500">{{ __('seo_admin.texts_hint') }}</p>

            @foreach(\App\Support\Seo::PAGES as $page)
                <div class="border border-stone-100 rounded-lg p-4 space-y-3" wire:key="seo-{{ $page }}-{{ $locale }}">
                    <p class="text-sm font-medium">{{ __('seo_admin.page.' . $page) }} <span class="text-stone-400 font-mono text-xs">{{ route($page, [], false) }}</span></p>
                    @foreach(\App\Support\Seo::FIELDS as $f => $max)
                        @php $default = __("seo.$page.$f", [], $locale); @endphp
                        <label class="block text-xs font-medium text-stone-600" x-data="{ v: @entangle("texts.$page.$f.$locale") }">
                            <span class="flex justify-between"><span>{{ __('seo_admin.' . $f) }}</span>
                                <span :class="(v || @js($default)).length > {{ $max }} ? 'text-rose-600' : 'text-stone-400'"><span x-text="(v || @js($default)).length"></span> / {{ $max }}</span></span>
                            @if($f === 'title')
                                <input x-model="v" type="text" maxlength="120" placeholder="{{ $default }}" class="{{ $field }} mt-1">
                            @else
                                <textarea x-model="v" rows="2" maxlength="320" placeholder="{{ $default }}" class="{{ $field }} mt-1"></textarea>
                            @endif
                        </label>
                    @endforeach
                    {{-- how it looks in Google --}}
                    @php $t = ($texts[$page]['title'][$locale] ?? '') ?: __("seo.$page.title", [], $locale); $d = ($texts[$page]['description'][$locale] ?? '') ?: __("seo.$page.description", [], $locale); @endphp
                    <div class="bg-stone-50 rounded-lg p-3">
                        <p class="text-[11px] text-stone-400 mb-1">{{ __('seo_admin.preview') }}</p>
                        <p class="text-xs text-emerald-800 truncate">{{ \App\Http\Controllers\Guest\HomeController::localizedUrl($page, $locale) }}</p>
                        <p class="text-[#1a0dab] text-base leading-snug truncate">{{ \Illuminate\Support\Str::limit($t, 62) }}</p>
                        <p class="text-xs text-stone-600 line-clamp-2">{{ \Illuminate\Support\Str::limit($d, 165) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Search engines --}}
        <div class="bg-white border border-stone-200 rounded-xl p-5 grid sm:grid-cols-2 gap-4">
            <h2 class="font-semibold text-sm sm:col-span-2">{{ __('seo_admin.engines') }}</h2>
            @foreach([
                'google'  => ['Google Search Console', 'abc123…', 'seo_admin.google_hint'],
                'yandex'  => ['Yandex Webmaster', '1a2b3c4d5e6f7a8b', 'seo_admin.yandex_hint'],
                'bing'    => ['Bing Webmaster', 'A1B2C3D4E5F6…', 'seo_admin.bing_hint'],
                'baidu'   => ['Baidu 站长平台 (Baidu Webmaster)', 'codeva-AbCd1234', 'seo_admin.baidu_hint'],
                'ga4'     => ['Google Analytics 4', 'G-XXXXXXXXXX', 'seo_admin.ga4_hint'],
                'metrika' => ['Yandex Metrika', '12345678', 'seo_admin.metrika_hint'],
            ] as $key => [$label, $placeholder, $hint])
                <label class="block text-xs font-medium text-stone-600">{{ $label }}
                    <input wire:model="codes.{{ $key }}" type="text" placeholder="{{ $placeholder }}" class="{{ $field }} mt-1 font-mono">
                    <span class="block text-[11px] text-stone-500 font-normal mt-1">{{ __($hint) }}</span>
                    @error("codes.$key") <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                </label>
            @endforeach
        </div>

        {{-- Indexing, robots.txt, sitemap --}}
        <div class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-sm">{{ __('seo_admin.indexing') }}</h2>
            <label class="flex items-start gap-3 text-sm">
                <input wire:model.live="noindex" type="checkbox" class="mt-0.5 rounded border-stone-300 text-rose-600">
                <span><span class="font-medium {{ $noindex ? 'text-rose-700' : '' }}">{{ __('seo_admin.noindex') }}</span>
                    <span class="block text-xs text-stone-500">{{ __('seo_admin.noindex_hint') }}</span></span>
            </label>
            <div class="space-y-2">
                <p class="text-xs font-medium text-stone-600">{{ __('seo_admin.ai_agents') }}</p>
                @foreach(['all', 'search', 'none'] as $opt)
                    <label class="flex items-start gap-2 text-sm">
                        <input wire:model="aiAgents" type="radio" value="{{ $opt }}" class="mt-0.5 border-stone-300 text-amber-600">
                        <span>{{ __('seo_admin.ai.' . $opt) }}<span class="block text-[11px] text-stone-500">{{ __('seo_admin.ai.' . $opt . '_hint') }}</span></span>
                    </label>
                @endforeach
                <p class="text-[11px] text-stone-500">{{ __('seo_admin.ai_files') }} <a href="{{ route('llms') }}" target="_blank" class="text-amber-700 hover:underline">llms.txt ↗</a> · <a href="{{ route('llms.full') }}" target="_blank" class="text-amber-700 hover:underline">llms-full.txt ↗</a></p>
            </div>
            <label class="block text-xs font-medium text-stone-600">{{ __('seo_admin.robots_extra') }}
                <textarea wire:model="robotsExtra" rows="4" placeholder="Disallow: /tmp/&#10;Clean-param: utm_source /" class="{{ $field }} mt-1 font-mono text-xs"></textarea>
                <span class="block text-[11px] text-stone-500 font-normal mt-1">{{ __('seo_admin.robots_hint') }}</span>
                @error('robotsExtra') <span class="text-rose-500 text-xs">{{ __('seo_admin.robots_error') }}</span> @enderror
            </label>
            <p class="text-xs text-stone-500">{{ __('seo_admin.sitemap_hint', ['count' => count(\App\Support\Seo::PAGES) * count($locales)]) }}</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
