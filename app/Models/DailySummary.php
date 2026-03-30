<?php

namespace App\Models;

use App\Enums\PreservationState;
use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    protected $fillable = [
        'registration_date',
        'filename',
        'from_protocol',
        'to_protocol',
        'preservation_state',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'preservation_state' => PreservationState::class,
    ];

    public function uploadUser(){
        return $this->belongsTo(User::class);
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
