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
            $fresh = ImportBatch::withoutGlobalScope('owner')->whereKey($batch->id)->lockForUpdate()->first();
            if ($fresh?->charged_at) {
                return true; // already charged (checked on the locked row, not the copy in memory)
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

    /**
     * Places an Asl Belgisi check order and charges it at once (price fixed on the order). Unlike a CSV
     * batch it is never locked: if the balance does not cover it, nothing is created.
     * @throws \App\Domain\Aslbelgi\InsufficientBalance
     */
    public function orderAslbelgiCheck(ImportBatch $batch, User $user, int $codes, float $unitPrice): \App\Domain\Aslbelgi\AslbelgiCheck
    {
        return DB::transaction(function () use ($batch, $user, $codes, $unitPrice) {
            $company = Company::lockForUpdate()->find($batch->company_id);
            $active = \App\Domain\Aslbelgi\AslbelgiCheck::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)
                ->whereIn('status', \App\Domain\Aslbelgi\AslbelgiCheck::ACTIVE)->exists();
            if ($active) {
                throw new \DomainException('aslbelgi_check.already_running');
            }
            $amount = Billing::amount($codes, $unitPrice, 0); // no discount, no free period for services
            if ((float) $company->balance < $amount) {
                throw new \App\Domain\Aslbelgi\InsufficientBalance(round($amount - (float) $company->balance, 2));
            }
            $check = \App\Domain\Aslbelgi\AslbelgiCheck::create([
                'import_batch_id' => $batch->id, 'company_id' => $company->id, 'requested_by' => $user->id,
                'codes' => $codes, 'unit_price' => $unitPrice, 'amount' => $amount, 'status' => 'queued',
            ]);
            if ($amount > 0) {
                $this->entry($company, 'aslbelgi_check', -$amount, ['import_batch_id' => $batch->id, 'created_by' => $user->id]);
            }

            return $check;
        });
    }

    /**
     * Final amount of a finished check: only the codes actually checked are paid; the rest of what was
     * charged at the order goes back to the balance. Returns false when the check was already settled.
     */
    public function settleAslbelgiCheck(\App\Domain\Aslbelgi\AslbelgiCheck $check, array $attributes, int $checkedCodes): bool
    {
        return DB::transaction(function () use ($check, $attributes, $checkedCodes) {
            $locked = \App\Domain\Aslbelgi\AslbelgiCheck::withoutGlobalScope('owner')->whereKey($check->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'running') {
                return false;
            }
            $final = min($locked->amount, Billing::amount(max(0, $checkedCodes), $locked->unit_price, 0));
            $refund = round($locked->amount - $final, 2);
            if ($refund > 0) {
                $company = Company::lockForUpdate()->find($locked->company_id);
                $this->entry($company, 'aslbelgi_refund', $refund, ['import_batch_id' => $locked->import_batch_id]);
            }
            $locked->update($attributes + ['amount' => $final, 'finished_at' => now()]);

            return true;
        });
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
