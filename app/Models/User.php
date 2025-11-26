<?php

namespace App\Models;

use App\Enums\Permission;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\HttpFoundation\Response;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole('super_admin'),
            'user'  => true,
            default => false,
        };
    }

    public function loginRedirect(): ?Response
    {
        // $destinationPanelId = null;
        // if ($this->hasRole('super_admin'))
        //     $destinationPanelId = 'admin';
        // else
            $destinationPanelId = 'user';

        if (!$destinationPanelId)
            return abort(403, 'Accesso non autorizzato a nessun pannello.');

        return redirect()->to(Filament::getPanel($destinationPanelId)->getUrl());
    }
}
