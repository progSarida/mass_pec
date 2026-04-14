<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Filament\User\Resources\SendEmailResource;
use App\Models\Company;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Models\ScopeType;
use App\Models\SendEmail;
use Exception;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class EditSendEmail extends EditRecord
{
    protected static string $resource = SendEmailResource::class;

    public function getTitle(): string
    {
        return "Modifica email in uscita";
    }

    protected function getHeaderActions(): array
    {
        $currentSendEmail = $this->record;
        // --- Navigazione per Data Creazione (solitamente non è null, ma per sicurezza...) ---
        $previousCSendEmail = SendEmail::where('create_date', '<', $currentSendEmail->create_date)
            ->orWhere(function ($query) use ($currentSendEmail) {
                $query->where('create_date', $currentSendEmail->create_date)
                    ->where('id', '<', $currentSendEmail->id);
            })
            ->orderBy('create_date', 'desc')->orderBy('id', 'desc')->first();
        $nextCSendEmail = SendEmail::where('create_date', '>', $currentSendEmail->create_date)
            ->orWhere(function ($query) use ($currentSendEmail) {
                $query->where('create_date', $currentSendEmail->create_date)
                    ->where('id', '>', $currentSendEmail->id);
            })
            ->orderBy('create_date', 'asc')->orderBy('id', 'asc')->first();
        // --- Navigazione per Data Invio (Gestione NULL) ---
        $previousSSendEmail = null;
        $nextSSendEmail = null;
        if ($currentSendEmail->send_date !== null) {
            $previousSSendEmail = SendEmail::whereNotNull('send_date')
                ->where(function ($query) use ($currentSendEmail) {
                    $query->where('send_date', '<', $currentSendEmail->send_date)
                        ->orWhere(function ($q) use ($currentSendEmail) {
                            $q->where('send_date', $currentSendEmail->send_date)
                                ->where('id', '<', $currentSendEmail->id);
                        });
                })
                ->orderBy('send_date', 'desc')->orderBy('id', 'desc')->first();
            $nextSSendEmail = SendEmail::whereNotNull('send_date')
                ->where(function ($query) use ($currentSendEmail) {
                    $query->where('send_date', '>', $currentSendEmail->send_date)
                        ->orWhere(function ($q) use ($currentSendEmail) {
                            $q->where('send_date', $currentSendEmail->send_date)
                                ->where('id', '>', $currentSendEmail->id);
                        });
                })
                ->orderBy('send_date', 'asc')->orderBy('id', 'asc')->first();
        }
        return [
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCSendEmail) { return $previousCSendEmail;})
                ->action(function () use ($previousCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $previousCSendEmail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCSendEmail) { return $nextCSendEmail;})
                ->action(function () use ($nextCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $nextCSendEmail->id]));
                }),
            // Scorrimento invio
            Actions\Action::make('previous_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousSSendEmail) { return $previousSSendEmail;})
                ->action(function () use ($previousSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $previousSSendEmail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextSSendEmail) { return $nextSSendEmail;})
                ->action(function () use ($nextSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $nextSSendEmail->id]));
                }),
            Actions\ActionGroup::make([
                Action::make('uploadFile')
                    ->label('Carica allegati')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    // ->visible(fn($record) => !$record->is_email)
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn ($record) => $record->attachment_path)
                            ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path;

                                // Estraiamo nome e estensione originali
                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();

                                $finalName = $filename . '.' . $extension;
                                $counter = 1;

                                // Finché esiste un file con questo nome, incrementiamo il suffisso
                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    $finalName = $filename . '_' . $counter . '.' . $extension;
                                    $counter++;
                                }

                                return $finalName;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // I file vengono caricati automaticamente nella cartella
                        // configurata nel metodo ->directory() sopra.

                        Notification::make()
                            ->title('Caricamento completato')
                            ->success()
                            ->send();
                    }),

                Action::make('deleteFile')
                    ->label('Elimina allegati')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record && $record->attachment_path && !empty(Storage::files($record->attachment_path)))
                    ->form([
                        Select::make('file_to_delete')
                            ->label('Seleziona il file da eliminare')
                            ->options(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return [];
                                }

                                $files = Storage::files($record->attachment_path);

                                return collect($files)->mapWithKeys(function ($file) {
                                    return [$file => basename($file)];
                                })->toArray();
                            })
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Elimina allegato')
                    ->modalDescription('Questa azione non può essere annullata.')
                    ->modalSubmitActionLabel('Elimina')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function (array $data) {
                        $file = $data['file_to_delete'];

                        if (Storage::exists($file)) {
                            Storage::delete($file);

                            Notification::make()
                                ->title('File eliminato con successo')
                                ->body('Il file ' . basename($file) . ' è stato eliminato.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('File non trovato')
                                ->warning()
                                ->send();
                        }
                    }),

                Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')))
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla email')
                    ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->options(ScopeType::orderBy('position', 'asc')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona il settore interno della registrazione'),
                        Select::make('manage_registry_type')
                            ->label('Gestione')
                            ->options(
                                collect(ManageRegistryType::cases())
                                    ->filter(fn (ManageRegistryType $enum) => $enum->showToAssign())
                                    ->mapWithKeys(fn (ManageRegistryType $enum) => [
                                        $enum->value => $enum->getLabel()
                                    ])
                            )
                            ->default(ManageRegistryType::NONE->value)
                    ])
                    ->action(function ($record, $data) {
                        try {
                            static::registerEmail($record, $data);
                            Notification::make()
                                ->title('Mail protocollata')
                                ->body('La mail e i suoi allegati sono stati protocollati con successo.')
                                ->success()
                                ->send();
                            $resource = $this->getResource();
                            return $this->redirect($resource::getUrl('index'));
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Errore registrazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                ->modalHeading('Conferma eliminazione mail')
                ->modalDescription('Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/contacts?')) {
                    return $this->previousUrl;
                }
                return SendEmailResource::getUrl('index');
            });
    }

    protected function getResetFormAction(): Actions\Action
    {
        return Actions\Action::make('reset')
            ->label('Annulla')
            ->color('gray')
            ->action(function () {
                $this->data = $this->getRecord()->toArray();
                $this->fillForm();
            });
    }

