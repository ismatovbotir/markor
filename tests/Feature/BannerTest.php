<?php

namespace Tests\Feature;

use App\Domain\Content\Banner;
use App\Http\Livewire\Admin\BannerForm;
use App\Http\Livewire\Admin\BannerIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BannerSeeder::class);
        $this->assertSame(6, Banner::where('is_default', false)->where('is_active', true)->count(), 'printers, scanner, PDA, labels, ribbons published');
        foreach (Banner::where('is_default', false)->whereNotNull('image_path')->get() as $b) {
            $this->assertFileExists(public_path($b->image_path), 'seeded picture ships with the code');
            $this->assertSame('/' . $b->image_path, $b->imageUrl());
        }
        Banner::where('is_default', false)->delete(); // tests below start from the default banner only
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
    }

    public function test_sponsored_brand_banner_follows_the_default_hero(): void
    {
        Livewire::test(BannerForm::class)
            ->set('item.title_uz', 'Zebra ZD230 — rasmiy diler')->set('item.text_uz', 'Termo printerlar Zebra.')
            ->set('item.url', 'https://reseller.example/zebra')->set('item.is_sponsored', true)->set('item.style', 'dark')
            ->call('save')->assertHasNoErrors();
        auth()->logout();

        $html = $this->get('/?lang=uz')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'), 'only the default hero has the H1');
        $this->assertLessThan(strpos($html, 'Zebra ZD230'), strpos($html, e(__('home.h1'))), 'default banner first');
        $this->assertStringContainsString('rel="noopener sponsored"', $html);
        $this->assertStringContainsString(__('banners.ad'), $html);
        $this->assertStringContainsString('x-data="{ i: 0, n: 2', $html, 'slider with 2 slides');
    }

    public function test_default_banner_cannot_be_edited_or_deleted_here(): void
    {
        $other = Banner::create(['title_uz' => 'Ribbon', 'is_active' => false]);
        Livewire::test(BannerIndex::class)->call('toggle', $other->id);
        $this->assertTrue($other->fresh()->is_active, 'publish checkbox');

        $default = Banner::where('is_default', true)->firstOrFail();
        Livewire::test(BannerIndex::class)->call('delete', $default->id)->assertNotFound();
        $this->assertNotNull(Banner::find($default->id));
    }

    public function test_dates_and_unsafe_links(): void
    {
        Livewire::test(BannerForm::class)->set('item.title_uz', 'X')->set('item.url', 'javascript:alert(1)')->call('save')->assertHasErrors('item.url');

        Banner::create(['title_uz' => 'Kelajak aksiyasi', 'is_active' => true, 'starts_at' => now()->addDays(3)->toDateString()]);
        Banner::create(['title_uz' => "O'tgan aksiya", 'is_active' => true, 'ends_at' => now()->subDay()->toDateString()]);
        auth()->logout();
        $this->get('/?lang=uz')->assertDontSee('Kelajak aksiyasi')->assertDontSee("O'tgan aksiya")->assertDontSee('x-data="{ i: 0', false);
    }

    public function test_banner_filled_from_a_marketplace_product_uses_its_photo_and_hides_with_it(): void
    {
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $offer = \App\Domain\Offers\Models\Offer::where('category', 'printers')->firstOrFail();
        $offer->update(['description_uz' => "Birinchi gap. Ikkinchi gap.", 'image_path' => 'offers/test.png']);

        Livewire::test(BannerForm::class)
            ->assertSee(__('banners.prompt_title'))
            ->set('item.offer_id', $offer->id)
            ->assertSet('item.title_uz', $offer->title_uz)->assertSet('item.text_uz', 'Birinchi gap.')
            ->assertSet('item.url', '/marketplace/' . $offer->slug)
            ->assertSee('Studio product photo of the ' . $offer->title_uz)
            ->call('save')->assertHasNoErrors();

        $banner = Banner::where('offer_id', $offer->id)->firstOrFail();
        $this->assertNull($banner->image_path, 'the product photo is not copied');
        $this->assertSame('/storage/offers/test.png', $banner->imageUrl());
        $this->assertTrue(Banner::forHome()->contains('id', $banner->id));

        $offer->update(['is_active' => false]);
        $this->assertFalse(Banner::forHome()->contains('id', $banner->id), 'hidden product → banner hidden');

        Livewire::test(BannerForm::class, ['banner' => $banner])->assertSet('item.offer_id', $offer->id)
            ->set('item.title_uz', 'Yangi sarlavha')->call('save')->assertRedirect(route('admin.banners'));
        $this->assertSame('Yangi sarlavha', $banner->fresh()->title_uz);

        Livewire::test(BannerIndex::class)->call('delete', $banner->id);
        $this->assertNotNull($offer->fresh()->image_path, 'deleting the banner keeps the product photo');
    }

    public function test_form_is_a_page_and_the_default_banner_has_no_edit_page(): void
    {
        $this->get(route('admin.banners.create'))->assertOk()->assertSee(__('banners.preview'))->assertSee(__('banners.sec_product'));
        $default = Banner::where('is_default', true)->firstOrFail();
        $this->get(route('admin.banners.edit', $default))->assertNotFound();
        $this->get(route('admin.banners'))->assertOk()->assertSee(route('admin.banners.create'));
    }

    public function test_product_banner_can_use_its_own_picture_instead_of_the_product_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(\Database\Seeders\OfferSeeder::class);
        $offer = \App\Domain\Offers\Models\Offer::where('category', 'printers')->firstOrFail();
        $offer->update(['image_path' => 'offers/product.png']);

        Livewire::test(BannerForm::class)->set('item.offer_id', $offer->id)
            ->assertSee(__('banners.use_product_image'))->assertSee('/storage/offers/product.png')
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('own.png', 1600, 1200))
            ->call('save')->assertHasNoErrors();
        $banner = Banner::where('offer_id', $offer->id)->firstOrFail();
        $this->assertNotNull($banner->image_path);
        $this->assertStringStartsWith('/storage/banners/', $banner->imageUrl(), 'own picture wins');
        $own = $banner->image_path;

        // back to the product photo: the own picture is removed
        Livewire::test(BannerForm::class, ['banner' => $banner])->call('dropImage')->call('save')->assertHasNoErrors();
        $this->assertNull($banner->fresh()->image_path);
        $this->assertSame('/storage/offers/product.png', $banner->fresh()->imageUrl());
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($own);
    }
}
