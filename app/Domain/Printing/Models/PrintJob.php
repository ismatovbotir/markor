<?php

namespace App\Domain\Printing\Models;

use App\Domain\Aggregation\Models\Aggregation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrintJob extends Model
{
    protected $fillable = [
        'uuid', 'template_id', 'printer_id', 'created_by',
        'code_ids', 'aggregation_id', 'status', 'output_path', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'code_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function template()
    {
        return $this->belongsTo(LabelTemplate::class);
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aggregation()
    {
        return $this->belongsTo(Aggregation::class);
    }

    public function scopeQueued($query)  { return $query->where('status', 'queued'); }
    public function scopeFailed($query)  { return $query->where('status', 'failed'); }
}
