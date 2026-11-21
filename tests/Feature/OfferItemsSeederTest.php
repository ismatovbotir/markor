<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use Database\Seeders\OfferItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferItemsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_file_loads_items_with_faq_for_the_product_page(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class); // the Marketplace categories
        $copy = tempnam(sys_get_temp_dir(), 'offers');
        copy(base_path('.claude/sample/marketplace-products-sample.csv'), $copy);

        $seeder = new OfferItemsSeeder();
        $seeder->path = $copy;
        $seeder->run();

        $printer = Offer::where('slug', 'xprinter-xp-365b')->firstOrFail();
        $this->assertSame("1 250 000 so'm", $printer->price);
        $this->assertCount(3, $printer->faq);
        $this->assertSame('Qaysi etiketkalar mos keladi?', $printer->faq[0]['q_uz']);
        $this->assertSame('Термоэтикетки шириной 20–80 мм.', $printer->faq[0]['a_ru']);
        $this->assertSame('call', Offer::where('slug', 'scanner-2d-wired')->value('action'));

        $this->get(route('marketplace.item', 'xprinter-xp-365b'))->assertOk()->assertSee('Qaysi etiketkalar mos keladi?');
    }
}
