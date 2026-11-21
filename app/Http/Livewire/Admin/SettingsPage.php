<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Settings\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: contacts shown on the site and used to receive order requests. */
#[Layout('layouts.app')]
class SettingsPage extends Component
{
    public array $contact = [];
    public ?string $cardNumber = null;
    public ?string $cardHolder = null;

    public function mount(): void
    {
        $this->contact = [
            'phone'        => config('contact.phone'),
            'telegram'     => config('contact.telegram'),
            'telegram_bot' => config('contact.telegram_bot'),
            'email'        => config('contact.email'),
        ];
        $this->cardNumber = config('billing.card_number');
        $this->cardHolder = config('billing.card_holder');
    }

    public function save(): void
    {
        foreach (['telegram', 'telegram_bot'] as $k) {
            $this->contact[$k] = ltrim(preg_replace('#^https?://t\.me/#', '', trim((string) ($this->contact[$k] ?? ''))), '@');
        }

        $data = $this->validate([
            'contact.phone'        => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{7,}$/'],
            'contact.telegram'     => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]{4,}$/'],
            'contact.telegram_bot' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]{4,}bot$/i'],
            'contact.email'        => ['nullable', 'email', 'max:120'],
        ])['contact'];

        $this->cardNumber = filled($this->cardNumber) ? preg_replace('/\D/', '', $this->cardNumber) : null;
        $this->validate(['cardNumber' => ['nullable', 'digits:16'], 'cardHolder' => ['nullable', 'string', 'max:80']]);
        Setting::put(collect($data)->mapWithKeys(fn ($v, $k) => ["contact.$k" => $v])->all() + ['billing.card_number' => $this->cardNumber, 'billing.card_holder' => $this->cardHolder]);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function render()
    {
        return view('livewire.admin.settings-page');
    }
}
