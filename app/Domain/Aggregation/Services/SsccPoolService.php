<?php

namespace App\Domain\Aggregation\Services;

use App\Domain\Aggregation\Models\SsccPool;
use App\Domain\Aggregation\SsccPoolGenerator;
use Illuminate\Support\Facades\DB;

class SsccPoolService
{
    public function __construct(private SsccPoolGenerator $generator) {}

    public function generateAndStore(string $gcp = '123456789', int $count = 1000): int
    {
        $pool = $this->generator->generate($gcp, $count);

        // Bulk insert in chunks
        $stored = 0;
        foreach (array_chunk($pool, 1000) as $chunk) {
            $stored += SsccPool::insert($chunk);
        }

        return $stored;
    }

    public function importAndStore(string $csvPath): int
    {
        $pool = $this->generator->importFromCsv($csvPath);

        $stored = 0;
        foreach (array_chunk($pool, 1000) as $chunk) {
            $stored += SsccPool::insert($chunk);
        }

        return $stored;
    }

    public function getAvailableCount(): int
    {
        return SsccPool::available()->count();
    }

    public function allocate(): ?SsccPool
    {
        return DB::transaction(function () {
            $sscc = SsccPool::available()->lockForUpdate()->first();
            if ($sscc) {
                $sscc->update(['is_used' => true]);
            }
            return $sscc;
        });
    }

    public function release(SsccPool $sscc): void
    {
        $sscc->update(['is_used' => false, 'aggregation_id' => null]);
    }
}
