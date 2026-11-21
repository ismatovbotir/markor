<?php

namespace App\Domain\Content;

use Illuminate\Database\Eloquent\Model;

/** A driver page for one printer model. The file itself stays on the manufacturer's site (driver_url). */
class PrinterDriver extends Model
{
    public const KINDS = ['desktop', 'industrial', 'mobile'];
    public const OS = ['windows', 'macos', 'linux'];

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

    public function name(): string
    {
        return "{$this->brand} {$this->model}";
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
