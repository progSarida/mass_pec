<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Filament\User\Resources\SendEmailResource;
use App\Models\Account;
use App\Models\Recipient;
use App\Models\Registry;
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
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\PHPMailer;

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
            // Actions\Action::make('send')
            //     ->label('Invia')
            //     ->visible(fn($record) => !$record?->send_date)
            //     ->icon('hugeicons-mail-send-01')
            //     ->requiresConfirmation()
            //     ->modalHeading('Conferma invio')
            //     ->modalDescription('L\'invio partirà immediatamente. Continuare?')
            //     ->modalSubmitActionLabel('Sì, invia')
            //     ->action(function () {
            //         $mailId = $this->record->id;
            //         try {
            //             $this->dispatch('start-mail-send', mailId: $mailId);

            //             Notification::make()
            //                 ->title('Invio PEC avviato')
            //                 ->body('L\'invio è in corso in background...')
            //                 ->success()
            //                 ->send();
            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore')
            //                 ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     }),

            // Actions\Action::make('register')
            //     ->label('Protocolla')
            //     ->icon('fluentui-pen-20-o')
            //     ->color('warning')
            //     ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')) && $record?->send_date)
            //     ->requiresConfirmation()
            //     ->modalHeading('Protocolla email')
            //     ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
            //     ->modalSubmitActionLabel('Protocolla')
            //     ->form([
            //         Select::make('scope_type_id')
            //             ->label('Ambito')
            //             ->options(ScopeType::pluck('name', 'id'))
            //             ->searchable()
            //             ->placeholder('Seleziona l\'ambito della registrazione')
            //     ])
            //     ->action(function ($record, $data) {
            //         try {
            //             $this->registerEmail($record, $data['scope_type_id']);
            //             Notification::make()
            //                 ->title('Mail protocollata')
            //                 ->body('La mail e i suoi allegati sono stati protocollati con successo.')
            //                 ->success()
            //                 ->send();
            //             $resource = $this->getResource();
            //             return $this->redirect($resource::getUrl('index'));
            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore registrazione')
            //                 ->body($e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     }),
        ];
    }

    protected $listeners = [
        'start-mail-send' => 'sendMailBackground',
        'mail-sent-success' => 'onMailSuccess',
        'mail-sent-error' => 'onMailError',
    ];

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

    public function sendMailBackground($mailId)
    {
        try {
            $this->sendMail($mailId);
            $this->dispatch('mail-sent-success');
        } catch (\Exception $e) {
            $this->dispatch('mail-sent-error', message: $e->getMessage());
        }
    }

    public function sendMail($id)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $tempAttachments = [];

        try {
            DB::beginTransaction();

            $sendEmail = SendEmail::find($id);
            if (!$sendEmail) throw new \Exception("Spedizione non trovata!");
// dd($sendEmail);
            $account = Account::find($sendEmail->account_id);
            $recipients = $sendEmail->recipients;
// dd($recipients);
            $sent = 0;
            $not_sent = 0;
            $errors = [];

            // Configurazione SMTP
            $smtp = strtolower($account->out_mail_protocol_type->value) == 'smtp';
            $auth = (bool) $account->out_authentication;
            $host = $account->out_mail_server;
            $port = $account->out_mail_port;
            $secure = $account->connection_safety_type->value;
            $username = $account->out_username;
            $password = decrypt($account->out_password);
            $from = $account->out_username;
            $name = $account->public_name;
            $body = $sendEmail->body;
            $subject = $sendEmail->subject;
// dd('SMTP: '.$smtp,'AUTH: '.$auth,'HOST: '.$host,'PORT: '.$port,'CONN_S: '.$secure,'USER: '.$username,'PWD: '.$password,'FROM: '.$from,'NAME: '.$name,'SUBJ: '.$subject,'BODY: '.$body);
            // Preparo allegati
            $attachmentRelativePath = ltrim($sendEmail->attachment_path, '/');
            $attachments = Storage::files($attachmentRelativePath);
// dd($attachments);
            // Creo file temporanei per tutti gli allegati
            foreach ($attachments as $attachment) {
                if (!Storage::exists($attachment)) {
                    throw new \Exception("Allegato non trovato: " . $attachment);
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'attachment_');
                file_put_contents($tempFile, Storage::get($attachment));

                $tempAttachments[] = [
                    'path' => $tempFile,
                    'name' => basename($attachment)
                ];
            }

            // Invio una email per ogni destinatario
            foreach ($recipients as $recipient) {
                try {
                    // Creo una nuova istanza PHPMailer per ogni destinatario
                    $email = new PHPMailer(true);
                    $email->Timeout = 60;

                    // Configurazione SMTP
                    if ($smtp) $email->isSMTP();
                    $email->Host = $host;
                    $email->Port = $port;

                    // Autenticazione
                    if ($auth) {
                        $email->SMTPAuth = true;
                        $email->Username = $username;
                        $email->Password = $password;
                    }

                    // Crittografia
                    switch (strtolower($secure)) {
                        case 'ssl':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            break;
                        case 'tls':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            break;
                        default:
                            $email->SMTPSecure = '';
                    }

                    // Mittente
                    $email->setFrom($from, $name);

                    // Destinatario
                    $email->addAddress($recipient, static::nameRecipient($recipient));

                    // Oggetto e corpo
                    $email->Subject = '[' . $id . '] ' . $subject;
                    $email->isHTML(true);
                    $email->Body = $body;

                    // Aggiungo allegati
                    foreach ($tempAttachments as $attachment) {
                        $email->addAttachment($attachment['path'], $attachment['name']);
                    }
// dd($email);
                    // Invio
                    if ($email->send()) {
                        $sent++;
                        Log::info("Email inviata con successo a: {$recipient}");
                    } else {
                        $not_sent++;
                        $errors[] = "Invio fallito a {$recipient}";
                        Log::error("Errore invio email a {$recipient}");
                    }

                    // Libero memoria
                    $email->clearAddresses();
                    $email->clearAttachments();
                    unset($email);

                    // Pausa tra invii per evitare rate limiting
                    usleep(500000); // 0.5 secondi

                } catch (Exception $e) {
                    $not_sent++;
                    $errors[] = "Errore con {$recipient}: " . $e->getMessage();
                    Log::error("Errore invio email a {$recipient}: " . $e->getMessage());
                    // Continua con il prossimo destinatario
                    continue;
                }
            }

            // Aggiorna il record solo se almeno un invio è riuscito
            if ($sent > 0) {
                $sendEmail->update([
                    'send_date' => now()->format('Y-m-d H:i:s'),
                    'send_user_id' => Auth::id(),
                ]);
            }

            // Elimina file temporanei
            foreach ($tempAttachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    @unlink($attachment['path']);
                }
            }
