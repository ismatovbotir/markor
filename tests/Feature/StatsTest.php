<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\StatsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_guest_views_are_counted_bots_apart_and_no_ip_is_stored(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone)', 'Referer' => 'https://www.google.com/search?q=etiketka'])->get('/contact?lang=ru')->assertOk();
        $this->flushHeaders()->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone)'])->get('/contact?lang=ru')->assertOk();
        $this->flushHeaders()->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/')->assertOk();
        $this->flushHeaders()->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone)']);
        $this->get('/not-a-page')->assertNotFound();

        $count = fn ($kind, $key) => (int) DB::table('site_visits')->where(compact('kind', 'key'))->value('count');
        $this->assertSame(2, $count('page', '/contact'));
        $this->assertSame(2, $count('lang', 'ru'));
        $this->assertSame(1, $count('ref', 'google.com'));
        $this->assertSame(1, $count('bot', 'Google'));
        $this->assertSame(0, $count('page', '/'), 'bots are not page views');
        $this->assertSame(1, DB::table('site_visitors')->count(), 'same visitor twice = one visitor');
        $this->assertSame(64, strlen(DB::table('site_visitors')->value('hash')), 'only a hash, no IP');

        $this->actingAs($this->admin())->get('/contact')->assertOk();
        $this->assertSame(2, $count('page', '/contact'), 'logged-in users are not counted');
    }

    public function test_stats_page_tabs_render_for_admin_only(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/stats')->assertOk()->assertSee(__('stats.tabs.queue'));
        Livewire::test(StatsPage::class)->set('tab', 'visits')->assertSee(__('stats.chart'))
            ->set('tab', 'resources')->assertSee(__('stats.disk'))->assertSee(__('stats.database'));

        auth()->logout();
        $this->actingAs(User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'operator', 'locale' => 'uz', 'is_active' => true]));
        $this->get('/admin/stats')->assertForbidden();
    }
}
