<?php

namespace App\Http\Controllers\Guest;

use App\Domain\Offers\Models\Offer;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;

class HomeController extends Controller
{
    /** Public pages that search engines should index. */
    private const PUBLIC_ROUTES = ['home', 'studio', 'aslbelgi', 'marketplace', 'faq', 'china', 'contact', 'drivers', 'news'];

    public function index()
    {
        // visitors get the cached page (6 hours, per language); logged-in users always a fresh one
        if (\App\Support\PageCache::enabled()) {
            [$html, $hit] = \App\Support\PageCache::remember('home', fn () => $this->homeView()->render());

            return response($html)->header('X-Page-Cache', $hit ? 'HIT' : 'MISS');
        }

        return $this->homeView();
    }

    private function homeView()
    {
        return view('home', [
            'offers'  => Offer::active()->orderBy('sort_order')->get(),
            'catalog' => \App\Domain\Offers\Models\OfferCategory::catalog(),
            'templatePreviews' => \App\Domain\Offers\Models\OfferCategory::templatePreviews(),
            'faq'    => self::faq(),
        ]);
    }

    /** Home FAQ (home.faq.1..9) in the current language. */
    public static function faq()
    {
        return collect(range(1, 9))
            ->map(fn ($i) => ['q' => __("home.faq.$i.q"), 'a' => __("home.faq.$i.a")])
            ->reject(fn ($item) => str_starts_with($item['q'], 'home.faq.'))
            ->values();
    }

