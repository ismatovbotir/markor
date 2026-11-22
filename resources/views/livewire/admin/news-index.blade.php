<div>
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.news')" :hint="__('news.admin_hint')" :view-url="route('news')" view-label="/news">
        <x-slot:actions>
            <x-admin.button variant="primary" icon="plus" :href="route('admin.news.create')">{{ __('news.add') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.toolbar>
        <x-admin.search model="search" :placeholder="__('news.search')" />
        <x-admin.filter-tabs model="status" :value="$status" :options="$statuses" />
        <x-admin.filter-toggle model="missing" :active="$missing" :count="$missingCount">{{ __('admin.list.missing') }}</x-admin.filter-toggle>
    </x-admin.toolbar>

    <x-admin.list :head="[__('news.field_title'), __('common.status'), __('admin.list.langs'), __('admin.list.updated'), __('admin.list.actions')]">
        @forelse($posts as $p)
            <x-admin.list-row :href="route('admin.news.edit', $p)" :muted="!$p->is_active" wire:key="news-{{ $p->id }}">
                {{ $p->title_uz }}
                <x-slot:meta>{{ __('news.published_at') }}: {{ $p->published_at?->format('d.m.Y H:i') ?? '—' }}</x-slot:meta>
                <x-slot:status><x-admin.status-pill :status="$p->status()" /></x-slot:status>
                <x-slot:langs><x-admin.lang-completeness :item="$p" :fields="\App\Domain\Content\NewsPost::TRANSLATABLE" /></x-slot:langs>
                <x-slot:updated><time datetime="{{ $p->updated_at?->toIso8601String() }}" title="{{ $p->updated_at?->format('d.m.Y H:i') }}">{{ $p->updated_at?->isToday() ? $p->updated_at->format('H:i') : $p->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    @if($p->isPublished())
                        <a href="{{ route('news.show', $p) }}" target="_blank" rel="noopener" aria-label="{{ __('admin.view_on_site') }}" title="{{ __('admin.view_on_site') }}"
                           class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')</a>
                    @endif
                    <a href="{{ route('admin.news.edit', $p) }}" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</a>
                    <x-admin.row-menu>
                        <x-admin.menu-item wire:click="toggle({{ $p->id }})">{{ $p->is_active ? __('admin.list.hide') : __('admin.list.show') }}</x-admin.menu-item>
                        <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('news.delete_title')) }}, name: {{ Js::from($p->title_uz) }}, text: {{ Js::from(__('news.delete_text')) }}, run: () => $wire.delete({{ $p->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                    </x-admin.row-menu>
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            @if($total)
                <x-admin.empty-state icon="magnifying-glass" :title="__('admin.list.none_found')" :text="__('admin.list.none_found_text')">
                    <x-slot:action><x-admin.button wire:click="clearFilters">{{ __('admin.list.clear_filters') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state icon="newspaper" :title="__('news.empty_admin')" :text="__('news.empty_admin_text')">
                    <x-slot:action><x-admin.button variant="primary" icon="plus" :href="route('admin.news.create')">{{ __('news.add') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @endif
        @endforelse
        @if($posts->isNotEmpty())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $posts->count(), 'total' => $total]) }}</x-slot:footer>
        @endif
    </x-admin.list>
</div>