//     private static function nameRecipient($email): string
//     {
//         $rec = Recipient::where(function ($query) use ($email) {
//             $query->where('mail_1', $email)
//                 ->orWhere('mail_2', $email)
//                 ->orWhere('mail_3', $email)
//                 ->orWhere('mail_4', $email)
//                 ->orWhere('mail_5', $email);
//         })
//         ->select('description', 'resp_surname', 'resp_name')
//         ->first();

//         if ($rec) {
//             // return "{$rec->description} - {$rec->resp_surname} {$rec->resp_name}";
//             return "{$rec->description}";
//         }

//         return $email;
//     }

    private static function registerEmail($record, $data){
        try {
            DB::beginTransaction();

            $scopeTypeId = $data['scope_type_id'];
            $manageRegistryType = $data['manage_registry_type'];

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'issued',
                'flow_index' => static::newIndex('issued'),
                'registry_origin_type' => 'send_email',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#send_email' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'sender_id' => $record->sender_id,                                                                  // GESTIONE
                'from' => $record->account->address,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => null,
                'account_id' => $record->account_id,
                'send_date' => $record->send_date,
                'send_user_id' => $record->send_user_id,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
                'manage_registry_type' => $manageRegistryType,
            ]);

            foreach(($record->recipients ?? []) as $receiver){
                RegistryReceiver::create([
                    'registry_id' => $registry->id,
                    'protocol_number' => $protocolNumber,
                    'recipient_id' => static::getRecipientId($receiver),
                    'address' => $receiver,
                    'pec_status' => PecStatus::WAITING,
                ]);
            }

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            if ($oldPath && $storage->exists($oldPath)) {

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }

                $files = $storage->allFiles($oldPath);

                // DEBUG: Logga quanti file hai trovato
                Log::info("File trovati in $oldPath: " . count($files));

                // foreach ($files as $file) {
                //     $fileName = basename($file);
                //     $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $fileName;
                //     $finalPath = $newPath . '/' . $newFileName;

                //     // Log per tracciamento
                //     Log::info("Copia file da $file a $finalPath");

                //     if (!$storage->copy($file, $finalPath)) {
                //         throw new \Exception("Impossibile copiare il file: $file");
                //     }
                // }

                // Spostamento allegati senza watermark
                // foreach ($files as $file) {
                //     $fileName = basename($file);
                //     $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $fileName;
                //     $finalPath = $newPath . '/' . $newFileName;

                //     try {
                //         // Usiamo lo Stream per bypassare i limiti del comando COPY di S3
                //         $stream = $storage->readStream($file);

                //         if ($stream === null) {
                //             throw new \Exception("Impossibile leggere il file sorgente: $file");
                //         }

                //         // Scriviamo il file nella nuova posizione
                //         // Il terzo parametro 'visibility' assicura che il nuovo file sia scrivibile
                //         $result = $storage->writeStream($finalPath, $stream, [
                //             'visibility' => 'private'
                //         ]);

                //         if (is_resource($stream)) {
                //             fclose($stream);
                //         }

                //         if (!$result) {
                //             throw new \Exception("Scrittura fallita per: $finalPath");
                //         }

                //         Log::info("File copiato con successo: $finalPath");

                //     } catch (\Exception $e) {
                //         Log::error("Errore durante la copia stream: " . $e->getMessage());
                //         // Fallback estremo se lo stream fallisce (usa più RAM)
                //         $storage->put($finalPath, $storage->get($file));
                //     }
                // }

                // Spostamento allegati con watermark
                foreach ($files as $file) {
                    $fileName = basename($file);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newFileName = today()->format('d-m-Y') . '_' . $protocolNumber . '_INV_' . $fileName;
                    $finalPath = $newPath . '/' . $newFileName;

                    try {
                        if ($extension === 'pdf') {
                            // Caso PDF: Scarichiamo in memoria, applichiamo watermark e ricarichiamo
                            $pdfContent = $storage->get($file);
                            $watermarkedPdf = static::addProtocolWatermarkBottom($pdfContent, $protocolNumber, $registry);

                            $storage->put($finalPath, $watermarkedPdf, [
                                'visibility' => 'private',
                                'ContentType' => 'application/pdf',
                            ]);
                            Log::info("PDF con watermark creato su S3: $finalPath");
                        } else {
                            // Caso NON PDF: Usiamo lo Stream per file grandi (ottimo per S3)
                            $stream = $storage->readStream($file);

                            if ($stream === null) {
                                throw new Exception("Impossibile leggere lo stream sorgente: $file");
                            }

                            $result = $storage->writeStream($finalPath, $stream, [
                                'visibility' => 'private'
                            ]);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }

                            if (!$result) {
                                throw new Exception("Scrittura stream fallita per: $finalPath");
                            }
                            Log::info("File non-PDF copiato via stream su S3: $finalPath");
                        }

                    } catch (Exception $e) {
                        Log::error("Errore durante il trasferimento su S3 per {$fileName}: " . $e->getMessage());

                        // Fallback: Tentativo di copia diretta (lato server S3) se lo stream/watermark fallisce
                        try {
                            $storage->copy($file, $finalPath);
                            Log::info("Fallback: file copiato tramite S3 Copy dopo errore.");
                        } catch (Exception $fallbackEx) {
                            Log::error("Anche il fallback è fallito: " . $fallbackEx->getMessage());
                        }
                    }
                }

            } else {
                Log::warning("Percorso non trovato o vuoto: " . ($oldPath ?? 'NULL'));
            }

            // Elimino la mail in uscita
            // Model::withoutEvents(function () use ($record) {
                $record->delete();
            // });
            // Elimina solo se hai effettivamente trovato dei file o se vuoi pulire comunque
            // $storage->deleteDirectory($oldPath);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
    }

    private static function newProtocol(): string
    {
        $lastRegistry = Registry::orderBy('created_at', 'desc')->first();

        if ($lastRegistry) {
            $parts = explode('-', $lastRegistry->protocol_number);

            if (count($parts) !== 3 || $parts[0] !== 'P') {
                return 'P-' . today()->year . '-00001';
            }

            $lastYear = (int) $parts[1];
            $lastNumber = (int) $parts[2];
            $currentYear = today()->year;

            if ($lastYear === $currentYear) {
                $newNumber = $lastNumber + 1;
                return 'P-' . $currentYear . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            } else {
                return 'P-' . $currentYear . '-00001';
            }
        }
        return 'P-' . today()->year . '-00001';
    }

    private static function newIndex($flow_type): int
    {
        $lastIndex = Registry::where('flow_type', $flow_type)->max('flow_index');
        if ($lastIndex) {
            $newIndex = $lastIndex+1;
            return $newIndex;
        }
        return 1;
    }

    // private static function getRecipientIdOld($from)
    // {
    //     $recipient = Recipient::where('mail_1', $from)
    //                     ->orWhere('mail_2', $from)
    //                     ->orWhere('mail_3', $from)
    //                     ->orWhere('mail_4', $from)
    //                     ->orWhere('mail_5', $from)
    //                     ->first();
    //     return $recipient?->id;
    // }

    private static function getRecipientId($from)
    {
        $recipient = Recipient::findByEmail($from);
        return $recipient?->id;
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

    private static function addProtocolWatermarkTop(string $pdfContent, string $protocolNumber): string
    {
        // Creiamo un file temporaneo per FPDI (lavora meglio con file fisici)
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_wm');
        file_put_contents($tempFile, $pdfContent);

        $pdf = new Fpdi();

        try {
            $pageCount = $pdf->setSourceFile($tempFile);

            for ($n = 1; $n <= $pageCount; $n++) {
                $tplIdx = $pdf->importPage($n);
                $specs = $pdf->getImportedPageSize($tplIdx);

                $pdf->AddPage($specs['orientation'], [$specs['width'], $specs['height']]);
                $pdf->useTemplate($tplIdx);

                // --- Configurazione Font e Testo ---
                $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->SetTextColor(80, 80, 80);             // grigio trasparente

                $text = "Prot. N: " . $protocolNumber . " del " . now()->format('d/m/Y');

                // Posizionamento in alto a destra (con margine di 10mm)
                $pdf->SetXY($specs['width'] - 100, 10);
                $pdf->Cell(90, 10, $text, 0, 0, 'R');
            }

            $output = $pdf->Output('S'); // Restituisce il PDF come stringa
            unlink($tempFile); // Pulizia file temporaneo

            return $output;
        } catch (Exception $e) {
            if (file_exists($tempFile)) unlink($tempFile);
            throw $e;
        }
    }
}
