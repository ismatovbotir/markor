@php
    use App\Http\Controllers\Guest\HomeController;
    use App\Support\Seo;
    $engines = [
        'google'  => ['Google Search Console', 'abc123…', 'seo_admin.google_hint'],
        'yandex'  => ['Yandex Webmaster', '1a2b3c4d5e6f7a8b', 'seo_admin.yandex_hint'],
        'bing'    => ['Bing Webmaster', 'A1B2C3D4E5F6…', 'seo_admin.bing_hint'],
        'baidu'   => ['Baidu 站长平台 (Baidu Webmaster)', 'codeva-AbCd1234', 'seo_admin.baidu_hint'],
        'ga4'     => ['Google Analytics 4', 'G-XXXXXXXXXX', 'seo_admin.ga4_hint'],
        'metrika' => ['Yandex Metrika', '12345678', 'seo_admin.metrika_hint'],
    ];
@endphp
{{-- seoTab: which block of the page is open (all three stay in the page and are saved by the one Save); openPage: the unfolded page row --}}
<div x-data="{ seoTab: 'texts', openPage: '{{ Seo::PAGES[0] }}' }">
    <x-admin.i18n-form submit="save">
        <x-slot:header>
            <x-admin.page-header :crumb="__('nav.group.content')" title="SEO" :hint="__('seo_admin.hint')">
                <x-slot:actions>
                    <x-admin.button :href="route('robots')" target="_blank" rel="noopener">robots.txt ↗</x-admin.button>
                    <x-admin.button :href="route('sitemap')" target="_blank" rel="noopener">sitemap.xml ↗</x-admin.button>
                </x-slot:actions>
            </x-admin.page-header>
            <x-admin.tabs model="seoTab" label="SEO" :tabs="[
                'texts' => ['label' => __('seo_admin.texts'), 'count' => count(Seo::PAGES)],
                'engines' => ['label' => __('seo_admin.engines')],
                'indexing' => ['label' => __('seo_admin.indexing')],
            ]" />
        </x-slot:header>

        {{-- 1. Titles and descriptions per page; the language switch is the only one on the page --}}
        <x-admin.lang-tabs x-show="seoTab === 'texts'" />
        <div x-show="seoTab === 'texts'" data-collapsible x-on:open-section="seoTab = 'texts'" role="tabpanel" :aria-labelledby="'admin-tab-' + lang" data-seo-texts>
            <p class="px-4 sm:px-6 py-3 text-xs text-stone-500 border-b border-stone-200">{{ __('seo_admin.texts_hint') }}</p>
            <ul class="divide-y divide-stone-200">
                @foreach(Seo::PAGES as $page)
                    @php
                        $t = "f-texts-$page-title"; $d = "f-texts-$page-description";
                        $urls = collect($locales)->map(fn ($name, $l) => HomeController::localizedUrl($page, $l))->all();
                    @endphp
                    <li data-collapsible data-seo-page="{{ $page }}" x-on:open-section="openPage = '{{ $page }}'" wire:key="seo-{{ $page }}">
                        <button type="button" x-on:click="openPage = openPage === '{{ $page }}' ? null : '{{ $page }}'" :aria-expanded="openPage === '{{ $page }}'"
                                class="admin-focus w-full flex items-center gap-3 px-4 sm:px-6 py-3 text-left hover:bg-stone-50">
                            <span class="text-stone-500 transition" :class="openPage === '{{ $page }}' && 'rotate-90'">@svg('heroicon-m-chevron-right', 'w-4 h-4')</span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-stone-900">{{ __('seo_admin.page.' . $page) }}</span>
                                <span class="block text-xs font-mono text-stone-500 truncate">{{ route($page, [], false) }}</span>
                            </span>
                            <span x-show="err('{{ $t }}-' + lang) || err('{{ $d }}-' + lang)" x-cloak class="text-rose-600">@svg('heroicon-o-exclamation-triangle', 'w-4 h-4')</span>
                            <span x-show="warn('{{ $t }}-' + lang) || warn('{{ $d }}-' + lang)" x-cloak
                                  class="px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 text-[11px] font-medium">{{ __('seo_admin.long') }}</span>
                            <span class="text-xs rounded-full px-2 py-0.5 ring-1 whitespace-nowrap"
                                  :class="changed('{{ $t }}') || changed('{{ $d }}') ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-white text-stone-600 ring-stone-300'"
                                  x-text="changed('{{ $t }}') || changed('{{ $d }}') ? {{ Js::from(__('seo_admin.changed')) }} : {{ Js::from(__('seo_admin.standard')) }}">{{ __('seo_admin.standard') }}</span>
                        </button>
                        <div x-show="openPage === '{{ $page }}'" @if(!$loop->first) x-cloak @endif class="px-4 sm:px-6 pb-5 sm:pl-[3.25rem] grid lg:grid-cols-2 gap-5">
                            <div class="space-y-4 min-w-0">
                                @foreach(array_keys(Seo::FIELDS) as $f)
                                    <x-admin.i18n-field name="texts.{{ $page }}.{{ $f }}" key="texts.{{ $page }}.{{ $f }}.:locale" field="{{ $page }}.{{ $f }}"
                                                        :label="__('seo_admin.' . $f)" :type="$f === 'title' ? 'input' : 'textarea'" rows="3"
                                                        :soft="Seo::recommended($f)" :max="Seo::MAX[$f]" fallback="default" :defaults="$defaults[$page][$f]" />
                                @endforeach
                            </div>
                            {{-- how it looks in a search result, in the language of the switch --}}
                            <div class="rounded-lg bg-stone-50 border border-stone-200 p-3 self-start min-w-0" data-seo-preview>
                                <p class="text-xs text-stone-600 mb-1.5">{{ __('seo_admin.preview') }} <span class="font-semibold" x-text="lang.toUpperCase()"></span></p>
                                <p class="text-xs text-emerald-800 truncate" x-text="({{ Js::from($urls) }})[lang]">{{ $urls['uz'] }}</p>
                                <p class="text-[#1a0dab] text-base leading-snug truncate" x-text="shown('{{ $t }}')">{{ $defaults[$page]['title']['uz'] }}</p>
                                <p class="text-xs text-stone-700 line-clamp-2 mt-0.5" x-text="shown('{{ $d }}')">{{ $defaults[$page]['description']['uz'] }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- 2. Search engines and statistics --}}
        <div x-show="seoTab === 'engines'" x-cloak data-collapsible x-on:open-section="seoTab = 'engines'">
            <x-admin.section :title="__('seo_admin.engines')" shared class="!border-t-0">
                <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
                    @foreach($engines as $key => [$label, $placeholder, $hint])
                        <x-admin.field name="codes.{{ $key }}" :label="$label" :placeholder="$placeholder" :hint="__($hint)" />
                    @endforeach
                </div>
            </x-admin.section>
        </div>

        {{-- 3. Indexing, AI agents, robots.txt, sitemap --}}
        <div x-show="seoTab === 'indexing'" x-cloak data-collapsible x-on:open-section="seoTab = 'indexing'">
            <x-admin.section :title="__('seo_admin.indexing')" shared class="!border-t-0">
                <x-admin.switch model="noindex" :label="__('seo_admin.noindex')" :hint="__('seo_admin.noindex_hint')" />
                <div>
                    <p class="text-[13px] font-medium text-stone-700 mb-1">{{ __('seo_admin.ai_agents') }}</p>
                    <x-admin.radio-cards model="aiAgents" :legend="__('seo_admin.ai_agents')" cols="grid-cols-1"
                                         :options="collect(['all', 'search', 'none'])->mapWithKeys(fn ($o) => [$o => ['label' => __('seo_admin.ai.' . $o), 'hint' => __('seo_admin.ai.' . $o . '_hint')]])->all()" />
                    <p class="text-xs text-stone-500 mt-2">{{ __('seo_admin.ai_files') }}
                        <a href="{{ route('llms') }}" target="_blank" rel="noopener" class="admin-focus rounded text-amber-800 hover:underline">llms.txt ↗</a> ·
                        <a href="{{ route('llms.full') }}" target="_blank" rel="noopener" class="admin-focus rounded text-amber-800 hover:underline">llms-full.txt ↗</a></p>
                </div>
                <x-admin.field name="robotsExtra" :label="__('seo_admin.robots_extra')" :hint="__('seo_admin.robots_hint')" id="f-robots-extra">
                    <textarea id="f-robots-extra" wire:model="robotsExtra" rows="4" placeholder="Disallow: /tmp/&#10;Clean-param: utm_source /" data-key="robotsExtra" data-label="{{ __('seo_admin.robots_extra') }}"
                              aria-invalid="{{ $errors->has('robotsExtra') ? 'true' : 'false' }}" class="admin-input font-mono text-xs"></textarea>
                </x-admin.field>
                <p class="text-xs text-stone-500">{{ __('seo_admin.sitemap_hint', ['count' => count(Seo::PAGES) * count($locales)]) }}</p>
            </x-admin.section>
        </div>
    </x-admin.i18n-form>
</div>
