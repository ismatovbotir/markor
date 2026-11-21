<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\GtinLookup;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Jobs\ProcessImportBatchJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled every minute: loads queued CSV batches into the database, then checks the batch's
 * products (GTIN) in the catalogue / national classifier. Batch status:
 *   uploaded (file on the server, upload dialog still open) → pending (queued) → processing (loading) → checking (items) → completed | failed
 * With --for=N it keeps polling for new uploads for N seconds, so uploads wait seconds, not a minute.
 */
class ProcessImportBatches extends Command
{
    protected $signature = 'batches:process {--for=0 : keep polling for new batches for this many seconds}';

    protected $description = 'Load queued CSV batches into the database and check their products';

    public function handle(GtinLookup $lookup): int
    {
        $until = microtime(true) + (int) $this->option('for');

        do {
            $worked = false;
            while ($batch = $this->claimNext()) {
                $worked = true;
                $this->line("Loading batch #{$batch->id} {$batch->original_filename}");
                try {
                    app()->call([new ProcessImportBatchJob($batch), 'handle']);
                    $batch->refresh();
                    if (in_array($batch->status, ['checking', 'completed'], true) && $batch->processed_rows > 0) {
                        app(\App\Domain\Billing\BalanceService::class)->chargeBatch($batch);
                        $batch->refresh();
                    }
                } catch (\Throwable $e) {
                    report($e); // this batch failed — the loop goes on with the others
                    $this->error("  batch #{$batch->id} failed: " . $e->getMessage());
                    continue;
                }

                if ($batch->status === 'checking') {
                    try {
                        $this->checkProducts($batch, $lookup); // classifier names: nice to have, never blocks the batch
                    } catch (\Throwable $e) {
                        report($e);
                    }
                    $batch->update(['status' => 'completed']);
                }
                rescue(fn () => app(\App\Domain\Telegram\BotHandler::class)->notifyBatchFinished($batch->fresh()), report: true);
                $this->line("  → {$batch->status}, {$batch->processed_rows} codes");
            }

            $this->retryFailedLookups($lookup);

            if (!$worked && microtime(true) < $until) {
                sleep(3);
            }
        } while (microtime(true) < $until);

        return self::SUCCESS;
    }

    /** Takes the oldest queued batch and marks it as being loaded (safe if two runs overlap). */
    private function claimNext(): ?ImportBatch
    {
        return DB::transaction(function () {
            // 'uploaded' batches wait for the dialog to release them; if the browser was closed, after 1 minute anyway
            $batch = ImportBatch::withoutGlobalScope('owner')
                ->where(fn ($q) => $q->where('status', 'pending')
                    ->orWhere(fn ($q) => $q->where('status', 'uploaded')->where('created_at', '<', now()->subMinute())))
                ->orderBy('id')->lockForUpdate()->first();
            if (!$batch) {
                return null;
            }
            $claimed = ImportBatch::withoutGlobalScope('owner')->whereKey($batch->id)->where('status', $batch->status)->update(['status' => 'processing']);

            return $claimed ? $batch->fresh() : null;
        });
    }

    /** Products of this batch that are not known yet get their name etc. from the classifier. */
    private function checkProducts(ImportBatch $batch, GtinLookup $lookup): void
    {
        $productIds = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->distinct()->pluck('product_id')->filter();

        Product::withoutGlobalScope('owner')->whereIn('id', $productIds)
            ->where(fn ($q) => $q->whereNull('lookup_status')->orWhere('lookup_status', 'error'))
            ->get()
            ->each(fn (Product $p) => $this->line("  item {$p->gtin}: " . $lookup->enrich($p)));
    }

    /** If the classifier was down, try again after an hour (index products(lookup_status, lookup_at)). */
    private function retryFailedLookups(GtinLookup $lookup): void
    {
        Product::withoutGlobalScope('owner')->where('lookup_status', 'error')
            ->where(fn ($q) => $q->whereNull('lookup_at')->orWhere('lookup_at', '<=', now()->subHour()))
            ->orderBy('lookup_at')->limit(20)->get()
            ->each(fn (Product $p) => $lookup->enrich($p));
    }
}
