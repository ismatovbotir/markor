<?php

namespace App\Support;

use App\Domain\Settings\Setting;

/**
 * SEO values edited by the super admin (Admin → SEO), stored in the settings table:
 *   seo.{page}.{field}.{locale}  — title / description override per public page and language
 *   seo.google | seo.yandex | seo.bing — site verification codes (meta tags)
 *   seo.ga4 | seo.metrika          — Google Analytics 4 / Yandex Metrika counter ids
 *   seo.noindex                    — "1": hide the whole site from search engines
 *   seo.robots_extra               — extra lines appended to robots.txt
 *   seo.updated_at                 — last change, used as <lastmod> in the sitemap
 * Without an override the texts come from lang/*.json (seo.home.title …).
 */
class Seo
{
    public const PAGES = ['home', 'studio'];
    public const FIELDS = ['title' => 60, 'description' => 160]; // recommended max length

    public static function get(string $key): ?string
    {
        $value = Setting::all_()["seo.$key"] ?? null;

        return filled($value) ? $value : null;
    }

    /** Title / description of a public page in the current language. */
    public static function text(string $page, string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return self::get("$page.$field.$locale") ?? __("seo.$page.$field", [], $locale);
    }

    public static function noindex(): bool
    {
        return self::get('noindex') === '1';
    }
}
