<?php

namespace App\Domain\Marking\Models;

use App\Domain\Aggregation\Models\Aggregation;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MarkingCode extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable, \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'user_id', 'company_id', 'code', 'gtin', 'serial', 'crypto_tail',
        'product_id', 'product_group_id', 'import_batch_id', 'aggregation_id',
        'status', 'printed_at', 'aggregated_at',
    ];

    protected function casts(): array
    {
        return [
            'printed_at'    => 'datetime',
            'aggregated_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** The user who uploaded the code. */
    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function productGroup()
    {
        return $this->belongsTo(\App\Domain\Catalog\Models\ProductGroup::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function aggregation()
    {
        return $this->belongsTo(Aggregation::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeImported($query)    { return $query->where('status', 'imported'); }
    public function scopeAssigned($query)    { return $query->where('status', 'assigned'); }
    public function scopePrinted($query)     { return $query->where('status', 'printed'); }
    public function scopeAggregated($query)  { return $query->where('status', 'aggregated'); }

    public function getFormatAttribute(): string
    {
        return strlen($this->code) === 38 ? 'tobacco_short' : 'standard_long';
    }
}
