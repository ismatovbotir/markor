<?php

namespace App\Support;

use App\Domain\Offers\Models\OfferCategory;
use App\Http\Controllers\Guest\HomeController;
use App\Http\Controllers\HelpController;
use App\Http\Middleware\SetLocale;

/**
 * /llms.txt and /llms-full.txt — a plain Markdown summary of Markor for AI agents (Claude, ChatGPT,
 * Perplexity …), built from the same texts as the home page, guides and Marketplace, so it never
 * says more than the site itself. One file per language (?lang=ru / ?lang=zh).
 */
class LlmsText
{
    public static function build(bool $full = false): string
    {
        $l = app()->getLocale();
        $t = fn (string $key, array $r = []) => __($key, $r);
        $out = [];

        $out[] = '# ' . config('app.name');
        $out[] = '> ' . $t('home.footer.about');
        $out[] = $t('home.h1') . '. ' . Seo::text('home', 'description');

        $out[] = "\n## " . $t('home.products.design.title') . ' — ' . route('studio');
        $out[] = $t('home.products.design.text');
        foreach ([1, 2, 3] as $i) {
            $out[] = '- ' . $t("home.products.design.point$i");
        }
        foreach (array_keys(['excel' => 1, 'designer' => 1, 'codes' => 1, 'rows' => 1, 'rotate' => 1, 'generated' => 1, 'privacy' => 1, 'autosave' => 1]) as $f) {
            $out[] = '- ' . $t("home.features.$f.title") . ': ' . $t("home.features.$f.text");
        }
        $out[] = '- ' . $t('studio.aslbelgi_text');

        $out[] = "\n## " . $t('home.aslbelgi.title') . ' — ' . route('import-batches.index');
        $out[] = $t('home.aslbelgi.text');
        foreach ([1, 2, 3, 4] as $i) {
            $out[] = '- ' . $t("home.aslbelgi.point$i.title") . ': ' . $t("home.aslbelgi.point$i.text");
        }
        $out[] = '- ' . $t('home.aslbelgi.private');
        $out[] = '- ' . $t('home.aslbelgi.price');
        $out[] = '- ' . $t('home.aslbelgi.topup');
        $out[] = '- ' . $t('home.aslbelgi.telegram');
        $out[] = '- ' . $t('home.aslbelgi.register') . ': ' . route('register');

        $catalog = OfferCategory::catalog();
        if ($catalog->isNotEmpty()) {
            $out[] = "\n## " . $t('home.offers.title') . ' (' . $t('nav.offers') . ')';
            $out[] = $t('home.offers.subtitle');
            foreach ($catalog as $category) {
                $out[] = "\n### " . $category->name;
                if ($category->isTemplates()) {
                    // label sizes we sell = the label templates
                    foreach (\App\Domain\Printing\Models\LabelTemplate::orderBy('width_mm')->orderBy('height_mm')->get(['width_mm', 'height_mm'])->unique(fn ($t) => $t->width_mm . 'x' . $t->height_mm) as $size) {
                        $out[] = '- ' . $t('offers.labels_size', ['size' => $size->width_mm . '×' . $size->height_mm . ' mm']);
                    }
                    continue;
                }
                foreach ($category->offers as $item) {
                    $line = '- ' . $item->title . ($item->description ? ': ' . $item->description : '') . ($item->price ? ' (' . $item->price . ')' : '');
                    $out[] = $line;
                    if ($full) {
                        foreach ($item->localizedFaq() as $f) {
                            $out[] = '  - ' . $f['q'] . ' — ' . $f['a'];
                        }
                    }
                }
            }
        }

        $out[] = "\n## " . $t('guide.title');
        foreach (HelpController::PAGES as $page) {
            if ($title = HelpController::title($page)) {
                $out[] = '- [' . $title . '](' . self::guideUrl($page, $l) . ')';
            }
        }

        $out[] = "\n## " . $t('home.faq.title');
        foreach (range(1, 6) as $i) {
            if (!str_starts_with($q = $t("home.faq.$i.q"), 'home.faq.')) {
                $out[] = '- **' . $q . '** ' . $t("home.faq.$i.a");
            }
        }

        $contacts = array_filter([
            config('contact.phone') ? $t('auth.phone') . ': ' . config('contact.phone') : null,
            config('contact.telegram') ? 'Telegram: https://t.me/' . config('contact.telegram') : null,
            config('contact.telegram_bot') ? 'Telegram bot: https://t.me/' . config('contact.telegram_bot') : null,
            config('contact.email') ? 'Email: ' . config('contact.email') : null,
        ]);
        if ($contacts) {
            $out[] = "\n## " . $t('llms.contacts');
            foreach ($contacts as $c) {
                $out[] = '- ' . $c;
            }
        }

        $out[] = "\n## " . $t('llms.languages');
        foreach (array_keys(SetLocale::LOCALES) as $other) {
            $out[] = '- ' . SetLocale::LOCALES[$other] . ': ' . self::fileUrl($full, $other);
        }
        if (!$full) {
            $out[] = '- ' . $t('llms.full') . ': ' . self::fileUrl(true, $l);
        }

        if ($full) {
            foreach (HelpController::PAGES as $page) {
                if ($md = HelpController::markdown($page)) {
                    $out[] = "\n---\n";
                    $out[] = preg_replace(['/^\{\{img:[a-z0-9\-]+\}\}\s*$/m', '/^# /m', "/\n{3,}/"], ['', '## ', "\n\n"], $md);
                }
            }
        }

        return trim(implode("\n", $out)) . "\n";
    }

    public static function guideUrl(string $page, string $locale): string
    {
        return HomeController::localizedUrl('guide', $locale, ['page' => $page]);
    }

    public static function fileUrl(bool $full, string $locale): string
    {
        $url = route($full ? 'llms.full' : 'llms');

        return $locale === 'uz' ? $url : $url . '?lang=' . $locale;
    }
}
