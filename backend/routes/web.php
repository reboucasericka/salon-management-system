<?php

use App\Http\Controllers\GoogleCalendarOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback'])
    ->name('google-calendar.callback');
