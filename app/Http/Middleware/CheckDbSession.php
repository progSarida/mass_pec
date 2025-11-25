<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class CheckDbSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->cookie(config('session.cookie'));

        if (!$sessionId) {
            return $next($request); // Nessun cookie, procedi.
        }

        // 1. Cerca il record della sessione nel DB
        $sessionExists = DB::table('sessions')
            ->where('id', $sessionId)
            ->exists(); // Usa exists() per maggiore efficienza

        // 2. SE IL RECORD MANCA: BLOCCO E LOGOUT FORZATO
        if (!$sessionExists) {
            // Questo è il caso in cui il server principale ha eliminato la sessione,
            // ma il cookie è rimasto nel browser.
            return $this->forceLogoutAndRedirect($request);
        }
        
        // 3. SE IL RECORD È PRESENTE: Lascia che Laravel e Filament facciano il resto.
        // I successivi middleware (StartSession, AuthenticateSession, ecc.)
        // si occuperanno della scadenza per inattività e della ri-autenticazione.
        return $next($request);
    }

    /**
     * Esegue il logout e reindirizza, pulendo i cookie.
     */
    protected function forceLogoutAndRedirect(Request $request): Response
    {
        // 1. Pulizia lato server e browser
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Reindirizza al login
        return redirect()->route('sso.login');
    }
}