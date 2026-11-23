@php use App\Domain\Printing\Models\LabelTemplate; @endphp
<div>
    @php $labelsCategory = \App\Domain\Offers\Models\OfferCategory::where('kind', 'templates')->first(); @endphp
    <x-admin.page-header :crumb="__('nav.group.content')" :title="__('nav.templates')" :hint="__('templates.admin_hint')">
        <x-slot:actions>
            @if($labelsCategory)
                {{-- short label; a missing shop link is an amber dot with a tooltip instead of extra text --}}
                <x-admin.button icon="cog-6-tooth" :href="route('admin.offers', [$labelsCategory, 'tab' => 'settings'])"
                                :title="$labelsCategory->market_url ? null : __('offers.market_missing')">
                    {{ __('offers.category_settings') }}
                    @unless($labelsCategory->market_url)
                        <span class="inline-block align-middle ml-1 w-2 h-2 rounded-full bg-amber-500" aria-hidden="true"></span><span class="sr-only">— {{ __('offers.market_missing') }}</span>
                    @endunless
                </x-admin.button>
            @endif
            @can('create', LabelTemplate::class)
                <x-admin.button variant="primary" icon="plus" wire:click="openCopy">{{ __('templates.new_from_default') }}</x-admin.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- owner filter: all / made by the super admin / made by clients --}}
    <div class="flex flex-wrap gap-2 mb-3 text-sm" role="group" aria-label="{{ __('templates.owner') }}">
        @foreach(['' => __('templates.owner_all'), 'global' => __('templates.owner_global'), 'clients' => __('templates.owner_clients'), 'deleted' => __('templates.owner_deleted')] as $key => $label)
            <button type="button" wire:click="$set('owner', @js($key))" aria-pressed="{{ $owner === $key ? 'true' : 'false' }}"
                    class="admin-focus px-3 py-1.5 rounded-full border {{ $owner === $key ? 'bg-amber-100 border-amber-300 text-amber-900' : 'bg-white border-stone-200 text-stone-600 hover:bg-stone-100' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- names are in one language for now, so the languages column stays empty --}}
    <x-admin.list wide :head="[__('common.name'), __('templates.size'), '', __('admin.list.updated'), __('admin.list.actions')]">
        @forelse($templates as $t)
            <x-admin.list-row wire:key="tpl-{{ $t->id }}">
                {{ $t->name }}
                <x-slot:meta>
                    @if($t->is_default)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-800 ring-1 ring-amber-300 px-1.5 py-0.5 text-[11px] font-medium" title="{{ __('templates.default_hint') }}">@svg('heroicon-o-star', 'w-3 h-3') {{ __('templates.default') }}</span>
                    @endif
                    @if(($t->columns ?: 1) > 1)<span>{{ $t->columns }}{{ __('templates.per_row_short') }}</span>@endif
                    @if($t->company_id)
                        <span class="inline-flex items-center gap-1 text-stone-600">@svg('heroicon-o-building-office', 'w-3 h-3') {{ $t->company?->name ?? '#' . $t->company_id }}</span>
                        @if($t->client_deleted_at && !$t->shared_at)
                            <span class="rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-200 px-1.5 py-0.5 text-[11px] font-medium" title="{{ $t->client_deleted_at->format('d.m.Y H:i') }}">{{ __('templates.status_deleted') }}</span>
                        @endif
                        @if($t->shared_at)
                            <span class="rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-1.5 py-0.5 text-[11px] font-medium">{{ __('templates.status_global') }}</span>
                        @elseif($t->share_consent)
                            <span class="rounded-full bg-sky-50 text-sky-800 ring-1 ring-sky-200 px-1.5 py-0.5 text-[11px] font-medium">{{ __('templates.status_consent') }}</span>
                        @else
                            <span class="rounded-full bg-stone-100 text-stone-600 px-1.5 py-0.5 text-[11px]">{{ __('templates.status_private') }}</span>
                        @endif
                    @endif
                </x-slot:meta>
                <x-slot:status><span class="text-sm text-stone-700 tabular-nums whitespace-nowrap">{{ $t->width_mm }}×{{ $t->height_mm }} mm</span></x-slot:status>
                <x-slot:updated><time datetime="{{ $t->updated_at?->toIso8601String() }}" title="{{ $t->updated_at?->format('d.m.Y H:i') }}">{{ $t->updated_at?->isToday() ? $t->updated_at->format('H:i') : $t->updated_at?->format('d.m.Y') }}</time></x-slot:updated>
                <x-slot:actions>
                    <a href="{{ route('label-templates.preview', $t) }}" target="_blank" rel="noopener" aria-label="{{ __('templates.preview_pdf') }}: {{ $t->name }}" title="{{ __('templates.preview_pdf') }}"
                       class="admin-focus p-2 rounded-lg text-stone-600 hover:bg-stone-200">@svg('heroicon-o-eye', 'w-4 h-4')</a>
                    @can('update', $t)
                        <button type="button" wire:click="openEdit({{ $t->id }})" class="admin-focus px-2.5 py-1.5 rounded-lg text-sm text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</button>
                    @endcan
                    @if($t->company_id || auth()->user()->can('create', LabelTemplate::class) || auth()->user()->can('delete', $t))
                        <x-admin.row-menu>
                            @if($t->company_id && $t->shared_at)
                                <x-admin.menu-item wire:click="share({{ $t->id }}, false)">{{ __('templates.unpublish') }}</x-admin.menu-item>
                            @elseif($t->company_id)
                                @can('publish', $t)
                                    <x-admin.menu-item wire:click="share({{ $t->id }}, true)">{{ $t->client_deleted_at ? __('templates.accept_global') : __('templates.publish') }}</x-admin.menu-item>
                                @else
                                    <span class="block px-3 py-2 text-xs text-stone-400">{{ __('templates.publish_needs_consent') }}</span>
                                @endcan
                            @endif
                            @can('create', LabelTemplate::class)
                                <x-admin.menu-item wire:click="openCopy({{ $t->id }})">{{ __('templates.copy') }}</x-admin.menu-item>
                            @endcan
                            @can('delete', $t)
                                <x-admin.menu-item danger x-on:click="$dispatch('confirm-delete', { title: {{ Js::from(__('templates.delete_title')) }}, name: {{ Js::from($t->name) }}, text: {{ Js::from(__('templates.delete_text')) }}, run: () => $wire.delete({{ $t->id }}) })">{{ __('common.delete') }}…</x-admin.menu-item>
                            @endcan
                        </x-admin.row-menu>
                    @endif
                </x-slot:actions>
            </x-admin.list-row>
        @empty
            <x-admin.empty-state icon="document-duplicate" :title="__('templates.empty_admin')" :text="__('templates.empty_admin_text')" />
        @endforelse
        @if($templates->total())
            <x-slot:footer>{{ __('admin.list.count', ['shown' => $templates->count(), 'total' => $templates->total()]) }}</x-slot:footer>
        @endif
    </x-admin.list>
    @if($templates->hasPages())<div class="mt-4">{{ $templates->links() }}</div>@endif

    @include('livewire.printing.partials.template-editor', ['clientMode' => false])
</div>
