<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendEmail extends Model
{
    protected $fillable = [
        'account_id',
        'recipients',
        'subject',
        'body',
        'attachment_path',
        'create_date',
        'create_user_id',
        // 'send_date',
        // 'send_user_id',
    ];

    protected $casts = [
        'recipients' => 'array',
    ];

    public function account(){
        return $this->belongsTo(Account::class);
    }

    public function createUser(){
        return $this->belongsTo(User::class,'create_user_id');
    }

    // public function sendUser(){
    //     return $this->belongsTo(User::class,'send_user_id');
    // }

    public function recipientsCount(): int{
        dd(count($this->recipients));
        return 1;
    }

    protected static function booted()
    {
        static::creating(function ($mail) {
            $mail->create_date = date('Y-m-d');                                                     // inserisco la data di oggi come data di creazione
            $mail->create_user_id = Auth::user()->id;                                               // inserisco l'id dell'utente che crea la mail
        });

        static::created(function ($mail) {
            $mail->update([
                'attachment_path' => 'send_email/' . $mail->id                                      // salvo il percorso della cartella degli allegati
            ]);
            $disk = config('filesystems.default');
            if ($mail->attachment_path && !Storage::disk($disk)->exists($mail->attachment_path)) {
                Storage::disk($disk)->makeDirectory($mail->attachment_path);                        // creo la cartella degli allegati
            }
            $files = Storage::disk($disk)->files('send_email/0');
            foreach ($files as $file) {
                // Estraiamo solo il nome del file (es: immagine.jpg)
                $fileName = basename($file);

                // Definiamo il percorso di destinazione completo
                $finalPath = rtrim($mail->attachment_path, '/') . '/' . $fileName;

                // 2. Spostiamo il file
                Storage::disk($disk)->move($file, $finalPath);
            }
        });

        static::updating(function ($mail) {
            //
        });

        static::saved(function ($mail) {
            //
        });

        static::deleting(function ($mail) {
            //
        });

        static::deleted(function ($mail) {
            if ($mail->attachment_path) {
                try {
                    Storage::deleteDirectory($mail->attachment_path);
                } catch (\Exception $e) {
                    // Logga l'errore se vuoi, ma non bloccare la cancellazione del record
                    Log::warning('Impossibile eliminare il file allegato', [
                        'path' => $mail->attachment_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    }
}
