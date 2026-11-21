<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'company_id', 'company_role', 'name', 'email', 'password',
        'role', 'locale', 'qr_token',
        'telegram_chat_id', 'phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function company()
    {
        return $this->belongsTo(\App\Domain\Companies\Company::class);
    }

    public function isCompanyOwner(): bool
    {
        return $this->company_id && $this->company_role === 'owner';
    }

    /** Registered client (the only other role besides the super admin). */
    public function isClient(): bool
    {
        return $this->role !== 'admin';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
