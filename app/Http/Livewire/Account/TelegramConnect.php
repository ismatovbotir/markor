<?php

namespace App\Http\Livewire\Account;

use App\Domain\Telegram\TelegramLink;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Profile block: link the account to the Telegram bot. In the bot the client presses Start and sends the
 * number; when it isn't their profile phone, the bot asks for the 6-digit code shown here. The code is made
 * on the server, lives 30 seconds and is replaced while the window is open (at most WINDOW minutes).
 */
class TelegramConnect extends Component
{
    /** Minutes the window keeps renewing the code. */
    public const WINDOW = 10;

    public bool $open = false;
    public ?int $linkId = null;
    public ?int $openedAt = null;

    public function start(): void
    {
        $user = auth()->user();
        TelegramLink::where('user_id', $user->id)->where('status', 'pending')->update(['status' => 'expired', 'code' => null]);

        $link = TelegramLink::create(['user_id' => $user->id, 'token' => Str::random(32), 'expires_at' => now()]);
        $link->renewCode();
        $this->linkId = $link->id;
        $this->openedAt = now()->timestamp;
        $this->open = true;
    }

    /** Every second while the window is open: a new code when the old one has run out. */
    public function tick(): void
    {
        $link = $this->link();
        if (!$link || $link->status !== 'pending' || $link->expires_at->isFuture()) {
            return;
        }
        if (now()->timestamp - (int) $this->openedAt > self::WINDOW * 60) {
            $link->update(['status' => 'expired', 'code' => null]); // window left open: stop making codes
            return;
        }
        $link->renewCode();
    }

    public function close(): void
    {
        $this->link()?->status === 'pending' && $this->link()->update(['status' => 'expired', 'code' => null]);
        $this->open = false;
    }

    public function disconnect(): void
    {
        auth()->user()->update(['telegram_chat_id' => null]);
        $this->dispatch('notify', type: 'success', message: __('telegram.disconnected'));
    }

    private function link(): ?TelegramLink
    {
        return $this->linkId ? TelegramLink::where('user_id', auth()->id())->find($this->linkId) : null;
    }

    public function render()
    {
        $bot = config('contact.telegram_bot');
        $link = $this->link();

        return view('livewire.account.telegram-connect', [
            'link'    => $link,
            'left'    => $link?->status === 'pending' ? max(0, now()->diffInSeconds($link->expires_at, false)) : 0,
            'url'     => $bot ? "https://t.me/{$bot}" : null,
            'bot'     => $bot,
            'linked'  => filled(auth()->user()->fresh()->telegram_chat_id),
            'ready'   => filled($bot) && filled(config('telegram.token')),
        ]);
    }
}
