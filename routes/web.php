<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceContractController;
use App\Http\Controllers\MaintenanceInvoiceController;

Route::redirect('/', '/dashboard');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/customers/{customer}/delete', [CustomerController::class, 'delete'])->name('customers.delete');
    Route::resource('customers', CustomerController::class)->except('show');

    Route::get('/contracts/{contract}/delete', [MaintenanceContractController::class, 'delete'])->name('contracts.delete');
    Route::resource('contracts', MaintenanceContractController::class)->except('show');
    Route::post('/contracts/{contract}/invoices', [MaintenanceInvoiceController::class, 'store'])->name('contracts.invoices.store');
    Route::delete('/contracts/{contract}/invoices/{invoice}', [MaintenanceInvoiceController::class, 'destroy'])->name('contracts.invoices.destroy');
});
