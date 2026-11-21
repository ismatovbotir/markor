<?php

namespace Database\Seeders;

use App\Domain\Settings\Setting;
use Illuminate\Database\Seeder;

/**
 * Initial values of Admin → Sozlamalar (dev and production).
 * Safe to run again (`php artisan db:seed --class=SettingsSeeder --force`): only empty keys are
 * filled — anything the super admin already changed on the page is never overwritten.
 */
class SettingsSeeder extends Seeder
{
    /** The owner's data. Empty = not set yet. */
    public const DATA = [
        'contact.phone'        => '+998 90 175 40 01',   // shown on "Call" buttons, e.g. +998 90 123 45 67
        'contact.telegram'     => 'posuz',   // Telegram username or channel, without @
        'contact.telegram_bot' => 'markor_uz_bot',   // bot username, without @
        'contact.email'        => 'info@pos.uz',   // order requests are sent here
        'billing.card_number'  => '9860600401849101',   // card clients transfer prepayments to (16 digits)
        'billing.card_holder'  => 'Ismatov B',   // name on the card
    ];

    public function run(): void
    {
        $current = Setting::query()->pluck('value', 'key');
        $fill = collect(self::DATA)
            ->filter(fn ($value, $key) => filled($value) && blank($current[$key] ?? null))
            ->all();

        if ($fill) {
            Setting::put($fill);
        }
        $this->command?->info('Settings filled: ' . ($fill ? implode(', ', array_keys($fill)) : 'nothing'));
    }
}
