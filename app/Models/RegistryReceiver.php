<?php

namespace App\Models;

use App\Enums\PecStatus;
use Illuminate\Database\Eloquent\Model;

class RegistryReceiver extends Model
{
    protected $fillable = [
        'registry_id',
        'protocol_number',
        'recipient_id',                         // id tabella recipients
        'address',
        'message_id',
        'pec_status',
        'anomaly_description',                  // descrizione anomalia invio pec
        'anomaly_managed',                      // flag gestione anomalia invio pec
        'anomaly_note',                         // commento gestione anomalia invio pec
    ];

    protected $casts = [
        'pec_status' => PecStatus::class,
        'anomaly_managed' => 'boolean',
    ];

    public function registry(){
        return $this->belongsTo(Registry::class);
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
