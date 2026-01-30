<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Enums\FlowType;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource;
use App\Models\Account;
use App\Models\Registry;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EditRegistry extends EditRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Modifica voce protocollo";
    }

    protected function getHeaderActions(): array
    {
        $currentRegistry = $this->record;
        $previousNRegistry = Registry::where('protocol_number', '<', $currentRegistry->protocol_number)->orderBy('protocol_number', 'desc')->first();
        $nextNRegistry = Registry::where('protocol_number', '>', $currentRegistry->protocol_number)->orderBy('protocol_number', 'asc')->first();
        $previousIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '<', $currentRegistry->flow_index)->orderBy('flow_index', 'desc')->first();
        $nextIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '>', $currentRegistry->flow_index)->orderBy('flow_index', 'asc')->first();
        return [
            // Actions\DeleteAction::make(),
            // Scorrimento protocollo
            Actions\Action::make('previous_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousNRegistry) { return $previousNRegistry;})
                ->action(function () use ($previousNRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $previousNRegistry->id]));
                }),
            Actions\Action::make('next_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextNRegistry) { return $nextNRegistry;})
                ->action(function () use ($nextNRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $nextNRegistry->id]));
                }),
            // Scorrimento tipo flusso
            Actions\Action::make('previous_i_registry')
                ->label('Flusso')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIRegistry) { return $previousIRegistry;})
                ->action(function () use ($previousIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $previousIRegistry->id]));
                }),
            Actions\Action::make('next_i_registry')
                ->label('Flusso')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIRegistry) { return $nextIRegistry;})
                ->action(function () use ($nextIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $nextIRegistry->id]));
                }),
            Actions\ActionGroup::make([
                Action::make('uploadFile')
                    ->label('Carica File')
                    ->icon('heroicon-o-document-arrow-up')
                    ->visible(fn($record) => $record->flow_type == FlowType::INTERNAL )
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    // ->visible(fn($record) => !$record->is_email)
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn ($record) => $record->attachment_path)
                            // ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path;

                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();

                                // Creiamo la base del nome che NON deve cambiare nel loop
                                $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . '_INT_';

                                // Nome iniziale
                                $finalName = $prefix . $filename . '.' . $extension;
                                $counter = 1;

                                // Finchè esiste un file con questo nome nella directory di destinazione
                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    // Applichiamo il counter mantenendo il prefisso richiesto
                                    $finalName = $prefix . $filename . '_' . $counter . '.' . $extension;
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
                    ->label('Elimina File')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record && $record->attachment_path && !empty(Storage::files($record->attachment_path)) && $record->flow_type == FlowType::INTERNAL)
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
            Actions\Action::make('send')
                ->label('Invia')
                ->visible(fn($record) => !$record?->send_date)
                ->icon('hugeicons-mail-send-01')
                ->visible(fn($record) => $record->registry_origin_type == RegistryOriginType::SEND_EMAIL)
                ->requiresConfirmation()
                ->modalHeading('Conferma invio')
                ->modalDescription('L\'invio partirà immediatamente. Continuare?')
                ->modalSubmitActionLabel('Sì, invia')
                ->action(function () {
                    $registryId = $this->record->id;
                    try {
                        $this->sendMail($registryId);

                        Notification::make()
                            ->title('Invio PEC avviato')
                            ->body('L\'invio è in corso in background...')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
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
                return RegistryResource::getUrl('index');
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

//     public function sendMail($id)
//     {
//         set_time_limit(300);
//         ini_set('max_execution_time', 300);

//         $tempAttachments = [];

//         try {
//             DB::beginTransaction();

//             $sendEmail = Registry::find($id);
//             if (!$sendEmail) throw new \Exception("Spedizione non trovata!");
// // dd($sendEmail);
//             $account = Account::find($sendEmail->account_id);
//             $recipients = $sendEmail->recipients;
// // dd($recipients);
//             $sent = 0;
//             $not_sent = 0;
//             $errors = [];

//             // Configurazione SMTP
//             $smtp = strtolower($account->out_mail_protocol_type->value) == 'smtp';
//             $auth = (bool) $account->out_authentication;
//             $host = $account->out_mail_server;
//             $port = $account->out_mail_port;
//             $secure = $account->connection_safety_type->value;
//             $username = $account->out_username;
//             $password = decrypt($account->out_password);
//             $from = $account->out_username;
//             $name = $account->public_name;
//             $body = $sendEmail->body;
//             $subject = $sendEmail->subject;
// // dd('SMTP: '.$smtp,'AUTH: '.$auth,'HOST: '.$host,'PORT: '.$port,'CONN_S: '.$secure,'USER: '.$username,'PWD: '.$password,'FROM: '.$from,'NAME: '.$name,'SUBJ: '.$subject,'BODY: '.$body);
//             // Preparo allegati
//             $attachmentRelativePath = ltrim($sendEmail->attachment_path, '/');
//             $attachments = Storage::files($attachmentRelativePath);
// // dd($attachments);
//             // Creo file temporanei per tutti gli allegati
//             foreach ($attachments as $attachment) {
//                 if (!Storage::exists($attachment)) {
//                     throw new \Exception("Allegato non trovato: " . $attachment);
//                 }

//                 $tempFile = tempnam(sys_get_temp_dir(), 'attachment_');
//                 file_put_contents($tempFile, Storage::get($attachment));

//                 $tempAttachments[] = [
//                     'path' => $tempFile,
//                     'name' => basename($attachment)
//                 ];
//             }

//             // Invio una email per ogni destinatario
//             foreach ($recipients as $recipient) {
//                 try {
//                     // Creo una nuova istanza PHPMailer per ogni destinatario
//                     $email = new PHPMailer(true);
//                     $email->Timeout = 60;

//                     // Configurazione SMTP
//                     if ($smtp) $email->isSMTP();
//                     $email->Host = $host;
//                     $email->Port = $port;

//                     // Autenticazione
//                     if ($auth) {
//                         $email->SMTPAuth = true;
//                         $email->Username = $username;
//                         $email->Password = $password;
//                     }

//                     // Crittografia
//                     switch (strtolower($secure)) {
//                         case 'ssl':
//                             $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
//                             break;
//                         case 'tls':
//                             $email->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//                             break;
//                         default:
//                             $email->SMTPSecure = '';
//                     }

//                     // Mittente
//                     $email->setFrom($from, $name);

//                     // Destinatario
//                     $email->addAddress($recipient, static::nameRecipient($recipient));

//                     // Oggetto e corpo
//                     $email->Subject = '[' . $id . '] ' . $subject;
//                     $email->isHTML(true);
//                     $email->Body = $body;

//                     // Aggiungo allegati
//                     foreach ($tempAttachments as $attachment) {
//                         $email->addAttachment($attachment['path'], $attachment['name']);
//                     }
// // dd($email);
//                     // Invio
//                     if ($email->send()) {
//                         $sent++;
//                         Log::info("Email inviata con successo a: {$recipient}");
//                     } else {
//                         $not_sent++;
//                         $errors[] = "Invio fallito a {$recipient}";
//                         Log::error("Errore invio email a {$recipient}");
//                     }

//                     // Libero memoria
//                     $email->clearAddresses();
//                     $email->clearAttachments();
//                     unset($email);

//                     // Pausa tra invii per evitare rate limiting
//                     usleep(500000); // 0.5 secondi

//                 } catch (Exception $e) {
//                     $not_sent++;
//                     $errors[] = "Errore con {$recipient}: " . $e->getMessage();
//                     Log::error("Errore invio email a {$recipient}: " . $e->getMessage());
//                     // Continua con il prossimo destinatario
//                     continue;
//                 }
//             }

//             // Aggiorna il record solo se almeno un invio è riuscito
//             if ($sent > 0) {
//                 $sendEmail->update([
//                     'send_date' => now()->format('Y-m-d H:i:s'),
//                     'send_user_id' => Auth::id(),
//                 ]);
//             }

//             // Elimina file temporanei
//             foreach ($tempAttachments as $attachment) {
//                 if (file_exists($attachment['path'])) {
//                     @unlink($attachment['path']);
//                 }
//             }
// // dd('STOP');
//             DB::commit();

//             // Notifica risultato
//             if ($sent > 0 && $not_sent === 0) {
//                 Notification::make()
//                     ->title('Email inviate con successo')
//                     ->body("Inviate {$sent} email su " . count($recipients) . " destinatari")
//                     ->success()
//                     ->send();
//             } elseif ($sent > 0 && $not_sent > 0) {
//                 Notification::make()
//                     ->title('Invio parziale')
//                     ->body("Inviate: {$sent}, Fallite: {$not_sent}")
//                     ->warning()
//                     ->send();
//             } else {
//                 throw new \Exception("Nessuna email inviata. Errori: " . implode('; ', $errors));
//             }

//             $this->dispatch('mail-sent-success', sent: $sent, failed: $not_sent);

//         } catch (\Exception $ex) {
//             DB::rollBack();

//             // Cleanup file temporanei in caso di errore
//             foreach ($tempAttachments as $attachment) {
//                 if (isset($attachment['path']) && file_exists($attachment['path'])) {
//                     @unlink($attachment['path']);
//                 }
//             }

//             Log::error("Errore invio spedizione {$id}: " . $ex->getMessage());

//             Notification::make()
//                 ->title('Errore invio email')
//                 ->body($ex->getMessage())
//                 ->danger()
//                 ->send();

//             $this->dispatch('mail-sent-error', message: $ex->getMessage());

//             throw $ex;
//         }
//     }
}
