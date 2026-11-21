<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \App\Http\Middleware\EnsureActiveAccount::class, \App\Http\Middleware\SecurityHeaders::class]);
        // Telegram posts updates without a CSRF token; the webhook is protected by its secret URL instead
        $middleware->preventRequestForgery(except: ['telegram/webhook/*']);
        // the real visitor IP behind a web server on the same machine (nginx → php-fpm); nobody else can set the header
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias([
            'client.only' => \App\Http\Middleware\ClientOnly::class,
            'company'     => \App\Http\Middleware\CompanyRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
