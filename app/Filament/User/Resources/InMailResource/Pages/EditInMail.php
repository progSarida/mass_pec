<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Enums\ManageRegistryType;
use App\Filament\User\Resources\InMailResource;
use App\Models\Company;
use App\Models\InMail;
use App\Models\Registry;
use App\Models\ScopeType;
use Exception;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class EditInMail extends EditRecord
{
    protected static string $resource = InMailResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Modifica email ricevuta";
    }

    protected function getHeaderActions(): array
    {
        $currentInMail = $this->record;
        $previousCInMail = InMail::where('created_at', '<=', $currentInMail->created_at)->where('id', '<', $currentInMail->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCInMail = InMail::where('created_at', '>=', $currentInMail->created_at)->where('id', '>', $currentInMail->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        // $previousRInMail = InMail::where('receive_date', '<=', $currentInMail->receive_date)->where('id', '<', $currentInMail->id)
        //                         ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();
        // $nextRInMail = InMail::where('receive_date', '>=', $currentInMail->receive_date)->where('id', '>', $currentInMail->id)
        //                         ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        $previousRInMail = null;
        $nextRInMail = null;
        if (!empty($currentInMail->receive_date)) {

            $previousRInMail = InMail::whereNotNull('receive_date')
                ->where('receive_date', '<', $currentInMail->receive_date)
                ->orWhere(function ($query) use ($currentInMail) {
                    $query->where('receive_date', $currentInMail->receive_date)
                        ->where('id', '<', $currentInMail->id);
                })
                ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();

            $nextRInMail = InMail::whereNotNull('receive_date')
                ->where('receive_date', '>', $currentInMail->receive_date)
                ->orWhere(function ($query) use ($currentInMail) {
                    $query->where('receive_date', $currentInMail->receive_date)
                        ->where('id', '>', $currentInMail->id);
                })
                ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        }
        return [
            // Actions\DeleteAction::make(),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCInMail) { return $previousCInMail;})
                ->action(function () use ($previousCInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $previousCInMail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCInMail) { return $nextCInMail;})
                ->action(function () use ($nextCInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $nextCInMail->id]));
                }),
            // Scorrimento ricezione
            Actions\Action::make('previous_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousRInMail) { return $previousRInMail;})
                ->action(function () use ($previousRInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $previousRInMail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextRInMail) { return $nextRInMail;})
                ->action(function () use ($nextRInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $nextRInMail->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')) && $record->sender_id)
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
                        } catch (\Exception $e) {
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
                ->modalHeading('Conferma eliminazione spedizione')
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
                return InMailResource::getUrl('index');
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

    private static function registerEmail($record, $data)
    {
        try {
            DB::beginTransaction();

            $scopeTypeId = $data['scope_type_id'];
            $manageRegistryType = $data['manage_registry_type'];

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();
            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'received',
                'flow_index' => static::newIndex('received'),
                'registry_origin_type' => 'in_mail',
                'receiving_mail' => $record->receiving_mail,
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'sender_id' => $record->sender_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'eml_body' => $record->eml_body,
                'receive_date' => $record->receive_date,
                'account_id' => null,
                'send_date' => null,
                'send_user_id' => null,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::id(),
                'manage_registry_type' => $manageRegistryType,
            ]);

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            // Copio cartella allegati
            if ($oldPath && $storage->exists($oldPath)) {
                // Storage::disk($disk)->makeDirectory($newPath);

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }
                $files = Storage::disk($disk)->allFiles($oldPath);

                // foreach ($files as $file) {
                //     $relativePath = str_replace($oldPath . '/', '', $file);
                //     $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $relativePath;

                //     $directory = dirname($newFilePath);
                //     if (!Storage::disk($disk)->exists($directory)) {
                //         Storage::disk($disk)->makeDirectory($directory);
                //     }

                //     Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                // }

                // Spostamento allegati senza watermark
                // foreach ($files as $file) {
                //     $fileName = basename($file);
                //     $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $fileName;
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
                    $append = static::getAppend($extension);
                    $finalPath = $newPath . $append . $newFileName;

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

            return $registry;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore protocollazione email: " . $e->getMessage() . ' - Linea: ' . $e->getLine());
            throw $e;
        }
    }

    private static function getAppend($extension): string
    {
        $append = '';
        switch($extension) {
            case 'xml':
            case 'eml':
            case 'p7s':
                $append = '/tech/';
                break;
            default:
                $append = '/';
        }
        return $append;
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
