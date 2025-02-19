<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmotionController;
use App\Http\Controllers\DashboardController;

Route::get('/emotions/enum', [EmotionController::class, 'EmotionsMapping']);

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('emotions', EmotionController::class);
});
