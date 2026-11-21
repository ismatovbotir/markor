<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_llms_txt_describes_the_site_in_each_language(): void
    {
        Setting::put(['contact.phone' => '+998 90 175 40 01']);
        $uz = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=utf-8')->getContent();
        $this->assertStringStartsWith('# ' . config('app.name'), $uz);
        $this->assertStringContainsString(__('home.aslbelgi.price', [], 'uz'), $uz);
        $this->assertStringContainsString('+998 90 175 40 01', $uz);
        $this->assertStringContainsString(route('guide', 'import'), $uz);
        $this->assertStringContainsString('/llms-full.txt', $uz);

        $ru = $this->get('/llms.txt?lang=ru')->assertOk()->getContent();
        $this->assertStringContainsString(__('home.aslbelgi.price', [], 'ru'), $ru);
    }

    public function test_llms_full_includes_guides_and_item_faq(): void
    {
        $category = OfferCategory::create(['slug' => 'printers-llm', 'name_uz' => 'Printerlar', 'icon' => 'heroicon-o-printer', 'is_active' => true, 'sort_order' => 1]);
        Offer::create(['offer_category_id' => $category->id, 'slug' => 'xp-365b', 'category' => 'printers-llm', 'title_uz' => 'Xprinter XP-365B', 'is_active' => true, 'action' => 'order',
            'faq' => [['q_uz' => 'Qaysi etiketkalar mos?', 'a_uz' => '20–80 mm.']]]);

        $full = $this->get('/llms-full.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Xprinter XP-365B', $full);
        $this->assertStringContainsString('Qaysi etiketkalar mos? — 20–80 mm.', $full);
        $this->assertStringContainsString('## ' . \App\Http\Controllers\HelpController::title('import'), $full, 'guide text included');
        $this->assertStringNotContainsString('{{img:', $full);
    }

    public function test_public_guide_page_with_canonical_and_howto(): void
    {
        $this->get(route('guide', 'import'))->assertOk()
            ->assertSee('<link rel="canonical" href="' . route('guide', 'import') . '">', false)
            ->assertSee('"@type":"HowTo"', false)
            ->assertSee(\App\Http\Controllers\HelpController::title('import'));
        $this->get(route('guide', 'nope'))->assertNotFound();
        $this->get(route('sitemap'))->assertSee(route('guide', 'import'), false);
    }

    public function test_robots_names_ai_crawlers_and_follows_the_setting(): void
    {
        $robots = $this->get(route('robots'))->getContent();
        $this->assertMatchesRegularExpression("/User-agent: ClaudeBot\nAllow: \/\nDisallow: \/aslbelgi/", $robots, 'named group repeats closed paths');
        $this->assertStringContainsString("User-agent: Claude-SearchBot\nAllow: /", $robots);

        Setting::put(['seo.ai_agents' => 'search']);
        $robots = $this->get(route('robots'))->getContent();
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots, 'training blocked');
        $this->assertStringContainsString("User-agent: ChatGPT-User\nAllow: /", $robots, 'user fetches allowed');

        Setting::put(['seo.ai_agents' => 'none']);
        $this->assertStringContainsString("User-agent: Claude-User\nDisallow: /", $this->get(route('robots'))->getContent());
        $this->get('/llms.txt')->assertNotFound();
    }

    public function test_marketplace_faq_is_in_the_home_faq_markup(): void
    {
        $category = OfferCategory::create(['slug' => 'scanners-llm', 'name_uz' => 'Skanerlar', 'icon' => 'heroicon-o-qr-code', 'is_active' => true, 'sort_order' => 1]);
        Offer::create(['offer_category_id' => $category->id, 'slug' => 'scan-1', 'category' => 'scanners-llm', 'title_uz' => 'Skaner', 'is_active' => true, 'action' => 'order',
            'faq' => [['q_uz' => 'DataMatrix o\'qiydimi?', 'a_uz' => 'Ha, 2D skaner.']]]);

        $this->get('/')->assertOk()->assertSee('"@type":"FAQPage"', false)->assertSee('Ha, 2D skaner.', false);
    }
}
