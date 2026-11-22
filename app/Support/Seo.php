<?php

namespace App\Support;

use App\Domain\Settings\Setting;

/**
 * SEO values edited by the super admin (Admin → SEO), stored in the settings table:
 *   seo.{page}.{field}.{locale}  — title / description override per public page and language
 *   seo.google | seo.yandex | seo.bing | seo.baidu — site verification codes (meta tags)
 *   seo.ga4 | seo.metrika          — Google Analytics 4 / Yandex Metrika counter ids
 *   seo.noindex                    — "1": hide the whole site from search engines
 *   seo.robots_extra               — extra lines appended to robots.txt
 *   seo.updated_at                 — last change, used as <lastmod> in the sitemap
 *   seo.ai_agents                  — all (default) / search (no model training) / none
 * Without an override the texts come from lang/*.json (seo.home.title …).
 */
class Seo
{
    public const PAGES = ['home', 'studio', 'aslbelgi', 'marketplace', 'faq', 'china', 'contact', 'drivers', 'news'];
    public const FIELDS = ['title' => 60, 'description' => 160]; // recommended max length
    public const FIELDS_ZH = ['title' => 32, 'description' => 85]; // Chinese: search results cut by width, so fewer characters fit
    public const MAX = ['title' => 120, 'description' => 320];     // hard limit of an override (validation)

    /** Recommended length of a field per language: ['uz' => 60, 'ru' => 60, 'zh' => 32]. */
    public static function recommended(string $field): array
    {
        return collect(AdminI18n::locales())->mapWithKeys(fn ($l) => [$l => ($l === 'zh' ? self::FIELDS_ZH : self::FIELDS)[$field]])->all();
    }

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

    /** Known AI crawlers: search = AI search / user-requested fetch, training = collects data for model training. */
    public const AI_BOTS = [
        'ClaudeBot' => 'training', 'Claude-User' => 'search', 'Claude-SearchBot' => 'search',
        'GPTBot' => 'training', 'OAI-SearchBot' => 'search', 'ChatGPT-User' => 'search',
        'PerplexityBot' => 'search', 'Perplexity-User' => 'search',
        'Google-Extended' => 'training', 'Applebot-Extended' => 'training', 'CCBot' => 'training',
    ];

    public static function aiAgents(): string
    {
        $value = self::get('ai_agents');

        return in_array($value, ['all', 'search', 'none'], true) ? $value : 'all';
    }

    public static function noindex(): bool
    {
        return self::get('noindex') === '1';
    }
}
