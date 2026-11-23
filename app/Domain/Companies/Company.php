<?php

namespace App\Domain\Companies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'tin', 'name', 'legal_address', 'director', 'phone', 'email',
        'bank_mfo', 'bank_account', 'vat_code', 'source', 'lookup_data', 'is_active', 'blocked_reason', 'free_until', 'free_codes_used', 'discount_percent', 'discount_until', 'balance', 'marking_system', 'template_limit',
    ];

    /** The Asl Belgisi API key is never serialised (JSON, arrays, logs). */
    protected $hidden = ['aslbelgi_api_key'];

    protected function casts(): array
    {
        return ['aslbelgi_api_key' => 'encrypted', 'aslbelgi_api_key_expires_at' => 'datetime', 'aslbelgi_api_key_checked_at' => 'datetime', 'lookup_data' => 'array', 'is_active' => 'boolean', 'discount_percent' => 'float', 'discount_until' => 'date', 'free_until' => 'date', 'balance' => 'float'];
    }

    public function hasAslbelgiKey(): bool
    {
        return filled($this->aslbelgi_api_key);
    }

    /** Read-only API client with this company's key (null when no key is entered). */
    public function aslbelgiClient(): ?\App\Services\Aslbelgi\AslbelgiClient
    {
        return $this->hasAslbelgiKey() ? new \App\Services\Aslbelgi\AslbelgiClient($this->aslbelgi_api_key) : null;
    }

    /** "My templates": label templates the company uses (see LabelTemplate::forCompany). */
    public function labelTemplates()
    {
        return $this->belongsToMany(\App\Domain\Printing\Models\LabelTemplate::class, 'company_label_template')->withTimestamps();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
