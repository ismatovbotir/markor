{{-- Super admin side menu: items grouped by meaning. A group folds/unfolds (remembered in the browser);
     the group of the current page is always open, and a folded group shows the sum of its badges. --}}
@php
    $groups = [
        ['key' => 'overview', 'label' => null, 'items' => [
            [route('admin.stats'), __('nav.stats'), 'heroicon-o-chart-bar', request()->routeIs('admin.stats'), null],
        ]],
        ['key' => 'clients', 'label' => __('nav.group.clients'), 'items' => [
            [route('admin.clients'), __('nav.companies'), 'heroicon-o-building-office-2', request()->routeIs('admin.clients', 'admin.companies.*'), null],
            [route('admin.payments'), __('nav.payments'), 'heroicon-o-banknotes', request()->routeIs('admin.payments'),
                \App\Domain\Billing\Payment::where('status', 'pending')->count()],
            [route('admin.telegram'), 'Telegram', 'heroicon-o-paper-airplane', request()->routeIs('admin.telegram'),
                \App\Domain\Telegram\TelegramLink::where('status', 'phone_mismatch')->where('updated_at', '>=', now()->subDays(7))->count()],
        ]],
        ['key' => 'aslbelgi', 'label' => 'Asl Belgisi', 'items' => [
            [route('products.index'), __('nav.products'), 'heroicon-o-tag', request()->routeIs('products.*'), null],
            [route('marking-codes.index'), __('nav.codes'), 'heroicon-o-qr-code', request()->routeIs('marking-codes.*'), null],
            [route('product-groups.index'), __('nav.product_groups'), 'heroicon-o-rectangle-group', request()->routeIs('product-groups.*'), null],
        ]],
        ['key' => 'marketplace', 'label' => __('nav.offers'), 'teal' => true, 'items' => array_merge([
            [route('admin.offer-requests'), __('nav.offer_requests'), 'heroicon-o-inbox-arrow-down', request()->routeIs('admin.offer-requests'),
                \App\Domain\Offers\Models\OfferRequest::where('status', 'new')->count()],
            [route('admin.template-requests'), __('nav.template_requests'), 'heroicon-o-document-plus', request()->routeIs('admin.template-requests'),
                \App\Domain\Printing\Models\TemplateRequest::where('status', 'new')->count()],
        ], \App\Domain\Offers\Models\OfferCategory::orderBy('sort_order')->orderBy('id')->get()->map(fn ($c) => [
            $c->isTemplates() ? route('label-templates.index') : route('admin.offers', $c),
            $c->name, $c->icon ?: 'heroicon-o-cube',
            request()->is('admin/offers/' . $c->slug, 'admin/offers/' . $c->slug . '/*') || ($c->isTemplates() && request()->routeIs('label-templates.*')),
            null, !$c->is_active,
        ])->all())],
        ['key' => 'site', 'label' => __('nav.group.site'), 'items' => [
            [route('admin.banners'), __('nav.banners'), 'heroicon-o-photo', request()->routeIs('admin.banners*'), null],
            [route('admin.drivers'), __('nav.drivers'), 'heroicon-o-arrow-down-tray', request()->routeIs('admin.drivers*'), null],
            [route('admin.seo'), 'SEO', 'heroicon-o-magnifying-glass', request()->routeIs('admin.seo'), \App\Support\Seo::noindex() ? 'noindex' : null],
        ]],
        ['key' => 'system', 'label' => __('nav.group.system'), 'items' => [
            [route('admin.settings'), __('nav.settings'), 'heroicon-o-cog-6-tooth', request()->routeIs('admin.settings'), null],
        ]],
    ];
@endphp
<div x-data="{ closed: (() => { try { return JSON.parse(localStorage.getItem('adminNavClosed') || '[]') } catch (e) { return [] } })(),
               toggle(k) { this.closed = this.closed.includes(k) ? this.closed.filter(x => x !== k) : [...this.closed, k]; try { localStorage.setItem('adminNavClosed', JSON.stringify(this.closed)) } catch (e) {} } }"
     class="space-y-3">
    @foreach($groups as $g)
        @php
            $hasActive = collect($g['items'])->contains(fn ($i) => $i[3]);
            $sum = collect($g['items'])->sum(fn ($i) => is_int($i[4] ?? null) ? $i[4] : 0);
        @endphp
        <div>
            @if($g['label'])
                <button type="button" @unless($hasActive) x-on:click="toggle('{{ $g['key'] }}')" @endunless
                        class="w-full flex items-center gap-1.5 px-4 pb-1 text-[11px] font-semibold uppercase tracking-wide {{ !empty($g['teal']) ? 'text-teal-700' : 'text-stone-400' }} {{ $hasActive ? 'cursor-default' : 'hover:text-stone-600' }}">
                    @if($hasActive)<span class="rotate-90 opacity-40">@svg('heroicon-m-chevron-right', 'w-3 h-3')</span>
                    @else<span class="transition" :class="closed.includes('{{ $g['key'] }}') ? '' : 'rotate-90'">@svg('heroicon-m-chevron-right', 'w-3 h-3')</span>@endif
                    <span class="flex-1 text-left">{{ $g['label'] }}</span>
                    @if($sum && !$hasActive)<span x-show="closed.includes('{{ $g['key'] }}')" x-cloak class="px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] normal-case">{{ $sum }}</span>@endif
                </button>
            @endif
            <div class="space-y-0.5" @if($g['label'] && !$hasActive) x-show="!closed.includes('{{ $g['key'] }}')" x-collapse.duration.150ms @endif>
                @foreach($g['items'] as $i)
                    @php [$href, $label, $icon, $active, $count] = $i; $muted = $i[5] ?? false; @endphp
                    <a href="{{ $href }}" class="flex items-center px-4 py-2 rounded-lg text-[15px] font-medium transition {{ $active ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-200' }} {{ $muted ? 'opacity-50' : '' }}">
                        @svg($icon, 'w-5 h-5 mr-3 shrink-0')
                        <span class="flex-1 truncate">{{ $label }}</span>
                        @if($count === 'noindex')<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-semibold">noindex</span>
                        @elseif($count)<span class="ml-2 px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-semibold">{{ $count }}</span>@endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
