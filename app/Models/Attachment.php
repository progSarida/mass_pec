<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'id',
        'name',
        'path',
        'extension',
        'insert_date'
    ];

    protected $casts = [
        //
    ];

    protected static function booted()
    {
        static::creating(function ($attachment) {
            $pathA = explode('/', $attachment->path);
            $attachment->name = $pathA[count($pathA)-1];
            $nameA = explode('.', $attachment->name);
            $attachment->extension = $nameA[count($nameA)-1];
            $attachment->insert_date = today()->toDateString();
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

    }
}
