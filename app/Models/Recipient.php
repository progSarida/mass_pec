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
        'city_cap',
        'resp_title',
        'resp_surname',
        'resp_name',
        'resp_tax_code',
        'mail_1',
        'mail_type_1',
        'office_type_id_1',
        'mail_2',
        'mail_type_2',
        'office_type_id_2',
        'mail_3',
        'mail_type_3',
        'office_type_id_3',
        'mail_4',
        'mail_type_4',
        'office_type_id_4',
        'mail_5',
        'mail_type_5',
        'office_type_id_5',
        'phone',
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

    public function officeType1(){
        return $this->belongsTo(OfficeType::class, 'office_type_id_1');
    }

    public function officeType2(){
        return $this->belongsTo(OfficeType::class, 'office_type_id_2');
    }

    public function officeType3(){
        return $this->belongsTo(OfficeType::class, 'office_type_id_3');
    }

    public function officeType4(){
        return $this->belongsTo(OfficeType::class, 'office_type_id_4');
    }

    public function officeType5(){
        return $this->belongsTo(OfficeType::class, 'office_type_id_5');
    }

    public function receivers(){
        return $this->hasMany(Receiver::class);
    }

    public function inMails(){
        return $this->hasMany(InMail::class);
    }

    public function downloadEmails(){
        return $this->hasMany(DownloadEmail::class);
    }

    public function registries(){
        return $this->hasMany(Registry::class);
    }

    public function registryReceivers(){
        return $this->hasMany(RegistryReceiver::class);
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

        static::saving(function ($recipient) {
            // assegno la colonna per il controllo dei duplicati
            if ($recipient->description) {
                $recipient->description_search = str($recipient->description)
                    ->trim()
                    ->squish()
                    ->lower();
            }
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
