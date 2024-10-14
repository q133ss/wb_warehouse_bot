<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tt', [\App\Http\Controllers\TelegramController::class, 'loadWarehouses']);
