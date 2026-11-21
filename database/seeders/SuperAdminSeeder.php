<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The super admin account, from .env: SUPER_ADMIN_NAME / SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD.
 * Safe for production (`php artisan db:seed --class=SuperAdminSeeder --force`):
 *  - creates the account only if no user with that email exists — an existing password is never overwritten;
 *  - refuses to run in production without a strong password in .env.
 * After the first login, change the password in the profile and you may clear it from .env.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('admin.email');
        $password = (string) config('admin.password');

        if ($email === '') {
            $this->command?->warn('SUPER_ADMIN_EMAIL is empty — super admin not created.');
            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Super admin $email already exists — left as is.");
            return;
        }

        if (app()->isProduction() && (strlen($password) < 12 || in_array($password, ['admin123', 'password', 'secret'], true))) {
            throw new \RuntimeException('Set a strong SUPER_ADMIN_PASSWORD (12+ characters) in .env before seeding production.');
        }
        if ($password === '') {
            $this->command?->warn('SUPER_ADMIN_PASSWORD is empty — super admin not created.');
            return;
        }

        User::create([
            'name'      => config('admin.name') ?: 'Administrator',
            'email'     => $email,
            'password'  => $password, // hashed by the model cast
            'role'      => 'admin',
            'locale'    => 'uz',
            'qr_token'  => Str::random(32),
            'is_active' => true,
        ]);
        $this->command?->info("Super admin $email created.");
    }
}
