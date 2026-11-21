<?php

namespace App\Domain\Billing;

use App\Domain\Companies\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['company_id', 'user_id', 'amount', 'method', 'status', 'comment', 'admin_note', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'decided_at' => 'datetime'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
