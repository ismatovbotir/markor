<?php

namespace App\Domain\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Key/value settings edited in the admin panel. Values override config at boot (see AppServiceProvider). */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Settings key → config key they override. */
    public const CONFIG_MAP = [
        'contact.phone'        => 'contact.phone',
        'contact.telegram'     => 'contact.telegram',
        'contact.telegram_bot' => 'contact.telegram_bot',
        'contact.email'        => 'contact.email',
        'telegram.webhook_secret' => 'telegram.webhook_secret',
        'billing.card_number'  => 'billing.card_number',
        'billing.card_holder'  => 'billing.card_holder',
    ];

    public static function all_(): array
    {
        return Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => filled($value) ? trim((string) $value) : null]);
        }
        Cache::forget('settings');
        static::applyToConfig();
        \App\Support\PageCache::bump(); // contacts / SEO texts appear on the cached home page
    }

    public static function applyToConfig(): void
    {
        try {
            if (!Schema::hasTable('settings')) {
                return;
            }
            $values = static::all_();
        } catch (\Throwable) {
            return; // no database yet (fresh install, artisan before migrate)
        }

        foreach (self::CONFIG_MAP as $setting => $config) {
            if (array_key_exists($setting, $values) && $values[$setting] !== null) {
                config([$config => $values[$setting]]);
            }
        }
    }
}
