<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'description',
        'sender_id',
        'mail_object',
        'mail_body',
        'attachment',
        'send_type',
        'insert_date',
        'shipment_path',
        'total_no_mails',
        'no_mails_sended',
        'no_mails_to_send',
        'no_send_receipt',
        'no_missed_send_receipt',
        'no_delivery_receipt',
        'no_missed_delivery_receipt',
        'no_anomaly_receipt',
        'no_anomaly_receipt',
        'extraction_zip_file',
    ];

    protected $casts = [
        //
    ];

    public function sender(){
        return $this->belongsTo(Sender::class);
    }

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
