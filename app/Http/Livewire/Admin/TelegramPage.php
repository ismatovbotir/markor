<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Telegram\TelegramApi;
use App\Domain\Telegram\TelegramLink;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: linked clients and link attempts (matched / unmatched / unfinished); bot & webhook via TelegramBotSettings. */
#[Layout('layouts.app')]
class TelegramPage extends Component
{
    use WithPagination;

    public string $tab = 'clients'; // clients / matched / unmatched / unfinished

    public function updatingTab(): void { $this->resetPage(); }

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

        return view('livewire.admin.telegram-page', [
            'rows'       => $rows,
            'counts'     => $counts,
        ]);
    }
}
