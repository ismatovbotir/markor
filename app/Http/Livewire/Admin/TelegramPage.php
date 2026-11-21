<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Telegram\TelegramApi;
use App\Domain\Telegram\TelegramLink;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: bot status & webhook, linked clients, link attempts (matched / unmatched / unfinished). */
#[Layout('layouts.app')]
class TelegramPage extends Component
{
    use WithPagination;

    public string $tab = 'clients'; // clients / matched / unmatched / unfinished
    public ?array $bot = null;
    public ?array $webhook = null;
    public ?string $message = null;

    public function updatingTab(): void { $this->resetPage(); }

    public function mount(TelegramApi $api): void
    {
        $this->refreshStatus($api);
    }

    public function refreshStatus(TelegramApi $api): void
    {
        $this->bot = $api->call('getMe');
        $this->webhook = $api->call('getWebhookInfo');
    }

    public function setWebhook(TelegramApi $api): void
    {
        $secret = config('telegram.webhook_secret');
        if (blank($secret)) {
            // generate one and keep it in settings so the webhook URL is not guessable
            $secret = Str::random(40);
            \App\Domain\Settings\Setting::put(['telegram.webhook_secret' => $secret]);
        }

        $url = TelegramApi::webhookUrl($secret);
        $ok = $api->call('setWebhook', ['url' => $url, 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => false]);
        $this->message = $ok !== null ? __('tg_admin.webhook_set') : __('tg_admin.webhook_failed');
        $this->refreshStatus($api);
    }

    public function deleteWebhook(TelegramApi $api): void
    {
        $ok = $api->call('deleteWebhook');
        $this->message = $ok !== null ? __('tg_admin.webhook_deleted') : __('tg_admin.webhook_failed');
        $this->refreshStatus($api);
    }

    public function render(TelegramApi $api)
    {
        $statuses = ['matched' => ['linked'], 'unmatched' => ['phone_mismatch'], 'unfinished' => ['pending', 'started', 'phone_ok', 'failed', 'expired']];

        $rows = $this->tab === 'clients'
            ? User::with('company:id,name,tin')->whereNotNull('telegram_chat_id')->orderBy('name')->paginate(30)
            : TelegramLink::with('user.company:id,name,tin')->whereIn('status', $statuses[$this->tab])
                // unfinished = never completed: an expired QR that was never opened is noise, so require the bot to have been opened
                ->when($this->tab === 'unfinished', fn ($q) => $q->whereNotNull('chat_id'))
                ->latest('updated_at')->paginate(30);

        $counts = [
            'clients'    => User::whereNotNull('telegram_chat_id')->count(),
            'matched'    => TelegramLink::where('status', 'linked')->count(),
            'unmatched'  => TelegramLink::where('status', 'phone_mismatch')->count(),
            'unfinished' => TelegramLink::whereIn('status', $statuses['unfinished'])->whereNotNull('chat_id')->count(),
        ];

        $appUrl = config('app.url');

        return view('livewire.admin.telegram-page', [
            'rows'       => $rows,
            'counts'     => $counts,
            'enabled'    => $api->enabled(),
            'webhookUrl' => filled(config('telegram.webhook_secret')) ? TelegramApi::webhookUrl(config('telegram.webhook_secret')) : null,
            'publicUrl'  => str_starts_with($appUrl, 'https://') && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1'),
        ]);
    }
}
