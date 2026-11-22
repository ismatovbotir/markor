<?php

namespace App\Domain\Content;

use Illuminate\Database\Eloquent\Model;

/** A driver page for one printer model. The file itself stays on the manufacturer's site (driver_url). */
class PrinterDriver extends Model
{
    public const KINDS = ['desktop', 'industrial', 'mobile'];
    public const OS = ['windows', 'macos', 'linux'];

    /** Texts kept per language as {field}_{uz|ru|zh}. */
    public const TRANSLATABLE = ['summary', 'notes'];

    protected $fillable = ['brand', 'model', 'slug', 'kind', 'language', 'os', 'driver_url',
        'summary_uz', 'summary_ru', 'summary_zh', 'notes_uz', 'notes_ru', 'notes_zh', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['os' => 'array', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Marketplace products this driver fits (one driver → several models of the brand). */
    public function offers()
    {
        return $this->belongsToMany(\App\Domain\Offers\Models\Offer::class, 'offer_printer_driver');
    }

    /** "Zebra ZD421", "Godex" (no driver name), "Zebra ZDesigner" — the brand is not repeated. */
    public function name(): string
    {
        $model = trim((string) $this->model);
        if ($model === '' || str_starts_with(mb_strtolower($model), mb_strtolower($this->brand))) {
            return $model ?: (string) $this->brand;
        }

        return "{$this->brand} {$model}";
    }

    /** A free address for a driver: brand + name, or brand + "driver"; a number is added when taken. */
    public static function freeSlug(string $brand, ?string $model, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($brand . ' ' . str_replace('/', ' ', trim((string) $model) ?: 'driver'));
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    public function localized(string $field): ?string
    {
        $l = app()->getLocale();

        return $this->{"{$field}_$l"} ?: $this->{"{$field}_uz"};
    }

    /** The manufacturer's host, shown next to the download button ("zebra.com"). */
    public function host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($this->driver_url, PHP_URL_HOST));
    }
}
