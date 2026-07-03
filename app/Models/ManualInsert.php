<?php

namespace App\Models;

use App\Enums\FlowType;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ManualInsert extends Model
{
    protected $fillable = [
        'flow_type',
        'scope_type_id',
        'receivers',
        'senders',
        'interested_parties',
        'addresses',
        'subject',
        'body',
        'receive_date',
        'send_date',
        'internal_date',
        'create_user_id',
        'is_reply',
        'is_forward',
        'linked_registry_id',
        'attachment_path',
    ];

    protected $casts = [
        'flow_type' => FlowType::class,
        'receivers' => 'array',
        'senders' => 'array',
        'interested_parties' => 'array',
        'addresses' => 'array',
        'receive_date' => 'datetime',
        'send_date' => 'datetime',
        'internal_date' => 'datetime',
        'is_reply' => 'boolean',
        'is_forward' => 'boolean',
        'linked_registry_id' => 'integer',
    ];

    public function createUser(){
        return $this->belongsTo(User::class,'create_user_id');
    }

    public function scopeType(){
        return $this->belongsTo(ScopeType::class,'scope_type_id');
    }

    public function receiveRecipients()
    {
        return $this->belongsToMany(Recipient::class, null, 'id', 'id')
            ->whereIn('recipients.id', $this->receivers ?? []);
    }

    public function sendRecipients()
    {
        return $this->belongsToMany(Recipient::class, null, 'id', 'id')
            ->whereIn('recipients.id', $this->senders ?? []);
    }

    public function interestedRecipients()
    {
        return $this->belongsToMany(Recipient::class, null, 'id', 'id')
            ->whereIn('recipients.id', $this->interested_parties ?? []);
    }

    protected static function booted()
    {
        static::creating(function ($insert) {
            $insert->create_user_id = Auth::user()->id;
        });

        static::created(function ($insert) {
            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            $insert->update(['attachment_path' => "manual_insert/{$insert->id}"]);

            if ($insert->attachment_path && !$storage->exists($insert->attachment_path)) {
                $storage->makeDirectory($insert->attachment_path);
            }

            $files = $storage->files('manual_insert/0');

            foreach ($files as $file) {
                $fileName = basename($file);
                // $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $newFileName = $fileName;
                $destination = rtrim($insert->attachment_path, '/') . '/' . $newFileName;

                // try {
                //     // Spostamento
                //     $storage->move($file, $finalPath);
                //     Log::info("File spostato: $finalPath");
                // } catch (\Exception $e) {
                //     Log::error("Anche il fallback è fallito: " . $e->getMessage());
                // }

                try {
                    $stream = $storage->readStream($file);

                    if ($stream === false || $stream === null) {
                        Log::error("Impossibile aprire stream per: {$file}");
                        continue;
                    }

                    $success = $storage->writeStream($destination, $stream, [
                        'visibility' => 'private'
                    ]);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if ($success) {
                        $storage->delete($file);
                        Log::info("File spostato con successo: {$destination}");
                    } else {
                        Log::error("Scrittura fallita per: {$destination}");
                    }

                } catch (Exception $e) {
                    Log::error("Errore spostamento file {$fileName}: " . $e->getMessage());
                }
            }
        });

        static::updating(function ($insert) {
            //
        });

        static::saved(function ($insert) {
            //
        });

        static::deleting(function ($insert) {
            //
        });

        static::deleted(function ($insert) {
            // if ($mail->attachment_path) {
            //     Storage::disk('public')->deleteDirectory($mail->attachment_path);
            // }
            if ($insert->attachment_path) {
                try {
                    Storage::deleteDirectory($insert->attachment_path);
                } catch (\Exception $e) {
                    // Logga l'errore se vuoi, ma non bloccare la cancellazione del record
                    Log::warning('Impossibile eliminare il file allegato', [
                        'path' => $insert->attachment_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

    }
}
