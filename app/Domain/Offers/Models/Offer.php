<?php

namespace App\Domain\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'offer_category_id', 'image_path', 'url',
        'slug', 'category', 'icon', 'title_uz', 'title_ru', 'title_zh',
        'description_uz', 'description_ru', 'description_zh', 'price', 'action',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Active item in an active category. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('offer_category_id')
                ->orWhereHas('offerCategory', fn ($c) => $c->where('is_active', true)));
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

    public function getTitleAttribute(): string
    {
        return $this->{'title_' . app()->getLocale()} ?: $this->title_uz;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->{'description_' . app()->getLocale()} ?: $this->description_uz;
    }
}
