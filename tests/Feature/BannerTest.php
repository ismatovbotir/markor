<?php

namespace Tests\Feature;

use App\Domain\Content\Banner;
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
        $this->assertSame(4, Banner::where('is_default', false)->where('is_active', true)->count(), 'Zebra printer, Godex, DS2278, MC3300 published');
        $this->assertSame(2, Banner::where('is_active', false)->count(), 'labels and ribbons seeded unpublished');
        foreach (Banner::where('is_default', false)->whereNotNull('image_path')->get() as $b) {
            $this->assertFileExists(public_path($b->image_path), 'seeded picture ships with the code');
            $this->assertSame('/' . $b->image_path, $b->imageUrl());
        }
        Banner::where('is_default', false)->delete(); // tests below start from the default banner only
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
    }

    public function test_sponsored_brand_banner_follows_the_default_hero(): void
    {
        Livewire::test(BannerIndex::class)->call('openCreate')
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
        Livewire::test(BannerIndex::class)->call('openCreate')->set('item.title_uz', 'X')->set('item.url', 'javascript:alert(1)')->call('save')->assertHasErrors('item.url');

        Banner::create(['title_uz' => 'Kelajak aksiyasi', 'is_active' => true, 'starts_at' => now()->addDays(3)->toDateString()]);
        Banner::create(['title_uz' => "O'tgan aksiya", 'is_active' => true, 'ends_at' => now()->subDay()->toDateString()]);
        auth()->logout();
        $this->get('/?lang=uz')->assertDontSee('Kelajak aksiyasi')->assertDontSee("O'tgan aksiya")->assertDontSee('x-data="{ i: 0', false);
    }
}
