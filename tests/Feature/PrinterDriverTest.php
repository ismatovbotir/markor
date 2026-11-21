<?php

namespace Tests\Feature;

use App\Domain\Content\PrinterDriver;
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

        Livewire::test(DriverIndex::class)->call('openCreate')
            ->set('item.brand', 'Xprinter')->set('item.model', 'XP-470B')->set('item.driver_url', 'http://insecure.example/driver')
            ->call('save')->assertHasErrors('item.driver_url'); // https only
        Livewire::test(DriverIndex::class)->call('openCreate')
            ->set('item.brand', 'Xprinter')->set('item.model', 'XP-470B')->set('item.driver_url', 'https://www.xprintertech.com/download')->set('item.os', ['windows', 'macos'])
            ->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('printer_drivers', ['slug' => 'xprinter-xp-470b']);
    }

    public function test_client_cannot_open_the_admin_page(): void
    {
        $this->actingAs(User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'operator', 'locale' => 'uz', 'is_active' => true]));
        $this->get('/admin/drivers')->assertForbidden();
    }
}
