<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Registry extends Model
{
    protected $fillable = [
        'protocol_number',
        'scope_type_id',
        'uid',
        'message_id',
        'from',
        'subject',
        'body',
        'receive_date',
        'attachment_path',
        'download_date',
        'download_user_id',
        'register_user_id',
    ];

    protected $casts = [
        //
    ];

    public function downloadUser(){
        return $this->belongsTo(User::class,'download_user_id');
    }

    public function registerUser(){
        return $this->belongsTo(User::class,'register_user_id');
    }

    public function scopeType(){
        return $this->belongsTo(ScopeType::class,'scope_type_id');
    }

    protected static function booted()
    {
        static::creating(function ($mail) {
            //
        });

        static::created(function ($mail) {
            //
        });

        static::updating(function ($mail) {
            //
        });

        static::saved(function ($mail) {
            //
        });

        static::deleting(function ($mail) {
            //
        });

        static::deleted(function ($mail) {
            if ($mail->attachment_path) {
                Storage::disk('public')->deleteDirectory($mail->attachment_path);
            }
        });

    }
}
