<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Reporting\AuditLog;
use App\Domain\Telegram\BotHandler;
use App\Domain\Telegram\TelegramApi;
use App\Domain\Telegram\TelegramLink;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Super admin: linked clients and link attempts (matched / unmatched / unfinished); bot & webhook via TelegramBotSettings.
 * An unmatched attempt (the number sent in the bot differs from the cabinet) can be linked by hand after the
 * admin has called the client: the attempt itself started from the client's own cabinet QR, only the phone differs.
 */
#[Layout('layouts.app')]
class TelegramPage extends Component
{
    use WithPagination;

    public string $tab = 'clients'; // clients / matched / unmatched / unfinished

    public function updatingTab(): void { $this->resetPage(); }

    /** Links an unmatched attempt by hand: the same result as a correct code in the bot (status admin_linked). */
    public function link(int $linkId, TelegramApi $api, BotHandler $bot): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $link = TelegramLink::with('user')->whereKey($linkId)->where('status', 'phone_mismatch')->whereNotNull('chat_id')->first();
        $user = $link?->user;
        if (!$user || !$user->is_active || $user->isAdmin()) {
            $this->dispatch('notify', type: 'error', message: __('tg_admin.link_unavailable'));
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($link, $user) {
            // one chat per account and one account per chat (as in the bot)
            User::where('telegram_chat_id', $link->chat_id)->where('id', '!=', $user->id)->update(['telegram_chat_id' => null]);
            $user->update(['telegram_chat_id' => $link->chat_id]);
            $link->update(['status' => 'admin_linked', 'code' => null]);
            AuditLog::record('telegram.admin_linked', 'user', $user->id, [
                'link_id' => $link->id, 'shared_phone' => $link->shared_phone, 'cabinet_phone' => $user->phone, 'tg_username' => $link->tg_username,
            ]);
        });

        $api->sendMessage($link->chat_id, $bot->t($user, 'bot.admin_linked', ['name' => e($user->name)]), ['remove_keyboard' => true]);
        $api->sendMessage($link->chat_id, $bot->t($user, 'bot.instructions', ['name' => e($user->name)]));
        $this->dispatch('notify', type: 'success', message: __('tg_admin.linked_done', ['name' => $user->name]));
    }

    public function render(TelegramApi $api)
    {
        $statuses = ['matched' => ['linked', 'admin_linked'], 'unmatched' => ['phone_mismatch'], 'unfinished' => ['pending', 'started', 'phone_ok', 'failed', 'expired']];

        $rows = $this->tab === 'clients'
            ? User::with('company:id,name,tin')->whereNotNull('telegram_chat_id')->orderBy('name')->paginate(30)
            : TelegramLink::with('user.company:id,name,tin')->whereIn('status', $statuses[$this->tab])
                // unfinished = never completed: an expired QR that was never opened is noise, so require the bot to have been opened
                ->when($this->tab === 'unfinished', fn ($q) => $q->whereNotNull('chat_id'))
                ->latest('updated_at')->paginate(30);

        $counts = [
            'clients'    => User::whereNotNull('telegram_chat_id')->count(),
            'matched'    => TelegramLink::whereIn('status', $statuses['matched'])->count(),
            'unmatched'  => TelegramLink::where('status', 'phone_mismatch')->count(),
            'unfinished' => TelegramLink::whereIn('status', $statuses['unfinished'])->whereNotNull('chat_id')->count(),
        ];

        return view('livewire.admin.telegram-page', [
            'rows'       => $rows,
            'counts'     => $counts,
        ]);
    }
}
