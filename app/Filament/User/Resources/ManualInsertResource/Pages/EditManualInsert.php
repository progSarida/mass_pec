<?php

namespace App\Filament\User\Resources\ManualInsertResource\Pages;

use App\Enums\FlowType;
use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Enums\RelationshipType;
use App\Filament\User\Resources\ManualInsertResource;
use App\Models\Company;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Models\ScopeType;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class EditManualInsert extends EditRecord
{
    protected static string $resource = ManualInsertResource::class;

    public function getTitle(): string
    {
        return "Modifica inserimento manuale";
    }

    protected function beforeCreate(): void
    {
        //
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),

            Actions\ActionGroup::make([

                Action::make('uploadFile')
                        ->label('Carica allegati')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('info')
                        ->modalSubmitActionLabel('Carica')
                        ->visible(function($record) {
                                return $record->attachment_path
                                        && Storage::exists($record->attachment_path);
                            }
                        )
                        ->form([
                            FileUpload::make('attachments')
                                ->label('Seleziona File')
                                ->multiple()
                                ->directory(fn ($record) => $record->attachment_path)
                                ->preserveFilenames()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                    $disk = config('filesystems.default');
                                    $directory = $record->attachment_path;

                                    $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                    $extension = $file->getClientOriginalExtension();
                                    
                                    $finalName = $filename . '.' . $extension;
                                    $counter = 1;

                                    while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                        $finalName = $filename . '_' . $counter . '.' . $extension;
                                        $counter++;
                                    }

                                    return $finalName;
                                })
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            Notification::make()
                                ->title('Caricamento completato')
                                ->success()
                                ->send();
                        }),

                Action::make('deleteFile')
                    ->label('Elimina file')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(function($record) {
                            return $record->attachment_path
                                    && Storage::exists($record->attachment_path)
                                    && !empty(Storage::files($record->attachment_path));
                        }
                    )
                    ->form([
                        Select::make('file_to_delete')
                            ->label('Seleziona il file da eliminare')
                            ->options(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return [];
                                }

                                $disk = config('filesystems.default');
                                $files = Storage::disk($disk)->files($record->attachment_path);

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
                        $disk = config('filesystems.default');
                        $file = $data['file_to_delete'];

                        if (Storage::disk($disk)->exists($file)) {
                            Storage::disk($disk)->delete($file);

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

                Action::make('uploadRelated')
                    ->label('Carica integrazioni')
                    ->icon('fluentui-document-link-20-o')
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    ->form([
                        FileUpload::make('receipts')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn () => $this->getRecord()->attachment_path . '/related')
                            ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path . '/related';

                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();
                                
                                $finalName = $filename . '.' . $extension;
                                $counter = 1;

                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    $finalName = $filename . '_' . $counter . '.' . $extension;
                                    $counter++;
                                }

                                return $finalName;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Notification::make()
                            ->title('Caricamento completato')
                            ->success()
                            ->send();
                    }),

                Action::make('deleteRelated')
                    ->label('Elimina integrazioni')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(function($record) {
                            $folder = $record->attachment_path . '/related';
                            return $record->attachment_path
                                && Storage::exists($folder)
                                && !empty(Storage::files($folder));
                        }
                    )
                    ->form([
                        Select::make('file_to_delete')
                            ->label('Seleziona il file da eliminare')
                            ->options(function ($record) {
                                $folder = $record->attachment_path . '/related';
                                if (!$record || !$folder) {
                                    return [];
                                }

                                $disk = config('filesystems.default');
                                $files = Storage::disk($disk)->files($folder);

                                return collect($files)->mapWithKeys(function ($file) {
                                    return [$file => basename($file)];
                                })->toArray();
                            })
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Elimina file')
                    ->modalDescription('Questa azione non può essere annullata.')
                    ->modalSubmitActionLabel('Elimina')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function (array $data) {
                        $disk = config('filesystems.default');
                        $file = $data['file_to_delete'];

                        if (Storage::disk($disk)->exists($file)) {
                            Storage::disk($disk)->delete($file);

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
                Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->label('Stampa')
                    ->tooltip('Stampa')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()
                            ->streamDownload(function () use ($record) {
                                echo Pdf::loadHTML(
                                    Blade::render('print.manual_insert', [
                                        'company' => Company::first(),
                                        'element' => $record,
                                    ])
                                )
                                ->setPaper('A4', 'portrait')
                                ->stream();
                            }, "Inserimento manuale_{$record->id}.pdf");
                    }),
                Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')) && static::canRegister($record))
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla')
                    ->modalDescription('L\'elemento verrà inserito nel protocollo ed eliminato dall\'elenco')
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
                            static::registerElement($record, $data);
                            Notification::make()
                                ->title('Elemento protocollato')
                                ->body('L\'elemento è stato protocollato con successo.')
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
                ->modalHeading('Conferma eliminazione voce protocollo')
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
                return ManualInsertResource::getUrl('index');
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

    private static function canRegister($record): bool
    {
        return (($record->flow_type === FlowType::RECEIVED && $record->receive_date && !empty($record->senders)) ||
                ($record->flow_type === FlowType::ISSUED && $record->send_date && !empty($record->receivers)) ||
                ($record->flow_type === FlowType::INTERNAL)) && $record->subject;
    }

    private static function registerElement($record, $data){
        try {
            DB::beginTransaction();

            $scopeTypeId = $data['scope_type_id'];
            $manageRegistryType = $data['manage_registry_type'];

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => $record->flow_type,
                'flow_index' => static::newIndex($record->flow_type->value),
                'registry_origin_type' => 'manual',
                'is_email' => false,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#manual' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'other_senders' => $record->senders,
                'interested_parties' => $record->interested_parties,
                'from' => "-",
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'account_id' => null,
                'send_date' => $record->send_date,
                'send_user_id' => null,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
                'manage_registry_type' => $manageRegistryType,
            ]);

            foreach(($record->receivers ?? []) as $receiver){
                $address =
                RegistryReceiver::create([
                    'registry_id' => $registry->id,
                    'protocol_number' => $protocolNumber,
                    'recipient_id' => static::getRecipientId($receiver),
                    'address' => $registry->isOutgoingPosta() ? null : $receiver,
                    'message_id' => $registry->flow_type === FlowType::ISSUED ? $registry->id . '_' . now()->format('YmdHis') : null,
                    'pec_status' => $registry->isOutgoingPosta() && !$record->pending_receipt ? PecStatus::DELIVERED : PecStatus::WAITING,
                ]);
            }

            // Creazione collegamento se esistente
            if($record->is_reply){
                $registry->parentRegistries()->attach($record->linked_registry_id, [
                    'relationship_type' => RelationshipType::REPLY->value
                ]);
            } elseif($record->is_forward){
                $registry->parentRegistries()->attach($record->linked_registry_id, [
                    'relationship_type' => RelationshipType::FORWARD->value
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

                $protocol = explode('-', $registry->protocol_number);
                $protocolYear = $protocol[1] ?? 'XXXX';
                $protocolCode = $protocol[2] ?? 'XXXXX';

                // Spostamento allegati con watermark
                foreach ($files as $file) {
                    // $fileName = basename($file);
                    // $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    // // $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . "_{$record->flow_type->getExt()}_";
                    // // $newFileName = $prefix . $fileName;
                    // $newFileName = $protocolYear . '_' . $protocolCode . "_{$record->flow_type->getExt()}_" . $fileName;
                    // $finalPath = $newPath . '/' . $newFileName;

                    $relativePath = substr($file, strlen($oldPath) + 1); // es. "sottocartella/file.pdf"
                    $fileName     = basename($file);
                    $extension    = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newFileName  = $protocolYear . '_' . $protocolCode . "_{$record->flow_type->getExt()}_" . $fileName;

                    // Mantieni la struttura: newPath / sottocartella / newFileName
                    $finalPath = $newPath . '/' . dirname($relativePath) . '/' . $newFileName;
                    $finalPath = str_replace('/./', '/', $finalPath); // pulizia se dirname è "."

                    try {
                        // TODO: disabilitata apposizione watermark 
                        // => studiare un modo per applicarlo senza perdere firma digitale
                        // => nella condizione usare il flag 'add_watermark' di Company per gestire da parametri il watermark una volta trovato il modo
                        if ($extension === 'pdf' && false) {
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
                        // try {
                        //     $storage->copy($file, $finalPath);
                        //     Log::info("Fallback: file copiato tramite S3 Copy dopo errore.");
                        // } catch (Exception $fallbackEx) {
                        //     Log::error("Anche il fallback è fallito: " . $fallbackEx->getMessage());
                        // }
                        try {
                            $stream = $storage->readStream($file);
                            if ($stream === false || $stream === null) {
                                Log::error("Impossibile aprire stream per: {$file}");
                                continue;
                            }

                            $success = $storage->writeStream($finalPath, $stream, [
                                'visibility' => 'private'
                            ]);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }

                            if ($success) {
                                Log::info("Fallback stream riuscito: {$finalPath}");
                            } else {
                                Log::error("Fallback stream fallito per: {$finalPath}");
                            }
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
}
