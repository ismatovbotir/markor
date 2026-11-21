<?php

namespace App\Domain\Aggregation\Models;

use Illuminate\Database\Eloquent\Model;

class SsccPool extends Model
{
    protected $table = 'sscc_pool';

    protected $fillable = [
        'sscc', 'sscc_source', 'gcp', 'serial', 'check_digit',
        'is_used', 'aggregation_id',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
        ];
    }

    public function aggregation()
    {
        return $this->belongsTo(Aggregation::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_used', false);
    }

    public static function nextAvailable()
    {
        return self::available()->lockForUpdate()->first();
    }
}
