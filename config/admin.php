<?php

// Super admin account created by Database\Seeders\SuperAdminSeeder (only when it doesn't exist yet).
return [
    'name'     => env('SUPER_ADMIN_NAME', 'Administrator'),
    'email'    => env('SUPER_ADMIN_EMAIL'),
    'password' => env('SUPER_ADMIN_PASSWORD'),
];
