<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmotionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DigestionController;

Route::get('/emotions/enum', [EmotionController::class, 'EmotionsMapping']);

Route::redirect('/', '/dashboard');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('emotions', EmotionController::class);
    Route::resource('digestions', DigestionController::class);
});
