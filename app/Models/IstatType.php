<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IstatType extends Model
{
    protected $fillable = [
        'id',
        'name',
        'position',
    ];

    protected $casts = [
        //
    ];

    protected static function booted()
    {
        static::creating(function ($attachment) {
            //
        });

        static::created(function ($attachment) {
            //
        });

        static::updating(function ($attachment) {
            //
        });

        static::saved(function ($attachment) {
            //
        });

        static::deleting(function ($attachment) {
            //
        });

        static::deleted(function ($attachment) {
            //
        });

    }
}
