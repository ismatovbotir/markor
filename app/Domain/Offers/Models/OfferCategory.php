<?php

namespace App\Domain\Offers\Models;

use Illuminate\Database\Eloquent\Model;

class OfferCategory extends Model
{
    /** How many items a category shows before the "more items" button. */
    public const SHOWN_ITEMS = 5;

    /** Texts kept per language as {field}_{uz|ru|zh}. */
    public const TRANSLATABLE = ['name', 'description'];

    protected $fillable = [
        'slug', 'kind', 'icon', 'name_uz', 'name_ru', 'name_zh',
        'description_uz', 'description_ru', 'description_zh',
        'market_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function offers()
    {
        return $this->hasMany(Offer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isTemplates(): bool
    {
        return $this->kind === 'templates';
    }

    public function getNameAttribute(): string
    {
        return $this->{'name_' . app()->getLocale()} ?: $this->name_uz;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->{'description_' . app()->getLocale()} ?: $this->description_uz;
    }

    /** Previews of the label sizes shown in the labels category: [template_id => html]. */
    public static function templatePreviews(): array
    {
        $pdf = app(\App\Domain\Printing\Services\LabelPdfService::class);

        return \App\Domain\Printing\Models\LabelTemplate::public()->orderByDesc('is_default')->orderBy('name')->limit(self::SHOWN_ITEMS)->get()
            ->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)])->all();
    }

    /** Active categories with their active items, in order — for the public and client pages. */
    public static function catalog()
    {
        return static::active()->orderBy('sort_order')->orderBy('id')
            ->with(['offers' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }
}
