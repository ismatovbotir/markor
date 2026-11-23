<div class="max-w-5xl">
    <div class="flex items-center justify-between mb-2 gap-4 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('templates.mine_title') }}</h1>
        <span class="flex items-center gap-2">
            <a href="{{ route('templates.catalog') }}" class="text-sm text-stone-600 hover:text-amber-700">← {{ __('nav.templates') }}</a>
            <span class="text-xs text-stone-500 tabular-nums">{{ __('templates.slots', ['used' => $limit - $slotsLeft, 'limit' => $limit]) }}</span>
            <button type="button" wire:click="openCopy" @disabled($slotsLeft < 1) class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition disabled:opacity-40">+ {{ __('templates.mine_new') }}</button>
        </span>
    </div>
    <p class="text-sm text-stone-500 mb-6">{{ __('templates.mine_intro') }}</p>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-xs"><tr>
                <th class="text-left px-4 py-2 font-medium">{{ __('common.name') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ __('templates.size') }}</th>
                <th class="text-left px-4 py-2 font-medium">{{ __('templates.share_title') }}</th>
                <th class="px-4 py-2"></th>
            </tr></thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($templates as $t)
                    <tr wire:key="mine-{{ $t->id }}">
                        <td class="px-4 py-3 font-medium">{{ $t->name }}</td>
                        <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ $t->width_mm }}×{{ $t->height_mm }} mm</td>
                        <td class="px-4 py-3 text-xs">
                            @if($t->shared_at)
                                <span class="rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5">{{ __('templates.status_global') }}</span>
                            @elseif($t->share_consent)
                                <span class="rounded-full bg-sky-50 text-sky-800 px-2 py-0.5">{{ __('templates.status_consent') }}</span>
                            @else
                                <span class="rounded-full bg-stone-100 text-stone-600 px-2 py-0.5">{{ __('templates.status_private') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('templates.preview', $t) }}" target="_blank" rel="noopener" class="inline-flex p-2 rounded-lg text-stone-600 hover:bg-stone-100" title="{{ __('templates.preview_pdf') }}" aria-label="{{ __('templates.preview_pdf') }}: {{ $t->name }}">@svg('heroicon-o-eye', 'w-4 h-4')</a>
                            <button type="button" wire:click="openEdit({{ $t->id }})" class="px-2.5 py-1.5 rounded-lg text-amber-800 hover:bg-amber-50 font-medium">{{ __('common.edit') }}</button>
                            <button type="button" wire:click="openCopy({{ $t->id }})" class="px-2.5 py-1.5 rounded-lg text-stone-600 hover:bg-stone-100">{{ __('templates.copy') }}</button>
                            @can('delete', $t)
                                <button type="button" wire:click="delete({{ $t->id }})" wire:confirm="{{ __('templates.client_delete_text') }}" class="px-2.5 py-1.5 rounded-lg text-rose-600 hover:bg-rose-50">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-stone-500">{{ __('templates.mine_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($templates->hasPages())<div class="mt-4">{{ $templates->links() }}</div>@endif

    @include('livewire.printing.partials.template-editor', ['clientMode' => true])
</div>
