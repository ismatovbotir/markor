<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Interface language: explicit choice (session) → the user's saved language → app default. */
class SetLocale
{
    public const LOCALES = ['uz' => "O'zbekcha", 'ru' => 'Русский', 'zh' => '中文'];

    /** hreflang / og:locale codes */
    public const HREFLANG = ['uz' => 'uz', 'ru' => 'ru', 'zh' => 'zh-Hans'];

    public function handle(Request $request, Closure $next)
    {
        // ?lang=ru gives every language its own URL (needed for search engines and shared links).
        $fromUrl = $request->query('lang');
        if (is_string($fromUrl) && isset(self::LOCALES[$fromUrl])) {
            $request->session()->put('locale', $fromUrl);
        }

        $locale = $request->session()->get('locale') ?? $request->user()?->locale;

        if (isset(self::LOCALES[$locale])) {
            app()->setLocale($locale);
            // dates like "5 daqiqa oldin": Uzbek in Latin script, like the rest of the site (Carbon's plain "uz" is Cyrillic)
            \Illuminate\Support\Carbon::setLocale($locale === 'uz' ? 'uz_Latn' : $locale);
        }

        return $next($request);
    }
}
