<?php

namespace Tests\Feature;

use App\Domain\Content\NewsPost;
use App\Http\Livewire\Admin\NewsForm;
use App\Http\Livewire\Admin\NewsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_menu_list_and_post_page_in_three_languages(): void
    {
        $post = NewsPost::create(['slug' => 'yangi-shablon', 'title_uz' => 'Yangi shablon', 'title_ru' => 'Новый шаблон',
            'body_uz' => "## Nima yangi\n\nMatn <script>alert(1)</script> [x](javascript:alert(1))", 'published_at' => now()->subDay(), 'is_active' => true]);

        $this->assertStringContainsString('href="' . route('news') . '"', $this->get('/?lang=uz')->getContent());

        foreach (['uz' => 'Yangi shablon', 'ru' => 'Новый шаблон', 'zh' => 'Yangi shablon'] as $l => $title) {
            $html = $this->get("/news?lang=$l")->assertOk()->assertSee($title)->getContent();
            $this->assertSame(1, substr_count($html, '<h1'));
            $this->assertStringContainsString('<title>' . e(\App\Support\Seo::text('news', 'title', $l)) . '</title>', $html);

            $page = $this->get("/news/yangi-shablon?lang=$l")->assertOk()->assertSee($title)->getContent();
            $this->assertSame(1, substr_count($page, '<h1'));
            $this->assertStringContainsString('"@type":"NewsArticle"', $page);
        }

        $page = $this->get('/news/yangi-shablon')->getContent();
        $this->assertStringContainsString('<h2>Nima yangi</h2>', $page);
        $this->assertStringNotContainsString('<script>alert(1)', $page);
        $this->assertStringNotContainsString('javascript:alert', $page);

        $this->get(route('sitemap'))->assertSee(route('news'), false)->assertSee(route('news.show', $post), false);
    }

    public function test_hidden_and_future_posts_are_not_public(): void
    {
        NewsPost::create(['slug' => 'hidden', 'title_uz' => 'Yashirin', 'body_uz' => 'x', 'published_at' => now()->subDay(), 'is_active' => false]);
        NewsPost::create(['slug' => 'later', 'title_uz' => 'Keyinroq', 'body_uz' => 'x', 'published_at' => now()->addDay(), 'is_active' => true]);

        $this->get('/news')->assertOk()->assertDontSee('Yashirin')->assertDontSee('Keyinroq')->assertSee(__('news.empty'));
        $this->get('/news/hidden')->assertNotFound();
        $this->get('/news/later')->assertNotFound();
    }

    public function test_admin_creates_edits_and_toggles_a_post(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.news'))->assertOk();
        $this->get(route('admin.news.create'))->assertOk();

        Livewire::test(NewsForm::class)->set('item.title_uz', '')->call('save')->assertHasErrors(['item.title_uz', 'item.body_uz']);
        Livewire::test(NewsForm::class)->set('item.title_uz', 'Printer drayverlari qo\'shildi')->set('item.body_uz', 'Matn')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.news.edit', NewsPost::firstOrFail()));

        $post = NewsPost::firstOrFail();
        $this->assertSame('printer-drayverlari-qoshildi', $post->slug);
        $this->get('/news/' . $post->slug)->assertOk();

        // editing the title keeps the address
        Livewire::test(NewsForm::class, ['post' => $post])->set('item.title_uz', 'Boshqa sarlavha')->call('save')->assertHasNoErrors();
        $this->assertSame('printer-drayverlari-qoshildi', $post->fresh()->slug);

        Livewire::test(NewsIndex::class)->call('toggle', $post->id);
        $this->assertFalse($post->fresh()->is_active);
    }

    public function test_client_cannot_open_the_admin_page(): void
    {
        $this->actingAs(User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'operator', 'locale' => 'uz', 'is_active' => true]));
        $this->get('/admin/news')->assertForbidden();
    }
}
