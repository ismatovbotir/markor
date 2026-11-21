<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Client data isolation: a logged-in registered user only ever sees their own rows.
 * The super admin sees everything. Queue jobs and console commands (no logged-in user)
 * are not filtered and must set `user_id` themselves.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            $user = Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where($query->qualifyColumn('user_id'), $user->id);
            }
        });

        static::creating(function ($model) {
            $model->user_id ??= Auth::id();
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
