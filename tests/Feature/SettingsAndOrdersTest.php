<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferRequest;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Livewire\Admin\SettingsPage;
use App\Models\User;
use Database\Seeders\OfferSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsAndOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_admin_saves_contacts_and_site_uses_them(): void
    {
        $this->seed(OfferSeeder::class);
        $this->actingAs($this->admin());

        Livewire::test(SettingsPage::class)
            ->set('contact.phone', '+998 90 111 22 33')
            ->set('contact.telegram', '@markor_uz')
            ->set('contact.telegram_bot', 'https://t.me/markor_bot')
            ->set('contact.email', 'sales@markor.uz')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('markor_bot', config('contact.telegram_bot'));
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('+998 90 111 22 33')->assertSee('t.me/markor_uz');
    }

    public function test_invalid_bot_name_is_rejected(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(SettingsPage::class)->set('contact.telegram_bot', 'markor')->call('save')->assertHasErrors('contact.telegram_bot');
    }

    public function test_visitor_orders_an_item_and_a_label_size(): void
    {
        $this->seed(OfferSeeder::class);
        config(['contact.email' => 'sales@markor.uz']);
        $offer = Offer::where('slug', 'printer-mobile')->firstOrFail();
        $template = LabelTemplate::default();

        $this->postJson('/offer-request', ['offer_id' => $offer->id, 'name' => 'Ali', 'phone' => '+998901112233', 'quantity' => '2'])->assertOk();
        $this->postJson('/offer-request', ['label_template_id' => $template->id, 'name' => 'Ali', 'phone' => '+998901112233', 'quantity' => '10 rulon'])->assertOk();
        $this->postJson('/offer-request', ['offer_id' => $offer->id, 'name' => 'Bot', 'phone' => '+998901112233', 'website' => 'spam'])->assertUnprocessable();

        $this->assertSame(2, OfferRequest::count());
        $this->assertSame('Etiketka 60×40 mm (Default 60×40)', OfferRequest::latest('id')->first()->item_title);

        $this->actingAs($this->admin())->get(route('admin.offer-requests'))->assertOk()->assertSee('Mobil printer, Bluetooth');
    }

    public function test_label_size_image(): void
    {
        $this->get('/label-image/60x40.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('60×40 mm');
        $this->get('/label-image/5x40.svg')->assertNotFound();
    }
}
