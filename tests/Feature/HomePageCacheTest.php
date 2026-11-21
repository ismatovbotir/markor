<?php

namespace Tests\Feature;

use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Settings\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomePageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.pages_in_tests' => true, 'cache.default' => 'array']);
    }

    public function test_visitors_get_the_cached_page_with_their_own_token(): void
    {
        $first = $this->get('/?lang=uz')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->flushSession();
        $second = $this->get('/?lang=uz')->assertOk()->assertHeader('X-Page-Cache', 'HIT');

        preg_match('/name="csrf-token" content="([^"]+)"/', $second->getContent(), $m);
        $this->assertSame(session()->token(), $m[1], 'each visitor gets their own CSRF token');
        $this->assertStringNotContainsString('__MARKOR_CSRF__', $second->getContent());
    }

    public function test_each_language_is_cached_separately(): void
    {
        $this->get('/?lang=uz')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/?lang=ru')->assertHeader('X-Page-Cache', 'MISS')->assertSee('<html lang="ru">', false);
    }

    public function test_changes_in_admin_refresh_the_cache_at_once(): void
    {
        $this->get('/?lang=uz');
        $this->get('/?lang=uz')->assertHeader('X-Page-Cache', 'HIT');

        Setting::put(['contact.phone' => '+998 71 000 00 00']);
        $this->get('/?lang=uz')->assertHeader('X-Page-Cache', 'MISS')->assertSee('+998 71 000 00 00');

        OfferCategory::create(['slug' => 'cache-test', 'name_uz' => 'Yangi toifa', 'icon' => 'heroicon-o-cube', 'is_active' => true, 'sort_order' => 1]);
        $this->get('/?lang=uz')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_expires_after_six_hours(): void
    {
        $this->get('/?lang=uz');
        $this->travel(6 * 60 + 1)->minutes();
        $this->get('/?lang=uz')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_logged_in_users_are_never_served_from_cache(): void
    {
        $user = User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->get('/?lang=uz');
        $this->actingAs($user)->get('/?lang=uz')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }
}
