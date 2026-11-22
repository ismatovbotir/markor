{{-- Super admin side menu. Groups: requests (work to answer) · clients · Asl Belgisi · content (everything shown on the site,
     in 3 languages) · system. A group folds/unfolds (remembered in the browser); the group of the current page is always open,
     and a folded group shows the sum of its counters.
     Two badges: rose = action needed; amber with the language icon = records with an incomplete translation. --}}
@php
    use App\Domain\Content\Banner;
    use App\Domain\Content\NewsPost;
    use App\Domain\Content\PrinterDriver;
    use App\Domain\Offers\Models\Offer;
    use App\Domain\Offers\Models\OfferCategory;
    use App\Support\AdminI18n;

    $categories = OfferCategory::withCount('offers')->orderBy('sort_order')->orderBy('id')->get();
    // products with an incomplete translation per category (FAQ rows count too, so it is checked in PHP)
    $offerGaps = Offer::query()->get(['id', 'offer_category_id', 'title_uz', 'title_ru', 'title_zh', 'description_uz', 'description_ru', 'description_zh', 'faq'])
        ->filter->translationIncomplete()->countBy('offer_category_id');

    $groups = [
        ['key' => 'overview', 'label' => null, 'items' => [
            ['href' => route('admin.stats'), 'label' => __('nav.stats'), 'icon' => 'heroicon-o-chart-bar', 'active' => request()->routeIs('admin.stats')],
        ]],
        ['key' => 'requests', 'label' => __('nav.group.requests'), 'items' => [
            ['href' => route('admin.offer-requests'), 'label' => __('nav.offer_requests'), 'icon' => 'heroicon-o-inbox-arrow-down', 'active' => request()->routeIs('admin.offer-requests'),
                'todo' => \App\Domain\Offers\Models\OfferRequest::where('status', 'new')->count()],
            ['href' => route('admin.template-requests'), 'label' => __('nav.template_requests'), 'icon' => 'heroicon-o-document-plus', 'active' => request()->routeIs('admin.template-requests'),
                'todo' => \App\Domain\Printing\Models\TemplateRequest::where('status', 'new')->count()],
        ]],
        ['key' => 'clients', 'label' => __('nav.group.clients'), 'items' => [
            ['href' => route('admin.clients'), 'label' => __('nav.companies'), 'icon' => 'heroicon-o-building-office-2', 'active' => request()->routeIs('admin.clients', 'admin.companies.*')],
            ['href' => route('admin.storage'), 'label' => __('storage.title'), 'icon' => 'heroicon-o-circle-stack', 'active' => request()->routeIs('admin.storage')],
            ['href' => route('admin.payments'), 'label' => __('nav.payments'), 'icon' => 'heroicon-o-banknotes', 'active' => request()->routeIs('admin.payments'),
                'todo' => \App\Domain\Billing\Payment::where('status', 'pending')->count()],
            ['href' => route('admin.telegram'), 'label' => 'Telegram', 'icon' => 'heroicon-o-paper-airplane', 'active' => request()->routeIs('admin.telegram'),
                'todo' => \App\Domain\Telegram\TelegramLink::where('status', 'phone_mismatch')->where('updated_at', '>=', now()->subDays(7))->count()],
        ]],
        ['key' => 'aslbelgi', 'label' => 'Asl Belgisi', 'items' => [
            ['href' => route('admin.batches'), 'label' => __('batches.title'), 'icon' => 'heroicon-o-inbox-stack', 'active' => request()->routeIs('admin.batches')],
            ['href' => route('products.index'), 'label' => __('nav.products'), 'icon' => 'heroicon-o-tag', 'active' => request()->routeIs('products.*')],
            ['href' => route('product-groups.index'), 'label' => __('nav.product_groups'), 'icon' => 'heroicon-o-rectangle-group', 'active' => request()->routeIs('product-groups.*')],
        ]],
        ['key' => 'content', 'label' => __('nav.group.content'), 'note' => __('nav.group.content_note'), 'accent' => true, 'items' => [
            ['label' => __('nav.offers'), 'icon' => 'heroicon-o-shopping-bag', 'gap' => $offerGaps->sum(),
                'children' => $categories->reject->isTemplates()->map(fn ($c) => [
                    'href' => route('admin.offers', $c), 'label' => $c->name, 'count' => $c->offers_count, 'muted' => !$c->is_active,
                    'gap' => $offerGaps[$c->id] ?? 0, 'gapHref' => route('admin.offers', [$c, 'missing' => 1]),
                    'active' => request()->is('admin/offers/' . $c->slug, 'admin/offers/' . $c->slug . '/*'),
                ])->values()->all()],
            ['href' => route('admin.news'), 'label' => __('nav.news'), 'icon' => 'heroicon-o-newspaper', 'active' => request()->routeIs('admin.news*'),
                'gap' => AdminI18n::incompleteCount(NewsPost::class, NewsPost::TRANSLATABLE), 'gapHref' => route('admin.news', ['missing' => 1])],
            ['href' => route('admin.banners'), 'label' => __('nav.banners'), 'icon' => 'heroicon-o-photo', 'active' => request()->routeIs('admin.banners*'),
                'gap' => AdminI18n::scopeIncomplete(Banner::where('is_default', false), Banner::TRANSLATION_CHECK)->count(), 'gapHref' => route('admin.banners', ['missing' => 1])],
            ['href' => route('admin.drivers'), 'label' => __('nav.drivers'), 'icon' => 'heroicon-o-arrow-down-tray', 'active' => request()->routeIs('admin.drivers*'),
                'gap' => AdminI18n::incompleteCount(PrinterDriver::class, PrinterDriver::TRANSLATABLE), 'gapHref' => route('admin.drivers', ['missing' => 1])],
            ['href' => route('label-templates.index'), 'label' => __('nav.templates'), 'icon' => 'heroicon-o-document-duplicate', 'active' => request()->routeIs('label-templates.*')],
            ['href' => route('admin.seo'), 'label' => 'SEO', 'icon' => 'heroicon-o-magnifying-glass', 'active' => request()->routeIs('admin.seo'), 'flag' => \App\Support\Seo::noindex() ? 'noindex' : null],
        ]],
        ['key' => 'system', 'label' => __('nav.group.system'), 'items' => [
            ['href' => route('admin.settings'), 'label' => __('nav.settings'), 'icon' => 'heroicon-o-cog-6-tooth', 'active' => request()->routeIs('admin.settings')],
            ['href' => route('admin.system'), 'label' => __('system.title'), 'icon' => 'heroicon-o-heart', 'active' => request()->routeIs('admin.system'), 'todo' => app(\App\Domain\System\SystemStatus::class)->problems()],
        ]],
    ];

    $isActive = fn (array $i) => !empty($i['active']) || collect($i['children'] ?? [])->contains(fn ($c) => $c['active']);
    $row = 'admin-focus flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition min-h-10';
    $todoBadge = 'px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[11px] font-semibold tabular-nums';
    $gapBadge = 'admin-focus inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 text-[11px] font-semibold tabular-nums';
