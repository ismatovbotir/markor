@php $field = 'w-full border border-stone-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400'; @endphp
<div>
    <div class="flex items-center justify-between mb-2 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('nav.product_groups') }}</h1>
        @can('create', \App\Domain\Catalog\Models\ProductGroup::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">+ {{ __('common.add') }}</button>
        @endcan
    </div>
    <p class="text-sm text-stone-500 mb-6">{{ __('product_groups.hint') }}</p>

    <div class="mb-4">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}"
               class="w-72 border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('product_groups.structure') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('product_groups.length') }}</th>
                    <th class="text-right px-4 py-3 font-medium text-stone-600">{{ __('nav.codes') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($groups as $group)
                    <tr class="hover:bg-stone-50" wire:key="group-{{ $group->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $group->name_uz }}
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[11px] font-normal {{ ($group->marking_system ?: 'aslbelgisi') === 'aslbelgisi' ? 'bg-stone-100 text-stone-600' : 'bg-sky-50 text-sky-800' }}">{{ \App\Domain\Marking\Support\MarkingSystem::label($group->marking_system) }}</span>
                            </div>
                            <div class="text-xs text-stone-500">{{ collect([$group->name_ru, $group->name_zh])->filter()->implode(' · ') }}</div>
                        </td>
                        <td class="px-4 py-3"><code class="text-xs bg-stone-100 px-1.5 py-0.5 rounded whitespace-nowrap">{{ $group->structureSummary() ?: '—' }}</code></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $group->code_length ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($counts[$group->id] ?? 0, 0, '.', ' ') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $group->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $group->is_active ? __('common.active') : __('common.inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            @can('update', $group)<button wire:click="openEdit({{ $group->id }})" class="text-stone-500 hover:text-amber-700 text-xs">{{ __('common.edit') }}</button>@endcan
                            @can('delete', $group)<button wire:click="delete({{ $group->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-stone-500 hover:text-rose-600 text-xs">{{ __('common.delete') }}</button>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $groups->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-start justify-center overflow-y-auto py-8 px-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('common.edit') : __('common.add') }} — {{ __('nav.product_groups') }}</h2>
                <form wire:submit="save" class="space-y-5">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('marking_system.field') }}</label>
                        <select wire:model="marking_system" class="{{ $field }} sm:max-w-xs">
                            @foreach(\App\Domain\Marking\Support\MarkingSystem::options() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('marking_system') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid sm:grid-cols-3 gap-3">
                        @foreach(['name_uz' => 'UZ', 'name_ru' => 'RU', 'name_zh' => '中文'] as $prop => $lang)
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('common.name') }} ({{ $lang }})</label>
                                <input wire:model="{{ $prop }}" type="text" class="{{ $field }}">
                                @error($prop) <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium">{{ __('product_groups.structure') }}</p>
                            <p class="text-xs text-stone-500">{{ __('product_groups.length') }}: <span class="font-mono font-semibold text-stone-800">{{ \App\Domain\Catalog\Models\ProductGroup::lengthOf(array_map(fn ($s) => ['ai' => (string) ($s['ai'] ?? ''), 'length' => (int) ($s['length'] ?? 0)], $segments)) }}</span></p>
                        </div>
                        <div class="border border-stone-200 rounded-lg overflow-hidden">
                            <div class="grid grid-cols-[2.5rem_6rem_1fr_6rem_8rem_5.5rem] gap-2 px-3 py-2 bg-stone-50 text-xs font-medium text-stone-600">
                                <span>#</span><span>AI</span><span>{{ __('product_groups.segment_label') }}</span><span>{{ __('product_groups.segment_length') }}</span><span>{{ __('product_groups.segment_gs') }}</span><span></span>
                            </div>
                            @foreach($segments as $i => $segment)
                                <div class="grid grid-cols-[2.5rem_6rem_1fr_6rem_8rem_5.5rem] gap-2 px-3 py-2 border-t border-stone-100 items-center" wire:key="seg-{{ $i }}">
                                    <span class="text-xs text-stone-400">{{ $i + 1 }}</span>
                                    <input wire:model.live.debounce.400ms="segments.{{ $i }}.ai" type="text" maxlength="4" placeholder="—" class="{{ $field }} font-mono">
                                    <input wire:model.blur="segments.{{ $i }}.label" type="text" class="{{ $field }}">
                                    <input wire:model.live.debounce.400ms="segments.{{ $i }}.length" type="number" min="1" max="90" class="{{ $field }} font-mono">
                                    <label class="flex items-center gap-2 text-xs"><input wire:model.live="segments.{{ $i }}.variable" type="checkbox" class="rounded border-stone-300 text-amber-600"> GS</label>
                                    <span class="flex items-center justify-end gap-1 text-stone-400">
                                        <button type="button" wire:click="moveSegment({{ $i }}, -1)" class="hover:text-stone-700" title="↑">@svg('heroicon-o-chevron-up', 'w-4 h-4')</button>
                                        <button type="button" wire:click="moveSegment({{ $i }}, 1)" class="hover:text-stone-700" title="↓">@svg('heroicon-o-chevron-down', 'w-4 h-4')</button>
                                        <button type="button" wire:click="removeSegment({{ $i }})" class="hover:text-rose-600" title="{{ __('common.delete') }}">@svg('heroicon-o-x-mark', 'w-4 h-4')</button>
                                    </span>
                                    @error("segments.$i.ai") <p class="col-span-6 text-rose-500 text-xs">{{ $message }}</p> @enderror
                                    @error("segments.$i.length") <p class="col-span-6 text-rose-500 text-xs">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <button type="button" wire:click="addSegment" class="text-xs text-amber-700 font-medium hover:underline">+ {{ __('product_groups.add_segment') }}</button>
                            <p class="text-[11px] text-stone-500">{{ __('product_groups.structure_hint') }}</p>
                        </div>
                        @error('segments') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('product_groups.sample') }}</label>
                        <textarea wire:model.live.debounce.500ms="sample_code" rows="2" spellcheck="false" class="{{ $field }} font-mono text-xs"></textarea>
                        @error('sample_code') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @if($test)
                            @if($test['fields'] !== null)
                                <div class="mt-2 flex gap-4 items-start border border-emerald-200 bg-emerald-50 rounded-lg p-3">
                                    @if($test['image'])<img src="{{ $test['image'] }}" alt="" class="w-24 h-24 bg-white p-1 rounded" style="image-rendering:pixelated">@endif
                                    <div class="text-xs space-y-1 min-w-0">
                                        <p class="text-emerald-700 font-medium">✓ {{ __('product_groups.sample_ok') }}</p>
                                        @foreach($segments as $s)
                                            @php $key = ($s['ai'] ?? '') === '' ? '_' : $s['ai']; @endphp
                                            <p class="font-mono break-all"><span class="text-stone-500">{{ $s['label'] ?: ($s['ai'] ?: '—') }}{{ ($s['ai'] ?? '') !== '' ? " ({$s['ai']})" : '' }}:</span> {{ $test['fields'][$key] ?? '' }}</p>
                                        @endforeach
                                        @isset($test['hri'])<p class="font-mono text-stone-500 break-all pt-1">{{ $test['hri'] }}</p>@endisset
                                    </div>
                                </div>
                            @else
                                <p class="mt-2 text-xs text-rose-600">✗ {{ __('product_groups.sample_mismatch') }} ({{ __('product_groups.length') }}: {{ $test['length'] }} / {{ $test['expected'] }})</p>
                            @endif
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-sm">
                        <label class="flex items-center gap-2"><input wire:model="is_active" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('common.active') }}</label>
                        <label class="flex items-center gap-2"><input wire:model="requires_eds" type="checkbox" class="rounded border-stone-300 text-amber-600"> {{ __('product_groups.requires_eds') }}</label>
                        <label class="flex items-center gap-2">{{ __('product_groups.order') }} <input wire:model="sort_order" type="number" min="0" class="w-20 {{ $field }}"></label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
