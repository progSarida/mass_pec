<?php

namespace App\Models;

use App\Enums\MailType;
use App\Enums\RecipientType;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Recipient extends Model
{
    protected $fillable = [
        'description',
        'recipient_type',
        'admin_type_id',
        'istat_type_id',
        'tax_code',
        'vat_code',
        'code_ipa',
        'acronym',
        'city_id',
        'address',
        'city_cap',
        'resp_title',
        'resp_surname',
        'resp_name',
        'resp_tax_code',
        // 'mail_1',
        // 'mail_type_1',
        // 'office_type_id_1',
        // 'mail_2',
        // 'mail_type_2',
        // 'office_type_id_2',
        // 'mail_3',
        // 'mail_type_3',
        // 'office_type_id_3',
        // 'mail_4',
        // 'mail_type_4',
        // 'office_type_id_4',
        // 'mail_5',
        // 'mail_type_5',
        // 'office_type_id_5',
        'phone',
        'fax',
        'site',
        'url_facebook',
        'url_twitter',
        'url_googleplus',
        'url_youtube',
    ];

    protected $casts = [
        // 'mail_type_1' =>  MailType::class,
        // 'mail_type_2' =>  MailType::class,
        // 'mail_type_3' =>  MailType::class,
        // 'mail_type_4' =>  MailType::class,
        // 'mail_type_5' =>  MailType::class,
        'recipient_type' => RecipientType::class,
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

    public function emails(): HasMany
    {
        return $this->hasMany(RecipientEmail::class)->orderBy('order');
    }

    // ========== METODI HELPER PER RETROCOMPATIBILITÀ ==========

    /**
     * Ottiene l'email alla posizione specificata (1-5)
     */
    public function getMail(int $index): ?string
    {
        $email = $this->emails()->skip($index - 1)->first();
        return $email?->email;
    }

    /**
     * Ottiene il tipo mail alla posizione specificata
     */
    public function getMailType(int $index): ?MailType
    {
        $email = $this->emails()->skip($index - 1)->first();
        return $email?->mail_type;
    }

    /**
     * Ottiene l'office_type_id alla posizione specificata
     */
    public function getOfficeTypeId(int $index): ?int
    {
        $email = $this->emails()->skip($index - 1)->first();
        return $email?->office_type_id;
    }

    /**
     * Accessor dinamici per mail_1, mail_2, etc.
     */
    public function __get($key)
    {
        // Gestisce mail_1, mail_2, mail_3, mail_4, mail_5
        if (preg_match('/^mail_(\d+)$/', $key, $matches)) {
            $index = (int)$matches[1];
            return $this->getMail($index);
        }

        // Gestisce mail_type_1, mail_type_2, etc.
        if (preg_match('/^mail_type_(\d+)$/', $key, $matches)) {
            $index = (int)$matches[1];
            return $this->getMailType($index);
        }

        // Gestisce office_type_id_1, office_type_id_2, etc.
        if (preg_match('/^office_type_id_(\d+)$/', $key, $matches)) {
            $index = (int)$matches[1];
            return $this->getOfficeTypeId($index);
        }

        return parent::__get($key);
    }

    /**
     * Verifica se un attributo esiste (per compatibilità con isset())
     */
    public function __isset($key)
    {
        if (preg_match('/^(mail|mail_type|office_type_id)_\d+$/', $key)) {
            return true;
        }
        return parent::__isset($key);
    }

    // ========== METODI DI RICERCA ==========

    /**
     * Cerca un recipient per email (qualsiasi delle sue email)
     */
    public static function findByEmail(string $email): ?self
    {
        return self::whereHas('emails', function ($query) use ($email) {
            $query->where('email', $email);
        })->first();
    }

    /**
     * Scope per cercare per email
     */
    public function scopeWhereEmail($query, string $email)
    {
        return $query->whereHas('emails', function ($q) use ($email) {
            $q->where('email', $email);
        });
    }

    // public function officeType1(){
    //     return $this->belongsTo(OfficeType::class, 'office_type_id_1');
    // }

    // public function officeType2(){
    //     return $this->belongsTo(OfficeType::class, 'office_type_id_2');
    // }

    // public function officeType3(){
    //     return $this->belongsTo(OfficeType::class, 'office_type_id_3');
    // }

    // public function officeType4(){
    //     return $this->belongsTo(OfficeType::class, 'office_type_id_4');
    // }

    // public function officeType5(){
    //     return $this->belongsTo(OfficeType::class, 'office_type_id_5');
    // }

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
        static::creating(function ($recipient) {
            //
        });

        static::created(function ($recipient) {
            //
        });

        static::updating(function ($recipient) {
            //
        });

        static::saving(function ($recipient) {
            if ($recipient->description) {
                $search = str($recipient->description)
                    // Converte le vocali accentate (e altri caratteri speciali) in ASCII
                    ->ascii()
                    // Rimuove i caratteri speciali richiesti (. " ' ( ) )
                    ->replace(['.', '"', "'", '(', ')'], '')
                    // Sostituisco '/' e '-' con spazio
                    ->replace(['/', '-'], ' ')
                    // Rimuove spazi doppi e spazi ai bordi
                    ->squish()
                    ->trim()
                    // Converte tutto in minuscolo
                    ->lower();
Log::info("Nuova colonna ricerca: {$search}");
                $recipient->description_search = $search;
            }
        });

        static::saved(function ($recipient) {
            //
        });

        static::deleting(function ($recipient) {
            $isReferenced = DB::table('recipients as r')
                ->where('r.id', $recipient->id)
                ->where(function ($query) {
                    $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('receivers as x')->whereColumn('x.recipient_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('registry_receivers as x')->whereColumn('x.recipient_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('archived_receivers as x')->whereColumn('x.recipient_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('archived_emails as x')->whereColumn('x.sender_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('emails as x')->whereColumn('x.sender_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('download_emails as x')->whereColumn('x.sender_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('registries as x')->whereColumn('x.sender_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('in_mails as x')->whereColumn('x.sender_id', 'r.id'))
                        ->orWhereExists(fn ($q) => $q->select(DB::raw(1))
                            ->from('shipment_errors as x')->whereColumn('x.recipient_id', 'r.id'));
                })
                ->exists();

            if ($isReferenced) {
                Notification::make()
                    ->title('Eliminazione bloccata')
                    ->body("L'interlocutore \"{$recipient->description}\" è referenziato all'interno del programma.")
                    ->danger()
                    ->send();

                return false;
            }
        });

        static::deleted(function ($recipient) {
            //
        });

    }
}
