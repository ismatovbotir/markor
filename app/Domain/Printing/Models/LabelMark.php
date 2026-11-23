<?php

namespace App\Domain\Printing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * An icon a label can carry ("Знаки" menu of the label designer: Asl Belgisi, Честный ЗНАК, EAC, fragile…).
 * Managed by the super admin: add, replace the image, hide. Never deleted — saved templates refer to it by key,
 * and a hidden icon still prints on them; it only leaves the menu.
 */
class LabelMark extends Model
{
    public const TRANSLATABLE = ['name'];

    /** Menu groups, in menu order. */
    public const GROUPS = ['marking', 'conformity', 'handling', 'ecology', 'info'];

    /** Groups whose icons may be printed only on certified products (the designer says so). */
    public const CERTIFIED_GROUPS = ['conformity'];

    private const CACHE = 'label_marks.v1';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'width' => 'integer', 'height' => 'integer', 'sort' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE));
    }

    public static function rules(): array
    {
        return [
            'name_uz' => 'required|string|max:120', 'name_ru' => 'nullable|string|max:120', 'name_zh' => 'nullable|string|max:120',
            'group'   => 'required|in:' . implode(',', self::GROUPS),
            'source'  => 'nullable|string|max:500',
            'sort'    => 'nullable|integer|min:0|max:100000',
        ];
    }

    /** Every icon with an image, as arrays (one query per cache flush; thousands of labels share it). */
    public static function cached(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => static::query()->orderBy('sort')->orderBy('id')->get()
            ->map(fn (self $m) => $m->only(['id', 'key', 'group', 'name_uz', 'name_ru', 'name_zh', 'path', 'width', 'height', 'is_active']) + ['v' => $m->updated_at?->timestamp])
            ->keyBy('key')->all());
    }

    /** Keys a saved element may use: every icon with an image, hidden ones too (old templates keep working). */
    public static function keys(): array
    {
        return array_keys(array_filter(self::cached(), fn ($m) => $m['path']));
    }

    /** The menu: visible icons with an image, grouped. */
    public static function menu(): array
    {
        $groups = array_fill_keys(self::GROUPS, []);
        foreach (self::cached() as $m) {
            if ($m['is_active'] && $m['path']) {
                $groups[$m['group']][] = $m + ['label' => self::label($m), 'url' => self::urlOf($m)];
            }
        }

        return array_filter($groups);
    }

    /** key => image url, for the designer preview (hidden icons included). */
    public static function urls(): array
    {
        return array_map(fn ($m) => self::urlOf($m), array_filter(self::cached(), fn ($m) => $m['path']));
    }

    public static function label(array $m): string
    {
        return ($m['name_' . app()->getLocale()] ?? '') ?: $m['name_uz'];
    }

    public static function urlOf(array $m): ?string
    {
        return $m['path'] ? '/' . $m['path'] . '?v=' . $m['v'] : null;
    }

    /** Absolute file path of an image path (built-in in public/, uploaded on the public disk). */
    public static function fileOf(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return str_starts_with($path, 'storage/') ? Storage::disk('public')->path(substr($path, 8)) : public_path($path);
    }

    public function url(): ?string
    {
        return $this->path ? '/' . $this->path . '?v=' . $this->updated_at?->timestamp : null;
    }

    public function name(): string
    {
        return $this->{'name_' . app()->getLocale()} ?: $this->name_uz;
    }
}
