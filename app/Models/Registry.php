<?php

namespace App\Models;

use App\Enums\FlowType;
use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Registry extends Model
{
    protected $fillable = [
        'protocol_number',
        'flow_type',
        'flow_index',
        'registry_origin_type',
        'receiving_mail',
        'parent_id',
        'is_email',
        'scope_type_id',
        'interested_parties',
        'uid',
        'message_id',
        'sender_id',                // id tabella recipients
        'other_senders',            // array con id della tabella recipients
        'from',
        'subject',
        'body',
        'receive_date',
        'account_id',
        // 'recipients',
        'send_date',
        'send_user_id',
        'shipment_id',
        'send_email_id',
        'attachment_path',
        'download_date',
        'download_user_id',
        'register_user_id',
        'manage_registry_type',
        'manage_registry_date',
    ];

    protected $casts = [
        'flow_type' => FlowType::class,
        'registry_origin_type' => RegistryOriginType::class,
        'interested_parties' => 'array',
        // 'recipients' => 'array',
        'send_date' => 'datetime',
        'receive_date' => 'datetime',
        'other_senders' => 'array',
        'manage_registry_type' => ManageRegistryType::class,
        'manage_registry_date' => 'date',
    ];

    public function downloadUser(){
        return $this->belongsTo(User::class,'download_user_id');
    }

    public function registerUser(){
        return $this->belongsTo(User::class,'register_user_id');
    }

    public function scopeType(){
        return $this->belongsTo(ScopeType::class,'scope_type_id');
    }

    public function sendUser(){
        return $this->belongsTo(User::class,'send_user_id');
    }

    public function shipment(){
        return $this->belongsTo(Shipment::class);
    }

    public function registryReceivers(){
        return $this->hasMany(RegistryReceiver::class);
    }

    public function sender(){
        return $this->belongsTo(Recipient::class,'sender_id');
    }

    public function account(){
        return $this->belongsTo(Account::class);
    }

    public function registry(){
        return $this->belongsTo(Registry::class,'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Registry::class, 'parent_id', 'id')
                    ->where('registry_origin_type', RegistryOriginType::REPLY);
    }

    public function forwards()
    {
        return $this->hasMany(Registry::class, 'parent_id', 'id')
                    ->where('registry_origin_type', RegistryOriginType::FORWARD);
    }

    public function checkReceipts(){
        $allDone = true;
        foreach($this->registryReceivers as $receiver){
            if($receiver->pec_status == PecStatus::WAITING) $allDone = false;
        }
        return $allDone;
    }

    public function isIngoingEmail()
    {
        switch($this->registry_origin_type){
            case RegistryOriginType::IN_MAIL:
            case RegistryOriginType::DOWNLOAD_EMAIL:
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    public function isOutgoingEmail()
    {
        switch($this->registry_origin_type){
            case RegistryOriginType::SEND_EMAIL:
            case RegistryOriginType::REPLY:
            case RegistryOriginType::FORWARD:
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    protected static function booted()
    {
        static::creating(function ($registry) {
            $registry->attachment_path = 'registry/' . $registry->protocol_number;
            if(!$registry->registry_origin_type){
                $registry->registry_origin_type = 'manual';
            }
            if(!$registry->uid){
                $registry->uid = $registry->protocol_number;
            }
            if(!$registry->message_id){
                $registry->message_id = $registry->protocol_number;
            }
            if($registry->flow_type == FlowType::INTERNAL){
                $lastIndex = Registry::where('flow_type', 'internal')->max('flow_index');
                $registry->flow_index = ++$lastIndex;
                $registry->from = '-';
            }
            $registry->register_user_id = Auth::user()->id;
        });

        static::created(function ($registry) {
            $disk = config('filesystems.default');
            if ($registry->attachment_path && !Storage::disk($disk)->exists($registry->attachment_path)) {
                Storage::disk($disk)->makeDirectory($registry->attachment_path);
            }
        });

        static::updating(function ($registry) {
            //
        });

        static::saved(function ($registry) {
            //
        });

        static::deleting(function ($registry) {
            //
        });

        static::deleted(function ($registry) {
            // if ($mail->attachment_path) {
            //     Storage::disk('public')->deleteDirectory($mail->attachment_path);
            // }
            if ($registry->attachment_path) {
                try {
                    Storage::deleteDirectory($registry->attachment_path);
                } catch (\Exception $e) {
                    // Logga l'errore se vuoi, ma non bloccare la cancellazione del record
                    Log::warning('Impossibile eliminare il file allegato', [
                        'path' => $registry->attachment_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    }
}
