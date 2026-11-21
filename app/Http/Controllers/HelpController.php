<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

/**
 * Illustrated client guides: resources/help/{locale}/{page}.md, screenshots in public/help/{locale}/{key}.jpg.
 * Placeholders {{img:key}} become images (falling back to the Uzbek screenshot).
 */
class HelpController extends Controller
{
    public const PAGES = ['company', 'balance', 'import', 'upload', 'pdf', 'products', 'codes', 'templates', 'offers', 'profile'];

    /** Which guide belongs to which page (route name). */
    public const FOR_ROUTE = [
        'company.setup' => 'company', 'import-batches.index' => 'import', 'products.index' => 'products',
        'marking-codes.index' => 'codes', 'templates.catalog' => 'templates', 'offers.index' => 'offers', 'profile' => 'profile', 'company.users' => 'profile', 'balance' => 'balance',
    ];

    public function show(?string $page = null)
    {
        $page ??= 'import';
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view('help.show', [
            'page'  => $page,
            'html'  => self::render($page),
            'pages' => collect(self::PAGES)->mapWithKeys(fn ($p) => [$p => self::title($p)])->filter(),
        ]);
    }

    public static function render(string $page): ?string
    {
        $markdown = self::source($page);
        if ($markdown === null) {
            return null;
        }

        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        return preg_replace_callback('/(?:<p>)?\{\{img:([a-z0-9\-]+)\}\}(?:<\/p>)?/', function ($m) {
            $url = self::image($m[1]);

            return $url ? '<figure class="help-shot"><img src="' . e($url) . '" alt="" loading="lazy"></figure>' : '';
        }, $html);
    }

    public static function title(string $page): ?string
    {
        $markdown = self::source($page);

        return $markdown && preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : null;
    }

    private static function source(string $page): ?string
    {
        foreach ([app()->getLocale(), 'uz'] as $locale) {
            $path = resource_path("help/$locale/$page.md");
            if (is_file($path)) {
                return file_get_contents($path);
            }
        }

        return null;
    }

    private static function image(string $key): ?string
    {
        foreach ([app()->getLocale(), 'uz'] as $locale) {
            foreach (['jpg', 'png'] as $ext) {
                if (is_file(public_path("help/$locale/$key.$ext"))) {
                    return asset("help/$locale/$key.$ext") . '?v=' . filemtime(public_path("help/$locale/$key.$ext"));
                }
            }
        }

        return null;
    }
}
