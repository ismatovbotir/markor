<div>
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.label_marks')" :hint="__('label_marks.admin_hint')">
        <x-slot:actions>
            <x-admin.button variant="primary" icon="plus" :href="route('admin.label-marks.create')">{{ __('label_marks.add') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.toolbar>
        <x-admin.search model="search" :placeholder="__('label_marks.search')" />
        <x-admin.filter-tabs model="group" :value="$group" :options="$groups" />
        <x-admin.filter-toggle model="missing" :active="$missing" :count="$missingCount">{{ __('admin.list.missing') }}</x-admin.filter-toggle>
    </x-admin.toolbar>

    <x-admin.list :head="[__('label_marks.field_name'), __('common.status'), __('admin.list.langs'), __('admin.list.updated'), __('admin.list.actions')]">
        @forelse($marks as $m)
            <x-admin.list-row :href="route('admin.label-marks.edit', $m)" :muted="!$m->is_active || !$m->path" wire:key="mark-{{ $m->id }}">
                {{ $m->name_uz }}
                <x-slot:thumb>
                    <span class="w-10 h-10 rounded-lg border border-stone-200 bg-white flex items-center justify-center overflow-hidden">
                        @if($m->path)<img src="{{ $m->url() }}" alt="" class="w-8 h-8 object-contain">@else<span class="text-stone-500">@svg('heroicon-o-photo', 'w-5 h-5')</span>@endif
                    </span>
                </x-slot:thumb>
                <x-slot:meta>{{ __("labels.marks_group.$m->group") }} · <span class="font-mono">{{ $m->key }}</span>@unless($m->path) · <span class="text-amber-800">{{ __('label_marks.no_image') }}</span>@endunless</x-slot:meta>
                <x-slot:status><x-admin.status-pill :status="$m->is_active ? 'published' : 'hidden'" /></x-slot:status>
                <x-slot:langs><x-admin.lang-completeness :item="$m" :fields="\App\Domain\Printing\Models\LabelMark::TRANSLATABLE" /></x-slot:langs>
                <x-slot:updated><time datetime="{{ $m->updated_at?->toIso8601String() }}" title="{{ $m->updated_at?->format('d.m.Y H:i') }}">{{ $m->updated_at?->isToday() ? $m->updated_at->format('H:i') : $m->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    <a href="{{ route('admin.label-marks.edit', $m) }}" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</a>
                    <x-admin.row-menu>
                        <x-admin.menu-item wire:click="toggle({{ $m->id }})">{{ $m->is_active ? __('admin.list.hide') : __('admin.list.show') }}</x-admin.menu-item>
                    </x-admin.row-menu>
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            <x-admin.empty-state icon="magnifying-glass" :title="__('admin.list.none_found')" :text="__('admin.list.none_found_text')">
                <x-slot:action><x-admin.button wire:click="clearFilters">{{ __('admin.list.clear_filters') }}</x-admin.button></x-slot:action>
            </x-admin.empty-state>
        @endforelse
        @if($marks->isNotEmpty())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $marks->count(), 'total' => $total]) }} · {{ __('label_marks.no_delete') }}</x-slot:footer>
        @endif
    </x-admin.list>
</div>
