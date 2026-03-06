<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Auth::routes();

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

require __DIR__ . '/admin.php';
require __DIR__ . '/client.php';
require __DIR__ . '/operator.php';
