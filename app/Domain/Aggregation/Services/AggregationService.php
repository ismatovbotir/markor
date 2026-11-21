<?php

namespace App\Domain\Aggregation\Services;

use App\Domain\Aggregation\Models\Aggregation;
use App\Domain\Marking\Models\MarkingCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AggregationService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Aggregation::with(['product', 'creator'])
            ->whereNull('parent_id')
            ->latest()
            ->paginate($perPage);
    }

    public function open(array $data): Aggregation
    {
        return Aggregation::create([
            ...$data,
            'status'     => 'open',
            'created_by' => Auth::id(),
        ]);
    }

    public function addCode(Aggregation $aggregation, string $rawScan): MarkingCode
    {
        if ($aggregation->status !== 'open') {
            throw ValidationException::withMessages(['code' => __('aggregation.not_open')]);
        }

        $normalized = str_replace(["\x1D", "\x1E"], '', trim($rawScan));

        return DB::transaction(function () use ($aggregation, $normalized) {
            $code = MarkingCode::where('code', $normalized)->lockForUpdate()->firstOrFail();

            if ($code->status !== 'printed') {
                throw ValidationException::withMessages(['code' => __('aggregation.code_not_printed')]);
            }

            $code->update([
                'aggregation_id' => $aggregation->id,
                'status'         => 'aggregated',
                'aggregated_at'  => now(),
            ]);

            $aggregation->increment('actual_quantity');

            if ($aggregation->fresh()->isFull()) {
                $aggregation->update(['status' => 'closed', 'closed_at' => now()]);
            }

            return $code->fresh();
        });
    }

    public function close(Aggregation $aggregation): Aggregation
    {
        $aggregation->update(['status' => 'closed', 'closed_at' => now()]);
        return $aggregation->fresh();
    }
}
