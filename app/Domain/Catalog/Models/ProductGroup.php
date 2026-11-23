<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Product category of a marking system (Asl Belgisi, Честный ЗНАК — `marking_system`) with the DataMatrix structure of its marking codes.
 * structure: [{ai: "01", label: "GTIN", length: 14, variable: false}, ...]
 *   ai       — GS1 application identifier ("" = raw tail without an AI)
 *   length   — exact number of characters of the value
 *   variable — a GS (\x1D) separator follows this value in the DataMatrix when another field comes after it
 */
class ProductGroup extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'slug', 'marking_system', 'name_uz', 'name_ru', 'name_zh', 'code_format', 'structure', 'code_length', 'sample_code',
        'serial_length', 'crypto_length', 'requires_eds', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'structure'    => 'array',
            'requires_eds' => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $group) {
            $segments = $group->structure ?? [];
            $group->code_length = $segments ? self::lengthOf($segments) : null;
            // keep the legacy columns meaningful
            $serial = collect($segments)->firstWhere('ai', '21');
            $group->serial_length = $serial['length'] ?? $group->serial_length ?? 0;
            $group->crypto_length = collect($segments)->reject(fn ($s) => in_array($s['ai'], ['01', '21'], true))->sum('length');
        });
    }

    public static function lengthOf(array $segments): int
    {
        return array_sum(array_map(fn ($s) => strlen((string) ($s['ai'] ?? '')) + (int) $s['length'], $segments));
    }

    /** "(01)14 (21)20 ‹GS› (91)4 ‹GS› (92)44" */
    public function structureSummary(): string
    {
        $segments = $this->structure ?? [];
        $parts = [];
        foreach ($segments as $i => $s) {
            $parts[] = ($s['ai'] !== '' ? "({$s['ai']})" : '…') . $s['length'];
            if (!empty($s['variable']) && $i < count($segments) - 1) {
                $parts[] = '‹GS›';
            }
        }

        return implode(' ', $parts);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): string
    {
        return $this->{'name_' . app()->getLocale()} ?: $this->name_uz ?: (string) $this->name_ru;
    }
}
