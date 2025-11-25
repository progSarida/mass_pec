<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class Attachment extends Model
{
    protected $fillable = [
        'id',
        'name',
        'path',
        'extension',
        'insert_date',
        'upload_user_id',
    ];

    protected $casts = [
        //
    ];

    public function uploadUser(){
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::creating(function ($attachment) {
            $pathA = explode('/', $attachment->path);
            $attachment->name = $pathA[count($pathA)-1];
            $nameA = explode('.', $attachment->name);
            $attachment->extension = $nameA[count($nameA)-1];
            $attachment->upload_date = today()->toDateString();
            $attachment->upload_user_id = Auth::user()->id;
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
            // File::delete(storage_path('app/public/' . $attachment->path));
        });

        static::deleted(function ($attachment) {
            $filePath = storage_path('app/public/' . $attachment->path);
            if ($attachment->path && File::exists($filePath)) {
                File::delete($filePath);
            }
        });

    }
}
