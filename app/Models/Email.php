<?php

namespace App\Models;

use App\Enums\FlowType;
use App\Enums\ManageEmailType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Email extends Model
{
    protected $fillable = [
        'flow_type',
        'flow_index',
        'scope_type_id',
        'parent_id',
        'uid',
        'message_id',
        'subject',
        'body',
        'attachment_path',
        'manage_email_type',
        'manage_email_date',
        'receiving_mail',
        'sender_id',
        'other_senders',
        'from',
        'receive_date',
        'download_user_id',
        'account_id',
        'signature_id',
        'mail_type',
        'office_type_id',
        'recipients',
        'create_user_id',
        'send_user_id',
        'send_date'
    ];

    protected $casts = [
        'flow_type' => FlowType::class,
        'manage_email_type' => ManageEmailType::class,
        'manage_email_date' => 'date',
        'other_senders' => 'array',
        'receive_date' => 'datetime',
        'recipients' => 'array',
        'send_date' => 'datetime',
    ];

    public function account(){
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function signature(){
        return $this->belongsTo(Signature::class, 'signature_id');
    }

    public function officeType(){
        return $this->belongsTo(OfficeType::class, 'office_type_id');
    }

    public function sender(){
        return $this->belongsTo(Recipient::class, 'sender_id');
    }

    public function parent(){
        return $this->belongsTo(Email::class, 'parent_id');
    }

    public function downloadUser(){
        return $this->belongsTo(User::class, 'download_user_id');
    }

    public function createUser(){
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function sendUser(){
        return $this->belongsTo(User::class, 'send_user_id');
    }

    public function scopeType(){
        return $this->belongsTo(ScopeType::class, 'scope_type_id');
    }

    public function scopeReceived(Builder $query): void
    {
        $query->where('flow_type', FlowType::RECEIVED)->orderBy('receive_date', 'desc');
    }

    public function scopeSent(Builder $query): void
    {
        $query->where('flow_type', FlowType::ISSUED)->orderBy('send_date', 'desc');
    }

    protected static function booted()
    {
        static::creating(function ($mail) {
            if (Auth::check()) {
                $mail->create_user_id = Auth::id();                                                 // inserisco l'id dell'utente che crea la mail
            }
        });

        static::created(function ($mail) {
            if($mail->flow_type == FlowType::ISSUED){
                $mail->update([
                    'attachment_path' => 'email_send/' . $mail->id,                                      // salvo il percorso della cartella degli allegati
                    'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $mail->id,
                    'uid' => '#email_send' . $mail->id,
                ]);
                $disk = config('filesystems.default');
                if ($mail->attachment_path && !Storage::disk($disk)->exists($mail->attachment_path)) {
                    Storage::disk($disk)->makeDirectory($mail->attachment_path);                        // creo la cartella degli allegati
                }
                // $files = Storage::disk($disk)->files('email_send/0');
                // foreach ($files as $file) {
                //     // Estraiamo solo il nome del file (es: immagine.jpg)
                //     $fileName = basename($file);

                //     // Definiamo il percorso di destinazione completo
                //     $finalPath = rtrim($mail->attachment_path, '/') . '/' . $fileName;

                //     // 2. Spostiamo il file
                //     Storage::disk($disk)->move($file, $finalPath);
                // }
            }
        });

        static::updating(function ($email) {
            //
        });

        static::saved(function ($email) {
            //
        });

        static::deleting(function ($email) {
            //
        });

        static::deleted(function ($email) {
            if ($email->attachment_path) {
                try {
                    Storage::deleteDirectory($email->attachment_path);
                } catch (\Exception $e) {
                    // Logga l'errore se vuoi, ma non bloccare la cancellazione del record
                    Log::warning('Impossibile eliminare il file allegato', [
                        'path' => $email->attachment_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    }

    public static function getNextIndex(FlowType $flowType): int
    {
        $lastFlowIndex = Email::where('flow_type', $flowType)->orderBy('flow_index', 'desc')->first()?->flow_index ?? 0;
        return ++$lastFlowIndex;
    }
}
