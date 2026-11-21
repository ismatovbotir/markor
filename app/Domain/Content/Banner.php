<?php

namespace App\Domain\Content;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const STYLES = ['light', 'dark', 'amber'];

    /** Tailwind classes per style: [background, title, text, button]. */
    public const THEMES = [
        'light' => ['bg-stone-100', 'text-stone-900', 'text-stone-600', 'bg-amber-700 text-white hover:bg-amber-800'],
        'dark'  => ['bg-stone-900', 'text-white', 'text-stone-300', 'bg-amber-500 text-stone-900 hover:bg-amber-400'],
        'amber' => ['bg-amber-700', 'text-white', 'text-amber-50', 'bg-white text-amber-800 hover:bg-amber-50'],
    ];

    public function theme(): array
    {
        return self::THEMES[$this->style] ?? self::THEMES['light'];
    }

    protected $fillable = ['is_default', 'title_uz', 'title_ru', 'title_zh', 'text_uz', 'text_ru', 'text_zh', 'button_uz', 'button_ru', 'button_zh',
        'url', 'offer_id', 'is_sponsored', 'image_path', 'style', 'sort_order', 'is_active', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'is_sponsored' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    /** The marketplace product this banner advertises (optional). */
    public function offer()
    {
        return $this->belongsTo(\App\Domain\Offers\Models\Offer::class);
    }

    /** Banners shown on the home page now: the default first, then active ones within their dates, in order. */
    public static function forHome()
    {
        $today = now()->toDateString();

        return static::query()
            ->where(fn ($q) => $q->where('is_default', true)->orWhere(fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
                // a product banner disappears while its product is hidden from the marketplace
                ->where(fn ($q) => $q->whereNull('offer_id')->orWhereHas('offer', fn ($o) => $o->active()))))
            ->with('offer')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function localized(string $field, ?string $locale = null): ?string
    {
        $l = $locale ?? app()->getLocale();

        return $this->{"{$field}_$l"} ?: $this->{"{$field}_uz"};
    }

    public function imageUrl(): ?string
    {
        // seeded brand pictures ship with the code (public/images/…); uploaded ones are in storage
        // without an own picture a product banner shows the product photo
        if (!$this->image_path && $this->offer_id) {
            return \App\Domain\Offers\Models\Offer::publicUrl($this->offer?->image_path);
        }

        return str_starts_with((string) $this->image_path, 'images/') ? '/' . $this->image_path
            : \App\Domain\Offers\Models\Offer::publicUrl($this->image_path);
    }

    /** A safe link: a site path ("/label-design") or http(s) URL only. */
    public function link(): ?string
    {
        $url = trim((string) $this->url);

        return $url !== '' && (str_starts_with($url, '/') && !str_starts_with($url, '//') || preg_match('#^https?://#i', $url)) ? $url : null;
    }

    /**
     * Brief for the banner / product picture (an AI image tool or a photographer). The banner shows the picture
     * inside a white rounded card, ~400×290 px, scaled to fit (object-contain): a landscape 4:3 shot on pure white
     * with even margins blends into the card on all three banner colours.
     */
    public static function imagePrompt(?string $product = null): string
    {
        $product = trim((string) $product) ?: '[brand and model, e.g. Zebra ZD421 thermal transfer label printer]';

        return <<<PROMPT
Studio product photo of the {$product}, for an e-commerce banner.
- The real device exactly as the manufacturer makes it: same shape, colours, buttons and logo; do not invent text, ports or parts. If a reference photo is attached, keep the product identical to it and only change the background and light.
- Three-quarter front view, camera slightly above, the whole product visible and centred.
- Pure white seamless background (#FFFFFF), no gradient, no props, no hands, no people, no added text, no watermark.
- Soft even studio light, a faint natural shadow under the product, sharp focus, true colours.
- The product takes about 75% of the frame width, with an equal empty margin on every side.
- Landscape 4:3, 1600×1200 px, PNG or JPG.
- Printers only: a short strip of white label with a small black DataMatrix code coming out of the print slot.
PROMPT;
    }
}
