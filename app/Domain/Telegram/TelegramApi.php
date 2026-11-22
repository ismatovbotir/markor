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
        $response = $this->request($method, $params, $timeout);
        if (!$response['ok']) {
            return null;
        }

        // some methods (setWebhook, deleteWebhook, answerCallbackQuery …) answer just `true`:
        // a successful call without data is an empty array, null always means it failed
        return is_array($response['result']) ? $response['result'] : [];
    }

    /**
     * A call that keeps Telegram's answer: ['ok', 'result', 'error' (description), 'code' (error_code), 'retry_after'].
     * Never logs the token or the request URL.
     */
    public function request(string $method, array $params = [], int $timeout = 15): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'result' => null, 'error' => 'Bot token is not set', 'code' => null, 'retry_after' => null];
        }

        try {
            $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))->asJson()->post($this->url($method), $params);
        } catch (\Throwable $e) {
            $error = $this->hideToken($e->getMessage());
            Log::warning("Telegram $method failed", ['error' => $error]);

            return ['ok' => false, 'result' => null, 'error' => $error, 'code' => null, 'retry_after' => null];
        }

        if (!$response->json('ok')) {
            Log::warning("Telegram $method error", ['response' => $response->json() ?? $this->hideToken(mb_substr($response->body(), 0, 500))]);

            return [
                'ok' => false, 'result' => null,
                'error' => (string) ($response->json('description') ?: 'HTTP ' . $response->status()),
                'code' => $response->json('error_code') ?? $response->status(),
                'retry_after' => $response->json('parameters.retry_after'),
            ];
        }

        return ['ok' => true, 'result' => $response->json('result'), 'error' => null, 'code' => null, 'retry_after' => null];
    }

    // ---------- bot profile (name, descriptions, commands per language; '' / null = default for all languages) ----------

    public function getMe(): array
    {
        return $this->request('getMe', [], 10);
    }

    public function getMyName(?string $lang = null): array
    {
        return $this->request('getMyName', $this->lang([], $lang), 10);
    }

    public function setMyName(string $name, ?string $lang = null): array
    {
        return $this->request('setMyName', $this->lang(['name' => $name], $lang), 10);
    }

    public function getMyDescription(?string $lang = null): array
    {
        return $this->request('getMyDescription', $this->lang([], $lang), 10);
    }

    public function setMyDescription(string $description, ?string $lang = null): array
    {
        return $this->request('setMyDescription', $this->lang(['description' => $description], $lang), 10);
    }

    public function getMyShortDescription(?string $lang = null): array
    {
        return $this->request('getMyShortDescription', $this->lang([], $lang), 10);
    }

    public function setMyShortDescription(string $shortDescription, ?string $lang = null): array
    {
        return $this->request('setMyShortDescription', $this->lang(['short_description' => $shortDescription], $lang), 10);
    }

    public function getMyCommands(?string $lang = null): array
    {
        return $this->request('getMyCommands', $this->lang([], $lang), 10);
    }

    /** @param array<int, array{command: string, description: string}> $commands an empty list removes the commands of that language */
    public function setMyCommands(array $commands, ?string $lang = null): array
    {
        return $commands === []
            ? $this->request('deleteMyCommands', $this->lang([], $lang), 10)
            : $this->request('setMyCommands', $this->lang(['commands' => array_values($commands)], $lang), 10);
    }

    private function lang(array $params, ?string $lang): array
    {
        return filled($lang) ? $params + ['language_code' => $lang] : $params;
    }

    private function hideToken(string $text): string
    {
        $token = (string) config('telegram.token');

        return $token === '' ? $text : str_replace($token, '***', $text);
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
            Log::warning('Telegram sendDocument failed', ['error' => $this->hideToken($e->getMessage())]);
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
