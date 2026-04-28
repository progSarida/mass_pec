<?php

namespace App\Filament\User\Resources\EmailReceiveResource\Pages;

use App\Filament\User\Resources\EmailReceiveResource;
use App\Jobs\EmailReceiveJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class ListEmailReceives extends ListRecords
{
    protected static string $resource = EmailReceiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),

            Actions\Action::make('download')
                ->label('Scarico email')
                ->icon('fluentui-mail-inbox-arrow-down-20-o')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email e ricevute')
                ->modalDescription('Verranno scaricate tutte le mail degli account previsti e processate le ricevute PEC in background; puoi continuare a lavorare, verrai avvisato con una notifica al termine')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    try {
                        EmailReceiveJob::dispatch(Auth::id());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download di email e ricevute è stato avviato in background. Riceverai una notifica al termine.')
                            ->success()
                            ->send();

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

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
