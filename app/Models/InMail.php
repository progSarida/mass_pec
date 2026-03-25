<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InMail extends Model
{
    protected $fillable = [
        'receiving_mail',
        'uid',
        'message_id',
        'sender_id',
        'from',
        'subject',
        'body',
        'receive_date',
        'attachment_path',
        'download_user_id',
    ];

    protected $casts = [
        //
    ];

    public function sender(){
        return $this->belongsTo(Recipient::class,'sender_id');
    }

    public function downloadUser(){
        return $this->belongsTo(User::class,'download_user_id');
    }

    protected static function booted()
    {
        static::creating(function ($mail) {
            if (auth()->check()) {
                $mail->download_user_id = auth()->id();
            }
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
            // if ($mail->attachment_path) {
            //     Storage::disk('public')->deleteDirectory($mail->attachment_path);
            // }
            if ($mail->attachment_path) {
                try {
                    Storage::deleteDirectory($mail->attachment_path);
                } catch (\Exception $e) {
                    // Logga l'errore se vuoi, ma non bloccare la cancellazione del record
                    Log::warning('Impossibile eliminare il file allegato', [
                        'path' => $mail->attachment_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    }
}
