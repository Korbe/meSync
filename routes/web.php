<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmotionController;

Route::get('/emotions/enum', [EmotionController::class, 'EmotionsMapping']);

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {

    Route::get('/', function () { return Inertia::render('Dashboard');})->name('dashboard');

    Route::resource('emotions', EmotionController::class);
});
