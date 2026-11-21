<?php

namespace App\Domain\Printing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Printer extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'type', 'connection_mode', 'host', 'port', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isNetwork(): bool
    {
        return $this->connection_mode === 'network';
    }
}
