<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: one company — details, its users, its batches. */
#[Layout('layouts.app')]
class CompanyShow extends Component
{
    public Company $company;
    public array $form = [];

    public string $freeUntil = '';
    public ?int $discountFor = null;
    public string $discountValue = '';
    public string $discountUntil = '';

    public function mount(Company $company): void
    {
        $this->freeUntil = $this->company->free_until?->toDateString() ?? '';
        $this->company = $company;
        $this->form = $company->only(['name', 'legal_address', 'director', 'phone', 'email', 'vat_code']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name'          => 'required|string|max:255',
            'form.legal_address' => 'nullable|string|max:255',
            'form.director'      => 'nullable|string|max:255',
            'form.phone'         => ['nullable', 'string', 'max:30'],
            'form.email'         => 'nullable|email|max:120',
            'form.vat_code'      => 'nullable|string|max:20',
        ])['form'];
        $this->company->update($data);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public string $blockReason = '';

    public bool $showMoney = false;
    public string $moneyType = 'payment'; // payment / adjustment
    public string $moneyAmount = '';
    public string $moneyMethod = 'cash';
    public string $moneyNote = '';

    public function saveMoney(\App\Domain\Billing\BalanceService $balance): void
    {
        $this->validate([
            'moneyType'   => 'required|in:payment,adjustment',
            'moneyAmount' => $this->moneyType === 'payment' ? 'required|numeric|min:1' : 'required|numeric|not_in:0',
            'moneyMethod' => 'required|in:cash,card,bank',
            'moneyNote'   => $this->moneyType === 'adjustment' ? 'required|string|max:500' : 'nullable|string|max:500',
        ]);
        $this->moneyType === 'payment'
            ? $balance->recordPayment($this->company, auth()->user(), (float) $this->moneyAmount, $this->moneyMethod, $this->moneyNote ?: null)
            : $balance->adjust($this->company, auth()->user(), (float) $this->moneyAmount, $this->moneyNote);

        $this->company->refresh();
        $this->reset(['showMoney', 'moneyAmount', 'moneyNote']);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function toggleCompany(): void
    {
        $active = !$this->company->is_active;
        $this->company->update(['is_active' => $active, 'blocked_reason' => $active ? null : (trim($this->blockReason) ?: null)]);
        $this->blockReason = '';
        $this->dispatch('notify', type: 'success', message: $active ? __('companies.unblocked') : __('companies.blocked'));
    }

    public function toggleActive(int $userId): void
    {
        $user = $this->company->users()->findOrFail($userId);
        $user->update(['is_active' => !$user->is_active]);
    }

    /** Prolong the free period by N months from its end (or from today if it has already ended). */
    public function prolongFree(int $months): void
    {
        abort_unless(in_array($months, [1, 3, 6], true), 422);
        $from = \App\Domain\Billing\Billing::isFree($this->company) ? $this->company->free_until->copy() : now();
        $this->setFree($from->addMonthsNoOverflow($months)->toDateString());
    }

    public function saveFreeUntil(): void
    {
        $this->validate(['freeUntil' => 'nullable|date']);
        $this->setFree($this->freeUntil ?: null);
    }

    public function endFree(): void
    {
        $this->setFree(now()->subDay()->toDateString());
    }

    private function setFree(?string $date): void
    {
        // a newly granted free period starts a fresh free-codes allowance
        $this->company->update(['free_until' => $date] + ($date && \Illuminate\Support\Carbon::parse($date)->endOfDay()->isFuture() && !\App\Domain\Billing\Billing::isFree($this->company) ? ['free_codes_used' => 0] : []));
        $this->company->refresh();
        $this->freeUntil = $this->company->free_until?->toDateString() ?? '';
        app(\App\Domain\Telegram\Notifier::class)->freePeriodChanged($this->company);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function editDiscount(): void
    {
        $this->discountFor = $this->company->id;
        $this->discountValue = $this->company->discount_percent === null ? '' : \App\Domain\Billing\Billing::percent($this->company->discount_percent);
        $this->discountUntil = $this->company->discount_until?->toDateString() ?? '';
    }

    /** Empty percent = default discount. Applies to batches loaded from now on. */
    public function saveDiscount(): void
    {
        $this->validate(['discountValue' => 'nullable|numeric|min:0|max:100', 'discountUntil' => 'nullable|date']);
        $this->company->update([
            'discount_percent' => $this->discountValue === '' ? null : (float) $this->discountValue,
            'discount_until'   => $this->discountValue === '' || $this->discountUntil === '' ? null : $this->discountUntil,
        ]);
        $this->discountFor = null;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function render()
    {
        return view('livewire.admin.company-show', [
            'storage' => app(\App\Domain\Reporting\CodeStorage::class)->perCompany()[$this->company->id] ?? null,
            'users'   => $this->company->users()->orderByRaw("company_role = 'owner' desc")->orderBy('name')->get(),
            'batches' => ImportBatch::withoutGlobalScope('owner')->with('uploader:id,name')->where('company_id', $this->company->id)->latest('id')->limit(20)->get(),
            'defaultDiscount' => (float) config('billing.discount_percent'),
            'entries'   => \App\Domain\Billing\BalanceEntry::with('batch:id,original_filename')->where('company_id', $this->company->id)->latest('id')->limit(15)->get(),
            'shortfall' => \App\Domain\Billing\BalanceService::shortfall($this->company->fresh()),
        ]);
    }
}
