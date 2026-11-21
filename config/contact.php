<?php

// Seller contacts. Edited by the super admin in Admin → Sozlamalar (stored in the `settings` table,
// which overrides these values); the .env values are only the initial defaults.
return [
    'phone'        => env('CONTACT_PHONE'),        // e.g. +998 90 123 45 67
    'telegram'     => env('CONTACT_TELEGRAM'),     // username without @
    'telegram_bot' => env('CONTACT_TELEGRAM_BOT'), // bot username without @
    'email'        => env('CONTACT_EMAIL'),        // order requests are sent here
];
