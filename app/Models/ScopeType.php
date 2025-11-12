<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScopeType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'position',
    ];

    protected $casts = [
        //
    ];

    protected static function booted()
    {
        static::creating(function ($service) {
            //
        });

        static::created(function ($service) {
            //
        });

        static::updating(function ($service) {
            //
        });

        static::saved(function ($service) {
            //
        });

        static::deleting(function ($service) {
            //
        });

        static::deleted(function ($service) {
            //
        });
    }
}
