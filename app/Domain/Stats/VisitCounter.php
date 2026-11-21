<?php

namespace App\Domain\Stats;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Counts a view of a public page into daily aggregates. Bots are counted apart and never as visitors. */
class VisitCounter
{
    /** Public pages that are counted (route names). */
    public const ROUTES = ['home', 'studio', 'china', 'contact', 'drivers', 'drivers.show', 'marketplace.item', 'guide'];

    public const RETENTION_DAYS = 180;

    private const BOTS = [
        'Googlebot' => 'Google', 'bingbot' => 'Bing', 'YandexBot' => 'Yandex', 'Baiduspider' => 'Baidu', 'DuckDuckBot' => 'DuckDuckGo',
        'GPTBot' => 'OpenAI', 'ChatGPT-User' => 'OpenAI', 'OAI-SearchBot' => 'OpenAI', 'ClaudeBot' => 'Anthropic', 'Claude-User' => 'Anthropic',
        'Claude-SearchBot' => 'Anthropic', 'PerplexityBot' => 'Perplexity', 'Applebot' => 'Apple', 'facebookexternalhit' => 'Facebook',
        'TelegramBot' => 'Telegram', 'Twitterbot' => 'X',
    ];

    public static function record(Request $request): void
    {
        $route = $request->route()?->getName();
        if (!in_array($route, self::ROUTES, true)) {
            return;
        }
        $date = now()->toDateString();
        $agent = (string) $request->userAgent();

        if ($bot = self::botName($agent)) {
            self::bump($date, 'bot', $bot);

            return;
        }

        $page = match ($route) {
            'drivers.show'     => '/drivers/' . $request->route('driver')?->slug,
            'marketplace.item' => '/marketplace/' . $request->route('offer')?->slug,
            'guide'            => '/guide' . ($request->route('page') ? '/' . $request->route('page') : ''),
            default            => '/' . ltrim(parse_url(route($route), PHP_URL_PATH) ?? '', '/'),
        };
        self::bump($date, 'page', $page);
        self::bump($date, 'lang', app()->getLocale());

        $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if ($referrer && preg_replace('/^www\./', '', $referrer) !== preg_replace('/^www\./', '', $request->getHost())) {
            self::bump($date, 'ref', mb_substr(preg_replace('/^www\./', '', strtolower($referrer)), 0, 191));
        }

        $hash = hash('sha256', $date . '|' . $request->ip() . '|' . $agent . '|' . config('app.key'));
        DB::table('site_visitors')->insertOrIgnore(['date' => $date, 'hash' => $hash]);
    }

    public static function botName(string $agent): ?string
    {
        foreach (self::BOTS as $needle => $name) {
            if (stripos($agent, $needle) !== false) {
                return $name;
            }
        }

        return ($agent === '' || preg_match('/bot|crawl|spider|slurp|curl|wget|python|headless|scrapy|httpclient|java\//i', $agent)) ? 'other' : null;
    }

    private static function bump(string $date, string $kind, string $key): void
    {
        DB::table('site_visits')->upsert([['date' => $date, 'kind' => $kind, 'key' => $key, 'count' => 1]],
            ['date', 'kind', 'key'], ['count' => DB::raw('count + 1')]);
    }

    public static function prune(): int
    {
        $before = now()->subDays(self::RETENTION_DAYS)->toDateString();

        return DB::table('site_visits')->where('date', '<', $before)->delete() + DB::table('site_visitors')->where('date', '<', $before)->delete();
    }
}
