{{-- Side card of an edit page (aside slot of x-admin.i18n-form): visibility switch, filled fields per language, "view on site".
     model: boolean Livewire property; date: Livewire property with the publish date (a future date shows "scheduled"). Slot: extra rows. --}}
@props(['model' => 'item.is_active', 'date' => null, 'viewUrl' => null])
@php $on = "\$wire.\$get('$model')"; $later = $date ? "(new Date(\$wire.\$get('$date')) > new Date())" : 'false'; @endphp
<div {{ $attributes->class('bg-white border border-stone-200 rounded-xl p-4 space-y-3') }}>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-stone-900">{{ __('admin.publish.title') }}</h2>
        <span x-show="{{ $on }} && !{{ $later }}" x-cloak><x-admin.status-pill status="published" /></span>
        <span x-show="{{ $on }} && {{ $later }}" x-cloak><x-admin.status-pill status="scheduled" /></span>
        <span x-show="!{{ $on }}" x-cloak><x-admin.status-pill status="hidden" /></span>
    </div>
    <x-admin.switch :model="$model" :label="__('admin.publish.show')" />
    {{ $slot }}
    <dl class="text-sm divide-y divide-stone-100 border-t border-stone-100">
        @foreach(\App\Support\AdminI18n::tabs() as $l => $tab)
            <div class="flex items-center justify-between gap-3 py-1.5">
                <dt class="text-stone-600">{{ $tab['name'] }}</dt>
                <dd class="tabular-nums flex items-center gap-1.5" :class="state('{{ $l }}') === 'error' ? 'text-rose-700' : (state('{{ $l }}') === 'complete' ? 'text-emerald-700' : 'text-stone-700')">
                    <span x-text="count('{{ $l }}') + ' / ' + total()"></span>
                    <button type="button" x-show="lang !== '{{ $l }}'" x-on:click="lang = '{{ $l }}'" class="admin-focus rounded text-xs text-amber-800 hover:underline">{{ __('admin.publish.open') }}</button>
                </dd>
            </div>
        @endforeach
    </dl>
    @if($viewUrl)
        <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="admin-focus rounded inline-flex items-center gap-1 text-sm text-amber-800 hover:underline">{{ __('admin.view_on_site') }} @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')</a>
    @endif
</div>
