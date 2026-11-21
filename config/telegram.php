<?php

// Telegram bot (token from @BotFather). The bot username is set by the super admin in Sozlamalar.
// Locally run `php artisan telegram:poll`; in production register the webhook with `php artisan telegram:webhook`.
return [
    'token'          => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'link_ttl'       => 15,   // minutes a QR link stays valid
    // chat where generated PDFs are archived (a private channel id like -100…, or a user chat id); empty = the super admin's linked Telegram
    'archive_chat'   => env('TELEGRAM_ARCHIVE_CHAT'),
];
