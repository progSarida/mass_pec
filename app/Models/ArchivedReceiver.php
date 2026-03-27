<?php

namespace App\Models;

use App\Enums\PecStatus;
use Illuminate\Database\Eloquent\Model;

class ArchivedReceiver extends Model
{
    protected $fillable = [
        'archived_email_id',                // identificativo email in archivio
        'protocol_number',
        'recipient_id',                     // identificativo interlocutore
        'name',                             // indirizzo casella di provenienza
        'address',                          // identificativo email archiviata collegata
        'message_id',                       // identifificativo email
        'pec_status',
    ];

    protected $casts = [
        'pec_status' => PecStatus::class,
    ];

    public function archivedEmail(){
        return $this->belongsTo(ArchivedEmail::class);
    }

    public function recipient(){
        return $this->belongsTo(Recipient::class);
    }

    protected static function booted()
    {
        static::creating(function ($receiver) {
            //
        });

        static::created(function ($receiver) {
            //
        });

        static::updating(function ($receiver) {
            //
        });

        static::saved(function ($receiver) {
            //
        });

        static::deleting(function ($receiver) {
            //
        });

        static::deleted(function ($receiver) {
            //
        });
    }
}
