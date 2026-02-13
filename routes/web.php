<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\SsoController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// ROTTE PER LA LOGIN CENTRALIZZATA
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso-login', [SsoController::class, 'redirect'])->name('sso.login');
Route::post('/slo-callback', [SsoController::class, 'handleSloCallback'])->name('sso.logout')->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/admin/login', fn() => redirect()->route('sso.login'))->name('filament.admin.auth.login');

Route::get('/login', fn() => redirect()->route('sso.login'));

// ROTTE DI UTILITA'
// scarico zip degli allegati
Route::get('/download-zip/{type}/{id}', [AttachmentController::class, 'downloadZip'])->name('attachments.zip')->middleware(['auth']);
// scarico zip delle ricevute
Route::get('/download-zip-receipts/{id}', [AttachmentController::class, 'downloadZipReceipts'])->name('receipts.zip')->middleware(['auth']);
