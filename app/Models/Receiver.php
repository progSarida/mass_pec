<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receiver extends Model
{
    protected $fillable = [
        'shipment_id',
        'ref',
        'address',
        'mail_type',
        'send_date',
        'send_receipt',
        'delivery_receipt',
        'anomaly_receipt',
        'recipient_id',
    ];

    protected $casts = [
        //
    ];

    public function shipment(){
        return $this->belongsTo(Shipment::class);
    }

    public function recipient(){
        return $this->belongsTo(Recipient::class);
    }
}
