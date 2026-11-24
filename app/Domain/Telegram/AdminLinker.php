<?php

namespace App\Domain\Telegram;

use App\Domain\Reporting\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The super admin connects a client to the bot by hand (Admin → Telegram "Connect"). Only an attempt that came from the client's own cabinet QR and failed on the
 * phone (phone_mismatch) is linked: the same result as a correct code — status admin_linked, audit, a message.
 */
class AdminLinker
{
    public function __construct(private TelegramApi $api, private BotHandler $bot) {}

    public function link(TelegramLink $link, string $reason): bool
    {
        $user = $link->user;
        if ($link->status !== 'phone_mismatch' || !$link->chat_id || !$user || !$user->is_active || $user->isAdmin()) {
            return false;
        }

        DB::transaction(function () use ($link, $user, $reason) {
            // one chat per account and one account per chat (as in the bot)
            User::where('telegram_chat_id', $link->chat_id)->where('id', '!=', $user->id)->update(['telegram_chat_id' => null]);
            $user->update(['telegram_chat_id' => $link->chat_id]);
            $link->update(['status' => 'admin_linked', 'code' => null]);
            AuditLog::record('telegram.admin_linked', 'user', $user->id, [
                'link_id' => $link->id, 'shared_phone' => $link->shared_phone, 'cabinet_phone' => $user->phone,
                'tg_username' => $link->tg_username, 'reason' => $reason,
            ]);
        });

        $this->api->sendMessage($link->chat_id, $this->bot->t($user, 'bot.admin_linked', ['name' => e($user->name)]), ['remove_keyboard' => true]);
        $this->api->sendMessage($link->chat_id, $this->bot->t($user, 'bot.instructions', ['name' => e($user->name)]));

        return true;
    }
}
