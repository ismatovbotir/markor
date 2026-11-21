<?php

namespace Tests\Feature;

use App\Support\Seo;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_seo_texts_fit_and_reach_every_public_page(): void
    {
        $this->seed(SettingsSeeder::class);

        foreach (Seo::PAGES as $page) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $title = Seo::get("$page.title.$l");
                $description = Seo::get("$page.description.$l");
                $this->assertNotNull($title, "$page.title.$l seeded");
                $this->assertLessThanOrEqual($l === 'zh' ? 32 : 60, mb_strlen($title), "$page.title.$l length");
                $this->assertLessThanOrEqual($l === 'zh' ? 85 : 160, mb_strlen($description), "$page.description.$l length");
            }
        }

        $urls = ['home' => '/', 'studio' => '/label-design', 'china' => '/uzbekistan-marking', 'contact' => '/contact'];
        foreach ($urls as $page => $url) {
            $this->get("$url?lang=ru")->assertSee('<title>' . e(Seo::get("$page.title.ru")) . '</title>', false)
                ->assertSee('<meta name="description" content="' . e(Seo::get("$page.description.ru")) . '">', false);
        }
    }
}
