<?php

namespace App\Domain\Marking\Services;

use App\Domain\Aslbelgi\AslbelgiCheck;
use App\Domain\Billing\BalanceEntry;
use App\Domain\Billing\BalanceService;
use App\Domain\Billing\Billing;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Reporting\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Super admin actions on a batch (CLAUDE.md §15, spec "Удаление"):
 *  - cancelCharge(): the batch charge goes back to the company balance; the batch stays, marked.
 *  - delete(): the batch and ALL its codes are removed for good (the codes can be uploaded again).
 *      printed batch (a code printed or a PDF made) → allowed straight away, the charge is kept;
 *      not printed → only after cancelCharge(), or when nothing was charged.
 *  A single code is never deleted.
 */
class BatchAdminService
{
    public function __construct(private BalanceService $balance) {}

    /** Charged and not cancelled yet: the CSV charge plus the finished Asl Belgisi checks of the batch. */
    public static function refundable(ImportBatch $batch): float
    {
        $csv = $batch->charged_at && !$batch->charge_cancelled_at ? (float) $batch->amount : 0.0;

        return round($csv + (float) self::finishedChecks($batch)->sum('amount'), 2);
    }

    /** Asl Belgisi checks of the batch whose money can still go back (settled, not cancelled). */
    private static function finishedChecks(ImportBatch $batch)
    {
        return AslbelgiCheck::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)
            ->whereNotIn('status', ['queued', 'running'])->whereNull('charge_cancelled_at')->where('amount', '>', 0);
    }

    /** A check still waiting or running: its final charge is not known yet. */
    public static function checkRunning(ImportBatch $batch): bool
    {
        return AslbelgiCheck::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->whereIn('status', ['queued', 'running'])->exists();
    }

    /** At least one code printed, or a PDF made for the batch. */
    public static function isPrinted(ImportBatch $batch): bool
    {
        return LabelPdf::where('import_batch_id', $batch->id)->exists()
            || MarkingCode::withoutGlobalScope('owner')->withTrashed()->where('import_batch_id', $batch->id)
                ->where(fn ($q) => $q->where('status', 'printed')->orWhereNotNull('printed_at'))->exists();
    }

    public static function canCancelCharge(ImportBatch $batch): bool
    {
        return !self::checkRunning($batch) && self::refundable($batch) > 0;
    }

    public static function canDelete(ImportBatch $batch): bool
    {
        return !self::checkRunning($batch) && (self::isPrinted($batch) || self::refundable($batch) <= 0);
    }

    public function cancelCharge(ImportBatch $batch, User $admin, string $reason): void
    {
        abort_unless($admin->isAdmin(), 403);
        abort_if(self::checkRunning($batch), 422, __('batches.check_running'));
        $amount = DB::transaction(function () use ($batch) {
            $fresh = ImportBatch::withoutGlobalScope('owner')->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $amount = self::refundable($fresh);
            if ($amount > 0) {
                if ($fresh->charged_at && !$fresh->charge_cancelled_at) {
                    $fresh->update(['charge_cancelled_at' => now()]);
                }
                self::finishedChecks($fresh)->update(['charge_cancelled_at' => now()]); // check charges go back too
            }

            return $amount;
        });
        abort_if($amount <= 0, 422, __('batches.nothing_to_cancel'));

        // money back through the ledger (also unlocks other batches the new balance now covers)
        $this->balance->adjust($batch->company, $admin, $amount, __('batches.ledger_cancelled', ['file' => $batch->original_filename, 'reason' => $reason]));
        AuditLog::record('batch.charge_cancelled', 'import_batch', $batch->id, ['amount' => $amount, 'reason' => $reason]);
        $this->tell($batch, 'bot.batch_charge_cancelled', ['amount' => Billing::money($amount), 'reason' => $reason]);
    }

    public function delete(ImportBatch $batch, User $admin, string $reason): void
    {
        abort_unless($admin->isAdmin(), 403);
        abort_if(self::checkRunning($batch), 422, __('batches.check_running'));
        abort_unless(self::canDelete($batch), 422, __('batches.cancel_first'));

        $printed = self::isPrinted($batch);
        $info = ['file' => $batch->original_filename, 'company_id' => $batch->company_id, 'codes' => $batch->processed_rows,
            'amount' => (float) $batch->amount, 'printed' => $printed, 'kept_charge' => self::refundable($batch), 'reason' => $reason];
        $files = LabelPdf::where('import_batch_id', $batch->id)->whereNotNull('local_path')->pluck('local_path')->all();
        $company = $batch->company;

        DB::transaction(function () use ($batch) {
            // the ledger keeps its entries (the balance does not change); they lose the link, so keep the name
            BalanceEntry::where('import_batch_id', $batch->id)->get()->each(fn ($e) => $e->update([
                'note' => trim(($e->note ? $e->note . ' · ' : '') . __('batches.ledger_deleted', ['file' => $batch->original_filename])),
            ]));
            if (Schema::hasTable('aslbelgi_checks')) {
                DB::table('aslbelgi_checks')->where('import_batch_id', $batch->id)->delete();
            }
            MarkingCode::withoutGlobalScope('owner')->withTrashed()->where('import_batch_id', $batch->id)->forceDelete();
            LabelPdf::where('import_batch_id', $batch->id)->delete();
            ImportBatch::withoutGlobalScope('owner')->whereKey($batch->id)->delete();
        });
        foreach ($files as $path) {
            Storage::disk('local')->delete($path); // LabelPdfStore keeps PDFs on the local disk
        }

        AuditLog::record('batch.deleted', 'import_batch', $batch->id, $info);
        if ($company) {
            $this->tell($batch, 'bot.batch_deleted_by_admin', ['reason' => $reason]);
        }
    }

    /** Telegram message to the company users who linked the bot (uz/ru/zh by each user's language). */
    private function tell(ImportBatch $batch, string $key, array $params): void
    {
        try {
            $api = app(\App\Domain\Telegram\TelegramApi::class);
            if (!$api->enabled()) {
                return;
            }
            User::where('company_id', $batch->company_id)->whereNotNull('telegram_chat_id')->where('is_active', true)->get()
                ->each(fn (User $u) => $api->sendMessage($u->telegram_chat_id, __($key, $params + ['file' => e($batch->original_filename)], $u->locale ?: 'uz')));
        } catch (\Throwable $e) {
            report($e); // a failed notice never blocks the admin action
        }
    }
}
