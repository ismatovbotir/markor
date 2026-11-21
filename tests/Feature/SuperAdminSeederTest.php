<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_admin_from_env_once_and_never_overwrites_the_password(): void
    {
        config(['admin.email' => 'owner@markor.uz', 'admin.password' => 'first-strong-pass', 'admin.name' => 'Owner']);
        $this->seed(SuperAdminSeeder::class);

        $admin = User::where('email', 'owner@markor.uz')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('first-strong-pass', $admin->password));

        config(['admin.password' => 'another-password']);
        $this->seed(SuperAdminSeeder::class);
        $this->assertTrue(Hash::check('first-strong-pass', $admin->fresh()->password), 'existing password kept');
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_production_refuses_a_weak_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['admin.email' => 'owner@markor.uz', 'admin.password' => 'admin123']);

        $this->expectException(\RuntimeException::class);
        (new SuperAdminSeeder())->run(); // directly: `db:seed` would ask for confirmation in production
    }

    public function test_login_page_never_shows_credentials_outside_local(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('admin123')->assertDontSee('Demo:');
    }
}
