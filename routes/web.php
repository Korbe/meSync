<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\MaintenanceContractController;
use App\Http\Controllers\MaintenanceInvoiceController;
use App\Http\Controllers\ProfileLogoController;

Route::redirect('/', '/dashboard');

// One-click login with the demo user
Route::post('/demo-login', DemoLoginController::class)->middleware(['guest', 'throttle:10,1'])->name('demo.login');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/user/profile/logo', [ProfileLogoController::class, 'store'])->name('profile.logo.store');
    Route::delete('/user/profile/logo', [ProfileLogoController::class, 'destroy'])->name('profile.logo.destroy');
    Route::put('/user/business-profile', [BusinessProfileController::class, 'update'])->name('business-profile.update');

    Route::get('/customers/{customer}/delete', [CustomerController::class, 'delete'])->name('customers.delete');
    Route::resource('customers', CustomerController::class);

    Route::get('/contracts/{contract}/delete', [MaintenanceContractController::class, 'delete'])->name('contracts.delete');
    Route::post('/contracts/{contract}/check', [MaintenanceContractController::class, 'check'])->name('contracts.check');
    Route::resource('contracts', MaintenanceContractController::class)->except('show');
    Route::post('/contracts/{contract}/invoices', [MaintenanceInvoiceController::class, 'store'])->name('contracts.invoices.store');
    Route::delete('/contracts/{contract}/invoices/{invoice}', [MaintenanceInvoiceController::class, 'destroy'])->name('contracts.invoices.destroy');

    Route::get('/invoices/{invoice}/delete', [InvoiceController::class, 'delete'])->name('invoices.delete');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('/invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
    Route::delete('/invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');
    Route::resource('invoices', InvoiceController::class)->except('show');

    Route::get('/export', [ExportController::class, 'index'])->name('export.index');
    Route::get('/export/csv', [ExportController::class, 'csv'])->name('export.csv');
    Route::get('/export/zip', [ExportController::class, 'zip'])->name('export.zip');

    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup.restore');

    Route::get('/catalog/{catalog}/delete', [CatalogItemController::class, 'delete'])->name('catalog.delete');
    Route::resource('catalog', CatalogItemController::class)->except('show');

    Route::get('/inventory/export', [InventoryItemController::class, 'csv'])->name('inventory.csv');
    Route::get('/inventory/{inventory}/delete', [InventoryItemController::class, 'delete'])->name('inventory.delete');
    Route::get('/inventory/{inventory}/receipt', [InventoryItemController::class, 'receipt'])->name('inventory.receipt');
    Route::resource('inventory', InventoryItemController::class)->except('show');
});
