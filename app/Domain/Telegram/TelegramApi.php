<?php

namespace App\Domain\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Thin client for the Telegram Bot API. */
class TelegramApi
{
    public function enabled(): bool
    {
        return filled(config('telegram.token'));
    }

    public function call(string $method, array $params = [], int $timeout = 15): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout($timeout)->asJson()->post($this->url($method), $params);
        } catch (\Throwable $e) {
            Log::warning("Telegram $method failed", ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->json('ok')) {
            Log::warning("Telegram $method error", ['response' => $response->json() ?? $response->body()]);
            return null;
        }

        // some methods (setWebhook, deleteWebhook, answerCallbackQuery …) answer just `true`:
        // a successful call without data is an empty array, null always means it failed
        $result = $response->json('result');

        return is_array($result) ? $result : [];
    }

    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true,
            'reply_markup' => $replyMarkup,
        ]));
    }

    public function sendDocument(string|int $chatId, string $contents, string $filename, ?string $caption = null): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->attach('document', $contents, $filename)
                ->post($this->url('sendDocument'), array_filter(['chat_id' => (string) $chatId, 'caption' => $caption, 'parse_mode' => 'HTML']));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendDocument failed', ['error' => $e->getMessage()]);
            return null;
        }

        return $response->json('ok') ? $response->json('result') : null;
    }

    /** Downloads a file the user sent to the bot. */
    public function download(string $fileId): ?string
    {
        $file = $this->call('getFile', ['file_id' => $fileId]);
        if (!isset($file['file_path'])) {
            return null;
        }

        $response = Http::timeout(60)->get('https://api.telegram.org/file/bot' . config('telegram.token') . '/' . $file['file_path']);

        return $response->ok() ? $response->body() : null;
    }

    /** Public webhook address, built from APP_URL (not the current request's host). */
    public static function webhookUrl(string $secret): string
    {
        return rtrim((string) config('app.url'), '/') . '/telegram/webhook/' . $secret;
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot' . config('telegram.token') . '/' . $method;
    }
}
