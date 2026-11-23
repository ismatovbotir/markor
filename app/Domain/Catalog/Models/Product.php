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
        'company_id', 'product_group_id', 'gtin', 'name', 'description', 'weight', 'manufacturer', 'country', 'shelf_life', 'gtin_seq',
        'name_uz', 'name_ru', // classifier names (tasnif / AI), the source of `name` until the client edits it
        'sku', 'barcode_ean', 'attributes', 'lookup_status', 'lookup_at', 'is_active',
    ];

    /** Printable fields of the product card (label designer "product" fields). */
    public const CARD_FIELDS = ['name', 'description', 'sku', 'weight', 'manufacturer', 'country', 'shelf_life'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'lookup_at'  => 'datetime',
            'is_active'  => 'boolean',
        ];
    }

    /**
     * Products belong to a company and are never shared: a client sees its company's products only
     * (GTIN unique per company); the super admin sees all, read-only. Queue jobs (no user) are not filtered.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && !$user->isAdmin()) {
                $query->where($query->qualifyColumn('company_id'), $user->company_id ?? 0);
            }
        });

        // Until the client edits the name, it follows the classifier (tasnif / AI); unknown to tasnif → the GTIN.
        static::saving(function (self $product) {
            if (!empty(($product->getAttribute('attributes') ?? [])['name_by_client'])) {
                return;
            }
            if ($auto = $product->name_uz ?: $product->name_ru) {
                $product->setAttribute('name', mb_substr($auto, 0, 255));
            } elseif (blank($product->getAttributeFromArray('name')) && $product->lookup_status === 'not_found') {
                $product->setAttribute('name', $product->gtin);
            }
        });
    }

    /** Saves a name the client typed: from now on the classifier never changes it. */
    public function markNamedByClient(): void
    {
        $this->setAttribute('attributes', ['name_by_client' => true] + ($this->getAttribute('attributes') ?? []));
    }

    public function company()
    {
        return $this->belongsTo(\App\Domain\Companies\Company::class);
    }

    /**
     * The next internal EAN-13 of a company: "20" (GS1 in-store range) + company id (4) + item number (6)
     * + check digit, stored as GTIN-14. Valid for in-house labels only — never for Asl Belgisi / Честный ЗНАК.
     * Call inside a transaction holding the company row lock.
     * @return array{gtin: string, gtin_seq: int}
     */
    public static function nextInternalGtin(int $companyId): array
    {
        if ($companyId > 9999) {
            throw new \DomainException('products.gtin_company_too_big');
        }
        $seq = (int) static::withoutGlobalScope('owner')->withTrashed()->where('company_id', $companyId)->max('gtin_seq') + 1;
        do {
            if ($seq > 999999) {
                throw new \DomainException('products.gtin_sequence_full');
            }
            $body = '20' . str_pad((string) $companyId, 4, '0', STR_PAD_LEFT) . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $gtin = '0' . $body . self::checkDigit($body);
            $taken = static::withoutGlobalScope('owner')->withTrashed()->where('company_id', $companyId)->where('gtin', $gtin)->exists();
        } while ($taken && ++$seq);

        return ['gtin' => $gtin, 'gtin_seq' => $seq];
    }

    /** GS1 check digit (mod 10) of the digits before it. */
    public static function checkDigit(string $digits): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }

    /** An internal (in-store, 20–29) barcode: fine for labels, not for marking. */
    public function hasInternalGtin(): bool
    {
        return (bool) preg_match('/^02[0-9]/', (string) $this->gtin);
    }

    /** The GTIN as the 13-digit EAN printed under the barcode (leading 0 of GTIN-14 dropped). */
    public function ean13(): string
    {
        return str_starts_with((string) $this->gtin, '0') ? substr($this->gtin, 1) : (string) $this->gtin;
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

    /** The card's name (one text, as the client wrote it); before it is set — the classifier name. */
    public function getNameAttribute(): ?string
    {
        $name = $this->attributes['name'] ?? null;
        if (blank($name)) {
            $name = app()->getLocale() === 'ru' ? ($this->name_ru ?: $this->name_uz) : ($this->name_uz ?: $this->name_ru);
        }

        return $name ?: null;
    }

    /** Classifier lookup result: found / not_found / error / null (not checked yet). */
    public function lookupStatus(): ?string
    {
        // a dedicated column if the schema has one, otherwise the JSON attributes
        return $this->getAttributeFromArray('lookup_status')
            ?? ($this->getAttribute('attributes')['lookup'] ?? null);
    }

    /** The classifier did not know this GTIN and the name is still the GTIN: the client should name it. */
    public function needsName(): bool
    {
        return (blank($this->attributes['name'] ?? null) || ($this->attributes['name'] ?? null) === $this->gtin)
            && blank($this->name_uz) && blank($this->name_ru) && $this->lookupStatus() === 'not_found';
    }

    /** Products are per company now: kept so older callers keep working. */
    public function forCompany(?int $companyId): static
    {
        return $this;
    }

    /** Name if filled in, otherwise the GTIN — for dropdowns and lists. */
    public function getLabelAttribute(): string
    {
        return $this->name ?? $this->gtin;
    }
}
