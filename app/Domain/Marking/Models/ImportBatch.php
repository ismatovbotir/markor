<?php

namespace App\Domain\Marking\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid', 'filename', 'original_filename', 'uploaded_by', 'company_id',
        'type', 'product_id', 'status',
        'total_rows', 'processed_rows', 'failed_rows',
        'unit_price', 'discount_percent', 'amount', 'source', 'is_locked', 'charged_at',
        'error_log', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log'    => 'array',
            'processed_at' => 'datetime',
            'unit_price'       => 'float',
            'discount_percent' => 'float',
            'amount'           => 'float',
            'is_locked'        => 'boolean',
            'charged_at'       => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());

        // Users of a company see the company's batches; the super admin sees all.
        static::addGlobalScope('owner', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where($query->qualifyColumn('company_id'), $user->company_id ?? 0);
            }
        });
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function company()
    {
        return $this->belongsTo(\App\Domain\Companies\Company::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function markingCodes()
    {
        return $this->hasMany(MarkingCode::class);
    }

    public function scopePending($query)     { return $query->where('status', 'pending'); }
    public function scopeCompleted($query)   { return $query->where('status', 'completed'); }
    public function scopeFailed($query)      { return $query->where('status', 'failed'); }
}
