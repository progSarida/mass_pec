<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Filament\User\Resources\SendEmailResource;
use App\Models\Company;
use App\Models\SendEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Blade;

class ViewSendEmail extends ViewRecord
{
    protected static string $resource = SendEmailResource::class;

    public function getTitle(): string
    {
        return "Visualizza email in uscita";
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
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCSendEmail) { return $previousCSendEmail;})
                ->action(function () use ($previousCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('view', ['record' => $previousCSendEmail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCSendEmail) { return $nextCSendEmail;})
                ->action(function () use ($nextCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('view', ['record' => $nextCSendEmail->id]));
                }),
            // Scorrimento invio
            Actions\Action::make('previous_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousSSendEmail) { return $previousSSendEmail;})
                ->action(function () use ($previousSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('view', ['record' => $previousSSendEmail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextSSendEmail) { return $nextSSendEmail;})
                ->action(function () use ($nextSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('view', ['record' => $nextSSendEmail->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->label('Stampa')
                    ->tooltip('Stampa')
                    ->color('print')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()
                            ->streamDownload(function () use ($record) {
                                echo Pdf::loadHTML(
                                    Blade::render('print.send_email', [
                                        'company' => Company::first(),
                                        'email' => $record,
                                    ])
                                )
                                ->setPaper('A4', 'portrait')
                                ->stream();
                            }, "Pec da inviare_{$record->id}.pdf");
                    }),
                Actions\EditAction::make(),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }
}
