<div>
    <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
        <div>
            <p class="text-xs text-stone-500">{{ __('nav.offers') }}</p>
            <h1 class="text-xl font-semibold">{{ __('nav.offer_requests') }}</h1>
        </div>
        <div class="flex rounded-lg border border-stone-200 bg-white overflow-hidden text-sm">
            @foreach(['new' => __('requests.new') . " ($newCount)", 'done' => __('requests.done'), 'all' => __('common.all')] as $value => $label)
                <button wire:click="$set('status', '{{ $value }}')" class="px-3 py-1.5 {{ $status === $value ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('requests.item') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('auth.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('auth.phone') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('requests.quantity') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('requests.comment') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($requests as $r)
                    <tr class="{{ $r->status === 'done' ? 'opacity-60' : '' }}" wire:key="req-{{ $r->id }}">
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap">{{ $r->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3"><div class="font-medium">{{ $r->item_title }}</div><div class="text-xs text-stone-500">{{ $r->category }}</div></td>
                        <td class="px-4 py-3">{{ $r->name }}@if($r->user)<div class="text-xs text-stone-500">{{ $r->user->email }}</div>@endif</td>
                        <td class="px-4 py-3 whitespace-nowrap"><a href="tel:{{ preg_replace('/[^\d+]/', '', $r->phone) }}" class="text-amber-700 hover:underline">{{ $r->phone }}</a></td>
                        <td class="px-4 py-3">{{ $r->quantity ?: '—' }}</td>
                        <td class="px-4 py-3 text-stone-600 max-w-xs">{{ $r->comment ?: '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="toggleDone({{ $r->id }})" class="text-xs {{ $r->status === 'done' ? 'text-stone-500' : 'text-emerald-700 font-medium' }} hover:underline">
                                {{ $r->status === 'done' ? __('requests.reopen') : __('requests.mark_done') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</div>
