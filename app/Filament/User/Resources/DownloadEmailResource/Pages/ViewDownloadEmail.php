<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use App\Filament\User\Resources\DownloadEmailResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewDownloadEmail extends ViewRecord
{
    protected static string $resource = DownloadEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('register')
                ->label('Protocolla')
                ->icon('fluentui-pen-20-o')
                ->color('warning')
                ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                ->requiresConfirmation()
                ->modalHeading('Protocolla email')
                ->modalDescription('La mail verrà inserita nel protocollo ed eliminata da questo elenco')
                ->modalSubmitActionLabel('Protocolla')
                ->action(function ($record) {
                    try {
                        $this->registerEmail($record);
                        dd('STOP');
                        Notification::make()
                            ->title('Mail protocollata')
                            ->body('La mail e i suoi allegati sono stati protocollati con successo.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore registrazione')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function registerEmail($record){
        try {
            DB::beginTransaction();
            dump('Registrata');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
    }
}
