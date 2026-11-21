<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every item of the public menu is its own page (no home page anchors). */
class MenuPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_are_pages_in_three_languages(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);

        foreach (['aslbelgi' => '/asl-belgisi', 'marketplace' => '/marketplace', 'faq' => '/faq'] as $route => $path) {
            $this->assertSame(url($path), route($route));
            foreach (['uz', 'ru', 'zh'] as $l) {
                $html = $this->get("$path?lang=$l")->assertOk()->getContent();
                $this->assertSame(1, substr_count($html, '<h1'), "$path ($l): one H1");
                $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
                $this->assertStringContainsString('<title>' . e(\App\Support\Seo::text($route, 'title', $l)) . '</title>', $html);
            }
        }

        $home = $this->get('/?lang=uz')->assertOk()->getContent();
        foreach (['aslbelgi', 'marketplace', 'faq'] as $route) {
            $this->assertStringContainsString('href="' . route($route) . '"', $home, "menu links to /$route");
        }
        $this->assertStringNotContainsString('href="' . route('home') . '#offers"', $home);
        $this->assertStringContainsString('"@type":"FAQPage"', $this->get('/faq')->getContent());
        $this->assertStringContainsString('"@type":"ItemList"', $this->get('/marketplace')->getContent());
        $this->get(route('sitemap'))->assertSee(route('marketplace'), false)->assertSee(route('faq'), false)->assertSee(route('aslbelgi'), false);
    }
}
