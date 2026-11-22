<?php

namespace App\Domain\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** One news post on the public site (/news). Texts per language, uz is the fallback. */
class NewsPost extends Model
{
    protected $fillable = ['slug', 'title_uz', 'title_ru', 'title_zh', 'excerpt_uz', 'excerpt_ru', 'excerpt_zh',
        'body_uz', 'body_ru', 'body_zh', 'source_name', 'source_url', 'published_at', 'is_active'];

    /** Texts kept per language as {field}_{uz|ru|zh}. */
    public const TRANSLATABLE = ['title', 'excerpt', 'body'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Visible on the site: switched on and the publish date has come. */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->is_active && $this->published_at && $this->published_at->lte(now());
    }

    /** For the admin list: published (on the site), scheduled (switched on, date not come yet) or hidden. */
    public function status(): string
    {
        if (!$this->is_active || !$this->published_at) {
            return 'hidden';
        }

        return $this->published_at->isFuture() ? 'scheduled' : 'published';
    }

    public function localized(string $field): ?string
    {
        $l = app()->getLocale();

        return $this->{"{$field}_$l"} ?: $this->{"{$field}_uz"};
    }

    /** Body as safe HTML: Markdown, raw HTML stripped, no javascript: links. */
    public function bodyHtml(): string
    {
        return Str::markdown((string) $this->localized('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** Short text for cards and the meta description: the excerpt, or the start of the body. */
    public function summary(int $limit = 160): string
    {
        $text = $this->localized('excerpt') ?: strip_tags($this->bodyHtml());

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $limit);
    }

    /** The source site's host, shown next to the source link ("crpt-turon.uz"). */
    public function sourceHost(): ?string
    {
        return $this->source_url ? preg_replace('/^www\./', '', (string) parse_url($this->source_url, PHP_URL_HOST)) : null;
    }

    /** Fields as plain data, for the MCP tools. */
    public function toToolArray(bool $withBody = false): array
    {
        $data = ['id' => $this->id, 'slug' => $this->slug, 'url' => route('news.show', $this->slug),
            'title_uz' => $this->title_uz, 'title_ru' => $this->title_ru, 'title_zh' => $this->title_zh,
            'excerpt_uz' => $this->excerpt_uz, 'excerpt_ru' => $this->excerpt_ru, 'excerpt_zh' => $this->excerpt_zh,
            'source_name' => $this->source_name, 'source_url' => $this->source_url,
            'published_at' => $this->published_at?->toIso8601String(), 'is_active' => $this->is_active, 'is_public' => $this->isPublished()];

        return $withBody ? $data + ['body_uz' => $this->body_uz, 'body_ru' => $this->body_ru, 'body_zh' => $this->body_zh] : $data;
    }

    /** Validation rules shared by the admin form and the MCP tools ($partial: update, every field optional). */
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title_uz' => "$req|string|max:200", 'title_ru' => 'nullable|string|max:200', 'title_zh' => 'nullable|string|max:200',
            'excerpt_uz' => 'nullable|string|max:300', 'excerpt_ru' => 'nullable|string|max:300', 'excerpt_zh' => 'nullable|string|max:300',
            'body_uz' => "$req|string|max:20000", 'body_ru' => 'nullable|string|max:20000', 'body_zh' => 'nullable|string|max:20000',
            'source_name' => 'nullable|string|max:120', 'source_url' => 'nullable|url:http,https|max:500',
            'published_at' => ($partial ? 'sometimes' : 'nullable') . '|date', 'is_active' => 'sometimes|boolean',
        ];
    }

    public static function freeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'news';
        $base = Str::limit($base, 140, '');
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }
}
