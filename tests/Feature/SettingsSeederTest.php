<?php

namespace Tests\Feature;

use App\Domain\Settings\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_empty_keys_and_keeps_admin_edits(): void
    {
        Setting::put(['contact.phone' => '+998 71 111 11 11']); // changed by the super admin on the page
        $this->seed(SettingsSeeder::class);
        $all = Setting::query()->pluck('value', 'key');

        $this->assertSame('+998 71 111 11 11', $all['contact.phone'], 'admin value kept');
        foreach (SettingsSeeder::DATA as $key => $value) {
            if ($key !== 'contact.phone' && filled($value)) {
                $this->assertSame($value, $all[$key]);
            }
        }
    }
}
