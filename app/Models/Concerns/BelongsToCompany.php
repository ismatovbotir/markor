<?php

namespace App\Models\Concerns;

use App\Domain\Companies\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Client data isolation by company: every user of a company sees the company's rows, nobody else
 * does; the super admin sees everything. Queue jobs and console commands (no logged-in user) are not
 * filtered and must set `company_id` themselves.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            $user = Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where($query->qualifyColumn('company_id'), $user->company_id ?? 0);
            }
        });

        static::creating(function ($model) {
            $model->company_id ??= Auth::user()?->company_id;
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
