<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Settings\Setting;
use App\Domain\Telegram\Notifier;
use App\Domain\Telegram\TelegramApi;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Bot status, webhook (set / check / delete) and the two chats from .env (read-only):
 * the admin chat for system alerts and the PDF archive group.
 * Shown in Sozlamalar (the Telegram page only lists clients and link attempts).
 */
class TelegramBotSettings extends Component
{
    public ?array $bot = null;
    public ?array $webhook = null;
    public ?string $message = null;
    public bool $messageOk = true;
    public bool $checked = false;

    /** Check: bot token + current webhook state at Telegram. */
    public function check(TelegramApi $api): void
    {
        $this->bot = $api->call('getMe');
        $this->webhook = $api->call('getWebhookInfo');
        $this->checked = true;
    }

    public function setWebhook(TelegramApi $api): void
    {
        $secret = config('telegram.webhook_secret');
        if (blank($secret)) {
            // generate one and keep it in settings so the webhook URL is not guessable
            $secret = Str::random(40);
            Setting::put(['telegram.webhook_secret' => $secret]);
        }

        $ok = $api->call('setWebhook', ['url' => TelegramApi::webhookUrl($secret), 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => false]);
        $this->flash($ok !== null, $ok !== null ? 'tg_admin.webhook_set' : 'tg_admin.webhook_failed');
        $this->check($api);
    }

    public function deleteWebhook(TelegramApi $api): void
    {
        $ok = $api->call('deleteWebhook');
        $this->flash($ok !== null, $ok !== null ? 'tg_admin.webhook_deleted' : 'tg_admin.webhook_failed');
        $this->check($api);
    }

    /** Sends a test message to the alerts chat ('admin') or the PDF archive ('archive'). */
    public function testChat(string $which, Notifier $notifier, TelegramApi $api): void
    {
        $chat = $which === 'archive' ? $notifier->archiveChat() : $notifier->adminChat();
        $ok = $chat && $api->sendMessage($chat, '✅ ' . __('tg_admin.test_text', ['site' => config('app.url')])) !== null;
        $this->flash($ok, $ok ? 'tg_admin.test_sent' : 'tg_admin.test_failed');
    }

    private function flash(bool $ok, string $key): void
    {
        $this->messageOk = $ok;
        $this->message = __($key);
    }

    public function render(TelegramApi $api)
    {
        $appUrl = (string) config('app.url');

        return view('livewire.admin.telegram-bot-settings', [
            'enabled'   => $api->enabled(),
            'publicUrl' => str_starts_with($appUrl, 'https://') && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1'),
            'chats'     => [
                // [label, value from .env, chat actually used (with fallbacks), .env key]
                'admin'   => ['tg_admin.admin_chat', config('telegram.admin_chat'), app(Notifier::class)->adminChat(), 'TELEGRAM_ADMIN_CHAT_ID'],
                'archive' => ['tg_admin.archive_chat', config('telegram.archive_chat'), app(Notifier::class)->archiveChat(), 'TELEGRAM_ARCHIVE_CHAT_ID'],
            ],
        ]);
    }
}
