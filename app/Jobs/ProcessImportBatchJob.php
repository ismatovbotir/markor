<?php

namespace App\Jobs;

use App\Domain\Billing\Billing;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Parsers\MarkingCodeParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK = 1000;

    /** A half-imported file must not be retried blindly; failures are reported on the batch. */
    public int $tries = 1;

    public int $timeout = 600;

    /** gtin => product id */
    private array $products = [];

    public function __construct(public ImportBatch $batch) {}

    public function handle(MarkingCodeParser $parser): void
    {
        try {
            $this->batch->update(['status' => 'processing']); // loading into the database

            $path = $this->path();
            $file = @fopen($path, 'r');
            if (!$file) {
                throw new \RuntimeException("File not found: {$path}");
            }

            $line = 0;
            $total = 0;
            $invalid = 0;
            $inserted = 0;
            $errors = [];
            $chunk = [];

            // Codes may contain quotes and commas, so the file is read line by line:
            // the whole line is tried first, then the first CSV column.
            while (($raw = fgets($file)) !== false) {
                $line++;
                if (trim($raw) === '') {
                    continue;
                }

                $parsed = $this->parseLine($parser, $raw);

                if ($parsed === null) {
                    if ($total === 0 && $invalid === 0 && !str_contains($raw, '01')) {
                        continue; // header row
                    }
                    $invalid++;
                    if (count($errors) < 100) {
                        $errors[] = "Line {$line}: not a valid marking code";
                    }
                    continue;
                }

                $total++;
                $chunk[] = [
                    'code'            => $parsed['code'],
                    'gtin'            => $parsed['gtin'],
                    'serial'          => $parsed['serial'],
                    'crypto_tail'     => $parsed['crypto_tail'],
                    'import_batch_id' => $this->batch->id,
                    'user_id'         => $this->batch->uploaded_by,
                    'company_id'      => $this->batch->company_id,
                    'product_id'      => $this->productId($parsed['gtin'], $parsed['product_group_id']),
                    'product_group_id'=> $parsed['product_group_id'],
                    'status'          => 'imported',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                if (count($chunk) >= self::CHUNK) {
                    $inserted += MarkingCode::insertOrIgnore($chunk);
                    $chunk = [];
                    $this->batch->update(['processed_rows' => $inserted]); // live progress in the UI
                }
            }
            fclose($file);

            if ($chunk) {
                $inserted += MarkingCode::insertOrIgnore($chunk);
            }

            $duplicates = $total - $inserted;
            if ($duplicates > 0) {
                array_unshift($errors, "{$duplicates} duplicate code(s) skipped (already in the system)");
            }

            // Billing: what this batch costs, fixed at load time.
            $unitPrice = Billing::unitPrice();
            $discount  = Billing::discountFor($this->batch->company);

            $this->batch->update([
                'unit_price'       => $unitPrice,
                'discount_percent' => $discount,
                'amount'           => Billing::amount($inserted, $unitPrice, $discount),
                // codes are in; next the scheduler checks the products (GTIN) — then `completed`
                'status'         => $inserted > 0 ? 'checking' : 'completed',
                'product_id'     => count($this->products) === 1 ? reset($this->products) : null,
                'total_rows'     => $total + $invalid,
                'processed_rows' => $inserted,
                'failed_rows'    => $invalid + $duplicates,
                'error_log'      => $errors ?: null,
                'processed_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($e);
        } finally {
            $this->deleteFile();
        }
    }

    /** Called by the queue when the job dies outside handle() (timeout, worker crash). */
    public function failed(?\Throwable $e): void
    {
        $this->markFailed($e);
        $this->deleteFile();
    }

    private function markFailed(?\Throwable $e): void
    {
        if ($e) {
            report($e); // the real error goes to the log, the client sees a plain message
        }
        $this->batch->update([
            'status'       => 'failed',
            'error_log'    => [__('import.failed_generic')],
            'processed_at' => now(),
        ]);
    }

    private function path(): string
    {
        return storage_path("app/imports/{$this->batch->filename}");
    }

    /** The uploaded CSV is only a transport: once its codes are in the database it is removed. */
    private function deleteFile(): void
    {
        @unlink($this->path());
    }

    private function parseLine(MarkingCodeParser $parser, string $raw): ?array
    {
        $candidates = [$raw];
        foreach ([',', ';', "\t"] as $delimiter) {
            $first = str_getcsv($raw, $delimiter, '"', '')[0] ?? null;
            if ($first !== null && $first !== $raw) {
                $candidates[] = $first;
            }
        }

        foreach ($candidates as $candidate) {
            try {
                return $parser->parse($candidate);
            } catch (\InvalidArgumentException) {
                // try next candidate
            }
        }

        return null;
    }

    /** One global product per GTIN, created from the GTIN alone; name etc. are filled in later. */
    private function productId(string $gtin, ?int $groupId): int
    {
        if (!isset($this->products[$gtin])) {
            $find = fn () => Product::withoutGlobalScope('owner')->withTrashed()->where('gtin', $gtin)->first();

            // Another upload may create the same product at the same moment: then use theirs.
            try {
                $product = $find() ?? Product::withoutGlobalScope('owner')->create(['gtin' => $gtin, 'is_active' => true, 'product_group_id' => $groupId]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $product = $find();
            }
            if ($product->trashed()) {
                $product->restore();
            }
            if (!$product->product_group_id && $groupId) {
                $product->update(['product_group_id' => $groupId]);
            }
            $this->products[$gtin] = $product->id;
        }

        return $this->products[$gtin];
    }
}
