<?php

namespace App\Models;

use App\Enums\MailType;
use Illuminate\Database\Eloquent\Model;

class Recipient extends Model
{
    protected $fillable = [
        'description',
        'admin_type_id',
        'istat_type_id',
        'code_ipa',
        'acronym',
        'city_id',
        'address',
        'resp_title',
        'resp_surname',
        'resp_name',
        'resp_tax_code',
        'mail_1',
        'mail_type_1',
        'mail_2',
        'mail_type_2',
        'mail_3',
        'mail_type_3',
        'mail_4',
        'mail_type_4',
        'mail_5',
        'mail_type_5',
        'site',
        'url_facebook',
        'url_twitter',
        'url_googleplus',
        'url_youtube',
    ];

    protected $casts = [
        'mail_type_1' =>  MailType::class,
        'mail_type_2' =>  MailType::class,
        'mail_type_3' =>  MailType::class,
        'mail_type_4' =>  MailType::class,
        'mail_type_5' =>  MailType::class,
    ];

    public function adminType(){
        return $this->belongsTo(AdminType::class);
    }

    public function istatType(){
        return $this->belongsTo(IstatType::class);
    }

    public function city(){
        return $this->belongsTo(City::class);
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
