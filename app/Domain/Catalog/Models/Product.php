<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_group_id', 'gtin', 'name_uz', 'name_ru',
        'sku', 'barcode_ean', 'attributes', 'lookup_status', 'lookup_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'lookup_at'  => 'datetime',
            'is_active'  => 'boolean',
        ];
    }

    /**
     * Global catalogue, one product per GTIN. A registered user only sees products they have
     * marking codes for; the super admin sees all.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->whereExists(fn ($q) => $q->selectRaw('1')->from('marking_codes')
                    ->whereColumn('marking_codes.product_id', 'products.id')
                    ->where('marking_codes.company_id', $user->company_id ?? 0));
            }
        });
    }

    /**
     * Owners of the given products: [product_id => [[user, codes], ...]], biggest owner first.
     */
    public static function ownersOf(iterable $productIds): array
    {
        $rows = \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')
            ->whereIn('product_id', collect($productIds)->all())
            ->whereNotNull('user_id')
            ->selectRaw('product_id, user_id, count(*) as codes')
            ->groupBy('product_id', 'user_id')
            ->orderByDesc('codes')
            ->get();
        $users = \App\Models\User::whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'name', 'email'])->keyBy('id');

        return $rows->groupBy('product_id')
            ->map(fn ($group) => $group->map(fn ($r) => ['user' => $users[$r->user_id] ?? null, 'codes' => (int) $r->codes])->filter(fn ($o) => $o['user'])->values()->all())
            ->all();
    }

    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function markingCodes()
    {
        return $this->hasMany(MarkingCode::class);
    }

    public function importBatches()
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getNameAttribute(): ?string
    {
        $name = app()->getLocale() === 'uz'
            ? ($this->name_uz ?: $this->name_ru)
            : ($this->name_ru ?: $this->name_uz);

        return $name ?: null;
    }

    /** Classifier lookup result: found / not_found / error / null (not checked yet). */
    public function lookupStatus(): ?string
    {
        // a dedicated column if the schema has one, otherwise the JSON attributes
        return $this->getAttributeFromArray('lookup_status')
            ?? ($this->getAttribute('attributes')['lookup'] ?? null);
    }

    /** The classifier did not know this GTIN and nobody has named it yet: the client must fill in the name. */
    public function needsName(): bool
    {
        return blank($this->name_uz) && blank($this->name_ru) && $this->lookupStatus() === 'not_found';
    }

    /**
     * This product as a company sees it: its own name for it (if it gave one) over the shared one.
     * Returns a copy for display/printing — never save it.
     */
    public function forCompany(?int $companyId): static
    {
        $own = $companyId ? self::companyName($companyId, $this->id) : null;
        if (!$own) {
            return $this;
        }
        $copy = clone $this;
        foreach (['name_uz', 'name_ru', 'sku'] as $field) {
            if (filled($own[$field] ?? null)) {
                $copy->setAttribute($field, $own[$field]);
            }
        }

        return $copy;
    }

    /** A company's own name for a product (cached for the request / queue job). */
    public static function companyName(int $companyId, int $productId): ?array
    {
        return once(fn () => CompanyProductName::where('company_id', $companyId)->where('product_id', $productId)
            ->first(['name_uz', 'name_ru', 'sku'])?->toArray());
    }

    /** Name if filled in, otherwise the GTIN — for dropdowns and lists. */
    public function getLabelAttribute(): string
    {
        return $this->name ?? $this->gtin;
    }
}
