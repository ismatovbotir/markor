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

    public function test_bot_profile_draft_is_seeded_in_three_languages_once(): void
    {
        $key = \App\Http\Livewire\Admin\TelegramBotProfile::DRAFT_KEY;
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $draft = \App\Domain\Settings\Setting::json($key);
        foreach (['default', 'uz', 'ru', 'zh'] as $l) {
            $this->assertSame('Markor', $draft['name'][$l]);
            $this->assertNotEmpty($draft['description'][$l]);
            $this->assertLessThanOrEqual(512, mb_strlen($draft['description'][$l]));
            $this->assertLessThanOrEqual(120, mb_strlen($draft['short'][$l]));
            $this->assertSame(['start', 'status'], array_column($draft['commands'][$l], 'command'));
        }
        $this->assertStringContainsString('CSV', $draft['description']['ru']);

        // an edited draft is never overwritten by the seeder
        $draft['name']['uz'] = 'Markor bot';
        \App\Domain\Settings\Setting::putRaw($key, json_encode($draft, JSON_UNESCAPED_UNICODE));
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $this->assertSame('Markor bot', \App\Domain\Settings\Setting::json($key)['name']['uz']);
    }
}
