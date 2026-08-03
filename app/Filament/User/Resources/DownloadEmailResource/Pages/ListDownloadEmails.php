<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use App\Filament\User\Resources\DownloadEmailResource;
use App\Jobs\DownloadEmailsJob;
use App\Jobs\DownloadEmailsWithReceiptsJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class ListDownloadEmails extends ListRecords
{
    protected static string $resource = DownloadEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),

            // Actions\Action::make('update_list')
            //     ->label('Aggiorna pagina')
            //     ->icon('ri-refresh-line')
            //     ->color('warning')
            //     ->visible(fn () => session('downloaded_emails_update'))
            //     ->action(function ($livewire) {
            //         try {
            //             session(['downloaded_emails' => false]);

            //             if ($livewire instanceof ListRecords) {
            //                 $livewire->dispatch('$refresh');
            //             }

            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore avvio download')
            //                 ->body($e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     }),

            // Actions\Action::make('download')
            //     ->label('Scarico email')
            //     ->icon('fluentui-mail-arrow-down-20')
            //     ->color('warning')
            //     ->requiresConfirmation()
            //     ->modalHeading('Scarica email')
            //     ->modalDescription('Verranno scaricate tutte le mail degli account previsti in background')
            //     ->modalSubmitActionLabel('Scarica')
            //     ->action(function () {
            //         try {
            //             // Dispatch job in background
            //             DownloadEmailsJob::dispatch(Auth::id());

            //             Notification::make()
            //                 ->title('Download avviato')
            //                 ->body('Il download delle email è stato avviato in background. Riceverai una notifica al termine.')
            //                 ->success()
            //                 ->send();

            //         } catch (\Exception $e) {
            //             Notification::make()
            //                 ->title('Errore avvio download')
            //                 ->body($e->getMessage())
            //                 ->danger()
            //                 ->send();
            //         }
            //     }),

            Actions\Action::make('download_with_receipts')
                ->label('Scarico email e ricevute')
                ->icon('fluentui-mail-arrow-down-20')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email e ricevute')
                ->modalDescription('Verranno scaricate tutte le mail degli account previsti e processate le ricevute PEC in background; puoi continuare a lavorare, verrai avvisato con una notifica al termine')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    try {
                        session(['downloaded_emails' => false]);

                        // Dispatch job combinato in background
                        DownloadEmailsWithReceiptsJob::dispatch(Auth::id());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download di email e ricevute è stato avviato in background. Riceverai una notifica al termine.')
                            ->success()
                            ->send();

                        // sleep(10);

                        session(['downloaded_emails' => true]);

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio download')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getPollingInterval(): ?string
    {
        return '30s';
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
