<?php

namespace App\Http\Livewire\Account;

use App\Domain\Reporting\AuditLog;
use App\Services\Aslbelgi\AslbelgiClient;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Company page, owner only: the company's Asl Belgisi business-user API key (read-only use).
 * The key is checked against the TIN, stored encrypted and never shown again (only its last 4 characters).
 */
class AslbelgiApiKey extends Component
{
    public string $apiKey = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->isCompanyOwner(), 403);
    }

    public function save(): void
    {
        $this->apiKey = trim($this->apiKey);
        $this->validate(['apiKey' => ['required', 'string', 'min:20', 'max:200', 'regex:/^[A-Za-z0-9\-_.]+$/']]);

        $key = 'aslbelgi-key-check:' . auth()->user()->company_id;
        if (!\Illuminate\Support\Facades\RateLimiter::attempt($key, 10, fn () => true, 3600)) {
            $this->addError('apiKey', __('auth.throttle', ['seconds' => \Illuminate\Support\Facades\RateLimiter::availableIn($key)]));
            return;
        }

        $company = auth()->user()->company;
        $check = (new AslbelgiClient($this->apiKey))->checkKey($company->tin);
        if (!$check['ok']) {
            $this->addError('apiKey', in_array($check['status'], [401, 403], true) ? __('aslbelgi_key.invalid') : __('aslbelgi_key.unreachable'));
            return;
        }
        if (!$check['belongs']) {
            $this->addError('apiKey', __('aslbelgi_key.wrong_company', ['tin' => $company->tin]));
            return;
        }

        $company->forceFill([
            'aslbelgi_api_key' => $this->apiKey,
            'aslbelgi_api_key_expires_at' => $check['expires_on'] ? Carbon::parse($check['expires_on']) : null,
            'aslbelgi_api_key_checked_at' => now(),
        ])->save();
        AuditLog::record('company.aslbelgi_key_saved', 'company', $company->id);
        $this->reset('apiKey');
        $this->dispatch('notify', type: 'success', message: __('aslbelgi_key.saved'));
    }

    public function remove(): void
    {
        $company = auth()->user()->company;
        $company->forceFill(['aslbelgi_api_key' => null, 'aslbelgi_api_key_expires_at' => null, 'aslbelgi_api_key_checked_at' => null])->save();
        AuditLog::record('company.aslbelgi_key_removed', 'company', $company->id);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        $company = auth()->user()->company;

        return view('livewire.account.aslbelgi-api-key', [
            'hasKey'    => $company->hasAslbelgiKey(),
            'last4'     => $company->hasAslbelgiKey() ? substr($company->aslbelgi_api_key, -4) : null,
            'expiresAt' => $company->aslbelgi_api_key_expires_at,
        ]);
    }
}
