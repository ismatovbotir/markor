<?php

namespace App\Http\Livewire\Account;

use App\Domain\Billing\BalanceEntry;
use App\Domain\Billing\BalanceService;
use App\Domain\Billing\Billing;
use App\Domain\Billing\Payment;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Telegram\Notifier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Client cabinet: prepaid balance, how to top up (card transfer), "I paid", history. */
#[Layout('layouts.app')]
class BalancePage extends Component
{
    use WithPagination;

    public string $amount = '';
    public string $comment = '';

    public function mount(): void
    {
        $need = BalanceService::shortfall(auth()->user()->company);
        $this->amount = $need > 0 ? (string) (int) ceil($need) : '';
    }

    public function reportPayment(Notifier $notifier): void
    {
        $data = $this->validate([
            'amount'  => 'required|numeric|min:1000|max:1000000000',
            'comment' => 'nullable|string|max:500',
        ]);

        abort_if(Payment::where('company_id', auth()->user()->company_id)->where('status', 'pending')->count() >= 5, 429);

        $payment = Payment::create([
            'company_id' => auth()->user()->company_id, 'user_id' => auth()->id(),
            'amount' => (float) $data['amount'], 'method' => 'card', 'comment' => $data['comment'] ?: null,
        ]);
        $notifier->paymentReported($payment->load('company', 'user'));

        $this->reset(['comment']);
        $this->dispatch('notify', type: 'success', message: __('balance.reported'));
    }

    public function render()
    {
        $company = auth()->user()->company->fresh();

        return view('livewire.account.balance-page', [
            'company'   => $company,
            'shortfall' => BalanceService::shortfall($company),
            'locked'    => ImportBatch::where('is_locked', true)->orderBy('id')->get(['id', 'original_filename', 'amount', 'processed_rows']),
            'pending'   => Payment::where('company_id', $company->id)->where('status', 'pending')->latest()->get(),
            'entries'   => BalanceEntry::with(['batch:id,original_filename', 'payment:id,method'])->where('company_id', $company->id)->latest('id')->paginate(20),
            'discount'  => Billing::discountFor($company),
            'discountUntil' => Billing::discountUntil($company),
            'price'     => Billing::unitPrice(),
            'card'      => config('billing.card_number'),
            'cardHolder'=> config('billing.card_holder'),
        ]);
    }
}
