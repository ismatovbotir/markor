@php $statusColor = ['linked' => 'emerald', 'phone_mismatch' => 'rose', 'phone_ok' => 'sky', 'started' => 'amber', 'pending' => 'stone', 'failed' => 'rose', 'expired' => 'stone']; @endphp
<div class="space-y-6">
    <h1 class="text-xl font-semibold">Telegram</h1>

    {{-- Bot & webhook --}}
    <div class="bg-white border border-stone-200 rounded-xl p-5">
        @if(!$enabled)
            <p class="text-sm text-amber-800">{{ __('tg_admin.no_token') }}</p>
        @else
            <div class="flex items-start justify-between gap-6 flex-wrap">
                <div class="space-y-1 text-sm">
                    <p><span class="text-stone-500">{{ __('tg_admin.bot') }}:</span>
                        @if($bot) <a href="https://t.me/{{ $bot['username'] }}" target="_blank" rel="noopener" class="font-medium text-sky-700 hover:underline">{{ '@' . $bot['username'] }}</a> · {{ $bot['first_name'] }}
                        @else <span class="text-rose-600">{{ __('tg_admin.token_invalid') }}</span> @endif
                    </p>
                    <p><span class="text-stone-500">Webhook:</span>
                        @if(filled($webhook['url'] ?? null))
                            <span class="text-emerald-700 font-medium">{{ __('tg_admin.webhook_on') }}</span>
                            <span class="block font-mono text-xs text-stone-500 break-all">{{ $webhook['url'] }}</span>
                            @if(($webhook['pending_update_count'] ?? 0) > 0)<span class="text-xs text-stone-500">{{ __('tg_admin.pending', ['n' => $webhook['pending_update_count']]) }}</span>@endif
                            @if(filled($webhook['last_error_message'] ?? null))<span class="block text-xs text-rose-600">{{ __('tg_admin.last_error') }}: {{ $webhook['last_error_message'] }}</span>@endif
                        @else
                            <span class="text-stone-500">{{ __('tg_admin.webhook_off') }}</span>
                        @endif
                    </p>
                    @unless($publicUrl)
                        <p class="text-xs text-amber-700">{{ __('tg_admin.needs_https', ['url' => config('app.url')]) }}</p>
                    @endunless
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="refreshStatus" class="px-3 py-2 text-sm text-stone-600 hover:text-stone-900">{{ __('tg_admin.refresh') }}</button>
                    @if(filled($webhook['url'] ?? null))
                        <button wire:click="deleteWebhook" wire:confirm="{{ __('tg_admin.confirm_delete') }}" class="px-3 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">{{ __('tg_admin.delete_webhook') }}</button>
                    @endif
                    <button wire:click="setWebhook" @disabled(!$publicUrl) class="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-medium hover:bg-sky-700 disabled:opacity-40">{{ __('tg_admin.set_webhook') }}</button>
                </div>
            </div>
            @if($message)<p class="text-sm mt-3 {{ str_contains($message, '✓') ? 'text-emerald-700' : 'text-stone-700' }}">{{ $message }}</p>@endif
        @endif
    </div>

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
