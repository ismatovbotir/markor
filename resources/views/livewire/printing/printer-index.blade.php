<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ __('nav.printers') }}</h1>
        @can('create', \App\Domain\Printing\Models\Printer::class)
            <button wire:click="openCreate" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">
                + {{ __('common.add') }}
            </button>
        @endcan
    </div>

    <div class="bg-white border border-stone-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.name') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('printers.type') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('printers.connection') }}</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">Host:Port</th>
                    <th class="text-left px-4 py-3 font-medium text-stone-600">{{ __('common.status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($printers as $printer)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3 font-medium">{{ $printer->name }}</td>
                        <td class="px-4 py-3"><code class="text-xs bg-stone-100 px-1 py-0.5 rounded">{{ $printer->type }}</code></td>
                        <td class="px-4 py-3 text-stone-500">{{ $printer->connection_mode }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-stone-500">
                            {{ $printer->host ? "{$printer->host}:{$printer->port}" : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $printer->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $printer->is_active ? __('common.active') : __('common.inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @can('update', $printer)
                                <button wire:click="openEdit({{ $printer->id }})" class="text-stone-400 hover:text-amber-700 text-xs">{{ __('common.edit') }}</button>
                            @endcan
                            @can('delete', $printer)
                                <button wire:click="delete({{ $printer->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="text-stone-400 hover:text-rose-600 text-xs">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">{{ __('common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $printers->links() }}</div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('common.edit') : __('common.add') }} {{ __('nav.printers') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('common.name') }}</label>
                        <input wire:model="name" type="text" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        @error('name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('printers.type') }}</label>
                            <select wire:model="type" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="pdf_browser">PDF Browser</option>
                                <option value="zpl_zebra">ZPL Zebra</option>
                                <option value="ezpl_godex">EZPL Godex</option>
                                <option value="sbpl_bixolon">SBPL Bixolon</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-600 mb-1">{{ __('printers.connection') }}</label>
                            <select wire:model.live="connection_mode" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="browser">Browser</option>
                                <option value="network">Network TCP</option>
                            </select>
                        </div>
                    </div>
                    @if($connection_mode === 'network')
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-stone-600 mb-1">Host</label>
                                <input wire:model="host" type="text" placeholder="192.168.1.100" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-600 mb-1">Port</label>
                                <input wire:model="port" type="number" class="w-full border border-stone-200 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400">
                            </div>
                        </div>
                    @endif
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
