@php $colors = ['new' => 'amber', 'done' => 'emerald', 'rejected' => 'stone']; @endphp
<div>
    <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.template_requests') }}</h1>
        <div class="flex rounded-lg border border-stone-200 bg-white overflow-hidden text-sm">
            @foreach(['new' => __('template_requests.status.new') . " ($newCount)", 'done' => __('template_requests.status.done'), 'rejected' => __('template_requests.status.rejected'), 'all' => __('common.all')] as $value => $label)
                <button wire:click="$set('status', '{{ $value }}')" class="px-3 py-1.5 {{ $status === $value ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.companies') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('templates.size') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('template_requests.comment') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($requests as $r)
                    <tr wire:key="tr-{{ $r->id }}">
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap">{{ $r->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.companies.show', $r->company_id) }}" class="font-medium hover:text-amber-700">{{ $r->company?->name }}</a><div class="text-xs text-stone-500">{{ $r->user?->name }} · {{ $r->user?->phone }}</div></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $r->size() }}@if($r->gap_mm !== null)<div class="text-xs text-stone-500">{{ __('template_requests.gap') }}: {{ rtrim(rtrim(number_format($r->gap_mm, 1, '.', ''), '0'), '.') }} mm</div>@endif</td>
                        <td class="px-4 py-3 text-stone-600 max-w-sm">{{ $r->comment ?: '—' }}@if($r->admin_note)<div class="text-xs text-stone-400 mt-0.5">↳ {{ $r->admin_note }}</div>@endif @if($r->template)<div class="text-xs text-emerald-700">→ {{ $r->template->name }}</div>@endif</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-{{ $colors[$r->status] }}-100 text-{{ $colors[$r->status] }}-700">{{ __('template_requests.status.' . $r->status) }}</span></td>
                        <td class="px-4 py-3 text-right"><button wire:click="open({{ $r->id }})" class="text-xs font-semibold text-amber-700 hover:underline">{{ __('template_requests.answer') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>

    @if($answering)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ __('template_requests.answer') }}</h2>
                <label class="block text-xs font-medium text-stone-600">{{ __('template_requests.ready_template') }}
                    <select wire:model="templateId" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm mt-1">
                        <option value="">—</option>
                        @foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->width_mm }}×{{ $t->height_mm }})</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs font-medium text-stone-600">{{ __('template_requests.note') }}
                    <textarea wire:model="note" rows="3" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm mt-1"></textarea>
                </label>
                <p class="text-[11px] text-stone-500">{{ __('template_requests.answer_hint') }}</p>
                <div class="flex justify-between gap-2 pt-1">
                    <button wire:click="answer('rejected')" class="px-4 py-2 text-sm text-rose-600 hover:text-rose-800">{{ __('template_requests.reject') }}</button>
                    <span class="flex gap-2">
                        <button wire:click="$set('answering', null)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                        <button wire:click="answer('done')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">{{ __('template_requests.done') }}</button>
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
