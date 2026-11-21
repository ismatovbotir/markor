<?php

namespace Tests\Feature;

use App\Domain\Content\PrinterDriver;
use App\Http\Livewire\Admin\DriverForm;
use App\Http\Livewire\Admin\DriverIndex;
use App\Models\User;
use Database\Seeders\PrinterDriverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrinterDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_model_page_link_to_the_official_download(): void
    {
        $this->seed(PrinterDriverSeeder::class);
        $this->assertSame(10, PrinterDriver::count());

        $this->get('/drivers?lang=ru')->assertOk()->assertSee('<h1', false)->assertSee('Zebra ZD421')->assertSee('Godex G500')
            ->assertSee(route('drivers.show', 'zebra-zd421') . '?lang=ru', false);

        $html = $this->get('/drivers/zebra-zd421?lang=ru')->assertOk()
            ->assertSee('<title>' . e(__('drivers.seo_title', ['name' => 'Zebra ZD421'], 'ru')) . '</title>', false)
            ->assertSee('href="https://www.zebra.com/us/en/support-downloads/printers/desktop/zd421.html"', false)
            ->assertSee('zebra.com')->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('"@type":"HowTo"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);

        $this->get(route('sitemap'))->assertSee(route('drivers.show', 'zebra-zd421'), false)->assertSee(route('drivers'), false);
    }

    public function test_hidden_model_is_404_and_admin_manages_models(): void
    {
        $d = PrinterDriver::create(['brand' => 'Zebra', 'model' => 'ZD888', 'slug' => 'zebra-zd888', 'driver_url' => 'https://www.zebra.com/x', 'is_active' => false]);
        $this->get('/drivers/zebra-zd888')->assertNotFound();

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        Livewire::test(DriverIndex::class)->call('toggle', $d->id);
        $this->assertTrue($d->fresh()->is_active);

        Livewire::test(DriverForm::class)
            ->set('item.brand', 'Xprinter')->set('item.model', 'XP-470B')->set('item.driver_url', 'http://insecure.example/driver')
            ->call('save')->assertHasErrors('item.driver_url'); // https only
        Livewire::test(DriverForm::class)
            ->set('item.brand', 'Xprinter')->set('item.model', 'XP-470B')->set('item.driver_url', 'https://www.xprintertech.com/download')->set('item.os', ['windows', 'macos'])
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('printer_drivers', ['slug' => 'xprinter-xp-470b']);
        $this->get(route('admin.drivers.create'))->assertOk()->assertSee(__('drivers.products'));
    }

    public function test_client_cannot_open_the_admin_page(): void
    {
        $this->actingAs(User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'operator', 'locale' => 'uz', 'is_active' => true]));
        $this->get('/admin/drivers')->assertForbidden();
    }

    public function test_one_driver_fits_several_products_and_both_pages_link_each_other(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $category = \App\Domain\Offers\Models\OfferCategory::where('slug', 'printers')->firstOrFail();
        $g500 = \App\Domain\Offers\Models\Offer::create(['offer_category_id' => $category->id, 'category' => 'printers', 'slug' => 'godex-g500u', 'title_uz' => 'Godex G500U printer', 'is_active' => true]);
        $g530 = \App\Domain\Offers\Models\Offer::create(['offer_category_id' => $category->id, 'category' => 'printers', 'slug' => 'godex-g530u', 'title_uz' => 'Godex G530U printer', 'is_active' => true]);

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        Livewire::test(DriverForm::class)
            ->set('item.brand', 'Godex')->set('item.model', 'G500 / G530')->set('item.driver_url', 'https://www.godexintl.com/downloads')
            ->set('addOffer', $g500->id)->set('addOffer', $g530->id)->set('addOffer', $g500->id) // twice → once
            ->assertSet('offerIds', [$g500->id, $g530->id])
            ->call('save')->assertRedirect(route('admin.drivers'));

        $driver = PrinterDriver::where('slug', 'godex-g500-g530')->firstOrFail();
        $this->assertEqualsCanonicalizing([$g500->id, $g530->id], $driver->offers()->pluck('offers.id')->all());

        Livewire::test(DriverForm::class, ['driver' => $driver])->assertSet('offerIds', [$g500->id, $g530->id])
            ->call('removeOffer', $g530->id)->call('save');
        $this->assertSame([$g500->id], $driver->offers()->pluck('offers.id')->all());
        $this->get(route('admin.drivers'))->assertSee('Godex G500U printer');
        auth()->logout();

        $this->get(route('drivers.show', $driver))->assertOk()->assertSee(__('drivers.products_h2', ['name' => $driver->name()]))->assertSee('Godex G500U printer');
        $this->get(route('marketplace.item', 'godex-g500u'))->assertOk()->assertSee(route('drivers.show', $driver), false);
        $this->get(route('marketplace.item', 'godex-g530u'))->assertOk()->assertDontSee(route('drivers.show', $driver), false);
    }

    public function test_driver_without_a_model_name_gets_a_free_address_and_is_found_by_product(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $category = \App\Domain\Offers\Models\OfferCategory::where('slug', 'printers')->firstOrFail();
        $g = \App\Domain\Offers\Models\Offer::create(['offer_category_id' => $category->id, 'category' => 'printers', 'slug' => 'godex-gx4300i', 'title_uz' => 'Godex GX4300i printer', 'is_active' => true]);

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        foreach ([1, 2] as $_) {
            Livewire::test(DriverForm::class)->set('item.brand', 'Godex')->set('item.driver_url', 'https://www.godexintl.com/downloads')
                ->set('addOffer', $g->id)->call('save')->assertHasNoErrors();
        }
        $this->assertSame(['godex-driver', 'godex-driver-2'], PrinterDriver::where('brand', 'Godex')->orderBy('id')->pluck('slug')->all());

        $driver = PrinterDriver::where('slug', 'godex-driver')->firstOrFail();
        $this->assertSame('Godex', $driver->name());
        Livewire::test(DriverForm::class, ['driver' => $driver])->set('item.summary_uz', 'Yangi')->call('save');
        $this->assertSame('godex-driver', $driver->fresh()->slug, 'address kept when brand and name are unchanged');
        auth()->logout();

        $this->get(route('drivers.show', $driver))->assertOk()->assertSee('Godex GX4300i printer');
        $this->get(route('drivers'))->assertOk()->assertSee('godexgx4300i', false); // search index includes product names
    }

    public function test_driver_page_shows_each_category_with_one_row_of_items(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $d = PrinterDriver::create(['brand' => 'Godex', 'model' => 'G500', 'slug' => 'godex-g500', 'driver_url' => 'https://www.godexintl.com/downloads', 'is_active' => true]);

        $html = $this->get(route('drivers.show', $d))->assertOk()->getContent();
        foreach (\App\Domain\Offers\Models\OfferCategory::active()->whereHas('offers', fn ($q) => $q->where('is_active', true))->get() as $c) {
            $this->assertStringContainsString(e($c->name), $html, "category {$c->slug} shown");
        }
        $this->assertStringNotContainsString('uchun ribbon va etiketkalar', $html, 'no "for this printer" claim');
        // at most one row (4) per category
        $this->assertLessThanOrEqual(4 * \App\Domain\Offers\Models\OfferCategory::count(), substr_count($html, '/marketplace/'));
    }
}
