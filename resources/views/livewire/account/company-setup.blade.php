@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white disabled:bg-stone-100 disabled:text-stone-500'; @endphp
<div class="w-full">
    @unless($editing)
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('company.step') }}</p>
    @endunless
    <h1 class="text-xl font-semibold mt-1">{{ $editing ? __('company.title_edit') : __('company.title') }}</h1>
    <p class="text-sm text-stone-500 mt-1 mb-6">{{ $editing ? __('company.hint_edit') : __('company.hint') }}</p>

    <form wire:submit="save" class="bg-white border border-stone-200 rounded-xl p-5 space-y-5">
        <div>
            <label for="tin" class="block text-xs font-medium text-stone-600 mb-1">{{ __('company.tin') }}</label>
            <div class="flex gap-2">
                <input id="tin" wire:model="tin" type="text" inputmode="numeric" maxlength="14" placeholder="301234567" @disabled($editing) autofocus
                       class="{{ $field }} font-mono text-base tracking-wider max-w-xs">
                @unless($editing)
                    <button type="button" wire:click="lookup" wire:loading.attr="disabled" class="px-4 py-2 bg-stone-900 text-white rounded-lg text-sm font-medium hover:bg-stone-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="lookup">{{ __('company.check') }}</span>
                        <span wire:loading wire:target="lookup">{{ __('common.loading') }}</span>
                    </button>
                @endunless
            </div>
            <p class="text-[11px] text-stone-500 mt-1">{{ __('company.tin_hint') }}</p>
            @error('tin') <p class="text-rose-500 text-xs mt-1">{{ $message === __('validation.unique', ['attribute' => 'tin']) ? __('company.tin_taken') : $message }}</p> @enderror
            @if($lookupMessage === 'found')
                <p class="text-sm text-emerald-700 mt-2 flex items-center gap-1.5">@svg('heroicon-o-check-circle', 'w-4 h-4') {{ __('company.found') }}</p>
            @elseif($lookupMessage === 'not_found')
                <p class="text-sm text-amber-700 mt-2">{{ __('company.not_found') }}</p>
            @elseif($lookupMessage === 'manual')
                <p class="text-sm text-stone-600 mt-2">{{ __('company.manual') }}</p>
            @endif
        </div>

        @if($lookedUp || $editing)
            <div class="grid sm:grid-cols-2 gap-4 pt-4 border-t border-stone-100">
                @foreach([
                    'name' => ['company.name', 'sm:col-span-2', 'organization'],
                    'legal_address' => ['company.legal_address', 'sm:col-span-2', 'street-address'],
                    'director' => ['company.director', '', 'name'],
                    'phone' => ['auth.phone', '', 'tel'],
                    'email' => ['auth.email', '', 'email'],
                    'vat_code' => ['company.vat_code', '', 'off'],
                ] as $key => [$label, $span, $autocomplete])
                    <div class="{{ $span }}">
                        <label class="block text-xs font-medium text-stone-600 mb-1">{{ __($label) }}@if($key !== 'name') <span class="text-stone-400">({{ __('common.optional') }})</span>@endif</label>
                        <input wire:model="company.{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" autocomplete="{{ $autocomplete }}" class="{{ $field }} {{ $key === 'vat_code' ? 'font-mono' : '' }}">
                        @error("company.$key") <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 transition">{{ __('common.save') }}</button>
            </div>
        @endif
    </form>

    @if($editing)
        <div class="mt-8">
            @livewire(\App\Http\Livewire\Account\CompanyUsers::class, ['embedded' => true])
        </div>
    @endif
    @if(auth()->user()->isCompanyOwner() && auth()->user()->company)
        @livewire(\App\Http\Livewire\Account\AslbelgiApiKey::class)
    @endif
</div>
