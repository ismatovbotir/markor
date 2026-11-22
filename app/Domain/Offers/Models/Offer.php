<?php

namespace App\Domain\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'offer_category_id', 'image_path', 'url',
        'slug', 'category', 'icon', 'title_uz', 'title_ru', 'title_zh',
        'description_uz', 'description_ru', 'description_zh', 'faq', 'action',
        'sort_order', 'is_active',
    ];

    /** Texts kept per language as {field}_{uz|ru|zh}; FAQ rows keep q_{l} / a_{l}. */
    public const TRANSLATABLE = ['title', 'description'];
    public const FAQ_PARTS = ['q', 'a'];
    public const FAQ_MAX = 8;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'faq' => 'array'];
    }

    /** Every translatable field of this product, FAQ rows included (admin: language completeness). */
    public function translatableFields(): array
    {
        return \App\Support\AdminI18n::fields($this, self::TRANSLATABLE, ['faq' => self::FAQ_PARTS]);
    }

    /** Admin: any text or FAQ row without a Russian or Chinese translation. */
    public function translationIncomplete(): bool
    {
        return \App\Support\AdminI18n::isIncomplete($this, $this->translatableFields());
    }

    /** Text limits shared by the admin form (counters) and its validation. */
    public static function textRules(): array
    {
        $rules = [];
        foreach (\App\Support\AdminI18n::locales() as $l) {
            $rules["title_$l"] = ($l === \App\Support\AdminI18n::SOURCE ? 'required' : 'nullable') . '|string|max:160';
            $rules["description_$l"] = 'nullable|string|max:400';
            $rules["faq.*.q_$l"] = 'nullable|string|max:200';
            $rules["faq.*.a_$l"] = 'nullable|string|max:600';
        }

        return $rules;
    }

    /** Active item in an active category. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('offer_category_id')
                ->orWhereHas('offerCategory', fn ($c) => $c->where('is_active', true)));
    }

    /** A free page address from the Uzbek name; set once, never changed (links and search results keep working). */
    public static function freeSlug(string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'item';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    /** Fields as plain data, for the MCP tools (no image upload and no price: products have none). */
    public function toToolArray(bool $full = false): array
    {
        $data = ['id' => $this->id, 'slug' => $this->slug, 'url' => route('marketplace.item', $this->slug),
            'category' => $this->offerCategory?->slug, 'title_uz' => $this->title_uz, 'title_ru' => $this->title_ru, 'title_zh' => $this->title_zh,
            'is_active' => $this->is_active, 'has_image' => filled($this->image_path), 'sort_order' => $this->sort_order];

        return $full ? $data + [
            'description_uz' => $this->description_uz, 'description_ru' => $this->description_ru, 'description_zh' => $this->description_zh,
            'link' => $this->url, 'button' => $this->action, 'faq' => $this->faq ?? [],
            'drivers' => $this->drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->name(), 'slug' => $d->slug])->all(),
        ] : $data;
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
