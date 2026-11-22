<div>
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.drivers')" :hint="__('drivers.admin_hint')" :view-url="route('drivers')" view-label="/drivers">
        <x-slot:actions>
            <x-admin.button variant="primary" icon="plus" :href="route('admin.drivers.create')">{{ __('drivers.add') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.toolbar>
        <x-admin.search model="search" />
        <x-admin.filter-tabs model="status" :value="$status" :options="$statuses" />
        <x-admin.filter-toggle model="missing" :active="$missing" :count="$missingCount">{{ __('admin.list.missing') }}</x-admin.filter-toggle>
    </x-admin.toolbar>

    <x-admin.list :head="[__('drivers.driver_name'), __('common.status'), __('admin.list.langs'), __('admin.list.updated'), __('admin.list.actions')]">
        @forelse($drivers as $d)
            <x-admin.list-row :href="route('admin.drivers.edit', $d)" :muted="!$d->is_active" wire:key="driver-{{ $d->id }}">
                {{ $d->name() }}
                <x-slot:meta>
                    <span class="block truncate">{{ __('drivers.kinds.' . $d->kind) }}@if($d->language) · {{ $d->language }}@endif · {{ $d->host() }}</span>
                    <span class="block truncate">@if($d->offers->isNotEmpty()){{ __('drivers.products') }}: {{ $d->offers->pluck('title_uz')->join(', ') }}@else{{ __('drivers.no_products') }}@endif</span>
                </x-slot:meta>
                <x-slot:status><x-admin.status-pill :status="$d->is_active ? 'published' : 'hidden'" /></x-slot:status>
                <x-slot:langs><x-admin.lang-completeness :item="$d" :fields="\App\Domain\Content\PrinterDriver::TRANSLATABLE" /></x-slot:langs>
                <x-slot:updated><time datetime="{{ $d->updated_at?->toIso8601String() }}" title="{{ $d->updated_at?->format('d.m.Y H:i') }}">{{ $d->updated_at?->isToday() ? $d->updated_at->format('H:i') : $d->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    @if($d->is_active)
                        <a href="{{ route('drivers.show', $d) }}" target="_blank" rel="noopener" aria-label="{{ __('admin.view_on_site') }}" title="{{ __('admin.view_on_site') }}"
                           class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')</a>
                    @endif
                    <a href="{{ route('admin.drivers.edit', $d) }}" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</a>
                    <x-admin.row-menu>
                        <x-admin.menu-item wire:click="toggle({{ $d->id }})">{{ $d->is_active ? __('admin.list.hide') : __('admin.list.show') }}</x-admin.menu-item>
                        <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('drivers.delete_title')) }}, name: {{ Js::from($d->name()) }}, text: {{ Js::from(__('drivers.delete_text')) }}, run: () => $wire.delete({{ $d->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                    </x-admin.row-menu>
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            @if($total)
                <x-admin.empty-state icon="magnifying-glass" :title="__('admin.list.none_found')" :text="__('admin.list.none_found_text')">
                    <x-slot:action><x-admin.button wire:click="clearFilters">{{ __('admin.list.clear_filters') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @else
                <x-admin.empty-state icon="arrow-down-tray" :title="__('drivers.empty')" :text="__('drivers.empty_admin_text')">
                    <x-slot:action><x-admin.button variant="primary" icon="plus" :href="route('admin.drivers.create')">{{ __('drivers.add') }}</x-admin.button></x-slot:action>
                </x-admin.empty-state>
            @endif
        @endforelse
        @if($drivers->isNotEmpty())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $drivers->count(), 'total' => $total]) }}</x-slot:footer>
        @endif
    </x-admin.list>
</div>
