<?php

namespace App\Domain\Content;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const STYLES = ['light', 'dark', 'amber'];

    protected $fillable = ['is_default', 'title_uz', 'title_ru', 'title_zh', 'text_uz', 'text_ru', 'text_zh', 'button_uz', 'button_ru', 'button_zh',
        'url', 'is_sponsored', 'image_path', 'style', 'sort_order', 'is_active', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'is_sponsored' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    /** Banners shown on the home page now: the default first, then active ones within their dates, in order. */
    public static function forHome()
    {
        $today = now()->toDateString();

        return static::query()
            ->where(fn ($q) => $q->where('is_default', true)->orWhere(fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))))
            ->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function localized(string $field): ?string
    {
        $l = app()->getLocale();

        return $this->{"{$field}_$l"} ?: $this->{"{$field}_uz"};
    }

    public function imageUrl(): ?string
    {
        return \App\Domain\Offers\Models\Offer::publicUrl($this->image_path);
    }

    /** A safe link: a site path ("/label-design") or http(s) URL only. */
    public function link(): ?string
    {
        $url = trim((string) $this->url);

        return $url !== '' && (str_starts_with($url, '/') && !str_starts_with($url, '//') || preg_match('#^https?://#i', $url)) ? $url : null;
    }
}
