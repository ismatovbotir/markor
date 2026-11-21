<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Billing\BalanceService;
use App\Domain\Billing\Payment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: payment reports to confirm or reject, and all payments. */
#[Layout('layouts.app')]
class PaymentIndex extends Component
{
    use WithPagination;

    public string $status = 'pending';
    public ?int $deciding = null;
    public string $confirmAmount = '';
    public string $note = '';

    public function updatingStatus(): void { $this->resetPage(); }

    public function open(int $id): void
    {
        $payment = Payment::findOrFail($id);
        $this->deciding = $id;
        $this->confirmAmount = (string) (float) $payment->amount;
        $this->note = '';
    }

    public function confirm(BalanceService $balance): void
    {
        $this->validate(['confirmAmount' => 'required|numeric|min:1', 'note' => 'nullable|string|max:500']);
        $balance->confirmPayment(Payment::findOrFail($this->deciding), auth()->user(), (float) $this->confirmAmount, $this->note ?: null);
        $this->deciding = null;
        $this->dispatch('notify', type: 'success', message: __('payments.confirmed'));
    }

    public function reject(BalanceService $balance): void
    {
        $balance->rejectPayment(Payment::findOrFail($this->deciding), auth()->user(), $this->note ?: null);
        $this->deciding = null;
        $this->dispatch('notify', type: 'success', message: __('payments.rejected'));
    }

    public function render()
    {
        return view('livewire.admin.payment-index', [
            'payments' => Payment::with(['company:id,name,tin,balance', 'user:id,name,phone'])
                ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
                ->latest()->paginate(30),
            'pendingCount' => Payment::where('status', 'pending')->count(),
        ]);
    }
}
