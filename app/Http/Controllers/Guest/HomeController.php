<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Offers\Models\Offer;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;

class HomeController extends Controller
{
    /** Public pages that search engines should index. */
    private const PUBLIC_ROUTES = ['home', 'studio'];

    public function index()
    {
        return view('home', [
            'offers'  => Offer::active()->orderBy('sort_order')->get(),
            'catalog' => \App\Domain\Offers\Models\OfferCategory::catalog(),
            'templatePreviews' => \App\Domain\Offers\Models\OfferCategory::templatePreviews(),
            'faq'    => collect(range(1, 6))
                ->map(fn ($i) => ['q' => __("home.faq.$i.q"), 'a' => __("home.faq.$i.a")])
                ->reject(fn ($item) => str_starts_with($item['q'], 'home.faq.'))
                ->values(),
        ]);
    }

    public function robots()
    {
        // the whole site hidden (Admin → SEO → noindex), e.g. while testing on a staging domain
        if (\App\Support\Seo::noindex()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $lines = [
            'User-agent: *',
            'Disallow: /aslbelgi',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /lang/',
            'Disallow: /telegram/',
            'Disallow: /dev/',
            'Disallow: /label-design/parse',
            'Disallow: /label-design/generate',
            'Disallow: /label-design/barcode.png',
            'Disallow: /*?*utm_',
        ];
        // extra rules from Admin → SEO
        if ($extra = trim((string) \App\Support\Seo::get('robots_extra'))) {
            $lines = [...$lines, '', ...preg_split('/\r?\n/', $extra)];
        }
        $lines = [...$lines, '', 'Sitemap: ' . route('sitemap')];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap()
    {
        if (\App\Support\Seo::noindex()) {
            abort(404); // hidden site: no sitemap
        }
        // last change of the page texts (SEO settings or the translation files)
        $lastmod = max(
            (string) \App\Support\Seo::get('updated_at'),
            date('Y-m-d', max(array_map('filemtime', glob(lang_path('*.json')) ?: [time()])))
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach (self::PUBLIC_ROUTES as $name) {
            foreach (array_keys(SetLocale::LOCALES) as $locale) {
                $xml .= "  <url>\n    <loc>" . e(self::localizedUrl($name, $locale)) . "</loc>\n";
                foreach (SetLocale::HREFLANG as $alt => $hreflang) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . e(self::localizedUrl($name, $alt)) . '"/>' . "\n";
                }
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e(route($name)) . '"/>' . "\n";
                $xml .= "    <lastmod>$lastmod</lastmod>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n    <priority>" . ($name === 'home' ? '1.0' : '0.9') . "</priority>\n  </url>\n";
            }
        }

        return response($xml . "</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /** Uzbek is the default and lives at the plain URL; other languages add ?lang=. */
    public static function localizedUrl(string $routeName, string $locale): string
    {
        return $locale === 'uz' ? route($routeName) : route($routeName, ['lang' => $locale]);
    }
}
