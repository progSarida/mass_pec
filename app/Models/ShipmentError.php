<?php

namespace App\Models;

use App\Enums\ShipmentErrorType;
use Illuminate\Database\Eloquent\Model;

class ShipmentError extends Model
{
    protected $fillable = [
        'shipment_id',
        'recipient_id',
        'address',
        'send_date',
        'shipment_error_type',
    ];

    protected $casts = [
        'shipment_error_type' =>  ShipmentErrorType::class,
    ];

    public array $receiverList = [];
    public array $attachmentList = [];

    public function shipment(){
        return $this->belongsTo(Shipment::class);
    }

    public function recipient(){
        return $this->belongsTo(Recipient::class);
    }

    protected static function booted()
    {
        static::creating(function ($shipment) {
            //
        });

        static::created(function ($shipment) {
            //
        });

        static::updating(function ($shipment) {
            //
        });

        static::saved(function ($shipment) {
            //
        });

        static::deleting(function ($shipment) {
            //
        });

        static::deleted(function ($shipment) {
            //
        });

    }
}
