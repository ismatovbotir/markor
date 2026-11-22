<?php

namespace Tests\Feature;

use App\Domain\Content\NewsPost;
use App\Mcp\Servers\ContentServer;
use App\Mcp\Tools\News;
use App\Support\McpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsMcpTest extends TestCase
{
    use RefreshDatabase;

    private function rpc(?string $token, string $method = 'tools/list', array $params = [])
    {
        return $this->withHeaders(array_filter(['Authorization' => $token ? "Bearer $token" : null, 'Accept' => 'application/json, text/event-stream']))
            ->postJson('/mcp/content', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    public function test_endpoint_needs_the_current_token(): void
    {
        $this->rpc(null)->assertStatus(401);                 // no token created yet
        $token = McpToken::create();
        $this->rpc(null)->assertStatus(401);
        $this->rpc('mkr_wrong')->assertStatus(401);
        $this->rpc($token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])->assertOk();

        $new = McpToken::create();                            // replacing kills the old token
        $this->rpc($token)->assertStatus(401);
        McpToken::revoke();
        $this->rpc($new)->assertStatus(401);
    }

    public function test_tools_create_update_hide_and_delete(): void
    {
        ContentServer::tool(News\CreateNews::class, ['title_uz' => 'Yangi imkoniyat'])->assertHasErrors();

        ContentServer::tool(News\CreateNews::class, [
            'title_uz' => 'Yangi imkoniyat', 'title_ru' => 'Новая возможность', 'body_uz' => '## Matn', 'source_name' => 'CRPT Turon', 'source_url' => 'https://crpt-turon.uz/x/1/',
        ])->assertOk()->assertSee('yangi-imkoniyat');
        $post = NewsPost::firstOrFail();
        $this->assertTrue($post->isPublished());
        $this->get('/news/yangi-imkoniyat')->assertOk()->assertSee('crpt-turon.uz');

        ContentServer::tool(News\CreateNews::class, ['title_uz' => 'Dubl', 'body_uz' => 'x', 'source_url' => 'https://crpt-turon.uz/x/1/'])->assertHasErrors();

        ContentServer::tool(News\UpdateNews::class, ['slug' => 'yangi-imkoniyat', 'title_zh' => '新功能', 'title_uz' => 'Boshqa'])->assertOk();
        $this->assertSame('新功能', $post->fresh()->title_zh);
        $this->assertSame('yangi-imkoniyat', $post->fresh()->slug);
        ContentServer::tool(News\UpdateNews::class, ['id' => $post->id, 'body_uz' => ''])->assertHasErrors();

        ContentServer::tool(News\ListNews::class, ['status' => 'public'])->assertOk()->assertSee('Boshqa');
        ContentServer::tool(News\GetNews::class, ['id' => $post->id])->assertOk()->assertSee('## Matn');

        ContentServer::tool(News\SetNewsVisibility::class, ['id' => $post->id, 'visible' => false])->assertOk();
        $this->get('/news/yangi-imkoniyat')->assertNotFound();

        ContentServer::tool(News\DeleteNews::class, ['id' => $post->id])->assertOk();
        $this->assertSame(0, NewsPost::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'mcp.news.deleted']);
    }

    public function test_crpt_turon_seeder_links_every_post_to_the_original(): void
    {
        $this->seed(\Database\Seeders\CrptTuronNewsSeeder::class);
        $this->seed(\Database\Seeders\CrptTuronNewsSeeder::class); // second run adds nothing
        $this->assertSame(8, NewsPost::count());
        $this->assertSame(0, NewsPost::whereNull('source_url')->orWhereNull('title_ru')->orWhereNull('title_zh')->count());

        $post = NewsPost::orderByDesc('published_at')->first();
        $this->get('/news/' . $post->slug . '?lang=ru')->assertOk()->assertSee('href="' . e($post->source_url) . '"', false)->assertSee('CRPT Turon');
    }

    public function test_admin_creates_the_token_once(): void
    {
        $this->actingAs(\App\Models\User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        $c = \Livewire\Livewire::test(\App\Http\Livewire\Admin\McpSettings::class)->call('generate');
        $token = $c->get('newToken');
        $this->assertTrue(McpToken::check($token));
        $this->assertDatabaseMissing('settings', ['value' => $token]); // only the hash is stored
    }
}
