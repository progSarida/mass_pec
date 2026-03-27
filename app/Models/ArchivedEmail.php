<?php

namespace App\Models;

use App\Enums\FlowType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArchivedEmail extends Model
{
    protected $fillable = [
        'protocol_number',
        'flow_type',
        'receiving_mail',                   // indirizzo casella di provenienza
        'parent_id',                        // identificativo email archiviata collegata
        'uid',
        'message_id',
        'account_id',                       // identificativo tabella accounts
        'sender_id',                        // identificativo tabella recipients
        'other_senders',                    // array con identificativi della tabella recipients
        'from',                             // indirizzo mittente
        'subject',
        'body',
        'send_date',
        'receive_date',
        'attachment_path',
        'download_user_id',
    ];

    protected $casts = [
        'flow_type' => FlowType::class,
        'other_senders' => 'array',
        'send_date' => 'datetime',
        'receive_date' => 'datetime',
    ];

    public function parent(){
        return $this->belongsTo(ArchivedEmail::class,'parent_id');
    }

    public function account(){
        return $this->belongsTo(Account::class);
    }

    public function sender(){
        return $this->belongsTo(Recipient::class,'sender_id');
    }

    public function downloadUser(){
        return $this->belongsTo(User::class,'download_user_id');
    }

    public function archivedReceivers(){
        return $this->hasMany(ArchivedReceiver::class);
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
