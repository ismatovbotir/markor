<?php

namespace App\Domain\Billing;

use App\Domain\Marking\Models\ImportBatch;
use Illuminate\Database\Eloquent\Model;

class BalanceEntry extends Model
{
    protected $fillable = ['company_id', 'type', 'amount', 'balance_after', 'payment_id', 'import_batch_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'balance_after' => 'float'];
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
