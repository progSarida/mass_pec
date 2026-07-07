<?php

namespace App\Models;

use App\Enums\PecInteractionType;
use Illuminate\Database\Eloquent\Model;

class PecInteraction extends Model
{
    protected $fillable = [
        'id',
        'pec_interaction_type',
        'registry_id',
        'interaction_date',
        'user_id',
    ];

    protected $casts = [
        'pec_interaction_type' => PecInteractionType::class,
        'interaction_date' => 'date',
    ];

    public function registry(){
        return $this->belongsTo(Registry::class,'registry_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id');
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
