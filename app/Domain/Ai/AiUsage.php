<?php

namespace App\Domain\Ai;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['feature', 'model', 'input_tokens', 'output_tokens', 'cost_usd', 'status', 'error'];

    protected function casts(): array
    {
        return ['cost_usd' => 'float'];
    }

    public static function monthCost(): float
    {
        try {
            return (float) static::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
        } catch (\Throwable) {
            return 0.0; // table not migrated yet
        }
    }
}
