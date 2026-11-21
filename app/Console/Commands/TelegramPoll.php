<?php

namespace App\Console\Commands;

use App\Domain\Telegram\BotHandler;
use App\Domain\Telegram\TelegramApi;
use Illuminate\Console\Command;

/** Local development: receive bot updates by long polling instead of a webhook. */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Receive Telegram bot updates by long polling (development)';

    public function handle(TelegramApi $api, BotHandler $bot): int
    {
        if (!$api->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');
            return self::FAILURE;
        }

        $api->call('deleteWebhook');
        $offset = 0;
        $this->info('Polling Telegram… (Ctrl+C to stop)');

        while (true) {
            foreach ($api->call('getUpdates', ['offset' => $offset, 'timeout' => 25], 35) ?? [] as $update) {
                $offset = $update['update_id'] + 1;
                try {
                    $bot->handle($update);
                } catch (\Throwable $e) {
                    report($e);
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
