<?php

namespace App\Models;

use App\Enums\Permission;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;

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
        Log::info('canAccessPanel called', [
            'user_id' => $this->id,
            'email' => $this->email,
            'panel' => $panel->getId(),
            'has_super_admin' => $this->hasRole('super_admin'),
            'roles' => $this->roles->pluck('name')->toArray(),
        ]);

        return match ($panel->getId()) {
            'admin' => $this->hasRole('super_admin'),
            'user'  => true,
            default => false,
        };
    }
}
