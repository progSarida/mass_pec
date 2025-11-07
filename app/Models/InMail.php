<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InMail extends Model
{
    protected $fillable = [
        'uid',
        'from',
        'subject',
        'body_preview',
        'body_path',
        'receive_date',
        'attachment_path',
        'download_user_id',
    ];

    protected $casts = [
        //
    ];

    public function downloadUser(){
        return $this->belongsTo(User::class,'download_user_id');
    }

    protected static function booted()
    {
        static::creating(function ($mail) {
            $mail->download_user_id = Auth::user()->id;
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
            if ($mail->attachments_path) {
                Storage::disk('public')->deleteDirectory($mail->attachments_path);
            }
        });

    }
}