@endphp
<div x-data="{ closed: (() => { try { return JSON.parse(localStorage.getItem('adminNavClosed') || '[]') } catch (e) { return [] } })(),
               toggle(k) { this.closed = this.closed.includes(k) ? this.closed.filter(x => x !== k) : [...this.closed, k]; try { localStorage.setItem('adminNavClosed', JSON.stringify(this.closed)) } catch (e) {} } }"
     class="space-y-4" data-admin-nav>
    @foreach($groups as $g)
        @php
            $hasActive = collect($g['items'])->contains($isActive);
            $sum = collect($g['items'])->sum(fn ($i) => $i['todo'] ?? 0);
        @endphp
        <div>
            @if($g['label'])
                <button type="button" @unless($hasActive) x-on:click="toggle('{{ $g['key'] }}')" :aria-expanded="!closed.includes('{{ $g['key'] }}')" @endunless
                        class="admin-focus w-full flex items-center gap-1.5 px-3 pb-1 rounded text-[11px] font-semibold uppercase tracking-wide {{ !empty($g['accent']) ? 'text-amber-800' : 'text-stone-500' }} {{ $hasActive ? 'cursor-default' : 'hover:text-stone-700' }}">
                    @if($hasActive)<span class="rotate-90 opacity-40">@svg('heroicon-m-chevron-right', 'w-3 h-3')</span>
                    @else<span class="transition" :class="closed.includes('{{ $g['key'] }}') ? '' : 'rotate-90'">@svg('heroicon-m-chevron-right', 'w-3 h-3')</span>@endif
                    <span class="flex-1 text-left">{{ $g['label'] }}</span>
                    @if(!empty($g['note']))<span class="normal-case tracking-normal font-normal text-stone-500">{{ $g['note'] }}</span>@endif
                    @if($sum && !$hasActive)<span x-show="closed.includes('{{ $g['key'] }}')" x-cloak class="{{ $todoBadge }} normal-case">{{ $sum }}</span>@endif
                </button>
            @endif
            <ul class="space-y-0.5" @if($g['label'] && !$hasActive) x-show="!closed.includes('{{ $g['key'] }}')" x-collapse.duration.150ms @endif>
                @foreach($g['items'] as $i)
                    @php $active = $isActive($i); @endphp
                    @continue(array_key_exists('children', $i) && !$i['children'])
                    @if(!empty($i['children']))
                        {{-- a parent with a nested list (marketplace categories with the number of products) --}}
                        <li x-data="{ open: {{ $active ? 'true' : "(() => { try { return localStorage.getItem('adminNavSub.{$g['key']}') !== '0' } catch (e) { return true } })()" }} }">
                            <button type="button" :aria-expanded="open" x-on:click="open = !open; try { localStorage.setItem('adminNavSub.{{ $g['key'] }}', open ? '1' : '0') } catch (e) {}"
                                    class="{{ $row }} w-full text-left text-stone-700 hover:bg-stone-200">
                                @svg($i['icon'], 'w-5 h-5 shrink-0')
                                <span class="flex-1 truncate">{{ $i['label'] }}</span>
                                @if(!empty($i['gap']))<span class="{{ $gapBadge }}" title="{{ __('admin.nav.gap', ['n' => $i['gap']]) }}">@svg('heroicon-o-language', 'w-3 h-3'){{ $i['gap'] }}</span>@endif
                                <span class="text-stone-500 transition" :class="open && 'rotate-90'">@svg('heroicon-m-chevron-right', 'w-4 h-4')</span>
                            </button>
                            <ul x-show="open" @unless($active) x-cloak @endunless class="mt-0.5 ml-5 pl-3 border-l border-stone-300 space-y-0.5">
                                @foreach($i['children'] as $c)
                                    <li class="flex items-center gap-1 rounded-lg transition {{ $c['active'] ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-700 hover:bg-stone-200' }} {{ !empty($c['muted']) ? 'opacity-60' : '' }}">
                                        <a href="{{ $c['href'] }}" @if($c['active']) aria-current="page" @endif
                                           class="admin-focus flex flex-1 min-w-0 items-center gap-2 px-3 py-1.5 rounded-lg text-sm min-h-9">
                                            <span class="flex-1 truncate">{{ $c['label'] }}</span>
                                            <span class="text-xs text-stone-500 tabular-nums font-normal">{{ $c['count'] }}</span>
                                        </a>
                                        @if(!empty($c['gap']))
                                            {{-- opens the category filtered by "translation incomplete" --}}
                                            <a href="{{ $c['gapHref'] ?? $c['href'] }}" class="{{ $gapBadge }} mr-2 hover:bg-amber-100" data-nav-gap
                                               title="{{ __('admin.nav.gap', ['n' => $c['gap']]) }}" aria-label="{{ $c['label'] }}: {{ __('admin.nav.gap', ['n' => $c['gap']]) }}">@svg('heroicon-o-language', 'w-3 h-3'){{ $c['gap'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="flex items-center gap-1 rounded-lg transition {{ $active ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }}">
                            <a href="{{ $i['href'] }}" @if($active) aria-current="page" @endif class="{{ $row }} flex-1 min-w-0">
                                @svg($i['icon'], 'w-5 h-5 shrink-0')
                                <span class="flex-1 truncate">{{ $i['label'] }}</span>
                                @if(($i['flag'] ?? null) === 'noindex')<span class="px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-semibold">noindex</span>@endif
                                @if(!empty($i['todo']))<span class="{{ $todoBadge }}" title="{{ __('admin.nav.todo') }}">{{ $i['todo'] }}</span>@endif
                            </a>
                            @if(!empty($i['gap']))
                                {{-- opens the list filtered by "translation incomplete" --}}
                                <a href="{{ $i['gapHref'] ?? $i['href'] }}" class="{{ $gapBadge }} mr-2 hover:bg-amber-100" data-nav-gap
                                   title="{{ __('admin.nav.gap', ['n' => $i['gap']]) }}" aria-label="{{ $i['label'] }}: {{ __('admin.nav.gap', ['n' => $i['gap']]) }}">@svg('heroicon-o-language', 'w-3 h-3'){{ $i['gap'] }}</a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
