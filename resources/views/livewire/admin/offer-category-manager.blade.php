@php use App\Domain\Offers\Models\Offer; @endphp
<div>
    @if($tab === 'settings')
        {{-- Category settings: names and descriptions in three languages, market link, visibility --}}
        <x-admin.i18n-form submit="saveSettings" :cancel="route('admin.offers', $category)">
            <x-slot:header>
                <x-admin.page-header :crumb="__('nav.group.content') . ' · ' . __('nav.offers')" :title="$category->name_uz" :hint="__('offers.category_settings_hint')" />
                <x-admin.tabs :tabs="$tabs" :value="$tab" :label="$category->name_uz" />
            </x-slot:header>

            <x-admin.lang-tabs />

            <x-admin.section :title="__('offers.category_settings')" :hint="__('banners.sec_content_hint')">
                <x-admin.i18n-field name="settings.name" :label="__('common.name')" :max="$limits['name']" required />
                <x-admin.i18n-field name="settings.description" :label="__('offers.description')" type="textarea" rows="2" :max="$limits['description']" />
            </x-admin.section>

            <x-admin.section :title="__('offers.sec_market')" shared>
                <x-admin.field name="settings.market_url" :label="__('offers.market_url')" type="url" placeholder="https://uzum.uz/uz/shop/…" :hint="__('offers.market_hint', ['n' => $shown])" />
            </x-admin.section>

            <x-slot:aside>
                <x-admin.publish-card model="settings.is_active" :view-url="$category->is_active ? route('marketplace') : null">
                    <p class="text-xs text-stone-500">{{ __('offers.category_visible_hint') }}</p>
                </x-admin.publish-card>
            </x-slot:aside>
        </x-admin.i18n-form>
    @else
        <x-admin.page-header :crumb="__('nav.group.content') . ' · ' . __('nav.offers')" :title="$category->name_uz"
                             :hint="$category->isTemplates() ? __('offers.templates_are_items') : __('offers.order_hint', ['n' => $shown])"
                             :view-url="$category->is_active ? route('marketplace') : null" view-label="/marketplace">
            <x-slot:actions>
                @if($category->isTemplates())
                    <x-admin.button variant="primary" icon="pencil-square" :href="route('label-templates.index')">{{ __('nav.template_designer') }}</x-admin.button>
                @else
                    <x-admin.button variant="primary" icon="plus" :href="route('admin.offers.create', $category)">{{ __('offers.add_item') }}</x-admin.button>
                @endif
            </x-slot:actions>
        </x-admin.page-header>
        <x-admin.tabs :tabs="$tabs" :value="$tab" :label="$category->name_uz" />

        @if($category->isTemplates())
            {{-- Labels: every template is a size we sell --}}
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($templates as $t)
                    <article class="bg-white border border-stone-200 rounded-xl p-4">
                        <div class="bg-stone-100 rounded-lg p-4 flex items-center justify-center h-44">
                            @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 240, 'maxH' => 140])
                        </div>
                        <p class="font-medium text-stone-900 mt-3">{{ $t->name }}</p>
                        <p class="text-xs text-stone-500">{{ $t->width_mm }}×{{ $t->height_mm }} mm</p>
                    </article>
                @empty
                    <p class="text-sm text-stone-600">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        @else
            <x-admin.toolbar>
                <x-admin.search model="search" />
                <x-admin.filter-tabs model="status" :value="$status" :options="$statuses" />
                <x-admin.filter-toggle model="missing" :active="$missing" :count="$missingCount">{{ __('admin.list.missing') }}</x-admin.filter-toggle>
            </x-admin.toolbar>

            <x-admin.list wide :head="[__('common.name'), __('common.status'), __('admin.list.langs'), __('admin.list.updated'), __('admin.list.actions')]">
                @forelse($items as $offer)
                    @php $pos = $positions[$offer->id]; @endphp
                    <x-admin.list-row :href="route('admin.offers.edit', [$category, $offer])" :muted="!$offer->is_active" wire:key="offer-{{ $offer->id }}">
                        <x-slot:thumb>
                            <div class="flex items-center gap-2">
                                <span class="w-5 text-xs tabular-nums text-right {{ $offer->is_active && $pos < $shown ? 'text-amber-800 font-semibold' : 'text-stone-500' }}" title="{{ __('offers.position') }}">{{ $pos + 1 }}</span>
                                @if($offer->image_url)
                                    <img src="{{ $offer->image_url }}" alt="" class="w-11 h-11 object-contain rounded-lg border border-stone-200 bg-white">
                                @else
                                    <span class="w-11 h-11 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">@svg($offer->icon ?: 'heroicon-o-cube', 'w-5 h-5')</span>
                                @endif
                            </div>
                        </x-slot:thumb>
                        {{ $offer->title_uz }}
                        <x-slot:meta><span class="line-clamp-1">{{ $offer->description_uz ?: '/marketplace/' . $offer->slug }}</span></x-slot:meta>
                        <x-slot:status><x-admin.status-pill :status="$offer->is_active ? 'published' : 'hidden'" /></x-slot:status>
                        <x-slot:langs><x-admin.lang-completeness :item="$offer" :fields="$offer->translatableFields()" /></x-slot:langs>
                        <x-slot:updated><time datetime="{{ $offer->updated_at?->toIso8601String() }}" title="{{ $offer->updated_at?->format('d.m.Y H:i') }}">{{ $offer->updated_at?->isToday() ? $offer->updated_at->format('H:i') : $offer->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                        <x-slot:actions>
                            @if($canMove)
                                <button type="button" wire:click="move({{ $offer->id }}, -1)" @disabled($loop->first) aria-label="{{ __('admin.list.move_up') }}: {{ $offer->title_uz }}" title="{{ __('admin.list.move_up') }}"
                                        class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200 disabled:opacity-30 disabled:hover:bg-transparent">@svg('heroicon-o-chevron-up', 'w-4 h-4')</button>
                                <button type="button" wire:click="move({{ $offer->id }}, 1)" @disabled($loop->last) aria-label="{{ __('admin.list.move_down') }}: {{ $offer->title_uz }}" title="{{ __('admin.list.move_down') }}"
                                        class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200 disabled:opacity-30 disabled:hover:bg-transparent">@svg('heroicon-o-chevron-down', 'w-4 h-4')</button>
                            @endif
                            <a href="{{ route('admin.offers.edit', [$category, $offer]) }}" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</a>
                            <x-admin.row-menu>
                                @if($offer->is_active && $category->is_active)
                                    <a href="{{ route('marketplace.item', $offer->slug) }}" target="_blank" rel="noopener" role="menuitem" class="admin-focus block w-full text-left px-3 py-2 text-stone-800 hover:bg-stone-100">{{ __('admin.view_on_site') }} ↗</a>
                                @endif
                                <x-admin.menu-item wire:click="toggle({{ $offer->id }})">{{ $offer->is_active ? __('admin.list.hide') : __('admin.list.show') }}</x-admin.menu-item>
                                <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('offers.delete_title')) }}, name: {{ Js::from($offer->title_uz) }}, text: {{ Js::from(__('offers.delete_text')) }}, run: () => $wire.delete({{ $offer->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                            </x-admin.row-menu>
                        </x-slot:actions>
                    </x-admin.list-row>
                @empty
                    @if($total)
                        <x-admin.empty-state icon="magnifying-glass" :title="__('admin.list.none_found')" :text="__('admin.list.none_found_text')">
                            <x-slot:action><x-admin.button wire:click="clearFilters">{{ __('admin.list.clear_filters') }}</x-admin.button></x-slot:action>
                        </x-admin.empty-state>
                    @else
                        <x-admin.empty-state icon="shopping-bag" :title="__('offers.empty_admin')" :text="__('offers.empty_admin_text')">
                            <x-slot:action><x-admin.button variant="primary" icon="plus" :href="route('admin.offers.create', $category)">{{ __('offers.add_item') }}</x-admin.button></x-slot:action>
                        </x-admin.empty-state>
                    @endif
                @endforelse
                @if($items->isNotEmpty())
                    <x-slot:footer>{{ __('admin.list.count', ['shown' => $items->count(), 'total' => $total]) }}@unless($canMove) · {{ __('admin.list.move_filtered') }}@endunless</x-slot:footer>
                @endif
            </x-admin.list>
        @endif
    @endif
</div>
