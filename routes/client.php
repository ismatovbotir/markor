<?php

use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ItemController;
use App\Http\Controllers\Client\MarkController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Client\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    //'auth', 'client'
])
    ->prefix('client')
    ->name('client.')
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('item', ItemController::class);
        Route::resource('order', OrderController::class);
        Route::resource('mark', MarkController::class);
        Route::resource('user', UserController::class);
    });
