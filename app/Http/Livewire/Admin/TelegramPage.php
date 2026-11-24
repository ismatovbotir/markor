<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Telegram\AdminLinker;
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
    public function link(int $linkId, AdminLinker $linker): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $link = TelegramLink::with('user')->find($linkId);
        if (!$link || !$linker->link($link, 'button')) {
            $this->dispatch('notify', type: 'error', message: __('tg_admin.link_unavailable'));
            return;
        }
        $this->dispatch('notify', type: 'success', message: __('tg_admin.linked_done', ['name' => $link->user->name]));
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
