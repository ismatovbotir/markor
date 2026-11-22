<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceItemPageTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $extra = []): Offer
    {
        $category = OfferCategory::create(['slug' => 'printers-page', 'name_uz' => 'Printerlar', 'name_ru' => 'Принтеры', 'icon' => 'heroicon-o-printer', 'is_active' => true, 'sort_order' => 1]);

        return Offer::create($extra + ['offer_category_id' => $category->id, 'slug' => 'xprinter-xp-365b', 'category' => 'printers-page', 'title_uz' => 'Xprinter XP-365B',
            'title_ru' => 'Термопринтер Xprinter XP-365B', 'description_uz' => '20–80 mm etiketkalar uchun.', 'action' => 'order', 'is_active' => true,
            'faq' => [['q_uz' => 'Qaysi etiketkalar mos?', 'a_uz' => '20–80 mm.']]]);
    }

    public function test_item_page_with_seo_product_data_and_faq(): void
    {
        $offer = $this->item();
        $html = $this->get(route('marketplace.item', $offer->slug))->assertOk()
            ->assertSee('<h1', false)->assertSee('Xprinter XP-365B')
            ->assertSee('<link rel="canonical" href="' . route('marketplace.item', 'xprinter-xp-365b') . '">', false)
            ->assertSee('Qaysi etiketkalar mos?')->getContent();
        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringNotContainsString('"price"', $html);                 // products have no price
        $this->assertStringNotContainsString("Narxi so'rov bo'yicha", $html);  // no "price on request" label either
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);

        $this->get(route('marketplace.item', $offer->slug) . '?lang=ru')->assertOk()->assertSee('Термопринтер Xprinter XP-365B');
    }

    public function test_hidden_items_are_not_public_and_items_are_in_the_sitemap(): void
    {
        $offer = $this->item();
        $this->get(route('sitemap'))->assertSee(route('marketplace.item', 'xprinter-xp-365b'), false);
        $this->get('/')->assertSee(route('marketplace.item', 'xprinter-xp-365b'), false); // card links to the page

        $offer->update(['is_active' => false]);
        $this->get(route('marketplace.item', $offer->slug))->assertNotFound();
        $this->get(route('marketplace.item', 'no-such-item'))->assertNotFound();
    }

    public function test_other_categories_follow_in_small_cards(): void
    {
        $offer = $this->item();
        $ribbons = OfferCategory::create(['slug' => 'ribbons-page', 'name_uz' => 'Ribbonlar', 'is_active' => true, 'sort_order' => 2]);
        Offer::create(['offer_category_id' => $ribbons->id, 'slug' => 'wax-110', 'category' => 'ribbons-page', 'title_uz' => 'Wax ribbon 110x300', 'action' => 'order', 'is_active' => true]);
        $empty = OfferCategory::create(['slug' => 'empty-page', 'name_uz' => 'Bo\'sh bo\'lim', 'is_active' => true, 'sort_order' => 3]);

        $this->get(route('marketplace.item', $offer->slug))->assertOk()
            ->assertSee(__('marketplace.other_categories'))
            ->assertSee('Ribbonlar')->assertSee('Wax ribbon 110x300')
            ->assertSee(route('marketplace.item', 'wax-110'), false)
            ->assertDontSee("Bo&#039;sh bo&#039;lim", false);
    }
}
