<?php

namespace App\Domain\Aggregation\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marking\Models\MarkingCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

class Aggregation extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'uuid', 'sscc', 'sscc_source', 'level', 'parent_id',
        'product_id', 'expected_quantity', 'actual_quantity',
        'status', 'created_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markingCodes()
    {
        return $this->hasMany(MarkingCode::class);
    }

    public function isFull(): bool
    {
        return $this->expected_quantity > 0 && $this->actual_quantity >= $this->expected_quantity;
    }

    public function scopeOpen($query)   { return $query->where('status', 'open'); }
    public function scopeClosed($query) { return $query->where('status', 'closed'); }
}
