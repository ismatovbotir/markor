<?php

namespace App\Domain\Billing;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Telegram\Notifier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid balance. Every change goes through the ledger (balance_entries) and updates companies.balance
 * in the same transaction, with the company row locked so concurrent loads/payments cannot overspend.
 *
 *   batch loaded, amount 0              → nothing to charge
 *   batch loaded, balance ≥ amount      → charged
 *   batch loaded, balance < amount      → locked (no codes / PDF) until a payment covers it
 *   payment confirmed                   → balance up, then locked batches are charged & unlocked, oldest first
 */
class BalanceService
{
    public function __construct(private Notifier $notifier) {}

    /** Called when a batch has been loaded and priced. Returns true if the batch is usable. */
    public function chargeBatch(ImportBatch $batch): bool
    {
        if ($batch->amount <= 0 || !$batch->company_id) {
            $batch->update(['is_locked' => false]);
            return true;
        }

        $charged = DB::transaction(function () use ($batch) {
            $company = Company::lockForUpdate()->find($batch->company_id);
            if ($batch->charged_at) {
                return true;
            }
            if ($company->balance < $batch->amount) {
                $batch->update(['is_locked' => true]);
                return false;
            }
            $this->entry($company, 'charge', -$batch->amount, ['import_batch_id' => $batch->id]);
            $batch->update(['is_locked' => false, 'charged_at' => now()]);

            return true;
        });

        if (!$charged) {
            $this->notifier->batchLocked($batch->fresh());
        }

        return $charged;
    }

    public function confirmPayment(Payment $payment, User $admin, ?float $amount = null, ?string $note = null): void
    {
        $unlocked = DB::transaction(function () use ($payment, $admin, $amount, $note) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            if ($payment->status !== 'pending') {
                return [];
            }
            $payment->update([
                'amount' => $amount ?? $payment->amount, 'status' => 'confirmed', 'admin_note' => $note,
                'decided_by' => $admin->id, 'decided_at' => now(),
            ]);
            $company = Company::lockForUpdate()->find($payment->company_id);
            $this->entry($company, 'payment', $payment->amount, ['payment_id' => $payment->id, 'created_by' => $admin->id]);

            return $this->unlockCovered($company);
        });

        $payment->refresh();
        if ($payment->status === 'confirmed') {
            $this->notifier->paymentConfirmed($payment);
            foreach ($unlocked as $batch) {
                $this->notifier->batchUnlocked($batch);
            }
        }
    }

    public function rejectPayment(Payment $payment, User $admin, ?string $note = null): void
    {
        // locked like confirmPayment, so a reject and a confirm at the same moment can't both apply
        $rejected = DB::transaction(function () use ($payment, $admin, $note) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'rejected', 'admin_note' => $note, 'decided_by' => $admin->id, 'decided_at' => now()]);

            return true;
        });
        if ($rejected) {
            $this->notifier->paymentRejected($payment->fresh());
        }
    }

    /** A payment the super admin records directly (cash, bank transfer seen on the account…). */
    public function recordPayment(Company $company, User $admin, float $amount, string $method, ?string $comment): Payment
    {
        $payment = Payment::create(['company_id' => $company->id, 'amount' => $amount, 'method' => $method, 'comment' => $comment, 'status' => 'pending']);
        $this->confirmPayment($payment, $admin, $amount);

        return $payment->fresh();
    }

    /** Manual correction by the super admin (positive or negative). */
    public function adjust(Company $company, User $admin, float $amount, string $note): void
    {
        $unlocked = DB::transaction(function () use ($company, $admin, $amount, $note) {
            $company = Company::lockForUpdate()->find($company->id);
            $this->entry($company, 'adjustment', $amount, ['note' => $note, 'created_by' => $admin->id]);

            return $amount > 0 ? $this->unlockCovered($company) : [];
        });
        foreach ($unlocked as $batch) {
            $this->notifier->batchUnlocked($batch);
        }
    }

    /** Sum the company still has to pay to open all its locked batches. */
    public static function shortfall(Company $company): float
    {
        $locked = (float) ImportBatch::withoutGlobalScope('owner')->where('company_id', $company->id)->where('is_locked', true)->sum('amount');

        return max(0, $locked - (float) $company->balance);
    }

    /** @return ImportBatch[] batches unlocked (inside the caller's transaction, company row locked) */
    private function unlockCovered(Company $company): array
    {
        $unlocked = [];
        $locked = ImportBatch::withoutGlobalScope('owner')->where('company_id', $company->id)->where('is_locked', true)->orderBy('id')->get();
        foreach ($locked as $batch) {
            if ($company->balance < $batch->amount) {
                break; // oldest first; stop at the first one the balance cannot cover
            }
            $this->entry($company, 'charge', -$batch->amount, ['import_batch_id' => $batch->id]);
            $batch->update(['is_locked' => false, 'charged_at' => now()]);
            $unlocked[] = $batch;
        }

        return $unlocked;
    }

    private function entry(Company $company, string $type, float $amount, array $extra = []): BalanceEntry
    {
        $company->balance = round((float) $company->balance + $amount, 2);
        $company->save();

        return BalanceEntry::create($extra + [
            'company_id' => $company->id, 'type' => $type, 'amount' => $amount, 'balance_after' => $company->balance,
        ]);
    }
}
