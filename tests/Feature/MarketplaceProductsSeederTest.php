<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use Database\Seeders\MarketplaceProductsSeeder;
use Database\Seeders\OfferSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function load(): void
    {
        (new MarketplaceProductsSeeder())->run();
    }

    public function test_real_products_load_and_placeholders_are_hidden(): void
    {
        $this->seed(OfferSeeder::class);
        $this->load();

        $printer = Offer::where('slug', 'godex-g500u')->firstOrFail();
        $this->assertSame('printers', $printer->category);
        $this->assertSame('call', $printer->action);
        $this->assertCount(3, $printer->faq);
        $this->assertSame(38, Offer::where('is_active', true)->whereIn('category', ['printers', 'scanners', 'pda', 'ribbons'])
            ->whereNotIn('slug', ['pda-cradle'])->count());
        $this->assertFalse(Offer::where('slug', 'printer-desktop-dt')->value('is_active'));

        $this->get(route('marketplace.item', 'zebra-ds2278'))->assertOk();
        $this->get(route('marketplace.item', 'printer-desktop-dt'))->assertNotFound();
    }

    public function test_running_again_and_offer_seeder_keep_the_products(): void
    {
        $this->seed(OfferSeeder::class);
        $this->load();
        $this->load();
        $this->seed(OfferSeeder::class);

        $this->assertSame(1, Offer::where('slug', 'zebra-zt411')->count());
        $this->assertSame(38, Offer::whereIn('slug', array_column(MarketplaceProductsSeeder::PRODUCTS, 'slug'))->count());
    }

    public function test_running_again_keeps_what_the_admin_edited(): void
    {
        $this->seed(OfferSeeder::class);
        $this->load();

        $p = Offer::where('slug', 'godex-g500u')->firstOrFail();
        $p->update(['title_uz' => 'Admin nomi', 'is_active' => false, 'sort_order' => 40, 'description_ru' => null]);
        Offer::where('slug', 'printer-desktop-dt')->update(['is_active' => true]); // admin showed a placeholder again

        $this->load();

        $p->refresh();
        $this->assertSame('Admin nomi', $p->title_uz, 'edited name kept');
        $this->assertFalse($p->is_active, 'hidden in admin stays hidden');
        $this->assertSame(40, $p->sort_order);
        $this->assertNotNull($p->description_ru, 'an emptied field is filled from the file');
        $this->assertTrue(Offer::where('slug', 'printer-desktop-dt')->value('is_active'), 'placeholder shown by admin stays');
    }
}
