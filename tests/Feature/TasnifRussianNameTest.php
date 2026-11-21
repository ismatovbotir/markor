<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\GtinLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TasnifRussianNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => null]); // AI unavailable: Russian must still come from tasnif
        $this->classifier = function ($request) {
            if (str_contains($request->url(), 'elasticsearch/search')) {
                $this->assertStringContainsString('search=6906974316793', $request->url(), 'GTIN-14 → 13-digit barcode');
                $ru = str_contains($request->url(), 'lang=ru');

                return Http::response(['success' => true, 'data' => [
                    ['internationalCode' => '6906974316000', 'name' => 'Другой товар'], // text search can return neighbours
                    ['internationalCode' => '6906974316793', 'name' => $ru ? 'Электрический чайник: Beltop, BT-EK-5510 цвет белый' : 'Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг'],
                ]]);
            }

            return Http::response(['success' => true, 'data' => [['mxik' => '08516999043100011', 'name' => 'Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг', 'packages' => []]]]);
        };
    }

    public function test_lookup_fills_the_official_russian_name(): void
    {
        $this->assertSame('Beltop, BT-EK-5510 цвет белый', app(GtinLookup::class)->nameIn('06906974316793', 'ru'));

        $product = Product::withoutGlobalScopes()->create(['gtin' => '06906974316793', 'is_active' => true]);
        $this->assertSame('found', app(GtinLookup::class)->enrich($product));
        $product->refresh();
        $this->assertSame('Beltop, BT-EK-5510 цвет белый', $product->name_ru);
        $this->assertSame('Beltop, BT-EK-5510 цвет белый', $product->attributes['classifier_name_ru']);
        $this->assertNotEmpty($product->name_uz);
    }

    public function test_a_russian_name_typed_by_a_person_is_kept(): void
    {
        $product = Product::withoutGlobalScopes()->create(['gtin' => '06906974316793', 'name_ru' => 'Мой чайник', 'is_active' => true]);
        app(GtinLookup::class)->enrich($product);
        $this->assertSame('Мой чайник', $product->fresh()->name_ru);
    }
}
