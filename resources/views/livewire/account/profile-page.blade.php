@php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
<div class="w-full space-y-6">
    <h1 class="text-xl font-semibold">{{ __('nav.profile') }}</h1>

    <form wire:submit="saveProfile" class="bg-white border border-stone-200 rounded-xl p-5 space-y-4">
        <div>
            <p class="block text-xs font-medium text-stone-600 mb-2">{{ __('users.locale') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach($locales as $code => $label)
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer text-sm {{ $locale === $code ? 'border-amber-600 bg-amber-50 font-medium' : 'border-stone-200 hover:bg-stone-50' }}">
                        <input type="radio" wire:model.live="locale" value="{{ $code }}" class="text-amber-600"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="text-[11px] text-stone-500 mt-1">{{ __('profile.locale_hint') }}</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <label class="block text-xs font-medium text-stone-600">{{ __('auth.name') }}
                <input wire:model="name" type="text" class="{{ $field }} mt-1">
                @error('name') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="block text-xs font-medium text-stone-600">{{ __('auth.phone') }}
                <input wire:model="phone" type="tel" class="{{ $field }} mt-1">
                @error('phone') <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
            </label>
        </div>
        <p class="text-xs text-stone-500">{{ __('auth.email') }}: {{ auth()->user()->email }}</p>
        <div class="flex justify-end"><button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">{{ __('common.save') }}</button></div>
    </form>

    @if($company)
        <div class="bg-white border border-stone-200 rounded-xl p-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-xs text-stone-500">{{ __('company.title_edit') }}</p>
                <p class="font-medium">{{ $company->name }}</p>
                <p class="text-xs text-stone-500 font-mono">{{ __('company.tin') }}: {{ $company->tin }}</p>
                <p class="text-xs text-stone-500 mt-1 flex items-center gap-1">@svg('heroicon-o-users', 'w-3.5 h-3.5') {{ __('company.users_count', ['count' => $company->users()->count()]) }}</p>
            </div>
            <a href="{{ route('company.setup') }}" class="text-sm text-amber-700 font-medium hover:underline whitespace-nowrap">{{ auth()->user()->isCompanyOwner() ? __('company.manage') : __('company.open') }} →</a>
        </div>
    @endif

    @livewire(\App\Http\Livewire\Account\TelegramConnect::class)

    {{-- password: only a button here; the fields are in a window so it can't be changed by accident --}}
    <div class="bg-white border border-stone-200 rounded-xl p-5 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="font-semibold text-sm">{{ __('profile.change_password') }}</h2>
            <p class="text-xs text-stone-500 mt-0.5">{{ __('profile.password_hint') }}</p>
        </div>
        <button type="button" wire:click="openPassword" class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-100">{{ __('profile.change_password') }}</button>
    </div>

    @if($passwordModal)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" wire:keydown.escape="$set('passwordModal', false)">
            <form wire:submit="savePassword" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h2 class="text-lg font-semibold">{{ __('profile.change_password') }}</h2>
                @foreach(['current_password' => 'profile.current_password', 'password' => 'profile.new_password', 'password_confirmation' => 'auth.password_confirm'] as $prop => $label)
                    <label class="block text-xs font-medium text-stone-600">{{ __($label) }}
                        <input wire:model="{{ $prop }}" type="password" autocomplete="{{ $prop === 'current_password' ? 'current-password' : 'new-password' }}" class="{{ $field }} mt-1">
                        @error($prop) <span class="text-rose-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                @endforeach
                <p class="text-[11px] text-stone-500">{{ __('profile.password_other_devices') }}</p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="$set('passwordModal', false)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
