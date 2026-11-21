<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\SeoPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_admin_overrides_title_per_language_and_adds_verification(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)
            ->set('texts.home.title.ru', 'Этикетки и коды Asl Belgisi — Markor')
            ->set('codes.google', 'abcDEF123_googleCode')
            ->set('codes.yandex', '1a2b3c4d5e6f7a8b')
            ->call('save')->assertHasNoErrors();
        auth()->logout();

        $this->get('/?lang=ru')->assertSee('<title>Этикетки и коды Asl Belgisi — Markor</title>', false)
            ->assertSee('name="google-site-verification" content="abcDEF123_googleCode"', false)
            ->assertSee('name="yandex-verification" content="1a2b3c4d5e6f7a8b"', false);
        $this->flushSession(); // the language is remembered in the session
        $this->get('/?lang=uz')->assertSee('<title>' . e(__('seo.home.title', [], 'uz')) . '</title>', false); // uz keeps the default
    }

    public function test_noindex_closes_robots_sitemap_and_pages(): void
    {
        $this->get(route('robots'))->assertSee('Disallow: /admin')->assertSee('Sitemap: ');
        $this->get(route('sitemap'))->assertOk()->assertSee('<lastmod>', false)->assertSee('hreflang="zh-Hans"', false);

        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)->set('noindex', true)->set('robotsExtra', "Disallow: /tmp/")->call('save');
        auth()->logout();

        $this->get(route('robots'))->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
        $this->get(route('sitemap'))->assertNotFound();
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_extra_lines_are_validated_and_appended(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(SeoPage::class)->set('robotsExtra', "<script>alert(1)</script>")->call('save')->assertHasErrors('robotsExtra');
        Livewire::test(SeoPage::class)->set('robotsExtra', "# temp\nDisallow: /tmp/")->call('save')->assertHasNoErrors();
        auth()->logout();
        $this->get(route('robots'))->assertSee("Disallow: /tmp/");
    }

    public function test_client_cannot_open_seo_page(): void
    {
        $client = User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($client)->get(route('admin.seo'))->assertForbidden();
    }
}
