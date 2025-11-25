<?php
 
namespace App\Responses;
 
use Filament\Http\Responses\Auth\Contracts\LogoutResponse;
use Illuminate\Http\RedirectResponse;
 
class SsoLogoutResponse implements LogoutResponse
{
    public function toResponse($request): RedirectResponse
    {
        $ssoDashboard = config('services.sso.dashboard'); 
        
        return redirect()->to($ssoDashboard);
    }
}