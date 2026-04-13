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
use setasign\Fpdi\Fpdi;

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
            $storage = Storage::disk($disk);

            if ($registry->attachment_path && !$storage->exists($registry->attachment_path)) {
                $storage->makeDirectory($registry->attachment_path);
            }

            $files = $storage->files('registry/0');

            foreach ($files as $file) {
                $fileName = basename($file);
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . "_{$registry->flow_type->getExt()}_" . $fileName;
                $finalPath = rtrim($registry->attachment_path, '/') . '/' . $newFileName;

                try {
                    if ($extension === 'pdf') {
                        // PDF: applica watermark
                        $pdfContent = $storage->get($file);
                        $watermarkedPdf = static::addProtocolWatermarkBottom(
                            $pdfContent,
                            $registry->protocol_number,
                            $registry  // Passa il registry invece di $record
                        );

                        $storage->put($finalPath, $watermarkedPdf, [
                            'visibility' => 'private',
                            'ContentType' => 'application/pdf',
                        ]);

                        // Elimina il file originale
                        $storage->delete($file);

                        Log::info("PDF con watermark creato: $finalPath");
                    } else {
                        // Non-PDF: spostamento semplice
                        $storage->move($file, $finalPath);
                        Log::info("File non-PDF spostato: $finalPath");
                    }

                } catch (\Exception $e) {
                    Log::error("Errore durante lo spostamento/watermark per {$fileName}: " . $e->getMessage());

                    // Fallback: spostamento semplice senza watermark
                    try {
                        $storage->move($file, $finalPath);
                        Log::warning("Fallback: file spostato senza watermark");
                    } catch (\Exception $fallbackEx) {
                        Log::error("Anche il fallback è fallito: " . $fallbackEx->getMessage());
                    }
                }
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

    private static function addProtocolWatermarkBottom(string $pdfContent, string $protocolNumber, $record): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_wm');
        file_put_contents($tempFile, $pdfContent);

        // Utilizziamo il namespace corretto per FPDI
        $pdf = new Fpdi();

        try {
            $pageCount = $pdf->setSourceFile($tempFile);

            $pdf->SetAutoPageBreak(false);

            for ($n = 1; $n <= $pageCount; $n++) {
                // 1. Importiamo la pagina n
                $tplIdx = $pdf->importPage($n);
                $specs = $pdf->getImportedPageSize($tplIdx);

                // 2. Aggiungiamo la pagina mantenendo orientamento e dimensioni originali
                // Questo crea la pagina n nel nuovo PDF
                $pdf->AddPage($specs['orientation'], [$specs['width'], $specs['height']]);

                // 3. "Stampiamo" il contenuto originale sulla pagina appena creata
                $pdf->useTemplate($tplIdx);

                // 4. Ora scriviamo il watermark SOPRA il contenuto appena inserito
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetTextColor(80, 80, 80);

                $name = Company::first()->name;
                $text = "Protocollo N. " . $protocolNumber . " del " . $record->created_at->format('d/m/Y');
                $flow = $record->flow_type->getLetter();

                // Calcolo posizione basso a destra
                $cellWidth = 65;
                $x = $specs['width'] - $cellWidth - 10; // 10mm dal bordo destro
                $y = $specs['height'] - 12;            // 10mm dal bordo inferiore

                $pdf->SetXY($x, $y);
                $pdf->Cell($cellWidth-5, 5, $name, 1, 0, 'L');
                $pdf->Cell(5, 5, $flow, 1, 0, 'C');
                $pdf->SetXY($x, $y+5);
                $pdf->Cell($cellWidth, 5, $text, 1, 0, 'R');
            }

            $output = $pdf->Output('S');

            if (file_exists($tempFile)) unlink($tempFile);

            return $output;
        } catch (\Exception $e) {
            if (file_exists($tempFile)) unlink($tempFile);
            throw $e;
        }
    }
}
