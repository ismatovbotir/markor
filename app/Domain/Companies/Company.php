<?php

namespace App\Domain\Companies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'tin', 'name', 'legal_address', 'director', 'phone', 'email',
        'bank_mfo', 'bank_account', 'vat_code', 'source', 'lookup_data', 'is_active', 'blocked_reason', 'free_until', 'discount_percent', 'discount_until', 'balance',
    ];

    protected function casts(): array
    {
        return ['lookup_data' => 'array', 'is_active' => 'boolean', 'discount_percent' => 'float', 'discount_until' => 'date', 'free_until' => 'date', 'balance' => 'float'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
