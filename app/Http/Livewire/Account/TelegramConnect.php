<?php

namespace App\Http\Livewire\Account;

use App\Domain\Studio\BarcodeImage;
use App\Domain\Telegram\TelegramLink;
use Illuminate\Support\Str;
use Livewire\Component;

/** Profile block: link the account to the Telegram bot (QR → share phone → 6-digit code). */
class TelegramConnect extends Component
{
    public bool $open = false;
    public ?int $linkId = null;

    public function start(): void
    {
        $user = auth()->user();
        TelegramLink::where('user_id', $user->id)->whereIn('status', ['pending', 'started', 'phone_ok'])->update(['status' => 'expired']);

        $link = TelegramLink::create([
            'user_id'    => $user->id,
            'token'      => Str::random(32),
            'expires_at' => now()->addMinutes(config('telegram.link_ttl', 15)),
        ]);
        $this->linkId = $link->id;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function disconnect(): void
    {
        auth()->user()->update(['telegram_chat_id' => null]);
        $this->dispatch('notify', type: 'success', message: __('telegram.disconnected'));
    }

    public function render(BarcodeImage $barcode)
    {
        $user = auth()->user();
        $link = $this->linkId ? TelegramLink::where('user_id', $user->id)->find($this->linkId) : null;
        if ($link && $link->status !== 'linked' && $link->expires_at->isPast()) {
            $link->status = 'expired';
        }

        $bot = config('contact.telegram_bot');
        $url = $link && $bot ? "https://t.me/{$bot}?start={$link->token}" : null;

        return view('livewire.account.telegram-connect', [
            'link'    => $link,
            'url'     => $url,
            'qr'      => $url ? 'data:image/png;base64,' . base64_encode($barcode->png('qr', $url, 6)) : null,
            'bot'     => $bot,
            'linked'  => filled($user->fresh()->telegram_chat_id),
            'ready'   => filled($bot) && filled(config('telegram.token')),
        ]);
    }
}
