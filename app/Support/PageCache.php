<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cached public pages (home page): the rendered HTML per language, kept for 6 hours.
 *  - only for visitors who are not logged in (logged-in users see their own header);
 *  - the per-visitor CSRF token is cut out before storing and put back on every request,
 *    so forms on the cached page (order requests) keep working;
 *  - any change of settings, SEO texts, Marketplace items or label templates clears it at once
 *    (bump()), so an edit never waits 6 hours.
 */
class PageCache
{
    public const TTL_HOURS = 6;
    private const TOKEN = '__MARKOR_CSRF__';

    public static function enabled(): bool
    {
        // tests opt in explicitly, so other tests always see a freshly rendered page
        return !auth()->check() && (!app()->runningUnitTests() || config('cache.pages_in_tests', false));
    }

    /** @param \Closure(): string $render */
    public static function remember(string $page, \Closure $render): array
    {
        $key = 'page.' . $page . '.' . app()->getLocale() . '.v' . self::version();
        $hit = Cache::has($key);
        $html = $hit ? Cache::get($key) : null;

        if ($html === null) {
            $html = str_replace(csrf_token(), self::TOKEN, $render());
            Cache::put($key, $html, now()->addHours(self::TTL_HOURS));
        }

        return [str_replace(self::TOKEN, csrf_token(), $html), $hit];
    }

    /** Forget every cached page (new version number = old entries are never read again and expire). */
    public static function bump(): void
    {
        Cache::forever('page.version', self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::get('page.version', 1);
    }
}
