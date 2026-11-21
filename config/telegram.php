<?php

// Telegram bot (token from @BotFather). The bot username is set by the super admin in Sozlamalar.
// Locally run `php artisan telegram:poll`; in production register the webhook with `php artisan telegram:webhook`.
return [
    'token'          => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'link_ttl'       => 15,   // minutes a QR link stays valid
    // Set in .env only (shown read-only in Sozlamalar):
    // super admin chat for system alerts (user id or group id -100…); empty = the super admin's linked Telegram
    'admin_chat'     => env('TELEGRAM_ADMIN_CHAT_ID'),
    // group / channel where every generated PDF is archived and re-sent to clients by file id; empty = the admin chat
    'archive_chat'   => env('TELEGRAM_ARCHIVE_CHAT_ID', env('TELEGRAM_ARCHIVE_CHAT')),
];
