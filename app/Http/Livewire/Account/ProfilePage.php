<?php

namespace App\Http\Livewire\Account;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Client cabinet: language, name/phone, password. Company details are edited on the company page. */
#[Layout('layouts.app')]
class ProfilePage extends Component
{
    public string $locale = 'uz';
    public string $name = '';
    public ?string $phone = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $passwordModal = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->locale = $user->locale ?: app()->getLocale();
        $this->name = $user->name;
        $this->phone = $user->phone;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'locale' => 'required|in:' . implode(',', array_keys(SetLocale::LOCALES)),
            'name'   => 'required|string|max:255',
            'phone'  => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{7,}$/'],
        ]);

        $changedLanguage = $data['locale'] !== auth()->user()->locale;
        auth()->user()->update($data);
        session(['locale' => $data['locale']]);

        if ($changedLanguage) {
            $this->redirectRoute('profile');   // reload the whole interface in the new language
            return;
        }
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function openPassword(): void
    {
        $this->resetValidation();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->passwordModal = true;
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => Hash::make($this->password)]);
        // other devices logged in with the old password are signed out
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', auth()->id())->where('id', '!=', session()->getId())->delete();
        auth()->user()->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
        $this->reset(['current_password', 'password', 'password_confirmation', 'passwordModal']);
        $this->dispatch('notify', type: 'success', message: __('profile.password_changed'));
    }

    public function render()
    {
        return view('livewire.account.profile-page', ['locales' => SetLocale::LOCALES, 'company' => auth()->user()->company]);
    }
}
