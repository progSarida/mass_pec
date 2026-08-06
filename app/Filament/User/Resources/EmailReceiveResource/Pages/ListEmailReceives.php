<?php

namespace App\Filament\User\Resources\EmailReceiveResource\Pages;

use App\Filament\User\Resources\EmailReceiveResource;
use App\Jobs\EmailReceiveJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

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
                        session(['email_receives' => false]);

                        EmailReceiveJob::dispatch(Auth::id());

                        Notification::make()
                            ->title('Download avviato')
                            ->body('Il download di email e ricevute è stato avviato in background. Riceverai una notifica al termine.')
                            ->success()
                            ->send();

                        sleep(10);

                        session(['email_receives' => true]);

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio download')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco email')
                ->color('print')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()
                        ->orderBy('created_at', 'asc')
                        ->get();
                    $filters = $livewire->tableFilters ?? [];
                    $search = $livewire->tableSearch ?? null;

                    if(count($records) === 0){
                        Notification::make()
                            ->title('Nessun elemento da stampare')
                            ->warning()
                            ->send();
                        return false;
                    }

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            ini_set('memory_limit', '1G');
                            echo Pdf::loadHTML(
                                Blade::render('print.email_receives', [
                                    'emails' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                            ->setPaper('A4', 'landscape')
                            ->stream();
                        }, "Elenco email.pdf");
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
