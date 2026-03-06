<?php

use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ItemController;
use App\Http\Controllers\Client\MarkController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'client'])
    ->prefix('client')
    ->name('client.')
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('items', ItemController::class);
        Route::resource('orders', OrderController::class);
        Route::resource('marks', MarkController::class);
        Route::resource('users', UserController::class);
    });
