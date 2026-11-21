@php $statusColor = ['linked' => 'emerald', 'phone_mismatch' => 'rose', 'phone_ok' => 'sky', 'started' => 'amber', 'pending' => 'stone', 'failed' => 'rose', 'expired' => 'stone']; @endphp
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Telegram</h1>

    <p class="text-sm text-stone-500">{{ __('tg_admin.settings_moved') }} <a href="{{ route('admin.settings') }}" class="text-amber-700 hover:underline">{{ __('nav.settings') }} →</a></p>

    {{-- Lists --}}
    <div class="flex rounded-lg border border-stone-200 bg-white overflow-hidden text-sm w-fit">
        @foreach(['clients' => 'tg_admin.tab_clients', 'matched' => 'tg_admin.tab_matched', 'unmatched' => 'tg_admin.tab_unmatched', 'unfinished' => 'tg_admin.tab_unfinished'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="px-3 py-2 {{ $tab === $key ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:bg-stone-50' }}">
                {{ __($label) }} <span class="text-xs {{ $key === 'unmatched' && $counts[$key] ? 'text-rose-600 font-semibold' : 'text-stone-400' }}">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>
    <p class="text-xs text-stone-500 -mt-3">{{ __('tg_admin.hint_' . $tab) }}</p>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('auth.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('nav.companies') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('tg_admin.cabinet_phone') }}</th>
                    @if($tab !== 'clients')
                        <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('tg_admin.shared_phone') }}</th>
                        <th class="text-left px-4 py-3 font-medium text-stone-600">Telegram</th>
                        <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    @else
                        <th class="text-left px-4 py-3 font-medium text-stone-600">Chat ID</th>
                    @endif
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($rows as $row)
                    @php $person = $tab === 'clients' ? $row : $row->user; @endphp
                    <tr wire:key="tg-{{ $tab }}-{{ $row->id }}">
                        <td class="px-4 py-3"><div class="font-medium">{{ $person?->name ?? '—' }}</div><div class="text-xs text-stone-500">{{ $person?->email }}</div></td>
                        <td class="px-4 py-3">
                            @if($person?->company)<a href="{{ route('admin.companies.show', $person->company) }}" class="hover:text-amber-700">{{ $person->company->name }}</a>@else — @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $person?->phone ?: '—' }}</td>
                        @if($tab !== 'clients')
                            <td class="px-4 py-3 whitespace-nowrap {{ $row->status === 'phone_mismatch' ? 'text-rose-700 font-medium' : '' }}">
                                @if($row->shared_phone)<a href="tel:{{ $row->shared_phone }}" class="hover:underline">{{ $row->shared_phone }}</a>@else — @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $row->tg_name ?: '—' }}
                                @if($row->tg_username)<a href="https://t.me/{{ $row->tg_username }}" target="_blank" rel="noopener" class="block text-sky-700 hover:underline">{{ '@' . $row->tg_username }}</a>@endif
                            </td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor[$row->status] ?? 'stone' }}-100 text-{{ $statusColor[$row->status] ?? 'stone' }}-700">{{ __('tg_admin.status.' . $row->status) }}</span></td>
                        @else
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->telegram_chat_id }}</td>
                        @endif
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap">{{ $row->updated_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $rows->links() }}</div>
</div>
