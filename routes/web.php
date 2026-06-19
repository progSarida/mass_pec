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
// scarico zip dei documenti integrativi
Route::get('/download-zip-related/{id}', [AttachmentController::class, 'downloadZipRelated'])->name('related.zip')->middleware(['auth']);

// ROTTA DI TEST PER VERIFICA IP PUBBLICO V6 E GEOLOCALIZZAZIONE
// Route::get('/my-ip', function () {
//     $ip = file_get_contents('https://ifconfig.me/ip');
//     $info = file_get_contents('https://ipinfo.io/' . $ip);
    
//     return response()->json([
//         'ip' => trim($ip),
//         'info' => json_decode($info, true)
//     ]);
// });

// ROTTA DI TEST PER VERIFICA IP PUBBLICO V4 E GEOLOCALIZZAZIONE
use Illuminate\Support\Facades\Http;
Route::get('/my-ip', function () {
    // Utilizziamo il client HTTP di Laravel forzando l'uso di IPv4 (CURLOPT_IPRESOLVE)
    $response = Http::withOptions([
        'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        ]
    ])->get('https://ipinfo.io/json');

    if ($response->failed()) {
        return response()->json(['error' => 'Impossibile recuperare i dati'], 500);
    }

    // Recupero i dati con IPv6
    $ip = file_get_contents('https://ifconfig.me/ip');
    $info = file_get_contents('https://ipinfo.io/' . $ip);

    $data = $response->json();

    return response()->json([
        'ip_v4'   => $data['ip'] ?? null,
        'info_v4' => $data,
        'ip_v6' => trim($ip),
        'info_v6' => json_decode($info, true)
    ]);
});