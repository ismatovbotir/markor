<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\OfferCategory;
use App\Http\Livewire\Admin\OfferCategoryManager;
use App\Models\User;
use Database\Seeders\OfferSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OfferCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OfferSeeder::class);
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => "$role@t.uz", 'password' => 'secret123', 'role' => $role, 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_seeded_categories_with_items(): void
    {
        $this->assertSame(['labels', 'ribbons', 'printers', 'scanners', 'pda', 'accessories', 'service'], OfferCategory::orderBy('sort_order')->pluck('slug')->all());
        $this->assertSame('templates', OfferCategory::where('slug', 'labels')->value('kind'));
        $this->assertSame(4, OfferCategory::where('slug', 'printers')->first()->offers()->count());
    }

    public function test_admin_adds_item_with_photo_and_market_link_shows_more_button(): void
    {
        Storage::fake('public');
        $printers = OfferCategory::where('slug', 'printers')->first();
        $this->actingAs($this->user('admin'));

        Livewire::test(OfferCategoryManager::class, ['category' => $printers])
            ->set('settings.market_url', 'https://uzum.uz/uz/shop/markor')
            ->call('saveSettings')->assertHasNoErrors();
        Livewire::test(\App\Http\Livewire\Admin\OfferForm::class, ['category' => $printers])
            ->set('item.title_uz', 'Termo printer X1')
            ->assertSet('item.slug', 'termo-printer-x1')
            ->set('photo', UploadedFile::fake()->image('p.jpg', 400, 300))
            ->call('save')->assertHasNoErrors()
            ->assertRedirect(route('admin.offers.edit', [$printers, $printers->offers()->where('title_uz', 'Termo printer X1')->firstOrFail()])); // a new product continues on its edit page

        $item = $printers->offers()->where('title_uz', 'Termo printer X1')->firstOrFail();
        $this->assertSame('termo-printer-x1', $item->slug);
        Storage::disk('public')->assertExists($item->image_path);

        // the address is fixed: renaming or a forged slug change keeps it; a same-name item gets "-2"
        $this->get(route('admin.offers.edit', [$printers, $item]))->assertOk()->assertSee('/marketplace/termo-printer-x1');
        Livewire::test(\App\Http\Livewire\Admin\OfferForm::class, ['category' => $printers, 'offer' => $item])
            ->set('item.title_uz', 'Boshqa nom')->set('item.slug', 'hacked')->call('save')->assertHasNoErrors();
        $this->assertSame('termo-printer-x1', $item->fresh()->slug);
        Livewire::test(\App\Http\Livewire\Admin\OfferForm::class, ['category' => $printers])
            ->set('item.title_uz', 'Termo printer X1')->call('save')->assertHasNoErrors();
        $this->assertTrue($printers->offers()->where('slug', 'termo-printer-x1-2')->exists());

        auth()->logout();
        $this->get('/')->assertOk()->assertSee('https://uzum.uz/uz/shop/markor')->assertSee("Ko'proq mahsulotlar");
    }

    public function test_hidden_category_is_not_shown_and_client_cannot_manage(): void
    {
        $ribbons = OfferCategory::where('slug', 'ribbons')->first();
        $ribbons->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('Ribbonlar');

        $this->actingAs($this->user('user'))->get(route('admin.offers', $ribbons))->assertForbidden();
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/label-design', '/register', '/login', '/robots.txt', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_short_rows_get_a_request_card_and_a_section_request_is_stored(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $cat = \App\Domain\Offers\Models\OfferCategory::create(['slug' => 'svc-test', 'name_uz' => 'Xizmat test', 'is_active' => true, 'sort_order' => 99]);
        \App\Domain\Offers\Models\Offer::create(['offer_category_id' => $cat->id, 'slug' => 'svc-one', 'category' => 'svc-test', 'title_uz' => 'Bitta xizmat', 'action' => 'order', 'is_active' => true]);

        $html = $this->get('/?lang=uz')->assertOk()->getContent();
        $this->assertStringContainsString('lg:col-span-3', $html, '1 item + a card spanning the other 3 columns');
        $this->assertStringContainsString("category: {$cat->id}", $html);

        $this->postJson(route('offer-request'), ['offer_category_id' => $cat->id, 'name' => 'Ali', 'phone' => '+998 90 123 45 67', 'comment' => '58 mm ribbon'])->assertOk();
        $this->assertDatabaseHas('offer_requests', ['category' => 'Xizmat test', 'offer_id' => null, 'label_template_id' => null]);
        $this->postJson(route('offer-request'), ['offer_category_id' => $cat->id, 'offer_id' => 1, 'name' => 'Ali', 'phone' => '+998 90 123 45 67'])->assertStatus(422);
    }
}