    private static function faqPage($items): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($items)->map(fn ($f) => [
            '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ])->values()->all()];
    }

    /** /asl-belgisi — the Asl Belgisi service on its own page (menu item). */
    public function aslbelgi()
    {
        $faq = self::faq();

        return view('pages.section', ['page' => 'aslbelgi', 'label' => 'Asl Belgisi', 'faq' => $faq, 'jsonLd' => $faq->isNotEmpty() ? [self::faqPage($faq)] : []]);
    }

    /** /marketplace — the whole catalog: equipment, labels, ribbons, service. */
    public function marketplace()
    {
        $catalog = \App\Domain\Offers\Models\OfferCategory::catalog();
        $items = $catalog->flatMap(fn ($c) => $c->isTemplates() ? [] : $c->offers)->values();
        $itemFaq = $items->flatMap(fn ($o) => $o->localizedFaq())->take(30);
        $jsonLd = [['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('home.offers.title'),
            'itemListElement' => $items->map(fn ($o, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $o->title,
                'url' => self::localizedUrl('marketplace.item', app()->getLocale(), ['offer' => $o->slug])])->all()]];
        if ($itemFaq->isNotEmpty()) {
            $jsonLd[] = self::faqPage($itemFaq);
        }

        return view('pages.section', ['page' => 'marketplace', 'label' => __('nav.offers'), 'catalog' => $catalog,
            'templatePreviews' => \App\Domain\Offers\Models\OfferCategory::templatePreviews(), 'jsonLd' => $jsonLd]);
    }

    /** /faq — frequently asked questions. */
    public function faqIndex()
    {
        $faq = self::faq();

        return view('pages.section', ['page' => 'faq', 'label' => __('home.nav.faq'), 'faq' => $faq, 'jsonLd' => $faq->isNotEmpty() ? [self::faqPage($faq)] : []]);
    }

    public function llms()
    {
        return $this->plain(\App\Support\LlmsText::build(false));
    }

    public function llmsFull()
    {
        return $this->plain(\App\Support\LlmsText::build(true));
    }

    private function plain(string $text)
    {
        abort_if(\App\Support\Seo::noindex() || \App\Support\Seo::aiAgents() === 'none', 404);

        return response($text, 200, ['Content-Type' => 'text/markdown; charset=utf-8', 'X-Robots-Tag' => 'noindex']);
    }

    /** Public guide page: resources/help/{locale}/{page}.md. */
    public function guide(?string $page = null)
    {
        $page ??= 'import';
        abort_unless(in_array($page, \App\Http\Controllers\HelpController::PAGES, true), 404);
        $html = \App\Http\Controllers\HelpController::render($page);
        abort_if($html === null, 404);

        return view('guide', [
            'page'  => $page,
            'html'  => $html,
            'title' => \App\Http\Controllers\HelpController::title($page),
            'pages' => collect(\App\Http\Controllers\HelpController::PAGES)->mapWithKeys(fn ($p) => [$p => \App\Http\Controllers\HelpController::title($p)])->filter(),
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
            'Disallow: /label-design/print',
            'Disallow: /label-design/barcode.png',
            'Disallow: /*?*utm_',
        ];
        // AI crawlers, named explicitly (a named group replaces "*", so it repeats the closed paths)
        $closed = array_values(array_filter($lines, fn ($l) => str_starts_with($l, 'Disallow: ')));
        foreach (\App\Support\Seo::AI_BOTS as $bot => $kind) {
            $blocked = match (\App\Support\Seo::aiAgents()) {
                'none'   => true,
                'search' => $kind === 'training',   // allow AI search and user-requested fetches, not model training
                default  => false,
            };
            $lines = [...$lines, '', "User-agent: $bot", ...($blocked ? ['Disallow: /'] : ['Allow: /', ...$closed])];
        }

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

        $items = Offer::where('is_active', true)->whereHas('offerCategory', fn ($q) => $q->where('is_active', true))->get(['slug', 'updated_at']);
        foreach ($items as $item) {
            foreach (array_keys(SetLocale::LOCALES) as $locale) {
                $xml .= "  <url>\n    <loc>" . e(self::localizedUrl('marketplace.item', $locale, ['offer' => $item->slug])) . "</loc>\n";
                foreach (SetLocale::HREFLANG as $alt => $hreflang) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . e(self::localizedUrl('marketplace.item', $alt, ['offer' => $item->slug])) . '"/>' . "\n";
                }
                $xml .= '    <lastmod>' . $item->updated_at?->toDateString() . "</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
            }
        }

        foreach (\App\Domain\Content\PrinterDriver::active()->get(['slug', 'updated_at']) as $driver) {
            foreach (array_keys(SetLocale::LOCALES) as $locale) {
                $xml .= "  <url>\n    <loc>" . e(self::localizedUrl('drivers.show', $locale, ['driver' => $driver->slug])) . "</loc>\n";
                foreach (SetLocale::HREFLANG as $alt => $hreflang) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . e(self::localizedUrl('drivers.show', $alt, ['driver' => $driver->slug])) . '"/>' . "\n";
                }
                $xml .= '    <lastmod>' . $driver->updated_at?->toDateString() . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
            }
        }

        foreach (\App\Domain\Content\NewsPost::published()->get(['slug', 'updated_at']) as $post) {
            foreach (array_keys(SetLocale::LOCALES) as $locale) {
                $xml .= "  <url>\n    <loc>" . e(self::localizedUrl('news.show', $locale, ['post' => $post->slug])) . "</loc>\n";
                foreach (SetLocale::HREFLANG as $alt => $hreflang) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . e(self::localizedUrl('news.show', $alt, ['post' => $post->slug])) . '"/>' . "\n";
                }
                $xml .= '    <lastmod>' . $post->updated_at?->toDateString() . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
            }
        }

        foreach (\App\Http\Controllers\HelpController::PAGES as $page) {
            if (!\App\Http\Controllers\HelpController::title($page)) {
                continue;
            }
            foreach (array_keys(SetLocale::LOCALES) as $locale) {
                $xml .= "  <url>\n    <loc>" . e(\App\Support\LlmsText::guideUrl($page, $locale)) . "</loc>\n";
                foreach (SetLocale::HREFLANG as $alt => $hreflang) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . e(\App\Support\LlmsText::guideUrl($page, $alt)) . '"/>' . "\n";
                }
                $xml .= "    <lastmod>$lastmod</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
            }
        }

        return response($xml . "</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /** Uzbek is the default and lives at the plain URL; other languages add ?lang=. */
    public static function localizedUrl(string $routeName, string $locale, array $params = []): string
    {
        return route($routeName, $locale === 'uz' ? $params : $params + ['lang' => $locale]);
    }
}
