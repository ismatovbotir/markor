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

    /**
     * A file with this name the company already loaded (same name, any letter case). A failed batch doesn't
     * count — its codes were removed, so the file may be loaded again.
     */
    public static function loadedWithName(?int $companyId, string $name): ?self
    {
        $name = mb_strtolower(trim(basename(str_replace('\\', '/', $name))));
        if (!$companyId || $name === '') {
            return null;
        }

        return static::withoutGlobalScope('owner')->where('company_id', $companyId)->where('status', '!=', 'failed')
            ->whereRaw('lower(original_filename) = ?', [$name])->latest('id')->first();
    }

    /** The warning shown when a file was already loaded. */
    public function duplicateMessage(?string $locale = null): string
    {
        return __('import.duplicate_file', ['file' => $this->original_filename, 'date' => $this->created_at->format('d.m.Y H:i'), 'id' => $this->id], $locale);
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
