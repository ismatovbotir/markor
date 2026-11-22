<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared helpers of the admin multilingual forms: the content languages, how complete a record's
 * translations are, and the length limits taken from the validation rules.
 * Columns follow the "{field}_{locale}" naming (title_uz, title_ru, title_zh).
 */
class AdminI18n
{
    /** The language every text is written in first; the only required one. */
    public const SOURCE = 'uz';

    /** Content languages in tab order. */
    public static function locales(): array
    {
        return array_keys(SetLocale::LOCALES);
    }

    /** Languages that are translations of the source. */
    public static function translations(): array
    {
        return array_values(array_diff(static::locales(), [static::SOURCE]));
    }

    /** Tab data per language: short code, own name, value for the HTML lang attribute. */
    public static function tabs(): array
    {
        $tabs = [];
        foreach (SetLocale::LOCALES as $locale => $name) {
            $tabs[$locale] = ['code' => strtoupper($locale), 'name' => $name, 'html' => SetLocale::HREFLANG[$locale] ?? $locale];
        }

        return $tabs;
    }

    /**
     * Translatable fields of a record with repeated rows (FAQ): the plain fields plus "{list}.{n}.{part}" per row,
     * e.g. fields(['title'], ['faq' => ['q', 'a']]) with two rows → title, faq.0.q, faq.0.a, faq.1.q, faq.1.a.
     */
    public static function fields(Model|array $item, array $fields, array $repeaters = []): array
    {
        foreach ($repeaters as $list => $parts) {
            foreach (array_keys((array) data_get($item, $list, [])) as $n) {
                foreach ($parts as $part) {
                    $fields[] = "$list.$n.$part";
                }
            }
        }

        return $fields;
    }

    /** Filled fields per language: ['uz' => 3, 'ru' => 2, 'zh' => 0]. $item is a model or an array. */
    public static function completeness(Model|array $item, array $fields): array
    {
        $counts = [];
        foreach (static::locales() as $locale) {
            $counts[$locale] = count(array_filter($fields, fn ($f) => static::filled(data_get($item, "{$f}_$locale"))));
        }

        return $counts;
    }

    /** True when any translatable field is empty in any translation language. */
    public static function isIncomplete(Model|array $item, array $fields): bool
    {
        $counts = static::completeness($item, $fields);

        return collect(static::translations())->contains(fn ($l) => $counts[$l] < count($fields));
    }

    /** Query filter: records with any translatable field empty in any translation language. */
    public static function scopeIncomplete(Builder $query, array $fields): Builder
    {
        return $query->where(function ($w) use ($fields) {
            foreach (static::translations() as $locale) {
                foreach ($fields as $field) {
                    $w->orWhereNull("{$field}_$locale")->orWhere("{$field}_$locale", '');
                }
            }
        });
    }

    /** How many records of a model have incomplete translations (the amber menu badge). */
    public static function incompleteCount(string $model, array $fields): int
    {
        return static::scopeIncomplete($model::query(), $fields)->count();
    }

    /** The "max:N" of a validation rule, so the counters and the server use one number. */
    public static function max(array $rules, string $key): ?int
    {
        $rule = $rules[$key] ?? '';
        $rule = is_array($rule) ? implode('|', array_filter($rule, 'is_string')) : (string) $rule;

        return preg_match('/(?:^|\|)max:(\d+)/', $rule, $m) ? (int) $m[1] : null;
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : filled($value);
    }
}
