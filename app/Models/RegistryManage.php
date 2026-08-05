<?php

namespace App\Models;

use App\Enums\ManageRegistryType;
use Illuminate\Database\Eloquent\Model;

class RegistryManage extends Model
{
    protected $fillable = [
        'registry_id',
        'manage_registry_type',
        'manage_registry_date',
        'manage_registry_mode',
        'manage_registration_datetime',
        'manage_registration_user_id',
    ];

    protected $casts = [
        'manage_registry_type' => ManageRegistryType::class,
        'manage_registry_date' => 'date',
        'manage_registration_datetime' => 'datetime',
    ];

    public function registry(){
        return $this->belongsTo(Registry::class);
    }

    public function user(){
        return $this->belongsTo(User::class, 'manage_registration_user_id');
    }
}
