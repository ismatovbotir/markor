<?php

namespace App\Domain\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'offer_category_id', 'image_path', 'url',
        'slug', 'category', 'icon', 'title_uz', 'title_ru', 'title_zh',
        'description_uz', 'description_ru', 'description_zh', 'faq', 'price', 'action',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'faq' => 'array'];
    }

    /** Active item in an active category. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('offer_category_id')
                ->orWhereHas('offerCategory', fn ($c) => $c->where('is_active', true)));
    }

    /** Printer drivers that fit this product (/drivers pages). */
    public function drivers()
    {
        return $this->belongsToMany(\App\Domain\Content\PrinterDriver::class, 'offer_printer_driver');
    }

    public function offerCategory()
    {
        return $this->belongsTo(OfferCategory::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return self::publicUrl($this->image_path);
    }

    /**
     * Site-relative address of a file on the public disk ("/storage/…"), so images load whatever
     * host the site is opened on (APP_URL may differ: localhost, 127.0.0.1, LAN IP, domain).
     */
    public static function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    /** FAQ in the current language: [['q' => …, 'a' => …], …] (falls back to Uzbek). */
    public function localizedFaq(): array
    {
        $l = app()->getLocale();

        return collect($this->faq ?? [])
            ->map(fn ($f) => ['q' => $f["q_$l"] ?? null ?: ($f['q_uz'] ?? ''), 'a' => $f["a_$l"] ?? null ?: ($f['a_uz'] ?? '')])
            ->filter(fn ($f) => $f['q'] !== '' && $f['a'] !== '')
            ->values()->all();
    }

    public function getTitleAttribute(): string
    {
        return $this->{'title_' . app()->getLocale()} ?: $this->title_uz;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->{'description_' . app()->getLocale()} ?: $this->description_uz;
    }
}
