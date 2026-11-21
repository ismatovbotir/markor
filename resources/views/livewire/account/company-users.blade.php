@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div class="w-full">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-2">
        <div>
            <p class="text-xs text-stone-500">{{ $company?->name }}</p>
            <h1 class="text-xl font-semibold">{{ __('nav.company_users') }}</h1>
        </div>
        @if($isOwner)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">+ {{ __('company_users.add') }}</button>
        @endif
    </div>
    <p class="text-sm text-stone-500 mb-6">{{ __('company_users.hint') }}</p>

    <div class="bg-white border border-stone-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('auth.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('auth.phone') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('company_users.role') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">Telegram</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'opacity-50' }}" wire:key="cu-{{ $u->id }}">
                        <td class="px-4 py-3"><div class="font-medium">{{ $u->name }} @if($u->id === auth()->id())<span class="text-xs text-stone-400">({{ __('company_users.you') }})</span>@endif</div><div class="text-xs text-stone-500">{{ $u->email }}</div></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $u->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ __('company_users.roles.' . ($u->company_role ?: 'member')) }}</td>
                        <td class="px-4 py-3">@if($u->telegram_chat_id)<span class="text-emerald-700 text-xs">{{ __('telegram.connected') }}</span>@else<span class="text-stone-400 text-xs">—</span>@endif</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">{{ $u->is_active ? __('common.active') : __('common.inactive') }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                            @if($isOwner && $u->id !== auth()->id())
                                <button wire:click="openEdit({{ $u->id }})" class="text-xs text-stone-500 hover:text-amber-700">{{ __('common.edit') }}</button>
                                <button wire:click="toggleActive({{ $u->id }})" class="text-xs text-stone-500 hover:text-amber-700">{{ $u->is_active ? __('company_users.block') : __('company_users.unblock') }}</button>
                                @if($u->company_role !== 'owner')
                                    <button wire:click="remove({{ $u->id }})" wire:confirm="{{ __('company_users.confirm_remove') }}" class="text-xs text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4">
            <form wire:submit="save" class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ $editingId ? __('common.edit') : __('company_users.add') }}</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach(['name' => ['auth.name', 'text'], 'email' => ['auth.email', 'email'], 'phone' => ['auth.phone', 'tel'], 'password' => [$editingId ? 'company_users.new_password' : 'auth.password', 'text']] as $key => [$label, $type])
                        <label class="block text-xs font-medium text-stone-600">{{ __($label) }}
                            <input wire:model="form.{{ $key }}" type="{{ $type }}" @if($key === 'password') autocomplete="new-password" @endif class="{{ $field }} mt-1 {{ $key === 'password' ? 'font-mono' : '' }}">
                            @error("form.$key") <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                    <label class="block text-xs font-medium text-stone-600">{{ __('users.locale') }}
                        <select wire:model="form.locale" class="{{ $field }} mt-1">
                            @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <p class="text-xs text-stone-500">{{ $editingId ? __('company_users.password_keep') : __('company_users.password_hint') }}</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
