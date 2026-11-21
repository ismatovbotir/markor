<?php

namespace App\Console\Commands;

use App\Domain\Telegram\TelegramApi;
use Illuminate\Console\Command;

/** Production: point the bot at this server (needs a public https APP_URL). */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook';
    protected $description = 'Register the Telegram webhook at APP_URL';

    public function handle(TelegramApi $api): int
    {
        $secret = config('telegram.webhook_secret');
        if (!$api->enabled() || blank($secret)) {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET first.');
            return self::FAILURE;
        }

        $url = TelegramApi::webhookUrl($secret);
        $ok = $api->call('setWebhook', ['url' => $url, 'allowed_updates' => ['message', 'callback_query']]);
        $ok !== null ? $this->info("Webhook set: $url") : $this->error('setWebhook failed, see the log.');

        return $ok !== null ? self::SUCCESS : self::FAILURE;
    }
}
