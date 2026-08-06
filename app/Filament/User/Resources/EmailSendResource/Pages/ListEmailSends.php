<?php

namespace App\Filament\User\Resources\EmailSendResource\Pages;

use App\Filament\User\Resources\EmailSendResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListEmailSends extends ListRecords
{
    protected static string $resource = EmailSendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
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
                                Blade::render('print.email_sends', [
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
