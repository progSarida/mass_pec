<?php

namespace App\Filament\User\Resources\EmailSendResource\Pages;

use App\Enums\FlowType;
use App\Enums\ManageEmailType;
use App\Filament\User\Resources\EmailSendResource;
use App\Models\Email;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditEmailSend extends EditRecord
{
    protected static string $resource = EmailSendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\Action::make('send_debug')
            //     ->label('Invia Email (DEBUG)')
            //     ->icon('hugeicons-mail-send-01')
            //     ->color('warning') // Cambia colore per distinguerlo
            //     ->visible(fn($record) =>
            //         !$record->send_date
            //         && $record->account_id
            //         && !empty($record->recipients)
            //     )
            //     ->requiresConfirmation()
            //     ->modalHeading('Debug invio email')
            //     ->modalDescription('Questa azione mostrerà i dati con dd() senza inviare email.')
            //     ->modalSubmitActionLabel('Mostra dati')
            //     ->modalCancelActionLabel('Annulla')
            //     ->action(fn($record) => $this->debugEmailSend($record)),
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
            Actions\ActionGroup::make([
                Action::make('uploadFile')
                    ->label('Carica File')
                    ->icon('heroicon-o-document-arrow-up')
                    ->visible(fn($record) => $record->flow_type == FlowType::INTERNAL )
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn ($record) => $record->attachment_path)
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path;

                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();

                                $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . '_INT_';

                                $finalName = $prefix . $filename . '.' . $extension;
                                $counter = 1;

                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    $finalName = $prefix . $filename . '_' . $counter . '.' . $extension;
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

                Actions\Action::make('send')
                    ->label('Invia Email')
                    ->icon('hugeicons-mail-send-01')
                    ->color('success')
                    ->visible(fn($record) =>
                        !$record->send_date
                        && $record->account_id
                        && !empty($record->recipients)
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Conferma invio email')
                    ->modalDescription(function ($record) {
                        $count = count($record->recipients ?? []);
                        return "L'email sarà inviata in background a {$count} destinatari. Riceverai una notifica al termine dell'invio.";
                    })
                    ->modalSubmitActionLabel('Sì, invia')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function ($record) {
                        try {
                            \App\Jobs\ProcessEmailJob::dispatch(
                                emailId: $record->id,
                                userId: Auth::id(),
                            );

                            Notification::make()
                                ->title('Invio avviato')
                                ->body("L'email '{$record->subject}' sarà inviata in background.")
                                ->success()
                                ->duration(5000)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore avvio invio')
                                ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('uploadFile')
                        ->label('Carica allegati')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('info')
                        ->modalSubmitActionLabel('Carica')
                        ->visible(function($record) {
                                return $record->attachment_path
                                        && Storage::exists($record->attachment_path)
                                        && !$record->send_date
                                        && $record->account_id
                                        && !empty($record->recipients);
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
                                    && !empty(Storage::files($record->attachment_path))
                                    && !$record->send_date
                                    && $record->account_id
                                    && !empty($record->recipients);
                        }
                    )
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

                Actions\Action::make('manage')
                    ->label('Gestisci')
                    ->icon('heroicon-o-cog-8-tooth')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record?->manage_email_type?->showManage())
                    ->fillForm(fn (Email $record): array => [
                        'manage_email_type' => $record?->manage_email_type?->value,
                        'manage_email_date' => now(),
                    ])
                    ->form([
                        Select::make('manage_email_type')
                            ->label('Gestione')
                            ->options(
                                collect(ManageEmailType::cases())
                                    ->filter(fn (ManageEmailType $enum) => $enum->showToUpdate())
                                    ->mapWithKeys(fn (ManageEmailType $enum) => [
                                        $enum->value => $enum->getLabel()
                                    ])
                            )
                            ->live(),
                        DatePicker::make('manage_registry_date')
                            ->label('Data gestione')
                            ->required()
                            ->visible(fn (Get $get) =>$get('manage_email_type') == ManageEmailType::DONE->value ),
                    ])
                    ->action(function (Email $record, $data) {
                        $manageRegistryDate = $data['manage_registry_date'] ?? null;
                        $record->update([
                            'manage_registry_type' => $data['manage_email_type'],
                            'manage_registry_date' => $manageRegistryDate,
                        ]);
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
                ->modalHeading('Conferma eliminazione email')
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
                return EmailSendResource::getUrl('index');
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

    /**
     * Simula l'invio email mostrando i dati con dd() per debug
     */
    protected function debugEmailSend($record): void
    {
        try {
            $emailService = app(\App\Services\EmailService::class);

            // 1. Carica l'email
            $email = Email::find($record->id);

            // dd([
            //     'step' => '1. Email Record',
            //     'email' => $email->toArray(),
            //     'account_id' => $email->account_id,
            //     'recipients_raw' => $email->recipients,
            //     'subject' => $email->subject,
            //     'body_preview' => substr($email->body, 0, 200) . '...',
            //     'attachment_path' => $email->attachment_path,
            // ]);

            // 2. Imposta account e recupera config SMTP
            $emailService->setAccount($email->account_id);
            $account = $emailService->getAccount();

            dd([
                'step' => '2. Account Config',
                'account' => $account->toArray(),
                'smtp_config' => $account->getSmtpMailerConfigBypass(),
                'from_address' => $account->getFromAddress(),
                'from_name' => $account->getFromName(),
            ]);

            // 3. Estrai destinatari
            $recipients = $emailService->extractRecipients($email);

            dd([
                'step' => '3. Recipients',
                'count' => $recipients->count(),
                'recipients' => $recipients->toArray(),
                'first_recipient' => $recipients->first(),
            ]);

            // 4. Prepara allegati
            $attachments = $emailService->prepareAttachments($email);

            dd([
                'step' => '4. Attachments',
                'disk' => config('filesystems.default'),
                'attachment_path' => $email->attachment_path,
                'files_found' => count($attachments),
                'attachments' => $attachments,
            ]);

            // 5. Simula creazione Mailable
            $mailable = new \App\Mail\EmailMailable(
                subject: $email->subject,
                body: $email->body,
                fromAddress: $account->getFromAddress(),
                fromName: $account->getFromName(),
                attachments: $attachments,
            );

            dd([
                'step' => '5. Mailable',
                'mailable_class' => get_class($mailable),
                'envelope' => $mailable->envelope(),
                'attachments_count' => count($mailable->attachments()),
                'full_mailable' => $mailable,
            ]);

            // 6. Simula creazione Jobs
            $jobs = $recipients->map(fn ($recipient) => [
                'job_class' => 'EmailSendJob',
                'email_id' => $email->id,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
            ])->toArray();

            dd([
                'step' => '6. Jobs Array',
                'total_jobs' => count($jobs),
                'jobs' => $jobs,
                'first_job' => $jobs[0] ?? null,
            ]);

        } catch (\Exception $e) {
            dd([
                'step' => 'ERROR',
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
        }
    }
}
