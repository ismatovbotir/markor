<?php

namespace App\Http\Livewire\Account;

use App\Domain\Companies\Company;
use App\Domain\Companies\CompanyLookup;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * First step after registration: the client registers their company, tax number first.
 * Used again from the profile to edit company details (tax number is then fixed).
 */
#[Layout('layouts.app')]
class CompanySetup extends Component
{
    public string $tin = '';
    public array $company = [];
    #[\Livewire\Attributes\Locked] public ?string $lookupMessage = null; // set only by lookup(), never from the browser
    public bool $lookedUp = false;
    #[\Livewire\Attributes\Locked] public ?array $lookupData = null;

    public const FIELDS = ['name', 'legal_address', 'director', 'phone', 'email', 'vat_code'];

    public function mount(): void
    {
        $existing = auth()->user()->company;
        $this->tin = (string) ($existing->tin ?? '');
        $this->company = $existing ? $existing->only(self::FIELDS) : array_fill_keys(self::FIELDS, '');
        $this->lookedUp = (bool) $existing;
    }

    public function lookup(CompanyLookup $lookup): void
    {
        $this->tin = preg_replace('/\D/', '', $this->tin);
        $this->validateOnly('tin', ['tin' => $this->tinRules()]);
        // the lookup service is paid: 10 lookups per user per hour
        if (!\Illuminate\Support\Facades\RateLimiter::attempt('company-lookup:' . auth()->id(), 10, fn () => true, 3600)) {
            $this->addError('tin', __('auth.throttle', ['seconds' => \Illuminate\Support\Facades\RateLimiter::availableIn('company-lookup:' . auth()->id())]));
            return;
        }

        $this->lookedUp = true;
        $found = $lookup->find($this->tin);
        if ($found) {
            foreach (self::FIELDS as $f) {
                if (filled($found[$f] ?? null)) {
                    $this->company[$f] = $found[$f];
                }
            }
            $this->lookupMessage = 'found';
            $this->lookupData = $found['raw'] ?? null;
        } else {
            $this->lookupMessage = $lookup->enabled() ? 'not_found' : 'manual';
        }
    }

    public function save(CompanyLookup $lookup): void
    {
        $user = auth()->user();
        $editing = (bool) $user->company;
        if (!$editing) {
            $this->tin = preg_replace('/\D/', '', $this->tin);
        }

        $this->validate(array_merge($editing ? [] : ['tin' => $this->tinRules()], [
            'company.name'          => 'required|string|max:255',
            'company.legal_address' => 'nullable|string|max:255',
            'company.director'      => 'nullable|string|max:255',
            'company.phone'         => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{7,}$/'],
            'company.email'         => 'nullable|email|max:120',
            'company.vat_code'      => 'nullable|string|max:20',
        ]));

        $values = collect($this->company)->only(self::FIELDS)->map(fn ($v) => filled($v) ? trim($v) : null)->all();

        if ($editing) {
            abort_unless($user->isCompanyOwner(), 403);
            $user->company->update($values);
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            return;
        }

        // the free month only for a real company (found by the tax-number lookup); a typed, unknown
        // number gets it after the super admin approves (Company page → +1 oy)
        $company = Company::create($values + ($this->lookupMessage === 'found' ? \App\Domain\Billing\Billing::freePeriod() : [])
            + ['tin' => $this->tin, 'source' => $this->lookupMessage === 'found' ? 'lookup' : 'manual', 'lookup_data' => $this->lookupData]);
        $user->update(['company_id' => $company->id, 'company_role' => 'owner']);
        app(\App\Domain\Telegram\Notifier::class)->companyRegistered($company, $user); // Telegram alert to the super admin

        session()->flash('company_created', true);
        $this->redirectRoute('import-batches.index');
    }

    private function tinRules(): array
    {
        // 9-digit STIR of a legal entity or 14-digit PINFL of an individual entrepreneur; one account per company
        return ['required', 'regex:/^(\d{9}|\d{14})$/', 'unique:companies,tin'];
    }

    public function render()
    {
        return view('livewire.account.company-setup', [
            'editing' => (bool) auth()->user()->company,
        ]);
    }
}
