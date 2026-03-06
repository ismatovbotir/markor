<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'operator'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function (): void {
        Route::view('dashboard', 'home')->name('dashboard');
    });
