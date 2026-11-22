<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use Database\Seeders\OfferItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferItemsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_loads_items_with_faq_and_ignores_the_price_column(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class); // the Marketplace categories
        $copy = tempnam(sys_get_temp_dir(), 'offers');
        file_put_contents($copy, implode("\n", [
            'category;slug;title_uz;title_ru;title_zh;description_uz;description_ru;description_zh;price;url;image;button;order;visible;faq_uz;faq_ru;faq_zh',
            'printers;xprinter-xp-365b;Xprinter XP-365B;Xprinter XP-365B;Xprinter XP-365B;Termo printer;Термопринтер;热敏打印机;'
                . "1 250 000 so'm;;;order;0;1;Qaysi etiketkalar mos keladi? | Termo etiketkalar 20–80 mm. || Ribbon kerakmi? | Yo'q.;"
                . 'Какие этикетки подходят? | Термоэтикетки шириной 20–80 мм. || Нужен риббон? | Нет.;适用哪些标签？ | 20–80 毫米热敏标签。 || 需要碳带吗？ | 不需要。',
            'scanners;scanner-2d-wired;2D skaner;2D-сканер;2D 扫描器;;;;;;;call;0;1;;;',
        ]));

        $seeder = new OfferItemsSeeder();
        $seeder->path = $copy;
        $seeder->run();

        $printer = Offer::where('slug', 'xprinter-xp-365b')->firstOrFail();
        $this->assertNull($printer->price); // products have no price: the column of the file is ignored
        $this->assertCount(2, $printer->faq);
        $this->assertSame('Qaysi etiketkalar mos keladi?', $printer->faq[0]['q_uz']);
        $this->assertSame('Термоэтикетки шириной 20–80 мм.', $printer->faq[0]['a_ru']);
        $this->assertSame('call', Offer::where('slug', 'scanner-2d-wired')->value('action'));

        $this->get(route('marketplace.item', 'xprinter-xp-365b'))->assertOk()->assertSee('Qaysi etiketkalar mos keladi?');
    }
}