// dd('STOP');
            DB::commit();

            // Notifica risultato
            if ($sent > 0 && $not_sent === 0) {
                Notification::make()
                    ->title('Email inviate con successo')
                    ->body("Inviate {$sent} email su " . count($recipients) . " destinatari")
                    ->success()
                    ->send();
            } elseif ($sent > 0 && $not_sent > 0) {
                Notification::make()
                    ->title('Invio parziale')
                    ->body("Inviate: {$sent}, Fallite: {$not_sent}")
                    ->warning()
                    ->send();
            } else {
                throw new \Exception("Nessuna email inviata. Errori: " . implode('; ', $errors));
            }

            $this->dispatch('mail-sent-success', sent: $sent, failed: $not_sent);

        } catch (\Exception $ex) {
            DB::rollBack();

            // Cleanup file temporanei in caso di errore
            foreach ($tempAttachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    @unlink($attachment['path']);
                }
            }

            Log::error("Errore invio spedizione {$id}: " . $ex->getMessage());

            Notification::make()
                ->title('Errore invio email')
                ->body($ex->getMessage())
                ->danger()
                ->send();

            $this->dispatch('mail-sent-error', message: $ex->getMessage());

            throw $ex;
        }
    }

    private static function nameRecipient($email): string
    {
        $rec = Recipient::where(function ($query) use ($email) {
            $query->where('mail_1', $email)
                ->orWhere('mail_2', $email)
                ->orWhere('mail_3', $email)
                ->orWhere('mail_4', $email)
                ->orWhere('mail_5', $email);
        })
        ->select('description', 'resp_surname', 'resp_name')
        ->first();

        if ($rec) {
            // return "{$rec->description} - {$rec->resp_surname} {$rec->resp_name}";
            return "{$rec->description}";
        }

        return $email;
    }

    private static function registerEmail($record, $scopeTypeId){
        try {
            DB::beginTransaction();

            $oldPath = $record->shipment_path;
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
                'from' => $record->sender->public_name,
                'subject' => $record->mail_object,
                'body' => $record->mail_body,
                'receive_date' => null,
                'send_date' => $record->send_date,
                'send_user_id' => $record->send_user_id,
                'shipment_id' => null,
                'send_email_id' => $record->id,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la spedizione
            // Model::withoutEvents(function () use ($record) {
            //     $record->delete();
            // });

            $disk = config('filesystems.default');

            // copio cartella allegati
            if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->makeDirectory($newPath);

                $files = collect(Storage::disk($disk)->files($oldPath))
                    ->filter(function ($path) {
                        return Str::contains($path, 'estrazione');
                    })
                    ->all();

                foreach ($files as $file) {
                    $relativePath = str_replace($oldPath . '/', '', $file);
                    $newFilePath = $newPath . '/' . $relativePath;

                    $directory = dirname($newFilePath);
                    if (!Storage::disk($disk)->exists($directory)) {
                        Storage::disk($disk)->makeDirectory($directory);
                    }

                    Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                }
            }

            // Elimino la vecchia cartella della spedizione
            // if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
            //     Storage::disk($disk)->deleteDirectory($oldPath);
            // }

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
}
