<?php

namespace Tests\Feature;

use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Mcp\Servers\ContentServer;
use App\Mcp\Tools\Marketplace;
use App\Support\McpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceMcpTest extends TestCase
{
    use RefreshDatabase;

    private OfferCategory $printers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->printers = OfferCategory::create(['slug' => 'printers', 'kind' => 'items', 'name_uz' => 'Printerlar', 'name_ru' => 'Принтеры', 'is_active' => true, 'sort_order' => 1]);
        OfferCategory::create(['slug' => 'labels', 'kind' => 'templates', 'name_uz' => 'Etiketkalar', 'is_active' => true, 'sort_order' => 2]);
    }

    public function test_create_fill_faq_and_show_on_the_site_without_price(): void
    {
        ContentServer::tool(Marketplace\ListCategories::class)->assertOk()->assertSee('printers')->assertDontSee('"labels"');
        ContentServer::tool(Marketplace\CreateOffer::class, ['category' => 'labels', 'title_uz' => 'X'])->assertHasErrors();   // template category is admin-only
        ContentServer::tool(Marketplace\CreateOffer::class, ['category' => 'printers', 'title_uz' => 'Godex G500', 'price' => '1 000 000'])->assertOk();

        $offer = Offer::where('slug', 'godex-g500')->firstOrFail();
        $this->assertNull($offer->price);                                     // a price argument is ignored
        $this->assertSame('order', $offer->action);

        ContentServer::tool(Marketplace\UpdateOffer::class, ['slug' => 'godex-g500', 'title_ru' => 'Принтер Godex G500', 'description_uz' => '203 dpi termo printer.', 'button' => 'call'])->assertOk();
        ContentServer::tool(Marketplace\SetOfferFaq::class, ['id' => $offer->id, 'faq' => [
            ['q_uz' => 'Ribbon kerakmi?', 'a_uz' => 'Termotransfer rejimida — ha.', 'q_ru' => 'Нужен риббон?', 'a_ru' => 'В режиме термотрансфера — да.'],
        ]])->assertOk();
        ContentServer::tool(Marketplace\SetOfferFaq::class, ['id' => $offer->id, 'faq' => [['q_uz' => 'Faqat savol']]])->assertHasErrors();

        $offer->refresh();
        $this->assertSame('call', $offer->action);
        $this->assertSame('Нужен риббон?', $offer->faq[0]['q_ru']);
        $html = $this->get('/marketplace/godex-g500?lang=ru')->assertOk()->assertSee('Принтер Godex G500')->assertSee('Нужен риббон?')->getContent();
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringNotContainsString('"price"', $html);

        ContentServer::tool(Marketplace\GetOffer::class, ['slug' => 'godex-g500'])->assertOk()->assertSee('203 dpi');
        ContentServer::tool(Marketplace\FindIncompleteOffers::class)->assertOk()->assertSee('title_zh')->assertSee('image (upload in admin)');
    }

    public function test_hide_reorder_and_link_driver(): void
    {
        $a = Offer::create(['offer_category_id' => $this->printers->id, 'category' => 'printers', 'slug' => 'a', 'title_uz' => 'A', 'sort_order' => 0, 'is_active' => true]);
        $b = Offer::create(['offer_category_id' => $this->printers->id, 'category' => 'printers', 'slug' => 'b', 'title_uz' => 'B', 'sort_order' => 1, 'is_active' => true]);
        $c = Offer::create(['offer_category_id' => $this->printers->id, 'category' => 'printers', 'slug' => 'c', 'title_uz' => 'C', 'sort_order' => 2, 'is_active' => true]);

        ContentServer::tool(Marketplace\SetOfferVisibility::class, ['slug' => 'b', 'visible' => false])->assertOk();
        $this->get('/marketplace/b')->assertNotFound();

        ContentServer::tool(Marketplace\ReorderOffers::class, ['category' => 'printers', 'ids' => [$c->id]])->assertOk();
        $this->assertSame([$c->id, $a->id, $b->id], Offer::orderBy('sort_order')->pluck('id')->all());
        ContentServer::tool(Marketplace\ReorderOffers::class, ['category' => 'printers', 'ids' => [999]])->assertHasErrors();

        PrinterDriver::create(['brand' => 'Zebra', 'model' => 'ZD421', 'slug' => 'zebra-zd421', 'driver_url' => 'https://www.zebra.com/x', 'is_active' => true]);
        ContentServer::tool(Marketplace\LinkOfferDriver::class)->assertOk()->assertSee('zebra-zd421');
        ContentServer::tool(Marketplace\LinkOfferDriver::class, ['slug' => 'a', 'driver' => 'zebra-zd421'])->assertOk();
        $this->assertSame(['zebra-zd421'], $a->drivers()->pluck('slug')->all());
        ContentServer::tool(Marketplace\LinkOfferDriver::class, ['slug' => 'a', 'driver' => 'zebra-zd421', 'linked' => false])->assertOk();
        $this->assertSame(0, $a->drivers()->count());
    }

    public function test_tools_are_listed_on_the_content_endpoint(): void
    {
        $token = McpToken::create();
        $names = collect($this->withHeaders(['Authorization' => "Bearer $token", 'Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp/content', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk()->json('result.tools'))->pluck('name');
        foreach (['list-news', 'create-offer', 'set-offer-faq', 'reorder-offers', 'link-offer-driver', 'find-incomplete-offers'] as $tool) {
            $this->assertContains($tool, $names);
        }
        $this->assertNotContains('delete-offer', $names);
    }
}
