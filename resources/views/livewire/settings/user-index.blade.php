<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.users') }}</h1>
        @can('create', \App\Models\User::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('common.add') }}
            </button>
        @endcan
    </div>

    <div class="mb-4">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('common.search') }}"
               class="w-80 border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white">
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">Email</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('users.role') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($users as $user)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $user->role === 'admin' ? 'bg-violet-100 text-violet-700' : 'bg-stone-100 text-stone-600' }}">
                                {{ __("users.roles.$user->role") }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">
                                {{ $user->is_active ? __('common.active') : __('common.inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $user)
                                <button wire:click="openEdit({{ $user->id }})" class="text-stone-400 hover:text-amber-700 text-xs">{{ __('common.edit') }}</button>
                                <button wire:click="regenerateQr({{ $user->id }})" wire:confirm="{{ __('users.confirm_qr') }}" class="text-stone-400 hover:text-sky-600 text-xs">QR</button>
                            @endcan
                            @can('delete', $user)
                                <button wire:click="delete({{ $user->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-stone-400 hover:text-rose-600 text-xs">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('common.edit') : __('common.add') }} {{ __('nav.users') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('common.name') }}</label>
                        <input wire:model="name" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @error('name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">Email</label>
                        <input wire:model="email" type="email" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @error('email') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('auth.password') }} {{ $editingId ? '('.__('common.optional').')' : '' }}</label>
                        <input wire:model="password" type="password" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('users.role') }}</label>
                            <select wire:model="role" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="user">{{ __('users.roles.user') }}</option>
                                <option value="admin">{{ __('users.roles.admin') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('users.locale') }}</label>
                            <select wire:model="locale" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="ru">Русский</option>
                                <option value="zh">中文</option>
                                <option value="uz">O'zbek</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('users.phone') }}</label>
                        <input wire:model="phone" type="text" placeholder="+998 90 000 00 00" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="is_active" type="checkbox" class="rounded border-stone-300 text-amber-600">
                        {{ __('common.active') }}
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800">{{ __('common.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
