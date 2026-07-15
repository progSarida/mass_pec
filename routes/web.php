<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\SsoController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

// 1. ROTTA IP V6 (o IP di default del server)
Route::get('/my-ip6', function () {
    // Recuperiamo prima l'IP del server tramite ifconfig.me
    $ipResponse = Http::timeout(3)->get('https://ifconfig.me/ip');
    
    if ($ipResponse->failed()) {
        return response()->json(['error' => 'Impossibile recuperare l\'IP pubblico'], 500);
    }
    
    $ip = trim($ipResponse->body());

    // Chiamata a ipinfo.io con gestione dell'errore (es. 429 o 500)
    $infoResponse = Http::timeout(3)->get("https://ipinfo.io/{$ip}");

    $infoData = null;
    if ($infoResponse->successful()) {
        $infoData = $infoResponse->json();
    } else {
        // Logghiamo l'errore per monitorarlo (es. se vedi 429 nei log, sai che hai finito il limite)
        Log::warning("Errore ipinfo.io per IP {$ip}: Codice " . $infoResponse->status());
    }

    return response()->json([
        'ip'   => $ip,
        'info' => $infoData ?? ['error' => 'Dati di geolocalizzazione non disponibili (Rate limit o API offline)']
    ]);
});


// 2. ROTTA IP V4 E GEOLOCALIZZAZIONE (Senza doppie chiamate ridondanti)
// Route::get('/my-ip4', function () {
    
//     // 1. Recupero Info IPv4 forzando la risoluzione di rete su IPv4
//     $responseV4 = Http::withOptions([
//         'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
//         'timeout' => 3
//     ])->get('https://ipinfo.io/json');

//     $dataV4 = null;
//     $ipV4 = null;

//     if ($responseV4->successful()) {
//         $dataV4 = $responseV4->json();
//         $ipV4 = $dataV4['ip'] ?? null;
//     } else {
//         Log::warning("Errore ipinfo.io (IPv4): Codice " . $responseV4->status());
//     }

//     // 2. Recupero IP V6 (ifconfig.me risolve normalmente in IPv6 se il server lo supporta)
//     $ipV6Response = Http::timeout(3)->get('https://ifconfig.me/ip');
//     $ipV6 = null;
//     $dataV6 = null;

//     if ($ipV6Response->successful()) {
//         $ipV6 = trim($ipV6Response->body());
        
//         // Eseguiamo la chiamata per IPv6 solo se abbiamo effettivamente un IP valido 
//         // e se la prima chiamata non ha già fallito per rate limit (per evitare di sprecare API call)
//         if ($ipV6 && $responseV4->status() !== 429) {
//             $responseV6 = Http::timeout(3)->get("https://ipinfo.io/{$ipV6}");
//             if ($responseV6->successful()) {
//                 $dataV6 = $responseV6->json();
//             }
//         }
//     }

//     return response()->json([
//         'ip_v4'   => $ipV4,
//         'info_v4' => $dataV4 ?? ['error' => 'Dati IPv4 non disponibili'],
//         'ip_v6'   => $ipV6,
//         'info_v6' => $dataV6 ?? ['error' => 'Dati IPv6 non disponibili o limite raggiunto']
//     ]);
// });

Route::get('/my-ip4', function () {
    // Chiamata a ip-api.com forzando IPv4
    $responseV4 = Http::withOptions([
        'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        'timeout' => 3
    ])->get('http://ip-api.com/json'); // Nota: la versione free usa http, non https

    $dataV4 = null;
    if ($responseV4->successful()) {
        $dataV4 = $responseV4->json();
    }

    return response()->json([
        'ip_v4'   => $dataV4['query'] ?? null, // ip-api usa la chiave 'query' per l'IP
        'info_v4' => $dataV4 ?? ['error' => 'Dati IPv4 non disponibili'],
    ]);
});