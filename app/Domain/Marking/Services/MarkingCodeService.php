<?php

namespace App\Domain\Marking\Services;

use App\Domain\Marking\Models\MarkingCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MarkingCodeService
{
    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return MarkingCode::with(['product', ...(auth()->user()?->isAdmin() ? ['owner:id,name,email'] : [])])
            // codes of batches waiting for payment are hidden from the client
            ->when(auth()->user()?->isClient(), fn ($q) => $q->whereNotIn('import_batch_id',
                \App\Domain\Marking\Models\ImportBatch::withoutGlobalScope('owner')->where('company_id', auth()->user()->company_id)->where('is_locked', true)->select('id')))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('code', 'like', "%$v%")
                ->orWhere('gtin', 'like', "%$v%")))
            ->latest('id') // newest first by id: served by the (company_id, …, id) indexes; same order as created_at
            ->paginate($perPage);
    }

    public function findByRawScan(string $raw): ?MarkingCode
    {
        $normalized = str_replace(["\x1D", "\x1E"], '', trim($raw));
        return MarkingCode::where('code', $normalized)->first();
    }

    public function assignToProduct(MarkingCode $code, int $productId): MarkingCode
    {
        $code->update(['product_id' => $productId, 'status' => 'assigned']);
        return $code->fresh();
    }

    public function cancel(MarkingCode $code): void
    {
        $code->update(['status' => 'cancelled']);
    }
}
