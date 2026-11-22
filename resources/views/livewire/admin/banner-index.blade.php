@php
    use App\Domain\Content\Banner;
    $thumb = fn (Banner $b) => ['light' => 'bg-stone-100', 'dark' => 'bg-stone-900', 'amber' => 'bg-amber-700'][$b->style] ?? 'bg-stone-100';
@endphp
<div>
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.banners')" :hint="__('banners.hint')" :view-url="route('home')" :view-label="__('nav.home')">
        <x-slot:actions>
            <x-admin.button variant="primary" icon="plus" :href="route('admin.banners.create')">{{ __('banners.add') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.toolbar>
        <x-admin.search model="search" />
        <x-admin.filter-tabs model="status" :value="$status" :options="$statuses" />
        <x-admin.filter-toggle model="missing" :active="$missing" :count="$missingCount">{{ __('admin.list.missing') }}</x-admin.filter-toggle>
    </x-admin.toolbar>

    <x-admin.list wide :head="[__('banners.title'), __('common.status'), __('admin.list.langs'), __('admin.list.updated'), __('admin.list.actions')]">
        @if($default)
            {{-- the built-in hero: always first, its texts come from the home page texts, it cannot be edited or deleted here --}}
            <x-admin.list-row wire:key="banner-default">
                <x-slot:thumb>
                    <div class="w-24 h-12 rounded-lg overflow-hidden flex items-center justify-center {{ $thumb($default) }}"><img src="/images/logo.png" alt="" class="h-5 w-auto"></div>
                </x-slot:thumb>
                {{ __('home.h1') }}
                <x-slot:meta>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 px-1.5 py-0.5 text-[11px] font-medium mr-1">@svg('heroicon-o-star', 'w-3 h-3') {{ __('banners.default') }}</span>{{ __('banners.default_hint') }}
                </x-slot:meta>
                <x-slot:status><x-admin.status-pill status="published" /></x-slot:status>
            </x-admin.list-row>
        @endif
        @forelse($banners as $b)
            <x-admin.list-row :href="route('admin.banners.edit', $b)" :muted="$b->status() === 'hidden'" wire:key="banner-{{ $b->id }}">
                <x-slot:thumb>
                    <div class="w-24 h-12 rounded-lg overflow-hidden flex items-center justify-center {{ $thumb($b) }}">
                        @if($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="" class="h-full w-full object-contain">
                        @else<span class="text-[11px] {{ $b->style === 'light' ? 'text-stone-600' : 'text-white' }}">{{ __('banners.no_image') }}</span>@endif
                    </div>
                </x-slot:thumb>
                {{ $b->title_uz }}
                <x-slot:meta>
                    <span class="block truncate">@if($b->is_sponsored)<span class="rounded bg-stone-100 text-stone-700 px-1.5 py-0.5 text-[10px] font-medium mr-1">{{ __('banners.ad') }}</span>@endif
                        @if($b->offer)<span class="text-amber-800">{{ $b->offer->title_uz }}</span> · @endif{{ $b->link() ?: '—' }}@if($b->starts_at || $b->ends_at) · {{ $b->starts_at?->format('d.m.Y') ?? '…' }} – {{ $b->ends_at?->format('d.m.Y') ?? '…' }}@endif</span>
                </x-slot:meta>
                <x-slot:status><x-admin.status-pill :status="$b->status()" /></x-slot:status>
                <x-slot:langs><x-admin.lang-completeness :item="$b" :fields="Banner::TRANSLATION_CHECK" /></x-slot:langs>
                <x-slot:updated><time datetime="{{ $b->updated_at?->toIso8601String() }}" title="{{ $b->updated_at?->format('d.m.Y H:i') }}">{{ $b->updated_at?->isToday() ? $b->updated_at->format('H:i') : $b->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    @if($canMove)
                        <button type="button" wire:click="move({{ $b->id }}, -1)" @disabled($loop->first) aria-label="{{ __('admin.list.move_up') }}: {{ $b->title_uz }}" title="{{ __('admin.list.move_up') }}"
                                class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200 disabled:opacity-30 disabled:hover:bg-transparent">@svg('heroicon-o-chevron-up', 'w-4 h-4')</button>
                        <button type="button" wire:click="move({{ $b->id }}, 1)" @disabled($loop->last) aria-label="{{ __('admin.list.move_down') }}: {{ $b->title_uz }}" title="{{ __('admin.list.move_down') }}"
                                class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200 disabled:opacity-30 disabled:hover:bg-transparent">@svg('heroicon-o-chevron-down', 'w-4 h-4')</button>
                    @endif
                    <a href="{{ route('admin.banners.edit', $b) }}" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</a>
                    <x-admin.row-menu>
                        <x-admin.menu-item wire:click="toggle({{ $b->id }})">{{ $b->is_active ? __('admin.list.hide') : __('admin.list.show') }}</x-admin.menu-item>
                        <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('banners.delete_title')) }}, name: {{ Js::from($b->title_uz) }}, text: {{ Js::from(__('banners.delete_text')) }}, run: () => $wire.delete({{ $b->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                    </x-admin.row-menu>
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            @if($total)
                <x-admin.empty-state icon="magnifying-glass" :title="__('admin.list.none_found')" :text="__('admin.list.none_found_text')">
                    <x-slot:action><x-admin.button wire:click="clearFilters">{{ __('admin.list.clear_filters') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state icon="photo" :title="__('banners.empty_admin')" :text="__('banners.empty_admin_text')">
                    <x-slot:action><x-admin.button variant="primary" icon="plus" :href="route('admin.banners.create')">{{ __('banners.add') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @endif
        @endforelse
        @if($banners->isNotEmpty())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $banners->count(), 'total' => $total]) }}@unless($canMove) · {{ __('admin.list.move_filtered') }}@endunless</x-slot:footer>
        @endif
    </x-admin.list>
</div>
