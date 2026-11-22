<?php

namespace Tests\Feature;

use App\Domain\Content\NewsPost;
use App\Http\Livewire\Admin\NewsForm;
use App\Http\Livewire\Admin\NewsIndex;
use App\Models\User;
use App\Support\AdminI18n;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The redesigned super admin: the multilingual news form, the list columns and filters, the menu. */
class AdminNewsFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    private function news(array $values = []): NewsPost
    {
        static $n = 0;
        $n++;

        return NewsPost::create($values + ['slug' => "post-$n", 'title_uz' => "Yangilik $n", 'body_uz' => 'Matn', 'published_at' => now()->subDay(), 'is_active' => true]);
    }

    public function test_saving_an_existing_post_stays_on_the_edit_page(): void
    {
        $this->actingAs($this->admin());
        $post = $this->news();

        Livewire::test(NewsForm::class, ['post' => $post])
            ->set('item.title_ru', 'Новость')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertDispatched('saved')
            ->assertDispatched('notify', type: 'success', message: __('common.saved'));

        $this->assertSame('Новость', $post->fresh()->title_ru);
        $this->assertSame('post-' . substr($post->slug, 5), $post->fresh()->slug);
    }

    public function test_a_new_post_continues_on_its_edit_address(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(NewsForm::class)->set('item.title_uz', 'Yangi printer')->set('item.body_uz', 'Matn')->call('save')
            ->assertHasNoErrors()->assertRedirect(route('admin.news.edit', NewsPost::firstOrFail()));
        $this->assertSame(route('admin.news.edit', 'yangi-printer'), route('admin.news.edit', NewsPost::firstOrFail()));
    }

    public function test_an_error_in_a_hidden_language_is_reported_on_its_key_and_keeps_the_text(): void
    {
        $this->actingAs($this->admin());
        $post = $this->news(['excerpt_uz' => 'Qisqa']);
        $long = str_repeat('я', 301);

        Livewire::test(NewsForm::class, ['post' => $post])
            ->set('item.excerpt_ru', $long)
            ->call('save')
            ->assertHasErrors(['item.excerpt_ru'])
            ->assertHasNoErrors(['item.excerpt_uz', 'item.title_uz'])
            ->assertSet('item.excerpt_ru', $long)                 // nothing is cut or lost
            ->assertSee(__('validation.max.string', ['attribute' => 'item.excerpt ru', 'max' => 300]), false);

        $this->assertNull($post->fresh()->excerpt_ru);
    }

    public function test_the_form_keeps_all_languages_in_the_page_without_hard_limits(): void
    {
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.news.edit', $this->news()))->assertOk()->getContent();

        foreach (['title', 'excerpt', 'body'] as $field) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $this->assertStringContainsString("wire:model=\"item.{$field}_$l\"", $html);
                $this->assertStringContainsString("id=\"f-item-$field-$l\"", $html);
            }
        }
        // one language switch, limits come from the rules and are counters, not maxlength
        $this->assertSame(1, substr_count($html, 'role="tablist"'));
        $this->assertStringContainsString('data-max="200"', $html);
        $this->assertStringContainsString('data-max="20000"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-i18n="[a-z]+"[^>]*maxlength|maxlength[^>]*data-i18n=/', $html);
        // the Uzbek original with the fallback note is under the ru / zh fields only
        $this->assertSame(6, substr_count($html, 'data-uz-original'));
        $this->assertStringContainsString(e(__('admin.i18n.fallback_uz')), $html);
        $this->assertSame(1, substr_count($html, 'data-save-bar'));
    }

    public function test_completeness_counts_every_translatable_field(): void
    {
        $full = $this->news(['excerpt_uz' => 'q', 'title_ru' => 'т', 'excerpt_ru' => 'к', 'body_ru' => 'т', 'title_zh' => '题', 'excerpt_zh' => '摘', 'body_zh' => '文']);
        $titleOnly = $this->news(['title_ru' => 'т', 'title_zh' => '题', 'body_ru' => '  ']);

        $this->assertSame(['uz' => 3, 'ru' => 3, 'zh' => 3], AdminI18n::completeness($full, NewsPost::TRANSLATABLE));
        $this->assertSame(['uz' => 2, 'ru' => 1, 'zh' => 1], AdminI18n::completeness($titleOnly, NewsPost::TRANSLATABLE));
        $this->assertFalse(AdminI18n::isIncomplete($full, NewsPost::TRANSLATABLE));
        $this->assertTrue(AdminI18n::isIncomplete($titleOnly, NewsPost::TRANSLATABLE));
        // zh counts the same as ru
        $zhGap = $this->news(['excerpt_uz' => 'q', 'title_ru' => 'т', 'excerpt_ru' => 'к', 'body_ru' => 'т', 'title_zh' => '题', 'excerpt_zh' => '摘']);
        $this->assertTrue(AdminI18n::isIncomplete($zhGap, NewsPost::TRANSLATABLE));
        $this->assertSame(2, AdminI18n::incompleteCount(NewsPost::class, NewsPost::TRANSLATABLE));
        $this->assertSame(200, AdminI18n::max(NewsPost::rules(), 'title_uz'));
    }

    public function test_list_shows_status_languages_and_filters_incomplete_translations(): void
    {
        $this->actingAs($this->admin());
        $this->news(['title_uz' => 'Toliq yangilik', 'excerpt_uz' => 'q', 'title_ru' => 'т', 'excerpt_ru' => 'к', 'body_ru' => 'т', 'title_zh' => '题', 'excerpt_zh' => '摘', 'body_zh' => '文']);
        $this->news(['title_uz' => 'Tarjimasiz yangilik']);
        $this->news(['title_uz' => 'Kelajak yangiligi', 'published_at' => now()->addWeek()]);
        $this->news(['title_uz' => 'Yopiq yangilik', 'is_active' => false]);

        Livewire::test(NewsIndex::class)
            ->assertSee(['Toliq yangilik', 'Tarjimasiz yangilik', 'Kelajak yangiligi', 'Yopiq yangilik'])
            ->assertSee([__('admin.status.published'), __('admin.status.scheduled'), __('admin.status.hidden'), __('admin.list.missing')])
            ->assertViewHas('missingCount', 3)
            ->set('missing', true)
            ->assertDontSee('Toliq yangilik')->assertSee('Tarjimasiz yangilik')
            ->set('missing', false)->set('status', 'scheduled')
            ->assertSee('Kelajak yangiligi')->assertDontSee('Yopiq yangilik')->assertDontSee('Tarjimasiz yangilik')
            ->set('status', 'hidden')
            ->assertSee('Yopiq yangilik')->assertDontSee('Kelajak yangiligi')
            ->set('search', 'hech narsa')
            ->assertSee(__('admin.list.none_found'))
            ->call('clearFilters')
            ->assertSet('status', 'all')->assertSee('Toliq yangilik');

        // the menu badge link opens the list already filtered
        $this->get(route('admin.news', ['missing' => 1]))->assertOk()->assertSee('Tarjimasiz yangilik')->assertDontSee('Toliq yangilik');
    }

    public function test_admin_menu_has_requests_and_content_groups_with_the_translation_badge(): void
    {
        $this->actingAs($this->admin());
        $this->news();   // ru / zh empty
        $this->news();

        $html = $this->get(route('admin.news'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'data-admin-nav'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        $this->assertStringContainsString(e(__('nav.group.requests')), $nav);
        $this->assertStringContainsString(__('nav.group.content'), $nav);
        $this->assertStringNotContainsString('>' . __('nav.group.site') . '<', $nav);
        // requests come before the content group; news sits in the content group with the amber badge "2"
        $this->assertLessThan(strpos($nav, __('nav.group.content')), strpos($nav, route('admin.offer-requests')));
        $this->assertGreaterThan(strpos($nav, __('nav.group.content')), strpos($nav, route('admin.news')));
        $this->assertMatchesRegularExpression('/href="[^"]*admin\/news\?missing=1"[^>]*data-nav-gap[^>]*>.*?<\/svg>\s*2\s*<\/a>/s', $nav);
        foreach (['admin.banners', 'admin.drivers', 'label-templates.index', 'admin.seo', 'admin.settings', 'admin.clients'] as $route) {
            $this->assertStringContainsString('href="' . route($route) . '"', $nav);
        }
        // the drawer button for small screens is in the shared layout
        $this->assertStringContainsString('data-menu-button', $html);
    }

    public function test_client_cabinet_still_renders_in_the_shared_layout(): void
    {
        $client = User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $html = $this->actingAs($client)->get(route('profile'))->assertOk()->getContent();

        $this->assertStringContainsString('data-menu-button', $html);
        $this->assertStringNotContainsString('data-admin-nav', $html);
        $this->assertStringContainsString(route('profile'), $html);
    }
}
