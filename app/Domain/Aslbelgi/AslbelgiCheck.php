<?php

namespace App\Domain\Aslbelgi;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One "Asl Belgisi tekshiruvi" order for a CSV batch (status + owner of every code).
 * queued → running → done | partial (some codes not checked) | failed (nothing checked, nothing charged).
 */
class AslbelgiCheck extends Model
{
    public const ACTIVE = ['queued', 'running'];

    protected $fillable = [
        'import_batch_id', 'company_id', 'requested_by', 'codes', 'unit_price', 'amount',
        'status', 'checked', 'problems', 'error', 'started_at', 'finished_at', 'charge_cancelled_at',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'float', 'amount' => 'float', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'charge_cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // users of a company see the company's orders; the super admin sees all
        static::addGlobalScope('owner', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where($query->qualifyColumn('company_id'), $user->company_id ?? 0);
            }
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
